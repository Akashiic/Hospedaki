<?php

namespace Database\Factories;

use App\Models\Accommodation;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Accommodation>
 */
class AccommodationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Chalé '.fake()->unique()->firstName();

        return [
            'property_id' => Property::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'max_guests' => 2,
            'base_price' => 30000,
            'weekend_price' => null,
            'min_nights' => 1,
        ];
    }
}
