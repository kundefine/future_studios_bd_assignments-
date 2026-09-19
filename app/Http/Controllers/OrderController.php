<?php

namespace App\Http\Controllers;

use App\DTOs\CreateOrderDTO;
use App\DTOs\UpdateOrderStatusDTO;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->orderService->history($request->user(), $request->query('status')));
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->user(), CreateOrderDTO::fromRequest($request));

        return response()->json($order, Response::HTTP_CREATED);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        return response()->json($this->orderService->show($request->user(), $order));
    }

    public function report(Request $request): JsonResponse
    {
        return response()->json($this->orderService->report($request->user()));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $updatedOrder = $this->orderService->updateStatus($request->user(), $order, UpdateOrderStatusDTO::fromRequest($request));

        return response()->json($updatedOrder);
    }
}
