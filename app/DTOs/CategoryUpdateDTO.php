<?php

namespace App\DTOs;

use App\Http\Requests\CategoryUpdateRequest;

final readonly class CategoryUpdateDTO
{
    public function __construct(public string $name) {}

    public static function fromRequest(CategoryUpdateRequest $request): self
    {
        return new self($request->validated('name'));
    }
}
