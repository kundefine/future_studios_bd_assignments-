<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a product can be updated', function () {
    $category = Category::factory()->create();
    $product = Product::query()->create([
        'name' => 'Old Desk',
        'slug' => 'old-desk',
        'price' => 100.00,
        'description' => 'Old description',
        'category_id' => $category->id,
    ]);

    $response = $this->putJson(route('products.update', $product), [
        'name' => 'New Desk',
        'price' => '125.50',
        'description' => 'Updated description',
        'category_id' => null,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'New Desk')
        ->assertJsonPath('data.slug', 'new-desk');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'New Desk',
        'slug' => 'new-desk',
        'price' => '125.50',
        'description' => 'Updated description',
        'category_id' => null,
    ]);
});
