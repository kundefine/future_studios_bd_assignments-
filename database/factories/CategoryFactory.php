<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categoryName = $this->faker->unique()->randomElement([
            'Electronics', 'Clothing', 'Home & Kitchen',
            'Books', 'Sports & Outdoors', 'Beauty & Personal Care'
        ]);

        return [
            'name' => $categoryName,
            'slug' => Category::generateUniqueSlug($categoryName),
            'description' => $this->faker->sentence(),
        ];

    }
}
