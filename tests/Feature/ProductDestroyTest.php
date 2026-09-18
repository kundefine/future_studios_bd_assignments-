<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a product can be deleted', function () {
    $product = Product::factory()->create();

    $response = $this->deleteJson(route('products.destroy', $product));

    $response->assertNoContent();

    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ]);
});
