<?php

namespace App\Services;

use App\DTOs\CategoryCreateDTO;
use App\DTOs\CategoryUpdateDTO;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function all(): LengthAwarePaginator
    {
        return Category::query()->withCount('products')->orderBy('name')->paginate(15);
    }

    public function create(CategoryCreateDTO $categoryCreateDto): Category
    {
        return Category::query()->create([
            'name' => $categoryCreateDto->name,
            'slug' => Category::generateUniqueSlug($categoryCreateDto->name),
        ]);
    }

    public function show(Category $category): Category
    {
        return $category->loadCount('products');
    }

    public function update(Category $category, CategoryUpdateDTO $categoryUpdateDto): Category
    {
        $attributes = ['name' => $categoryUpdateDto->name];

        if ($category->name !== $categoryUpdateDto->name) {
            $attributes['slug'] = Category::generateUniqueSlug($categoryUpdateDto->name);
        }

        $category->update($attributes);

        return $category->refresh()->loadCount('products');
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
