<?php

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the product factory creates a product with a category', function () {
    $product = Product::factory()->create();

    expect($product->category)->toBeInstanceOf(Category::class);
    expect($product->slug)->not->toBeEmpty();
});

test('the product seeder creates products using existing categories', function () {
    Category::factory(2)->create();

    $this->seed(ProductSeeder::class);

    expect(Product::query()->count())->toBe(20);
    expect(Category::query()->count())->toBe(2);
});
