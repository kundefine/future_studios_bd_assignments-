<?php

namespace App\DTOs;

use App\Http\Requests\ProductCreateRequest;
use App\Models\Product;

final readonly class ProductCreateDTO {
    public function __construct(
        public string $name,
        public float $price,
        public ?string $slug = null,
        public ?string $description = null,
        public ?int $category_id = null
    ) {}

    public static function fromArray(array $data) : self {

        return new self(
            name: $data["name"],
            price: $data["price"],
            slug: Product::generateUniqueSlug($data["name"]),
            description: $data["description"] ?? null,
            category_id: $data["category_id"] ?? null
        );
    }

    public static function fromRequest(ProductCreateRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public function toArray() : array {
        return [
            "name" => $this->name,
            "slug" => $this->slug,
            "price" => $this->price,
            "description" => $this->description,
            "category_id" => $this->category_id
        ];
    }
}
