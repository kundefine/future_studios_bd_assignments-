<?php

namespace App\DTOs;

use Illuminate\Support\Facades\Request;

final readonly class ProductCreateDTO {
    public function __construct(
        public string $name,
        public float $price,
        public ?string $description = null,
        public ?int $category_id = null
    ) {}

    public static function fromArray(array $data) : self {
        return new self(name: $data["name"],price: $data["price"],description: $data["description"],category_id: $data["category_id"]);
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->validated());
    }

    public function toArray() : array {
        return [
            "name" => $this->name,
            "price" => $this->price,
            "description" => $this->description,
            "category_id" => $this->category_id
        ];
    }
}
