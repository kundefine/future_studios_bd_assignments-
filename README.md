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

### Endpoint overview

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

### Complete API reference

All JSON requests use `Content-Type: application/json`. For endpoints marked **Bearer token**, include `Authorization: Bearer <token>` from the login or registration response.

#### Response and error formats

Successful JSON responses use:

```json
{"success": true, "status": 200, "data": {}}
```

The product payload returned by create, show, and update contains `id`, `name`, `slug`, `price`, `description`, `category_id`, timestamps, and (when loaded) `category`. A category contains `id`, `name`, `slug`, and `products_count` when applicable. An order contains `id`, `user_id`, `status`, `total`, `cancelled_at`, timestamps, and its `items` with each item's product/price/quantity snapshot.

Validation errors return HTTP `422`:

```json
{
  "success": false,
  "status": 422,
  "data": {"message": "The given data was invalid.", "errors": {"field": ["Validation message"]}}
}
```

Unauthenticated protected endpoints return HTTP `401`:

```json
{"success": false, "status": 401, "message": "Unauthorized."}
```

#### Authentication

##### `POST /api/auth/register`

Public. Required body fields: `name` (string, max 255), `email` (unique valid email), `password`, and `password_confirmation`. Optional: `device_name` (defaults to `web`).

```json
{"name": "Jane Doe", "email": "jane@example.com", "password": "password123", "password_confirmation": "password123", "device_name": "postman"}
```

Returns HTTP `201`:

```json
{"success": true, "status": 201, "data": {"user": {"id": 1, "name": "Jane Doe", "email": "jane@example.com"}, "token": "1|plain-text-sanctum-token"}}
```

##### `POST /api/auth/login`

Public. Required: `email`, `password`. Optional: `device_name`.

```json
{"email": "jane@example.com", "password": "password123", "device_name": "postman"}
```

Returns HTTP `200` with the same `data.user` and `data.token` structure as registration. Invalid credentials return HTTP `422` with an `email` validation error.

##### `GET /api/user`

**Bearer token.** No body. Returns HTTP `200` with the authenticated user's `id`, `name`, `email`, and timestamps in `data`.

##### `POST /api/auth/logout`

**Bearer token.** No body. Revokes the bearer token used by this request. Returns HTTP `204 No Content`.

#### Products

##### `GET /api/products`

Public. Query parameters are all optional:

| Parameter | Validation | Description |
| --- | --- | --- |
| `search` | string, max 255 | Partial name search |
| `category_id` | existing category ID | Category filter |
| `min_price` | decimal, minimum 0 | Inclusive lower price filter |
| `max_price` | decimal, at least `min_price` | Inclusive upper price filter |
| `per_page` | integer, 1-100 | Page size; default 15 |
| `page` | integer | Page number |

Example: `GET /api/products?search=keyboard&category_id=1&min_price=10&max_price=200&per_page=15&page=1`

Returns HTTP `200`; products are in `data.items`, with `current_page`, `per_page`, `total`, and Laravel pagination links in `data`.

##### `POST /api/products`

Public. Creates a product and initializes its inventory to zero.

```json
{"name": "Mechanical Keyboard", "category_id": 1, "price": "99.99", "description": "Hot-swappable mechanical keyboard."}
```

`name` and positive `price` are required. `category_id` must exist when supplied; `category_id` and `description` may be `null`. Returns HTTP `201` with the Product payload in `data`.

##### `GET /api/products/{product}`

Public. Returns one product and its category. Response is cached in Redis for 10 minutes. Returns HTTP `200`; an unknown product returns HTTP `404`.

##### `PUT /api/products/{product}`

Public. Fully updates a product; it requires the same fields and validation as product creation.

```json
{"name": "Wireless Mechanical Keyboard", "category_id": 1, "price": "119.99", "description": "Bluetooth mechanical keyboard."}
```

Returns HTTP `200` with the updated Product payload. A name change regenerates the slug; any update invalidates the cached product detail.

##### `DELETE /api/products/{product}`

Public. No body. Returns HTTP `204 No Content`; it also removes the related product and inventory cache keys.

#### Categories

##### `GET /api/categories`

Public. No parameters. Returns HTTP `200`; categories are paginated in `data.items` and each has `products_count`.

##### `POST /api/categories`

Public. Required body: `name` (string, max 255).

```json
{"name": "Electronics"}
```

Returns HTTP `201` with the Category payload in `data`.

##### `GET /api/categories/{category}`

Public. No body. Returns HTTP `200` with a Category payload and `products_count`; unknown IDs return HTTP `404`.

##### `PUT /api/categories/{category}`

Public. Required body: `name` (string, max 255).

```json
{"name": "Computer Accessories"}
```

Returns HTTP `200` with the updated Category payload. A name change regenerates its slug.

##### `DELETE /api/categories/{category}`

Public. No body. Returns HTTP `204 No Content`. Products in that category remain, with `category_id` set to `null`.

#### Inventory

##### `GET /api/inventory/{product}`

**Bearer token.** No body. Returns cached stock availability in HTTP `200`:

```json
{"success": true, "status": 200, "data": {"product_id": 1, "quantity": 20, "reserved_quantity": 2, "available_quantity": 18}}
```

##### `PUT /api/inventory/{product}`

**Bearer token.** Sets physical stock.

```json
{"quantity": 50}
```

`quantity` is required and must be a non-negative integer. It cannot be lower than the quantity reserved by active orders. Returns HTTP `200` with the inventory record and invalidates the availability cache.

#### Orders

All order endpoints require a **Bearer token**. Users can access only their own orders.

##### `GET /api/orders`

Optional query: `status`, one of `pending`, `confirmed`, `completed`, or `cancelled`. Returns HTTP `200`; the user's newest orders are paginated in `data.items`.

##### `POST /api/orders`

Requires `Idempotency-Key`, a UUID header, and at least one order item.

```http
POST /api/orders
Authorization: Bearer <token>
Idempotency-Key: 7d3c0ee7-5582-42e7-9005-7afad09fd1bf
Content-Type: application/json
```

```json
{"items": [{"product_id": 1, "quantity": 2}, {"product_id": 4, "quantity": 1}]}
```

Every `product_id` must exist and be distinct; every `quantity` must be a positive integer. Returns HTTP `201` with the Order payload. Insufficient stock returns HTTP `422`. Retrying the same idempotency key returns the original order without an additional reservation.

##### `GET /api/orders/report`

Returns HTTP `200` with the authenticated user's order summary:

```json
{"success": true, "status": 200, "data": {"total_orders": 4, "completed_revenue": "399.96", "orders_by_status": {"pending": 1, "confirmed": 1, "completed": 1, "cancelled": 1}}}
```

##### `GET /api/orders/{order}`

No body. Returns HTTP `200` with the Order payload, including item/product data. Orders belonging to another user and unknown IDs return HTTP `404`.

##### `PATCH /api/orders/{order}/status`

Required body: `status`.

```json
{"status": "confirmed"}
```

Allowed statuses: `pending`, `confirmed`, `completed`, `cancelled`. Valid transitions are `pending -> confirmed` or `cancelled`, and `confirmed -> completed` or `cancelled`. Completing deducts physical stock; cancellation releases reserved stock. Returns HTTP `200` with the updated Order payload. Invalid states or transitions return HTTP `422`.

## Cache, events, and queues

- Product detail and inventory availability use read-through Redis caching. Both `.env.docker` and `.env.example` set `CACHE_STORE=redis`; Redis cache data uses logical database `1` through `REDIS_CACHE_DB=1`.
- Product/inventory mutations invalidate their affected cache key.
- `OrderCreated` dispatches after a successful transaction commit.
- `QueueOrderNotification` is an auto-discovered queued listener. Run a worker with `php artisan queue:work` or use the `queue` Docker service.
- The API has a global 100-requests-per-minute limiter keyed by authenticated user or client IP.

## Production scalability and high availability

> **Production readiness:** The provided Docker Compose stack is intended for local development and assessment review. A production deployment should run multiple application instances behind a load balancer and use managed/high-availability database and Redis infrastructure.

```text
Clients
   |
Load Balancer
   |
Laravel API instances (horizontal scaling)
   |---------------------|
Primary database       Redis Cluster
   |                     |-- primary shards
Read replicas           |-- replica nodes / automatic failover
```

| Component | Production requirement | Why it matters |
| --- | --- | --- |
| **Load balancer** | Place a managed load balancer or reverse proxy in front of two or more stateless Laravel API instances. Use health checks, TLS termination, and rolling deployments. | Distributes traffic, removes unhealthy instances, and allows horizontal scaling without downtime. |
| **Database replication** | Use a primary database for writes and one or more read replicas for reporting, catalog reads, and non-locking history queries. Keep order creation, inventory reservation, and other transactions on the primary. | Improves read capacity while preserving the strong consistency required for stock locks and order writes. |
| **Redis Cluster** | Use a multi-node Redis Cluster with sharded primary nodes and replica nodes for automatic failover and horizontal cache capacity. | Prevents a single Redis node from becoming a cache bottleneck or single point of failure. This is commonly called a **Redis Cluster**. |
| **Redis Sentinel alternative** | If sharding is not required, use Redis primary/replica nodes monitored by Redis Sentinel. | Sentinel provides automatic failover and high availability; Redis Cluster provides both high availability and sharding. |
| **Queue workers** | Run multiple independently scalable queue workers and monitor failed jobs, retries, and queue depth. | Keeps notifications and other background work from delaying API requests. |

For Laravel, configure multiple application instances with the same `APP_KEY`, use Redis for cache/queue coordination, and store user uploads outside the application container (for example, object storage). Do not send inventory reservations to read replicas: the reservation transaction must continue to use the primary database with row locking.

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

Docker Compose starts Nginx, PHP-FPM, the queue worker, MySQL, and Redis. Ensure Docker Desktop is running, then review `.env.docker` before the first start. In particular, replace the example `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and `REDIS_PASSWORD` values outside local development.

Start the complete stack in the background:

```bash
docker compose up -d --build
```

Wait until MySQL, Redis, and PHP are healthy:

```bash
docker compose ps
```

Run every outstanding migration and seed the 20 categories plus 20 products/inventory records:

```bash
docker compose exec php php artisan migrate --seed
```

Confirm migration state and inspect seeded data:

```bash
docker compose exec php php artisan migrate:status
docker compose exec php php artisan tinker --execute 'dump(App\Models\Category::count(), App\Models\Product::count(), App\Models\Inventory::count());'
```

The API is available at `http://localhost:8080`; the queue worker starts automatically as the `queue` service. Check its output with:

```bash
docker compose logs -f queue
```

Useful Docker commands:

```bash
# Rebuild after PHP, configuration, or Dockerfile changes
docker compose up -d --build --force-recreate php nginx queue

# Run the full test suite inside the PHP container
docker compose exec php php artisan test --compact

# Stop containers but preserve MySQL and Redis volumes
docker compose down
```

To reset local Docker database and Redis data completely, use the following destructive command only when you no longer need the existing data:

```bash
docker compose down -v
```

## Quality checks

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Focused tests cover product/category management, factory/seeding, order reservation, idempotent retries, cancellation, inventory commitment, and customer reporting.
