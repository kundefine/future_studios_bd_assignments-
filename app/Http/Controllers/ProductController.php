<?php

namespace App\Http\Controllers;

use App\DTOs\ProductCreateDTO;
use App\Http\Requests\ProductCreateRequest;
use App\Services\ProductService;
use Illuminate\Http\Request;


class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {

    }
    public function index() {

    }

    public function store(ProductCreateRequest $request) {

        $dto = ProductCreateDTO::fromRequest($request);

        return $this->productService->create($dto);

    }
}
