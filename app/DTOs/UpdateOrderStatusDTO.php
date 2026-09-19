<?php

namespace App\DTOs;

use App\Http\Requests\UpdateOrderStatusRequest;
use App\OrderStatus;

final readonly class UpdateOrderStatusDTO
{
    public function __construct(public OrderStatus $status) {}

    public static function fromRequest(UpdateOrderStatusRequest $request): self
    {
        return new self(OrderStatus::from($request->validated('status')));
    }
}
