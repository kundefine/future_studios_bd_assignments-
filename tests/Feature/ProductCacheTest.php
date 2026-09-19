<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('product details are cached and invalidated after an update', function () {
    $product = Product::factory()->create(['name' => 'Cached Desk']);

    $this->getJson(route('products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.name', 'Cached Desk');

    expect(Cache::get("product:{$product->id}"))->toMatchArray(['name' => 'Cached Desk']);

    $this->putJson(route('products.update', $product), [
        'name' => 'Updated Desk',
        'price' => '199.99',
        'description' => null,
        'category_id' => null,
    ])->assertOk();

    expect(Cache::has("product:{$product->id}"))->toBeFalse();
});
