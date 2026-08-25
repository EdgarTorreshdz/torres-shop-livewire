<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductColorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->safeColorName(),
            // Explicit, not left to the column's DB-level default (0):
            // Eloquent doesn't reflect a DB default on the in-memory model
            // after create() until it's refreshed, so a factory instance
            // handed straight into a form/component would see sort_order
            // as null (then '' once cast to string) instead of 0 — a real
            // gotcha this hit while writing ProductColorsTest.
            'sort_order' => 0,
        ];
    }
}
