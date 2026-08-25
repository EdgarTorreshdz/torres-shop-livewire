<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SizeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('T-##'),
            // Explicit rather than relying on the column default — see
            // ProductColorFactory for why (Eloquent doesn't reflect a DB
            // default onto the in-memory model until it's refreshed).
            'sort_order' => 0,
        ];
    }
}
