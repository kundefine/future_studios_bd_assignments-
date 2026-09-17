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
        static $index = 0;

        $names = [
            'Electronics', 'Clothing', 'Home & Kitchen', 'Books',
            'Sports & Outdoors', 'Beauty & Personal Care', 'Toys & Games',
            'Automotive', 'Health & Household', 'Pet Supplies',
            'Office Products', 'Garden & Outdoor', 'Grocery & Gourmet',
            'Jewelry', 'Shoes', 'Baby Products', 'Musical Instruments',
            'Movies & TV', 'Video Games', 'Tools & Home Improvement',
        ];

        $categoryName = $names[$index % count($names)] . ($index >= count($names) ? ' ' . (intdiv($index, count($names)) + 1) : '');
        $index++;

        return [
            'name' => $categoryName,
            'slug' => Category::generateUniqueSlug($categoryName),
        ];

    }
}
