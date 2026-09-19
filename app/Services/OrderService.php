<?php

namespace App\Services;

use App\DTOs\CreateOrderDTO;
use App\DTOs\UpdateOrderStatusDTO;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(User $user, CreateOrderDTO $createOrderDto): Order
    {
        try {
            $order = DB::transaction(function () use ($user, $createOrderDto): Order {
                $existingOrder = Order::query()
                    ->where('idempotency_key', $createOrderDto->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder !== null) {
                    return $existingOrder->load('items.product');
                }

                $productIds = collect($createOrderDto->items)->pluck('product_id')->sort()->values();
                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $inventories = Inventory::query()
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $this->ensureStockIsAvailable($createOrderDto->items, $products, $inventories);

                $total = 0.0;
                foreach ($createOrderDto->items as $item) {
                    $total += (float) $products[$item['product_id']]->price * $item['quantity'];
                }

                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'idempotency_key' => $createOrderDto->idempotencyKey,
                    'status' => OrderStatus::Pending,
                    'total' => round($total, 2),
                ]);

                foreach ($createOrderDto->items as $item) {
                    $product = $products[$item['product_id']];
                    $inventory = $inventories[$item['product_id']];

                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                    ]);

                    $inventory->increment('reserved_quantity', $item['quantity']);
                }

                foreach ($productIds as $productId) {
                    Cache::forget("inventory:{$productId}");
                }

                OrderCreated::dispatch($order);

                return $order->load('items.product');
            }, 3);
        } catch (QueryException) {
            $order = Order::query()
                ->where('idempotency_key', $createOrderDto->idempotencyKey)
                ->with('items.product')
                ->firstOrFail();
        }

        return $order;
    }

    public function history(User $user, ?string $status): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with('items.product')
            ->latest()
            ->paginate(15);
    }

    public function show(User $user, Order $order): Order
    {
        return Order::query()
            ->whereKey($order->id)
            ->where('user_id', $user->id)
            ->with('items.product')
            ->firstOrFail();
    }

    /**
     * @return array{total_orders: int, completed_revenue: string, orders_by_status: array<string, int>}
     */
    public function report(User $user): array
    {
        $orders = Order::query()->where('user_id', $user->id);
        $ordersByStatus = (clone $orders)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (int $count): int => $count)
            ->all();

        return [
            'total_orders' => (clone $orders)->count(),
            'completed_revenue' => (string) (clone $orders)
                ->where('status', OrderStatus::Completed)
                ->sum('total'),
            'orders_by_status' => $ordersByStatus,
        ];
    }

    public function updateStatus(User $user, Order $order, UpdateOrderStatusDTO $updateOrderStatusDto): Order
    {
        return DB::transaction(function () use ($user, $order, $updateOrderStatusDto): Order {
            $order = Order::query()
                ->whereKey($order->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ($order->status === $updateOrderStatusDto->status) {
                return $order->load('items.product');
            }

            $this->ensureValidStatusTransition($order->status, $updateOrderStatusDto->status);

            if (in_array($updateOrderStatusDto->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
                $this->releaseOrCommitReservedStock($order, $updateOrderStatusDto->status);
            }

            $order->update([
                'status' => $updateOrderStatusDto->status,
                'cancelled_at' => $updateOrderStatusDto->status === OrderStatus::Cancelled ? now() : null,
            ]);

            if ($updateOrderStatusDto->status === OrderStatus::Cancelled) {
                OrderCancelled::dispatch($order);
            }

            return $order->refresh()->load('items.product');
        }, 3);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Inventory>  $inventories
     */
    private function ensureStockIsAvailable(array $items, Collection $products, Collection $inventories): void
    {
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $inventory = $inventories->get($item['product_id']);

            if ($product === null || $inventory === null || ($inventory->quantity - $inventory->reserved_quantity) < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for product ID {$item['product_id']}"],
                ]);
            }
        }
    }

    private function ensureValidStatusTransition(OrderStatus $currentStatus, OrderStatus $nextStatus): void
    {
        $validTransitions = [
            OrderStatus::Pending->value => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Confirmed->value => [OrderStatus::Completed, OrderStatus::Cancelled],
            OrderStatus::Completed->value => [],
            OrderStatus::Cancelled->value => [],
        ];

        if (! in_array($nextStatus, $validTransitions[$currentStatus->value], true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition an order from {$currentStatus->value} to {$nextStatus->value}."],
            ]);
        }
    }

    private function releaseOrCommitReservedStock(Order $order, OrderStatus $nextStatus): void
    {
        $productIds = $order->items->pluck('product_id')->sort()->values();
        $inventories = Inventory::query()
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        foreach ($order->items as $item) {
            $inventory = $inventories->get($item->product_id);

            if ($inventory === null || $inventory->reserved_quantity < $item->quantity) {
                throw ValidationException::withMessages([
                    'status' => ['The order reservation is no longer valid.'],
                ]);
            }

            $inventory->decrement('reserved_quantity', $item->quantity);

            if ($nextStatus === OrderStatus::Completed) {
                $inventory->decrement('quantity', $item->quantity);
            }

            Cache::forget("inventory:{$item->product_id}");
        }
    }
}
