<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_color_id' => null,
            'size_id' => null,
            'stock' => fake()->numberBetween(0, 50),
        ];
    }
}
