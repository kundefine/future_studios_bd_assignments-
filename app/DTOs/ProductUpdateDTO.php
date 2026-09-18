<?php

namespace App\DTOs;

use App\Http\Requests\ProductUpdateRequest;

final readonly class ProductUpdateDTO
{
    public function __construct(
        public string $name,
        public float $price,
        public ?string $description,
        public ?int $categoryId,
    ) {}

    public static function fromRequest(ProductUpdateRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            price: $request->validated('price'),
            description: $request->validated('description'),
            categoryId: $request->validated('category_id'),
        );
    }

    /**
     * @return array{name: string, price: float, description: ?string, category_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'category_id' => $this->categoryId,
        ];
    }
}
