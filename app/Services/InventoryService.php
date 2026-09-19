<?php

namespace App\Services;

use App\DTOs\InventoryAdjustmentDTO;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * @return array{product_id: int, quantity: int, reserved_quantity: int, available_quantity: int}
     */
    public function availability(Product $product): array
    {
        return Cache::remember("inventory:{$product->id}", now()->addMinutes(5), function () use ($product): array {
            $inventory = Inventory::query()->where('product_id', $product->id)->first();
            $quantity = $inventory?->quantity ?? 0;
            $reservedQuantity = $inventory?->reserved_quantity ?? 0;

            return [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'reserved_quantity' => $reservedQuantity,
                'available_quantity' => $quantity - $reservedQuantity,
            ];
        });
    }

    public function adjust(Product $product, InventoryAdjustmentDTO $inventoryAdjustmentDto): Inventory
    {
        $inventory = DB::transaction(function () use ($product, $inventoryAdjustmentDto): Inventory {
            $inventory = Inventory::query()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                return Inventory::query()->create([
                    'product_id' => $product->id,
                    'quantity' => $inventoryAdjustmentDto->quantity,
                    'reserved_quantity' => 0,
                ]);
            }

            if ($inventoryAdjustmentDto->quantity < $inventory->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot be lower than the quantity reserved for pending orders.'],
                ]);
            }

            $inventory->update(['quantity' => $inventoryAdjustmentDto->quantity]);

            return $inventory->refresh();
        }, 3);

        Cache::forget("inventory:{$product->id}");

        return $inventory;
    }
}
