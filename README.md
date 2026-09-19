# Future Studios BD - Order Processing & Inventory API

Laravel 13 REST API for product/catalog management, stock tracking, and reliable customer order processing.

## Architecture

The application follows the existing layered pattern:

```text
Route -> Controller -> Form Request -> DTO -> Service -> Eloquent model
```

- Controllers are thin HTTP adapters.
- Form Requests own validation and authorization decisions.
- DTOs carry validated command data into services.
- Services own business rules, transactions, cache invalidation, and state transitions.
- Models express persistence, relationships, casts, and factories.

## Domain model

| Model | Purpose | Important relationships |
| --- | --- | --- |
| Category | Product grouping | has many products |
| Product | Catalog item and current sell price | belongs to category; has one inventory; has many order items |
| Inventory | Physical stock and pending reservation count | belongs to product |
| Order | Customer checkout and lifecycle state | belongs to user; has many order items |
| OrderItem | Immutable product/price/quantity snapshot | belongs to order and product |

`available_quantity` is calculated as `quantity - reserved_quantity`; it is never stored separately, preventing a second source of truth.

The schema protects key invariants with unique category/product slugs, one inventory row per product, indexed product names and order statuses, and a unique order idempotency key. These indexes support catalog lookup, order reporting, safe stock locking, and retry handling.

## Order workflow and concurrency

1. `POST /api/orders` requires an `Idempotency-Key` UUID header.
2. The service opens a database transaction and locks products and inventory rows in ascending product-ID order.
3. It validates availability, creates the order and price-snapshot items, then increments `reserved_quantity`.
4. Retrying the same idempotency key returns the original order without reserving stock again.
5. `Pending` orders may become `Confirmed` or `Cancelled`; confirmed orders may become `Completed` or `Cancelled`.
6. Cancellation releases the reservation. Completion moves reserved units out of physical quantity.

The ordered locks, database transaction, unique idempotency key, and retry-safe lookup prevent overselling and duplicate order creation under concurrent requests.

## API

Every JSON response uses the existing envelope:

```json
{
  "success": true,
  "status": 200,
  "data": {}
}
```

### Public catalog APIs

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/api/products` | Paginated products. Supports `search`, `category_id`, `min_price`, `max_price`, `per_page` (1-100). |
| POST | `/api/products` | Create a product; initializes inventory at zero. |
| GET | `/api/products/{product}` | Cached product detail. |
| PUT | `/api/products/{product}` | Update product. |
| DELETE | `/api/products/{product}` | Delete product. |
| GET/POST | `/api/categories` | List/create categories. |
| GET/PUT/DELETE | `/api/categories/{category}` | Show/update/delete a category. |

### Authenticated operations

These endpoints require a Sanctum bearer token.

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/api/inventory/{product}` | Cached stock availability. |
| PUT | `/api/inventory/{product}` | Set physical quantity; it cannot be below reservations. |
| GET | `/api/orders` | Current customer's paginated order history; optional `status`. |
| POST | `/api/orders` | Create/reserve an order. |
| GET | `/api/orders/report` | Current customer's order count, completed revenue, and status totals. |
| GET | `/api/orders/{order}` | Current customer's order detail. |
| PATCH | `/api/orders/{order}/status` | Update state, including cancellation. |

### Authentication

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/api/auth/register` | Register a user and return a Sanctum token. |
| POST | `/api/auth/login` | Log in and return a Sanctum token. |
| GET | `/api/user` | Return the authenticated user. |
| POST | `/api/auth/logout` | Revoke the current bearer token. |

Register or log in with `name` (register only), `email`, `password`, and an optional `device_name`. Registration additionally requires `password_confirmation`. Send the returned token as `Authorization: Bearer <token>` for protected API calls.

Create an order:

```http
POST /api/orders
Authorization: Bearer <sanctum-token>
Idempotency-Key: 7d3c0ee7-5582-42e7-9005-7afad09fd1bf
Content-Type: application/json

{
  "items": [
    {"product_id": 1, "quantity": 2}
  ]
}
```

Cancel an order:

```http
PATCH /api/orders/1/status
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{"status": "cancelled"}
```

## Cache, events, and queues

- Product detail and inventory availability use read-through Redis caching. Both `.env.docker` and `.env.example` set `CACHE_STORE=redis`; Redis cache data uses logical database `1` through `REDIS_CACHE_DB=1`.
- Product/inventory mutations invalidate their affected cache key.
- `OrderCreated` dispatches after a successful transaction commit.
- `QueueOrderNotification` is an auto-discovered queued listener. Run a worker with `php artisan queue:work` or use the `queue` Docker service.
- The API has a global 100-requests-per-minute limiter keyed by authenticated user or client IP.

## Setup

### Local PHP

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Run the worker separately when using an asynchronous queue driver:

```bash
php artisan queue:work
```

### Docker

Provide the required values in `.env.docker`, then run:

```bash
docker compose up --build
docker compose exec php php artisan migrate --seed
```

The API is available at `http://localhost:8080` and the queue worker starts automatically.

## Quality checks

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Focused tests cover product/category management, factory/seeding, order reservation, idempotent retries, cancellation, inventory commitment, and customer reporting.
