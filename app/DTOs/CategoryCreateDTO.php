<?php

namespace App\DTOs;

use App\Http\Requests\CategoryCreateRequest;

final readonly class CategoryCreateDTO
{
    public function __construct(public string $name) {}

    public static function fromRequest(CategoryCreateRequest $request): self
    {
        return new self($request->validated('name'));
    }
}
