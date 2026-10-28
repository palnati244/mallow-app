<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {
            // Lock products in a consistent order to reduce deadlocks.
            $productIds = collect($data['items'])
                ->pluck('product_id')
                ->sort()
                ->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['One or more products no longer exist.'],
                ]);
            }

            // Reuse an existing customer based on email.
            $customer = Customer::query()->firstOrNew([
                'email' => strtolower($data['customer']['email']),
            ]);

            $customer->name = $data['customer']['name'];
            $customer->save();

            $subtotal = 0;
            $totalTax = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];

                if ($product->stock_on_hand < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Insufficient stock for {$product->name}.",
                        ],
                    ]);
                }

                // Store currency amounts in cents while calculating.
                $unitPriceCents = (int) round(
                    (float) $product->price * 100
                );

                $lineSubtotalCents = $unitPriceCents * $quantity;

                $lineTaxCents = (int) round(
                    $lineSubtotalCents
                    * (float) $product->tax_percentage
                    / 100
                );

                $lineTotalCents = $lineSubtotalCents + $lineTaxCents;

                $subtotal += $lineSubtotalCents;
                $totalTax += $lineTaxCents;

                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => number_format(
                        $unitPriceCents / 100, 2, '.', ''
                    ),
                    'tax_percentage' => $product->tax_percentage,
                    'line_subtotal' => number_format(
                        $lineSubtotalCents / 100, 2, '.', ''
                    ),
                    'line_tax' => number_format(
                        $lineTaxCents / 100, 2, '.', ''
                    ),
                    'line_total' => number_format(
                        $lineTotalCents / 100, 2, '.', ''
                    ),
                ];
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'subtotal' => number_format($subtotal / 100, 2, '.', ''),
                'tax' => number_format($totalTax / 100, 2, '.', ''),
                'grand_total' => number_format(
                    ($subtotal + $totalTax) / 100, 2, '.', ''
                ),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_percentage' => $line['tax_percentage'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_tax' => $line['line_tax'],
                    'line_total' => $line['line_total'],
                ]);

                $line['product']->decrement(
                    'stock_on_hand',
                    $line['quantity']
                );
            }

            return $order;
        }, 3);

        // The transaction has committed successfully at this point.
        SendOrderConfirmation::dispatch($order->id);

        return $order->load(['customer', 'items.product']);
    }
}