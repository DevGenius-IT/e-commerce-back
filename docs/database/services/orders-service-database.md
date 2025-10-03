# Orders Service Database Documentation

## Table of Contents
- [Overview](#overview)
- [Database Information](#database-information)
- [Entity Relationship Diagram](#entity-relationship-diagram)
- [Table Schemas](#table-schemas)
- [Order State Machine](#order-state-machine)
- [Tax Calculations](#tax-calculations)
- [Events Published](#events-published)
- [Cross-Service References](#cross-service-references)
- [Checkout Workflow Saga](#checkout-workflow-saga)
- [Indexes and Performance](#indexes-and-performance)

## Overview

The orders-service database (`orders_service_db`) manages the complete order lifecycle from placement to delivery. This service orchestrates complex checkout workflows, tracks order status transitions through a state machine, handles tax calculations, and coordinates with multiple services (auth, addresses, products, deliveries) through asynchronous messaging.

**Service:** orders-service
**Database:** orders_service_db
**External Port:** 3330
**Total Tables:** 3 (1 reference, 1 core, 1 items)

**Key Capabilities:**
- Order placement and lifecycle management
- State machine for order status transitions
- Tax calculations (HT/TTC/VAT)
- Multi-item order support
- Address association (billing/shipping)
- Order history tracking
- Soft deletes for order preservation
- Discount and promotion handling

## Database Information

### Connection Details
```bash
Host: localhost (in Docker network: mysql-orders)
Port: 3330 (external), 3306 (internal)
Database: orders_service_db
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Engine: InnoDB
```

### Environment Configuration
```bash
DB_CONNECTION=mysql
DB_HOST=mysql-orders
DB_PORT=3306
DB_DATABASE=orders_service_db
DB_USERNAME=orders_user
DB_PASSWORD=orders_pass
```

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                     ORDERS SERVICE DATABASE                         │
│                    orders_service_db (3 tables)                     │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                         ORDER STATUS REFERENCE                       │
└──────────────────────────────────────────────────────────────────────┘

    ┌──────────────────┐
    │  order_status    │
    ├──────────────────┤
    │ id           PK  │
    │ name         UK  │ UNIQUE -- pending, confirmed, processing, shipped, delivered, cancelled
    │ description      │
    │ created_at       │
    │ updated_at       │
    └────────┬─────────┘
             │
             │ status_id (FK)
             ▼
┌──────────────────────────────────────────────────────────────────────┐
│                           CORE ORDERS                                │
└──────────────────────────────────────────────────────────────────────┘

    ┌────────────────────────────────────────────────────────┐
    │                      orders                            │
    ├────────────────────────────────────────────────────────┤
    │ id                      PK                             │
    │ order_number            UNIQUE                         │
    │ user_id                 FK → auth-service              │────────┐
    │ billing_address_id      FK → addresses-service         │──────┐ │
    │ shipping_address_id     FK → addresses-service (NULL)  │────┐ │ │
    │ status_id               FK → order_status              │──┐ │ │ │
    │ total_amount_ht         DECIMAL(10,2)                  │  │ │ │ │
    │ total_amount_ttc        DECIMAL(10,2)                  │  │ │ │ │
    │ total_discount          DECIMAL(10,2) DEFAULT 0        │  │ │ │ │
    │ vat_amount              DECIMAL(10,2)                  │  │ │ │ │
    │ notes                   TEXT (NULL)                    │  │ │ │ │
    │ created_at                                             │  │ │ │ │
    │ updated_at                                             │  │ │ │ │
    │ deleted_at                                             │  │ │ │ │
    └────────┬───────────────────────────────────────────────┘  │ │ │ │
             │                                                   │ │ │ │
             │ order_id (FK CASCADE)                             │ │ │ │
             ▼                                                   │ │ │ │
    ┌────────────────────────────────────────────────┐           │ │ │ │
    │              order_items                       │           │ │ │ │
    ├────────────────────────────────────────────────┤           │ │ │ │
    │ id                    PK                       │           │ │ │ │
    │ order_id              FK → orders              │───────────┘ │ │ │
    │ product_id            FK → products-service    │─────────────┤ │ │
    │ product_name          VARCHAR(255) -- snapshot │             │ │ │
    │ product_ref           VARCHAR(255) -- snapshot │             │ │ │
    │ quantity              INT                      │             │ │ │
    │ unit_price_ht         DECIMAL(8,2)             │             │ │ │
    │ unit_price_ttc        DECIMAL(8,2)             │             │ │ │
    │ total_price_ht        DECIMAL(10,2)            │             │ │ │
    │ total_price_ttc       DECIMAL(10,2)            │             │ │ │
    │ vat_rate              DECIMAL(5,2)             │             │ │ │
    │ created_at                                     │             │ │ │
    │ updated_at                                     │             │ │ │
    └────────────────────────────────────────────────┘             │ │ │
                                                                   │ │ │
    CROSS-SERVICE REFERENCES (Virtual):                           │ │ │
    ─────────────────────────────────────────────────────────────┼─┼─┼─┘
                                                                   │ │ │
    orders.status_id       → order_status.id (Internal FK)       ─┘ │ │
    orders.billing_address_id  → addresses-service.addresses.id ────┘ │
    orders.shipping_address_id → addresses-service.addresses.id ──────┘
    orders.user_id             → auth-service.users.id
    order_items.product_id     → products-service.products.id

LEGEND:
────────  Relationship / Foreign Key (Internal)
───────   Virtual Foreign Key (Cross-Service)
PK        Primary Key
FK        Foreign Key
UK        Unique Key
UNIQUE    Unique Constraint
```

## Table Schemas

### 1. order_status
Order status reference table defining valid order states.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Status ID |
| name | VARCHAR(255) | NOT NULL, UNIQUE | Status name (machine-readable) |
| description | VARCHAR(255) | NULLABLE | Human-readable description |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (name)

**Business Rules:**
- Static reference data (seeded on migration)
- No soft deletes (permanent status definitions)
- name is unique and used for state machine transitions

**Standard Status Values:**
```sql
INSERT INTO order_status (name, description) VALUES
('pending', 'Order created, awaiting confirmation'),
('confirmed', 'Order confirmed by customer'),
('processing', 'Order being prepared for shipment'),
('shipped', 'Order shipped to customer'),
('delivered', 'Order delivered to customer'),
('cancelled', 'Order cancelled by customer or admin');
```

**Status Flow:**
```
pending → confirmed → processing → shipped → delivered
            ↓
        cancelled (from any state except delivered)
```

---

### 2. orders (CORE)
Central order entity with financial calculations and status tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Order ID |
| order_number | VARCHAR(255) | NOT NULL, UNIQUE | Human-readable order reference |
| user_id | BIGINT UNSIGNED | NOT NULL | Customer reference (auth-service) |
| billing_address_id | BIGINT UNSIGNED | NOT NULL | Billing address (addresses-service) |
| shipping_address_id | BIGINT UNSIGNED | NULLABLE | Shipping address (addresses-service) |
| status_id | BIGINT UNSIGNED | NOT NULL, FK | Order status (order_status.id) |
| total_amount_ht | DECIMAL(10,2) | NOT NULL, DEFAULT 0 | Total excluding tax |
| total_amount_ttc | DECIMAL(10,2) | NOT NULL, DEFAULT 0 | Total including tax |
| total_discount | DECIMAL(10,2) | NOT NULL, DEFAULT 0 | Total discount amount |
| vat_amount | DECIMAL(10,2) | NOT NULL, DEFAULT 0 | Total VAT amount |
| notes | TEXT | NULLABLE | Order notes or special instructions |
| created_at | TIMESTAMP | NULLABLE | Order placement timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete timestamp |

**Foreign Keys:**
- status_id → order_status(id) ON DELETE RESTRICT

**Cross-Service References (Virtual):**
- user_id → auth-service.users.id (no FK constraint)
- billing_address_id → addresses-service.addresses.id (no FK constraint)
- shipping_address_id → addresses-service.addresses.id (no FK constraint)

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (order_number)
- INDEX (user_id)
- INDEX (billing_address_id)
- INDEX (shipping_address_id)
- INDEX (status_id)
- INDEX (order_number)
- INDEX (created_at)

**Business Rules:**
- Soft deletes enabled (preserve order history)
- order_number must be unique (format: ORD-YYYYMMDD-XXXX)
- shipping_address_id nullable (can be same as billing)
- Financial amounts stored with 2 decimal precision
- Timestamps track order lifecycle
- notes field for customer/admin comments

**Model Relationships:**
- belongsTo: OrderStatus (via status_id)
- hasMany: OrderItem
- Virtual relationships to other services via events

**Calculated Fields:**
```php
// Derived from order_items
total_amount_ht = sum(order_items.total_price_ht) - total_discount
vat_amount = sum(order_items.total_price_ttc - order_items.total_price_ht)
total_amount_ttc = total_amount_ht + vat_amount
```

---

### 3. order_items
Order line items with product snapshots and tax calculations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Order item ID |
| order_id | BIGINT UNSIGNED | NOT NULL, FK | Order reference (orders.id) |
| product_id | BIGINT UNSIGNED | NOT NULL | Product reference (products-service) |
| product_name | VARCHAR(255) | NOT NULL | Product name snapshot |
| product_ref | VARCHAR(255) | NOT NULL | Product reference snapshot |
| quantity | INTEGER | NOT NULL | Quantity ordered |
| unit_price_ht | DECIMAL(8,2) | NOT NULL | Unit price excluding tax |
| unit_price_ttc | DECIMAL(8,2) | NOT NULL | Unit price including tax |
| total_price_ht | DECIMAL(10,2) | NOT NULL | Line total excluding tax |
| total_price_ttc | DECIMAL(10,2) | NOT NULL | Line total including tax |
| vat_rate | DECIMAL(5,2) | NOT NULL, DEFAULT 0 | VAT rate at purchase time |
| created_at | TIMESTAMP | NULLABLE | Item added timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- order_id → orders(id) ON DELETE CASCADE

**Cross-Service References (Virtual):**
- product_id → products-service.products.id (no FK constraint)

**Indexes:**
- PRIMARY KEY (id)
- INDEX (order_id)
- INDEX (product_id)

**Business Rules:**
- Cascade delete with parent order
- Product data snapshotted (name, ref) to preserve history
- Prices captured at order time (immutable)
- vat_rate stored per item (products may have different rates)
- No soft deletes (deleted with parent order)

**Calculated Fields:**
```php
unit_price_ttc = unit_price_ht * (1 + vat_rate / 100)
total_price_ht = unit_price_ht * quantity
total_price_ttc = unit_price_ttc * quantity
```

**Model Relationships:**
- belongsTo: Order (via order_id)
- Virtual relationship to products-service via events

**Data Snapshot Rationale:**
- product_name, product_ref preserved for order history
- Even if product deleted/updated, order shows original details
- Prices frozen at order time (no retroactive changes)

## Order State Machine

### Valid Status Transitions

```
┌────────────────────────────────────────────────────────────┐
│                    ORDER STATE MACHINE                     │
└────────────────────────────────────────────────────────────┘

                    ┌──────────┐
                    │ pending  │ -- Initial state
                    └────┬─────┘
                         │
                         │ CustomerConfirms()
                         ▼
                    ┌──────────┐
                    │confirmed │
                    └────┬─────┘
                         │
                         │ StartProcessing()
                         ▼
                    ┌──────────┐
                    │processing│
                    └────┬─────┘
                         │
                         │ ShipOrder()
                         ▼
                    ┌──────────┐
                    │ shipped  │
                    └────┬─────┘
                         │
                         │ ConfirmDelivery()
                         ▼
                    ┌──────────┐
                    │delivered │ -- Terminal state
                    └──────────┘

                    ┌──────────┐
                    │cancelled │ -- Terminal state
                    └──────────┘
                         ▲
                         │
                         │ CancelOrder()
                         │ (from pending, confirmed, processing)
                         │
                    [any state except delivered]
```

### State Transition Rules

| From State | To State | Action | Validation |
|------------|----------|--------|------------|
| pending | confirmed | CustomerConfirms() | Payment authorized |
| pending | cancelled | CancelOrder() | Always allowed |
| confirmed | processing | StartProcessing() | Inventory reserved |
| confirmed | cancelled | CancelOrder() | Payment not captured |
| processing | shipped | ShipOrder() | Delivery created |
| processing | cancelled | CancelOrder() | Admin approval required |
| shipped | delivered | ConfirmDelivery() | Delivery confirmed |
| shipped | cancelled | CancelOrder() | Return initiated (rare) |

### Terminal States

**delivered:**
- Final successful state
- No further transitions allowed
- Order complete and archived

**cancelled:**
- Final failure state
- No further transitions allowed
- Inventory released, refund processed

### State Machine Implementation

```php
// OrderService.php - State Machine Logic

public function transitionToConfirmed(Order $order): bool
{
    if ($order->status->name !== 'pending') {
        throw new InvalidStateTransitionException('Can only confirm pending orders');
    }

    // Validate payment authorization
    $this->validatePaymentAuthorization($order);

    // Update status
    $order->status()->associate(OrderStatus::where('name', 'confirmed')->first());
    $order->save();

    // Publish event
    event(new OrderConfirmed($order));

    return true;
}

public function transitionToCancelled(Order $order, string $reason): bool
{
    if ($order->status->name === 'delivered') {
        throw new InvalidStateTransitionException('Cannot cancel delivered orders');
    }

    // Release inventory
    $this->releaseInventory($order);

    // Initiate refund if payment captured
    if ($order->status->name !== 'pending') {
        $this->initiateRefund($order);
    }

    // Update status
    $order->status()->associate(OrderStatus::where('name', 'cancelled')->first());
    $order->notes = "Cancelled: {$reason}";
    $order->save();

    // Publish event
    event(new OrderCancelled($order, $reason));

    return true;
}
```

### Status Validation Guards

```php
// Middleware: ValidateOrderStatusTransition

public function handle(Request $request, Closure $next)
{
    $order = $request->route('order');
    $targetStatus = $request->input('status');

    $validTransitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [], // terminal state
        'cancelled' => []  // terminal state
    ];

    if (!in_array($targetStatus, $validTransitions[$order->status->name])) {
        abort(422, "Invalid status transition from {$order->status->name} to {$targetStatus}");
    }

    return $next($request);
}
```

## Tax Calculations

### HT/TTC/VAT Relationships

```
HT  = Hors Taxes (Price Excluding Tax)
TTC = Toutes Taxes Comprises (Price Including Tax)
VAT = Value Added Tax (TVA in French)

Formulas:
TTC = HT * (1 + VAT_RATE / 100)
HT  = TTC / (1 + VAT_RATE / 100)
VAT = TTC - HT
```

### Order-Level Calculations

```php
// OrderService.php - Tax Calculations

public function calculateOrderTotals(Order $order): void
{
    $items = $order->items;

    // Sum from all order items
    $total_ht = $items->sum('total_price_ht');
    $total_ttc = $items->sum('total_price_ttc');

    // Apply discount to HT amount
    $discounted_ht = $total_ht - $order->total_discount;

    // Recalculate VAT after discount
    $vat_amount = $total_ttc - $total_ht;

    // Final TTC after discount
    $final_ttc = $discounted_ht + $vat_amount;

    // Update order
    $order->total_amount_ht = $discounted_ht;
    $order->vat_amount = $vat_amount;
    $order->total_amount_ttc = $final_ttc;
    $order->save();
}
```

### Item-Level Calculations

```php
// OrderItemService.php - Item Tax Calculations

public function calculateItemTotals(array $itemData): array
{
    $unit_price_ht = $itemData['unit_price_ht']; // from products-service
    $quantity = $itemData['quantity'];
    $vat_rate = $itemData['vat_rate']; // from products-service.types.vat

    // Calculate unit price TTC
    $unit_price_ttc = $unit_price_ht * (1 + $vat_rate / 100);

    // Calculate line totals
    $total_price_ht = $unit_price_ht * $quantity;
    $total_price_ttc = $unit_price_ttc * $quantity;

    return [
        'unit_price_ht' => round($unit_price_ht, 2),
        'unit_price_ttc' => round($unit_price_ttc, 2),
        'total_price_ht' => round($total_price_ht, 2),
        'total_price_ttc' => round($total_price_ttc, 2),
        'vat_rate' => $vat_rate,
    ];
}
```

### VAT Rate Sources

VAT rates come from products-service:
```sql
-- products-service
types.id_1 → vat.id → vat.value_

-- Example:
Electronics → Standard VAT (20%)
Books → Reduced VAT (5.5%)
Food → Reduced VAT (5.5%)
```

### Discount Handling

```php
// Apply discount BEFORE VAT calculation (French law)

// Example: Product HT=100, VAT=20%, Discount=10
$item_ht = 100.00;
$vat_rate = 20.00;
$discount = 10.00;

// Method 1: Item-level discount
$discounted_ht = $item_ht - $discount; // 90
$item_ttc = $discounted_ht * (1 + $vat_rate / 100); // 108

// Method 2: Order-level discount (current implementation)
$item_ttc_before_discount = $item_ht * (1 + $vat_rate / 100); // 120
$order_ht_total = sum($all_items_ht) - $order_discount;
$order_ttc_total = $order_ht_total + sum($all_items_vat);
```

### Tax Breakdown Example

```
Order #ORD-20251003-0042
┌──────────────────┬─────┬──────────┬──────────┬──────────┬──────────┐
│ Product          │ Qty │ Price HT │ VAT Rate │ Price TTC│ Line TTC │
├──────────────────┼─────┼──────────┼──────────┼──────────┼──────────┤
│ Laptop           │  1  │  833.33  │   20%    │ 1000.00  │ 1000.00  │
│ Book             │  2  │   18.95  │   5.5%   │   20.00  │   40.00  │
│ Mouse            │  3  │   16.67  │   20%    │   20.00  │   60.00  │
└──────────────────┴─────┴──────────┴──────────┴──────────┴──────────┘

Subtotal HT:                                               1053.93
Discount:                                                   -50.00
────────────────────────────────────────────────────────────────
Total HT:                                                  1003.93
VAT (20%):                                                  183.34
VAT (5.5%):                                                   2.08
────────────────────────────────────────────────────────────────
Total TTC:                                                 1189.35
```

## Events Published

The orders-service publishes events to RabbitMQ for inter-service communication.

### Event Schema

All events follow standard message format:
```json
{
  "event": "order.placed",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "order_id": 42,
    "order_number": "ORD-20251003-0042",
    "user_id": 7,
    "total_amount_ttc": 189.35
  }
}
```

### 1. OrderPlaced
**Queue:** orders.placed
**Published:** When order successfully created

**Payload:**
```json
{
  "event": "order.placed",
  "data": {
    "order_id": 42,
    "order_number": "ORD-20251003-0042",
    "user_id": 7,
    "billing_address_id": 15,
    "shipping_address_id": 16,
    "status": "pending",
    "total_amount_ht": 157.79,
    "total_amount_ttc": 189.35,
    "vat_amount": 31.56,
    "total_discount": 0.00,
    "items": [
      {
        "product_id": 23,
        "product_ref": "PROD-001",
        "product_name": "Laptop XYZ",
        "quantity": 1,
        "unit_price_ht": 833.33,
        "unit_price_ttc": 1000.00,
        "vat_rate": 20.00
      }
    ],
    "created_at": "2025-10-03T14:30:00Z"
  }
}
```

**Consumers:**
- products-service: Reserve inventory, update stock
- baskets-service: Clear user's basket
- deliveries-service: Prepare delivery record
- newsletters-service: Send order confirmation email
- payment-service: Initiate payment authorization

---

### 2. OrderConfirmed
**Queue:** orders.confirmed
**Published:** When customer confirms and payment authorized

**Payload:**
```json
{
  "event": "order.confirmed",
  "data": {
    "order_id": 42,
    "order_number": "ORD-20251003-0042",
    "user_id": 7,
    "status": "confirmed",
    "previous_status": "pending",
    "confirmed_at": "2025-10-03T14:35:00Z"
  }
}
```

**Consumers:**
- products-service: Confirm inventory reservation
- payment-service: Capture payment
- deliveries-service: Activate delivery tracking
- newsletters-service: Send confirmation email

---

### 3. OrderPaid
**Queue:** orders.paid
**Published:** When payment successfully captured

**Payload:**
```json
{
  "event": "order.paid",
  "data": {
    "order_id": 42,
    "order_number": "ORD-20251003-0042",
    "user_id": 7,
    "payment_method": "credit_card",
    "amount_paid": 189.35,
    "currency": "EUR",
    "transaction_id": "txn_abc123",
    "paid_at": "2025-10-03T14:36:00Z"
  }
}
```

**Consumers:**
- accounting-service: Record payment (future)
- newsletters-service: Send payment receipt
- fraud-detection-service: Validate transaction (future)

---

### 4. StatusChanged
**Queue:** orders.status.changed
**Published:** On any status transition

**Payload:**
```json
{
  "event": "order.status.changed",
  "data": {
    "order_id": 42,
    "order_number": "ORD-20251003-0042",
    "user_id": 7,
    "previous_status": "confirmed",
    "new_status": "processing",
    "changed_by": "system",
    "reason": "Automatic transition after payment",
    "changed_at": "2025-10-03T15:00:00Z"
  }
}
```

**Consumers:**
- deliveries-service: Update delivery status
- newsletters-service: Send status update email
- analytics-service: Track order funnel (future)

---

### 5. OrderCancelled
**Queue:** orders.cancelled
**Published:** When order cancelled

**Payload:**
```json
{
  "event": "order.cancelled",
  "data": {
    "order_id": 42,
    "order_number": "ORD-20251003-0042",
    "user_id": 7,
    "previous_status": "processing",
    "reason": "Customer requested cancellation",
    "cancelled_by": "user",
    "cancelled_by_id": 7,
    "refund_required": true,
    "refund_amount": 189.35,
    "cancelled_at": "2025-10-03T16:00:00Z"
  }
}
```

**Consumers:**
- products-service: Release inventory
- payment-service: Initiate refund
- deliveries-service: Cancel delivery
- newsletters-service: Send cancellation confirmation

---

### RabbitMQ Configuration

**Exchange:** orders_exchange (topic)
**Routing Keys:**
- order.placed
- order.confirmed
- order.paid
- order.status.changed
- order.cancelled

**Consumer Queues:**
- products-service: orders.events.products
- baskets-service: orders.events.baskets
- deliveries-service: orders.events.deliveries
- newsletters-service: orders.events.newsletters
- payment-service: orders.events.payment

## Cross-Service References

### Referenced BY Other Services

#### deliveries-service
```sql
-- deliveries table
CREATE TABLE deliveries (
    order_id BIGINT UNSIGNED,        -- References orders.id
    order_number VARCHAR(255),       -- Cache of orders.order_number
    shipping_address_id BIGINT UNSIGNED, -- Sync from orders.shipping_address_id
    -- NO foreign key constraint (different database)
);
```

**Synchronization:**
- Listen to OrderPlaced: Create delivery record
- Listen to StatusChanged: Update delivery status
- Listen to OrderCancelled: Cancel delivery

---

#### newsletters-service
```sql
-- email_queue table
CREATE TABLE email_queue (
    order_id BIGINT UNSIGNED,        -- References orders.id
    order_number VARCHAR(255),       -- Cache for email content
    user_id BIGINT UNSIGNED,         -- Recipient
    -- NO foreign key constraint (different database)
);
```

**Synchronization:**
- Listen to OrderPlaced: Send order confirmation
- Listen to OrderConfirmed: Send payment confirmation
- Listen to StatusChanged: Send status update emails
- Listen to OrderCancelled: Send cancellation notification

---

### References TO Other Services

#### auth-service (users)
```sql
-- orders table
orders.user_id → auth-service.users.id (virtual FK)
```

**Synchronization:**
- Query user details for order placement validation
- Listen to UserDeleted: Soft delete user's orders (optional)

---

#### addresses-service (addresses)
```sql
-- orders table
orders.billing_address_id → addresses-service.addresses.id (virtual FK)
orders.shipping_address_id → addresses-service.addresses.id (virtual FK)
```

**Synchronization:**
- Validate addresses exist before order placement
- Snapshot address data at order time (future enhancement)
- Listen to AddressDeleted: Handle gracefully (preserve orders)

---

#### products-service (products)
```sql
-- order_items table
order_items.product_id → products-service.products.id (virtual FK)
```

**Synchronization:**
- Fetch product details (name, ref, price, vat_rate) at order time
- Snapshot product data in order_items for history
- Listen to ProductUpdated: No action (historical data frozen)
- Listen to ProductDeleted: No action (historical data preserved)

## Checkout Workflow Saga

### Complete Checkout Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                   CHECKOUT WORKFLOW SAGA                            │
│        Orchestrated by orders-service with compensation             │
└─────────────────────────────────────────────────────────────────────┘

[User] → API Gateway → orders-service.checkout()

Step 1: VALIDATE BASKET
├─ orders-service → baskets-service.getBasket(user_id)
├─ Validate: basket not empty
└─ Compensation: Return 400 "Empty basket"

Step 2: VALIDATE PRODUCTS
├─ For each basket item:
│   ├─ orders-service → products-service.getProduct(product_id)
│   ├─ Validate: product exists, not deleted
│   ├─ Validate: sufficient stock
│   └─ Fetch: price_ht, vat_rate, product_name, product_ref
└─ Compensation: Return 422 "Product unavailable or out of stock"

Step 3: VALIDATE ADDRESSES
├─ orders-service → addresses-service.getAddress(billing_address_id)
├─ orders-service → addresses-service.getAddress(shipping_address_id)
├─ Validate: addresses belong to user
├─ Validate: addresses not deleted
└─ Compensation: Return 422 "Invalid address"

Step 4: CREATE ORDER (Transaction Start)
├─ BEGIN TRANSACTION
├─ Generate order_number: ORD-YYYYMMDD-XXXX
├─ Insert into orders table (status='pending')
├─ Insert into order_items table
├─ Calculate totals (HT/TTC/VAT)
├─ COMMIT TRANSACTION
└─ Compensation: ROLLBACK, return 500 "Order creation failed"

Step 5: RESERVE INVENTORY
├─ orders-service → products-service (RabbitMQ)
├─ Publish: InventoryReserveRequested(order_id, items[])
├─ Wait for: InventoryReserved OR InventoryReserveFailed
├─ Timeout: 5 seconds
└─ Compensation: Delete order, return 409 "Inventory reservation failed"

Step 6: AUTHORIZE PAYMENT
├─ orders-service → payment-service (RabbitMQ)
├─ Publish: PaymentAuthorizationRequested(order_id, amount_ttc)
├─ Wait for: PaymentAuthorized OR PaymentAuthorizationFailed
├─ Timeout: 10 seconds
└─ Compensation: Release inventory, delete order, return 402 "Payment failed"

Step 7: CONFIRM ORDER
├─ Transition: pending → confirmed
├─ Update orders.status_id
└─ Publish: OrderConfirmed(order_id)

Step 8: CLEAR BASKET
├─ orders-service → baskets-service (RabbitMQ)
├─ Publish: BasketClearRequested(user_id)
└─ No compensation (fire-and-forget, non-critical)

Step 9: CREATE DELIVERY
├─ orders-service → deliveries-service (RabbitMQ)
├─ Publish: DeliveryCreationRequested(order_id, shipping_address_id)
└─ No compensation (asynchronous, handled later)

Step 10: CAPTURE PAYMENT
├─ orders-service → payment-service (RabbitMQ)
├─ Publish: PaymentCaptureRequested(order_id)
├─ Wait for: PaymentCaptured
└─ Compensation: Cancel order, refund authorization

Step 11: SEND CONFIRMATION EMAIL
├─ orders-service → newsletters-service (RabbitMQ)
├─ Publish: OrderConfirmationEmailRequested(order_id, user_id)
└─ No compensation (fire-and-forget, retry mechanism in newsletters-service)

[SUCCESS] → Return order details to client

COMPENSATION FLOWS:

If Step 5 fails (Inventory):
  └─ Delete order → Return 409

If Step 6 fails (Payment Authorization):
  ├─ Release inventory (products-service)
  ├─ Delete order
  └─ Return 402

If Step 10 fails (Payment Capture):
  ├─ Transition: confirmed → cancelled
  ├─ Release inventory
  ├─ Refund authorization
  └─ Send cancellation email

SAGA PATTERNS USED:
- Orchestration: orders-service orchestrates entire flow
- Compensation: Automatic rollback on failures
- Timeout Handling: All async operations have timeouts
- Idempotency: All RabbitMQ messages include correlation IDs
```

### Saga Implementation

```php
// OrderService.php - Checkout Saga Orchestrator

public function checkout(int $userId, array $checkoutData): Order
{
    DB::beginTransaction();

    try {
        // Step 1-3: Validations
        $basket = $this->validateBasket($userId);
        $products = $this->validateProducts($basket);
        $addresses = $this->validateAddresses($checkoutData);

        // Step 4: Create Order
        $order = $this->createOrder($userId, $basket, $products, $addresses);

        DB::commit();

        // Step 5: Reserve Inventory (async with compensation)
        try {
            $this->reserveInventory($order);
        } catch (InventoryReservationException $e) {
            $this->compensateOrderCreation($order);
            throw new CheckoutFailedException('Inventory reservation failed', 409);
        }

        // Step 6: Authorize Payment (async with compensation)
        try {
            $this->authorizePayment($order);
        } catch (PaymentAuthorizationException $e) {
            $this->compensateInventoryReservation($order);
            $this->compensateOrderCreation($order);
            throw new CheckoutFailedException('Payment authorization failed', 402);
        }

        // Step 7: Confirm Order
        $this->transitionToConfirmed($order);

        // Step 8-11: Fire-and-forget operations
        $this->clearBasket($userId);
        $this->createDelivery($order);
        $this->capturePayment($order); // async with retry
        $this->sendConfirmationEmail($order);

        return $order->fresh(['items', 'status']);

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

private function compensateOrderCreation(Order $order): void
{
    $order->delete(); // soft delete
}

private function compensateInventoryReservation(Order $order): void
{
    event(new InventoryReleaseRequested($order));
}
```

### Saga Timeout Configuration

```php
// config/saga.php

return [
    'timeouts' => [
        'inventory_reservation' => 5,  // seconds
        'payment_authorization' => 10, // seconds
        'payment_capture' => 15,       // seconds
        'basket_clear' => 3,           // seconds (fire-and-forget)
    ],

    'retry' => [
        'payment_capture' => [
            'max_attempts' => 3,
            'delay' => 2, // seconds between retries
        ],
    ],
];
```

## Indexes and Performance

### Strategic Indexes

#### orders Table
```sql
INDEX (order_number)              -- Unique order lookup
INDEX (user_id)                   -- User order history
INDEX (status_id)                 -- Status filtering
INDEX (created_at)                -- Date range queries
INDEX (billing_address_id)        -- Address reference
INDEX (shipping_address_id)       -- Address reference
```

**Query Optimization:**
```sql
-- Fast lookup by order number
SELECT * FROM orders WHERE order_number = 'ORD-20251003-0042';

-- User order history
SELECT * FROM orders
WHERE user_id = 7
  AND deleted_at IS NULL
ORDER BY created_at DESC
LIMIT 20;

-- Orders by status
SELECT * FROM orders
WHERE status_id = 2  -- confirmed
  AND created_at >= '2025-10-01'
ORDER BY created_at DESC;

-- Recent orders for dashboard
SELECT * FROM orders
WHERE created_at >= NOW() - INTERVAL 7 DAY
  AND deleted_at IS NULL
ORDER BY created_at DESC;
```

---

#### order_items Table
```sql
INDEX (order_id)                  -- Order items lookup
INDEX (product_id)                -- Product sales tracking
```

**Query Optimization:**
```sql
-- Get all items for an order
SELECT * FROM order_items
WHERE order_id = 42;

-- Product sales report
SELECT product_id, product_name,
       SUM(quantity) as total_sold,
       SUM(total_price_ttc) as total_revenue
FROM order_items
GROUP BY product_id, product_name
ORDER BY total_revenue DESC;

-- Best sellers in date range
SELECT oi.product_id, oi.product_name,
       COUNT(DISTINCT oi.order_id) as order_count,
       SUM(oi.quantity) as units_sold
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE o.created_at >= '2025-10-01'
  AND o.deleted_at IS NULL
GROUP BY oi.product_id, oi.product_name
ORDER BY units_sold DESC
LIMIT 10;
```

---

### Composite Indexes

#### orders Table
```sql
INDEX (user_id, created_at)       -- User history with date sorting
INDEX (status_id, created_at)     -- Status filtering with date sorting
```

**Benefits:**
- Fast user order history queries
- Efficient status-based reporting
- Date range filtering with status

---

### Performance Recommendations

1. **Order Lookup:**
   - Use order_number for external references (unique, indexed)
   - Cache recent orders in Redis (reduce DB load)
   - Paginate user order history (don't load all orders)

2. **Status Queries:**
   - Index on status_id enables fast filtering
   - Consider materialized views for dashboard stats
   - Cache status counts in Redis

3. **Order Items:**
   - Always query with order_id (indexed)
   - Avoid N+1 queries (eager load with orders)
   - Product sales reports: Use date range limits

4. **Soft Deletes:**
   - Always include deleted_at IS NULL in queries
   - Consider archiving old deleted orders (>1 year)
   - Soft delete index: (deleted_at, created_at)

5. **Cross-Service Queries:**
   - Cache user/address/product data locally
   - Use event-driven denormalization for frequent queries
   - Implement circuit breakers for service calls

---

**Document Version:** 1.0
**Last Updated:** 2025-10-03
**Database Version:** MySQL 8.0
**Laravel Version:** 12.x
