<?php

namespace App\DTOs;

use App\Http\Requests\AdjustInventoryRequest;

final readonly class InventoryAdjustmentDTO
{
    public function __construct(public int $quantity) {}

    public static function fromRequest(AdjustInventoryRequest $request): self
    {
        return new self($request->validated('quantity'));
    }
}
