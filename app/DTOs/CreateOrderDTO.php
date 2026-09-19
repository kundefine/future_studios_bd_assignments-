<?php

namespace App\DTOs;

use App\Http\Requests\CreateOrderRequest;

final readonly class CreateOrderDTO
{
    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function __construct(public string $idempotencyKey, public array $items) {}

    public static function fromRequest(CreateOrderRequest $request): self
    {
        return new self(
            idempotencyKey: $request->validated('idempotency_key'),
            items: $request->validated('items'),
        );
    }
}
