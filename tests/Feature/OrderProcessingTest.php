<?php

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('order creation reserves stock and is idempotent', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 25.50]);
    $inventory = Inventory::factory()->for($product)->create(['quantity' => 5]);
    $idempotencyKey = Str::uuid()->toString();

    $payload = [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson(route('orders.store'), $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total', '51.00');

    $retryResponse = $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson(route('orders.store'), $payload);

    $retryResponse->assertCreated();
    expect(Order::query()->count())->toBe(1);
    expect($inventory->refresh()->reserved_quantity)->toBe(2);
});

test('cancelling an order releases its reserved stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 25.50]);
    $inventory = Inventory::factory()->for($product)->create(['quantity' => 5]);

    $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson(route('orders.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertCreated();

    $order = Order::query()->firstOrFail();

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson(route('orders.update-status', $order), ['status' => 'cancelled']);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($inventory->refresh()->reserved_quantity)->toBe(0);
});

test('completed orders deduct committed inventory', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 25.50]);
    $inventory = Inventory::factory()->for($product)->create(['quantity' => 5]);

    $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson(route('orders.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertCreated();

    $order = Order::query()->firstOrFail();

    $this->actingAs($user, 'sanctum')
        ->patchJson(route('orders.update-status', $order), ['status' => 'confirmed'])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->patchJson(route('orders.update-status', $order), ['status' => 'completed'])
        ->assertOk();

    expect($inventory->refresh()->quantity)->toBe(3);
    expect($inventory->refresh()->reserved_quantity)->toBe(0);
});

test('an authenticated customer can view an order report', function () {
    $user = User::factory()->create();
    $completedOrder = Order::factory()->for($user)->create([
        'status' => 'completed',
        'total' => 49.50,
    ]);
    Order::factory()->for($user)->create([
        'status' => 'cancelled',
        'total' => 20.00,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson(route('orders.report'));

    $response
        ->assertOk()
        ->assertJsonPath('data.total_orders', 2)
        ->assertJsonPath('data.completed_revenue', '49.5')
        ->assertJsonPath('data.orders_by_status.completed', 1)
        ->assertJsonPath('data.orders_by_status.cancelled', 1);
});
