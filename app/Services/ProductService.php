<?php
namespace App\Services;
use App\DTOs\ProductCreateDTO;
use App\Models\Product;

class ProductService {

    public function all() {
        return Product::paginate(10);
    }
    public function create(ProductCreateDTO $productCreateDto) : Product {

        return Product::create($productCreateDto->toArray());

    }

    public function show(Product $product)
    {
        return $product->load('category');
    }

}
