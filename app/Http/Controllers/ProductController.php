<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\Request;


class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {

    }
    public function index() {
        dd($this->productService->create());
    }
}
