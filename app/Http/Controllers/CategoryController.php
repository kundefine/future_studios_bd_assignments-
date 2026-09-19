<?php

namespace App\Http\Controllers;

use App\DTOs\CategoryCreateDTO;
use App\DTOs\CategoryUpdateDTO;
use App\Http\Requests\CategoryCreateRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->categoryService->all());
    }

    public function store(CategoryCreateRequest $request): JsonResponse
    {
        return response()->json($this->categoryService->create(CategoryCreateDTO::fromRequest($request)), Response::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($this->categoryService->show($category));
    }

    public function update(CategoryUpdateRequest $request, Category $category): JsonResponse
    {
        return response()->json($this->categoryService->update($category, CategoryUpdateDTO::fromRequest($request)));
    }

    public function destroy(Category $category): Response
    {
        $this->categoryService->delete($category);

        return response()->noContent();
    }
}
