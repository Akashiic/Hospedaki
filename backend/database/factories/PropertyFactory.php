<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Pousada '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'whatsapp' => '5511999999999',
            'city' => fake()->city(),
            'state' => 'SP',
        ];
    }
}
