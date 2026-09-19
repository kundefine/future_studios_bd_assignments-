<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Category::query()->doesntExist()) {
            Category::factory(20)->create();
        }

        $categoryIds = Category::query()->pluck('id');

        Product::factory(20)
            ->state(fn (): array => ['category_id' => $categoryIds->random()])
            ->create()
            ->each(fn (Product $product) => Inventory::factory()->for($product)->create());
    }
}
