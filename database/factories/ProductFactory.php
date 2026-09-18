<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productName = fake()->unique()->words(3, true);

        return [
            'name' => $productName,
            'slug' => Product::generateUniqueSlug($productName),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 1, 999999),
            'category_id' => Category::factory(),
        ];
    }
}
