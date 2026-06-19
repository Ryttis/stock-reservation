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

This project uses Docker Compose to run supporting services. **PHP itself runs on the host.** There is no PHP application container.

| Service      | Image             | Host port  | Purpose                          |
|--------------|-------------------|------------|----------------------------------|
| `database`   | mysql:8.4         | `3307`     | MySQL database                   |
| `phpmyadmin` | phpmyadmin:latest | `8081`     | Database UI (`http://127.0.0.1:8081`) |
| `mailer`     | axllent/mailpit   | random     | Local SMTP catch-all             |

Start all supporting services:

```bash
docker compose up -d
```

Stop them:

```bash
docker compose down
```

## Setup

### 1. Start Docker services

```bash
docker compose up -d
```

This starts MySQL on `127.0.0.1:3307` and phpMyAdmin on `http://127.0.0.1:8081`.

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env .env.local
```

Edit `.env.local` and point `DATABASE_URL` at the Docker MySQL container:

```dotenv
DATABASE_URL="mysql://app:app@127.0.0.1:3307/stock_reservation?serverVersion=8.4&charset=utf8mb4"
```

MySQL credentials (defined in `compose.yaml`): user `app`, password `app`, database `stock_reservation`.

### 4. Create database and run migrations

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

### 5. Seed sample data

```bash
php bin/console app:seed-sample-data
```

Use `--reset` to wipe existing data before seeding:

```bash
php bin/console app:seed-sample-data --reset
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

Start the application server (PHP runs on the host):

```bash
symfony server:start
```

Or using the built-in PHP server:

```bash
php -S 127.0.0.1:8000 -t public
```

The API is then available at `http://127.0.0.1:8000`.

## API Endpoints

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

Returns full order detail including items and reservation.

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

Returns `404` if the order does not exist. The `reservation` field is `null` for pending orders.

### POST /api/orders/{id}/reserve

Reserves stock for a pending order. Sets status to `reserved` (fully allocated) or `partially_reserved`.

```json
{
  "id": 1,
  "status": "reserved",
  "createdAt": "2025-01-01T10:00:00+00:00",
  "shippedAt": null,
  "cancelledAt": null
}
```

Returns `409` if the order cannot be reserved (wrong status, already reserved, no items).

### POST /api/orders/{id}/ship

Ships a reserved or partially reserved order. Decreases `WarehouseStock` quantities. Sets status to `shipped`.

```json
{
  "id": 1,
  "status": "shipped",
  "createdAt": "2025-01-01T10:00:00+00:00",
  "shippedAt": "2025-01-02T09:00:00+00:00",
  "cancelledAt": null
}
```

Returns `409` if the order cannot be shipped.

### POST /api/orders/{id}/cancel

Cancels a reserved or partially reserved order. Recalculates all remaining active reservations using the freed stock. Sets status to `cancelled`.

```json
{
  "id": 1,
  "status": "cancelled",
  "createdAt": "2025-01-01T10:00:00+00:00",
  "shippedAt": null,
  "cancelledAt": "2025-01-02T08:00:00+00:00"
}
```

Returns `409` if the order cannot be cancelled.

## Manual API Check

After seeding sample data and starting the application server:

```bash
curl -s http://127.0.0.1:8000/api/products | jq
curl -s http://127.0.0.1:8000/api/warehouses | jq
curl -s http://127.0.0.1:8000/api/orders | jq
curl -s http://127.0.0.1:8000/api/orders/1 | jq
curl -s -X POST http://127.0.0.1:8000/api/orders/1/reserve | jq
curl -s -X POST http://127.0.0.1:8000/api/orders/1/ship | jq
curl -s -X POST http://127.0.0.1:8000/api/orders/2/reserve | jq
curl -s -X POST http://127.0.0.1:8000/api/orders/2/cancel | jq
```

## Testing

Tests use an isolated SQLite database by default and do not require Docker to be running.

Run the full test suite:

```bash
php bin/phpunit
php bin/phpunit --no-coverage
```

Run specific groups:

```bash
php bin/phpunit tests/Integration/Controller --no-coverage
php bin/phpunit tests/Integration/Service --no-coverage
```

## Validation

```bash
php bin/console lint:container
php bin/console doctrine:schema:validate
php bin/console debug:router | grep '/api/'
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
- [x] Unit tests (allocation strategy, entity guards)
- [x] Integration tests (all services)
- [x] API tests (all endpoints)
