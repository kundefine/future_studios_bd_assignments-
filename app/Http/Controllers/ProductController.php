<?php

namespace App\Http\Controllers;

use App\DTOs\ProductCreateDTO;
use App\Http\Requests\ProductCreateRequest;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {

    }
    public function index() {

    }

    public function store(ProductCreateRequest $request) : JsonResponse {
        $dto = ProductCreateDTO::fromRequest($request);
        $product = $this->productService->create($dto);
        return response()->json($product);
    }
}
