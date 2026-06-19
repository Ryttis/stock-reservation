# Stock Reservation API

A JSON API for reserving stock across multiple warehouses. Orders are allocated using the fewest possible warehouses, with support for partial reservations, shipping, and cancellation.

## Tech Stack

- PHP 8.4
- Symfony 8.1
- MySQL 8.4
- Doctrine ORM
- PHPUnit

## Architecture

Layered monolith with clear separation of concerns:

- **Domain entities** — `CustomerOrder`, `OrderItem`, `Reservation`, `ReservationItem`, `Product`, `Warehouse`, `WarehouseStock`
- **Application services** — `StockReservationService`, `OrderShippingService`, `OrderCancellationService`; each performs a single `flush()` at the end
- **Thin API controllers** — find entity, call service, return JSON; no business logic
- **Pure allocation module** — `FewestWarehousesAllocationStrategy` has zero Symfony or Doctrine dependencies; accepts DTOs and returns a result; fully unit-testable in isolation
- **API exception handling** — `ApiExceptionSubscriber` converts exceptions to JSON for all `/api/*` routes; 404 and 409 responses are JSON
- **Integration tests** — real database, no mocks; tests cover all service and controller layers
- **Deterministic behavior** — tiebreaking by smallest warehouse surplus; ordering by `createdAt ASC, id ASC` during cancellation recalculation
- **Audit trail** — `ReservationItem` records are preserved on shipping (status → `released`) and cancellation (status → `cancelled`)
- **Physical stock** — `WarehouseStock.quantity` is only decreased on shipping; reservation and cancellation do not mutate it

## Business Flow

```
Pending → Reserved / PartiallyReserved → Shipped
Pending → Reserved / PartiallyReserved → Cancelled
```

Cancelling a reserved order triggers recalculation of all remaining active reservations in `createdAt ASC, id ASC` order using the newly freed stock.

## Allocation Rules

- Satisfies each requested SKU using the fewest possible warehouses
- Duplicate SKUs in the same order are aggregated before allocation
- Partial allocation is supported — unmet quantities appear as missing items
- When multiple warehouse combinations tie on count, the one with the smallest total surplus is preferred
- Result is deterministic for the same input

## Docker Services

The project runs through Docker Compose. PHP-FPM runs in the `app` container, Nginx exposes the API on port `8000`, and MySQL runs in the `database` container.

| Service      | Image                  | Host port | Purpose                               |
|--------------|------------------------|-----------|---------------------------------------|
| `app`        | PHP 8.4 FPM (built)    | —         | Symfony application                   |
| `nginx`      | nginx:1.27-alpine      | `8000`    | HTTP server — `http://127.0.0.1:8000` |
| `database`   | mysql:8.4              | `3307`    | MySQL database                        |
| `phpmyadmin` | phpmyadmin:latest      | `8081`    | Database UI — `http://127.0.0.1:8081` |

Inside Docker, the application connects to MySQL using the Compose service name `database` and internal port `3306`. The host port `3307` is only for connecting from the host machine or external DB clients (e.g. phpMyAdmin, a local MySQL client).

## Setup

### 1. Build and start all services

```bash
docker compose up -d --build
```

### 2. Install PHP dependencies

```bash
docker compose exec app composer install
```

### 3. Configure environment

The `app` container already has `DATABASE_URL` set via `compose.yaml`:

```dotenv
DATABASE_URL="mysql://app:app@database:3306/stock_reservation?serverVersion=8.4&charset=utf8mb4"
```

No `.env.local` changes are needed for Docker. The MySQL credentials are `app` / `app` (defined in `compose.yaml`).

### 4. Create database and run migrations

```bash
docker compose exec app php bin/console doctrine:database:create --if-not-exists
docker compose exec app php bin/console doctrine:migrations:migrate
```

### 5. Seed sample data

```bash
docker compose exec app php bin/console app:seed-sample-data --reset
```

## Sample Data

The seeded dataset includes:

**Products:** `PENCIL`, `NOTEBOOK`, `BAG`, `PEN`, `ERASER`

**Warehouses:** `WH_A`, `WH_B`, `WH_C`

**Stock levels:**

| Product  | WH_A | WH_B | WH_C |
|----------|------|------|------|
| PENCIL   | 10   | 5    | 100  |
| NOTEBOOK | 2    | 10   | 0    |
| BAG      | 0    | 3    | 2    |
| PEN      | 20   | 5    | 1    |
| ERASER   | 5    | 20   | 0    |

**Orders:**

| Order | Items                           |
|-------|---------------------------------|
| 1     | PENCIL ×8, NOTEBOOK ×2          |
| 2     | PENCIL ×12, NOTEBOOK ×8, BAG ×2 |
| 3     | BAG ×10, ERASER ×30             |
| 4     | PENCIL ×100                     |

## Run

```bash
docker compose up -d --build
```

The API is available at `http://127.0.0.1:8000`.

## Optional: Run without Docker

If you prefer to run PHP on the host instead of Docker, configure `.env.local` to point at the host-exposed MySQL port:

```dotenv
DATABASE_URL="mysql://app:app@127.0.0.1:3307/stock_reservation?serverVersion=8.4&charset=utf8mb4"
```

Start the application:

```bash
symfony server:start
```

Or using the built-in PHP server:

```bash
php -S 127.0.0.1:8000 -t public
```

## API Endpoints

All error responses under `/api/*` are JSON. Unexpected errors return HTTP 500 with `{"error": "Internal server error."}`.

### GET /api/products

Returns all products.

```json
[
  { "id": 1, "sku": "PENCIL", "name": "Pencil" },
  { "id": 2, "sku": "NOTEBOOK", "name": "Notebook" }
]
```

### GET /api/warehouses

Returns all warehouses.

```json
[
  { "id": 1, "code": "WH_A", "name": "Warehouse A" },
  { "id": 2, "code": "WH_B", "name": "Warehouse B" }
]
```

### GET /api/orders

Returns a compact list of all orders.

```json
[
  {
    "id": 1,
    "status": "pending",
    "createdAt": "2025-01-01T10:00:00+00:00",
    "shippedAt": null,
    "cancelledAt": null
  }
]
```

### GET /api/orders/{id}

Returns full order detail including items and reservation. Returns `404` JSON if the order does not exist. The `reservation` field is `null` for pending orders.

```json
{
  "id": 1,
  "status": "reserved",
  "createdAt": "2025-01-01T10:00:00+00:00",
  "shippedAt": null,
  "cancelledAt": null,
  "items": [
    {
      "product": { "id": 1, "sku": "PENCIL" },
      "quantity": 8
    }
  ],
  "reservation": {
    "status": "active",
    "items": [
      {
        "product": { "id": 1, "sku": "PENCIL" },
        "warehouse": { "id": 1, "code": "WH_A" },
        "quantity": 8
      }
    ]
  }
}
```

### POST /api/orders/{id}/reserve

Reserves stock for a pending order. Sets status to `reserved` or `partially_reserved`. Returns `409` JSON if the order cannot be reserved.

```json
{ "id": 1, "status": "reserved", "createdAt": "...", "shippedAt": null, "cancelledAt": null }
```

### POST /api/orders/{id}/ship

Ships a reserved or partially reserved order. Decreases `WarehouseStock` quantities. Sets status to `shipped`. Returns `409` JSON if the order cannot be shipped.

```json
{ "id": 1, "status": "shipped", "createdAt": "...", "shippedAt": "...", "cancelledAt": null }
```

### POST /api/orders/{id}/cancel

Cancels a reserved or partially reserved order. Recalculates active reservations using freed stock. Sets status to `cancelled`. Returns `409` JSON if the order cannot be cancelled.

```json
{ "id": 1, "status": "cancelled", "createdAt": "...", "shippedAt": null, "cancelledAt": "..." }
```

## Manual API Check

Requires `jq` for JSON formatting (optional but recommended).

```bash
BASE_URL="http://127.0.0.1:8000"

curl -s "$BASE_URL/api/products" | jq
curl -s "$BASE_URL/api/warehouses" | jq
curl -s "$BASE_URL/api/orders" | jq

ORDER_ID=$(curl -s "$BASE_URL/api/orders" | jq 'map(select(.status == "pending"))[0].id')
CANCEL_ORDER_ID=$(curl -s "$BASE_URL/api/orders" | jq 'map(select(.status == "pending"))[1].id')

echo "Ship flow order: $ORDER_ID"
curl -s "$BASE_URL/api/orders/$ORDER_ID" | jq
curl -s -X POST "$BASE_URL/api/orders/$ORDER_ID/reserve" | jq
curl -s -X POST "$BASE_URL/api/orders/$ORDER_ID/ship" | jq
curl -s "$BASE_URL/api/orders/$ORDER_ID" | jq

echo "Cancel flow order: $CANCEL_ORDER_ID"
curl -s "$BASE_URL/api/orders/$CANCEL_ORDER_ID" | jq
curl -s -X POST "$BASE_URL/api/orders/$CANCEL_ORDER_ID/reserve" | jq
curl -s -X POST "$BASE_URL/api/orders/$CANCEL_ORDER_ID/cancel" | jq
curl -s "$BASE_URL/api/orders/$CANCEL_ORDER_ID" | jq

echo "404 JSON check"
curl -i "$BASE_URL/api/orders/999999"

echo "409 JSON check"
CONFLICT_ORDER_ID=$(curl -s "$BASE_URL/api/orders" | jq 'map(select(.status == "pending"))[0].id')
curl -s -X POST "$BASE_URL/api/orders/$CONFLICT_ORDER_ID/reserve" | jq
curl -i -X POST "$BASE_URL/api/orders/$CONFLICT_ORDER_ID/reserve"
```

Note: After `--reset` re-seeding, MySQL auto-increment IDs continue from where they left off. The script above selects orders dynamically by status rather than by hardcoded ID.

## Testing

Tests use the `stock_reservation_test` MySQL database. Set it up once before running tests:

```bash
docker compose exec database mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS stock_reservation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON stock_reservation_test.* TO 'app'@'%'; FLUSH PRIVILEGES;"
docker compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

Run the full test suite:

```bash
docker compose exec app php bin/phpunit
docker compose exec app php bin/phpunit --no-coverage
```

Run specific groups:

```bash
docker compose exec app php bin/phpunit tests/Integration/Controller --no-coverage
docker compose exec app php bin/phpunit tests/Integration/Service --no-coverage
```

## Validation

```bash
docker compose exec app php bin/console lint:container
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/console debug:router | grep '/api/'
```

## Expected API Routes

```
POST /api/orders/{id}/reserve
POST /api/orders/{id}/ship
POST /api/orders/{id}/cancel
GET  /api/orders
GET  /api/orders/{id}
GET  /api/products
GET  /api/warehouses
```

## Project Structure

```
src/
  Allocation/         Pure allocation module (no framework dependencies)
  Controller/
    Api/              Thin JSON controllers
  Entity/             Doctrine ORM entities
  Enum/               OrderStatus, ReservationStatus
  EventSubscriber/    ApiExceptionSubscriber
  Repository/
  Service/            Application services

tests/
  Unit/               Allocation strategy unit tests, entity unit tests
  Integration/
    Controller/       API endpoint tests (WebTestCase)
    Service/          Service integration tests (KernelTestCase)
```

## Current Status

- [x] Sample data (products, warehouses, stock levels, orders)
- [x] Pure allocation strategy (fewest warehouses, smallest surplus tiebreaker)
- [x] Reservation service with partial allocation support
- [x] Shipping service with physical stock mutation
- [x] Cancellation service with active reservation recalculation
- [x] Read API endpoints (products, warehouses, orders)
- [x] Order action API endpoints (reserve, ship, cancel)
- [x] JSON API error responses for 404 and 409 cases
- [x] Unit tests (allocation strategy, entity guards)
- [x] Integration tests (all services)
- [x] API tests (all endpoints)
