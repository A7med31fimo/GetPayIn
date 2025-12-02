# Flash Sale API - (Laravel 12)

A high-concurrency flash sale API that prevents overselling using database transactions, row-level locking, and optimistic concurrency control.

## Features

- **No Overselling**: Uses optimistic locking with count field + pessimistic locking for critical sections
- **Temporary Holds**: 2-minute reservations with automatic expiration after time expire
- **Idempotent Webhooks**: Duplicate webhook calls are safely ignored
- **Out-of-Order Safe**: Webhooks can arrive before order creation response
- **High Concurrency**: Handles burst traffic with proper locking strategies

## Setup

```bash
# Install dependencies
composer install

# Configure database in .env
DB_CONNECTION=mysql 
DB_HOST=127.0.0.1 //localhost
DB_PORT=3306 //3307
DB_DATABASE=flash_sale
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate
press yes to accept creating db 
# adding some products from seeders
php artisan db:seed -- FlashSaleSeeder

# Start scheduler (for auto-releasing expired holds)
php artisan schedule:work

# Start development server
php artisan serve
```

## API Endpoints

### 1. Get Product
```bash
GET http://127.0.0.1:8000/api/products/{id}

Response:
{
  "id": 1,
  "name": "Limited Edition Flash Sale Item",
  "price": 99.99,
  "available_stock": 100,
  "total_stock": 100
}
```

### 2. Create Hold
```bash
POST http://127.0.0.1:8000/api/holds
Content-Type: json

{
  "product_id": 1,
  "quantity": 2
}

Success Response (201):
{
  "hold_id": 1,
  "expires_at": "2024-01-01T12:02:00.000000Z"
}

Error Response (400):
{
  "error": "Insufficient stock available"
}
```

### 3. Create Order
```bash
POST http://127.0.0.1:8000/api/orders
Content-Type: json

{
  "hold_id": 1
}

Success Response (201):
{
  "order_id": 1,
  "product_id": 1,
  "quantity": 2,
  "total_price": 199.98,
  "status": "pending"
}

Error Response (400):
{
  "error": "Hold has expired"
}
```

### 4. Payment Webhook (Idempotent)
```bash
POST /api/payments/webhook
Content-Type: json

{
  "idempotency_key": "unique-transaction-123",
  "order_id": 1,
  "status": "success",
  "payload": {
    "transaction_id": "txn_abc123",
    "amount": 199.98
  }
}

Response:
{
  "success": true,
  "already_processed": false,
  "order_status": "paid",
  "webhook_id": 1
}
```

## Concurrency Handling

### Stock Reservation Strategy
1. **Optimistic Locking**: count field increments on every stock change
2. **Pessimistic Locking**: `lockForUpdate()` on critical paths
3. **Atomic Updates**: All stock changes use database-level calculations

### Hold Expiration
- Holds expire after 2 minutes as u asked
- Scheduled job runs every minute to release expired holds to make db shows the real data in all times

### Payment Webhook Idempotency
- Idempotency key ensures duplicate webhooks are safely ignored
- Same webhook can be sent multiple times without side effects
- Works even if webhook arrives before client receives order response

## License

MIT