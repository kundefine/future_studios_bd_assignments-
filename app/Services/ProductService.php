<?php

namespace App\Services;

use App\DTOs\ProductCreateDTO;
use App\DTOs\ProductUpdateDTO;
use App\Models\Product;

class ProductService
{
    public function all()
    {
        return Product::paginate(10);
    }

    public function create(ProductCreateDTO $productCreateDto): Product
    {

        return Product::create($productCreateDto->toArray());

    }

    public function show(Product $product)
    {
        return $product->load('category');
    }

    public function update(Product $product, ProductUpdateDTO $productUpdateDto): Product
    {
        $attributes = $productUpdateDto->toArray();

        if ($product->name !== $productUpdateDto->name) {
            $attributes['slug'] = Product::generateUniqueSlug($productUpdateDto->name);
        }

        $product->update($attributes);

        return $product->refresh()->load('category');
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
