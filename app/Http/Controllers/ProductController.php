<?php

namespace App\Http\Controllers;

use App\DTOs\ProductCreateDTO;
use App\Http\Requests\ProductCreateRequest;
use App\Models\Product;
use App\Services\ProductService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;


class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {

    }
    public function index() {
        $products = $this->productService->all();
        return response()->json($products);
    }

    public function store(ProductCreateRequest $request) : JsonResponse {
        $dto = ProductCreateDTO::fromRequest($request);
        $product = $this->productService->create($dto);
        return response()->json($product, Response::HTTP_CREATED);
    }

    public function show(Product $product) {
        $product = $this->productService->show($product);
        return response()->json($product);
    }
}
