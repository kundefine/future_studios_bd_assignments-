<?php

namespace App\Http\Controllers;

use App\DTOs\InventoryAdjustmentDTO;
use App\Http\Requests\AdjustInventoryRequest;
use App\Models\Product;
use App\Services\InventoryService;
use Symfony\Component\HttpFoundation\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function show(Product $product): JsonResponse
    {
        return response()->json($this->inventoryService->availability($product));
    }

    public function update(AdjustInventoryRequest $request, Product $product): JsonResponse
    {
        $inventory = $this->inventoryService->adjust($product, InventoryAdjustmentDTO::fromRequest($request));

        return response()->json($inventory);
    }
}
