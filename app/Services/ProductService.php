<?php

namespace App\Services;

use App\DTOs\ProductCreateDTO;
use App\DTOs\ProductUpdateDTO;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * @param  array{search?: string, category_id?: int, min_price?: string, max_price?: string, per_page?: int}  $filters
     */
    public function all(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->with('category')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['min_price'] ?? null, fn ($query, string $minPrice) => $query->where('price', '>=', $minPrice))
            ->when($filters['max_price'] ?? null, fn ($query, string $maxPrice) => $query->where('price', '<=', $maxPrice))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function create(ProductCreateDTO $productCreateDto): Product
    {

        return DB::transaction(function () use ($productCreateDto): Product {
            $product = Product::query()->create($productCreateDto->toArray());

            Inventory::query()->create([
                'product_id' => $product->id,
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]);

            return $product->load('category');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Product $product): array
    {
        return Cache::remember("product:{$product->id}", now()->addMinutes(10), fn (): array => Product::query()
            ->with('category')
            ->findOrFail($product->id)
            ->toArray());
    }

    public function update(Product $product, ProductUpdateDTO $productUpdateDto): Product
    {
        $attributes = $productUpdateDto->toArray();

        if ($product->name !== $productUpdateDto->name) {
            $attributes['slug'] = Product::generateUniqueSlug($productUpdateDto->name);
        }

        $product->update($attributes);
        Cache::forget("product:{$product->id}");

        return $product->refresh()->load('category');
    }

    public function delete(Product $product): void
    {
        Cache::forget("product:{$product->id}");
        Cache::forget("inventory:{$product->id}");
        $product->delete();
    }
}
