<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('MAP-####??')),
            'price' => fake()->randomFloat(2, 5, 500),
            'tax_percentage' => fake()->randomElement([0, 5, 12, 18]),
            'stock_on_hand' => fake()->numberBetween(10, 50),
        ];
    }
}