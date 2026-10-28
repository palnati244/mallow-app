<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_created_and_stock_is_deducted(): void
    {
        Queue::fake();

        $customer = Customer::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
        ]);

        $product = Product::factory()->create([
            'price' => 100.00,
            'tax_percentage' => 18.00,
            'stock_on_hand' => 5,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.tax', '36.00')
            ->assertJsonPath('data.grand_total', '236.00');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_on_hand' => 3,
        ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'grand_total' => 236.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'line_tax' => 36.00,
        ]);

        Queue::assertPushed(
            SendOrderConfirmation::class,
            fn ($job) => true
        );
    }

    public function test_order_fails_when_stock_is_insufficient(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'price' => 50.00,
            'tax_percentage' => 18.00,
            'stock_on_hand' => 1,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'Priya Sharma',
                'email' => 'priya@example.com',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_on_hand' => 1,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_order_requires_at_least_one_item(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'Priya Sharma',
                'email' => 'priya@example.com',
            ],
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }
    public function test_customer_can_be_found_by_email_for_order_history(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'history@example.com',
        ]);

        $response = $this->getJson(
            '/api/customers/orders?email=history@example.com'
        );

        $response->assertOk()
            ->assertJsonPath('customer.email', 'history@example.com')
            ->assertJsonPath('orders.data', []);
    }

    public function test_low_stock_endpoint_returns_products_below_threshold(): void
    {
        Product::factory()->create([
            'code' => 'LOW-001',
            'stock_on_hand' => 2,
        ]);

        Product::factory()->create([
            'code' => 'HIGH-001',
            'stock_on_hand' => 10,
        ]);

        $response = $this->getJson(
            '/api/products/low-stock?threshold=5'
        );

        $response->assertOk()
            ->assertJsonPath('threshold', 5)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'LOW-001');
    }
}
