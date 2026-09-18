<?php
namespace App\Services;
use App\DTOs\ProductCreateDTO;
use App\Models\Product;

class ProductService {

    public function all() {

    }
    public function create(ProductCreateDTO $productCreateDto) : Product {

        return Product::create($productCreateDto->toArray());

    }

}
