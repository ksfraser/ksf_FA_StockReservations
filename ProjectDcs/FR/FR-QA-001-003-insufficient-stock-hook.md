# FR-QA-001-003: Insufficient Stock Hook

## Version
1.0.0

## Author
KSF Development Team

## Created
2026-09-04

## Status
Approved

## Functional Requirement

### Related BR
BR-QA-001-stock-reservations

### Description

When a sales order is confirmed and stock is insufficient, the system SHALL broadcast the `stock_reservation_insufficient` hook so other modules (e.g., ksf_FA_SuggestedPurchaseOrder) can react by creating suggested purchase orders.

### Preconditions

1. Sales order line item has requested quantity > available quantity
2. Other modules are listening for `stock_reservation_insufficient` hook

### Postconditions

1. `stock_reservation_insufficient` hook is broadcast via `hook_invoke_all()`
2. Warning message displayed to user
3. Event logged for audit

### Data Flows

```
[Insufficient Stock Detected]
         │
         ▼
┌─────────────────────────┐
│ Broadcast Hook:          │
│ stock_reservation_       │
│ insufficient             │
└─────────────────────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌─────────┐ ┌──────────────┐
│Suggested│ │Other Modules │
│PO (SPO) │ │(future)      │
└─────────┘ └──────────────┘
```

### Hook Payload

```php
$data = [
    'module' => 'ksf_FA_StockReservations',
    'event' => 'insufficient_stock',
    'order_no' => 123,
    'items' => [
        [
            'item_code' => 'SKU-001',
            'requested' => 100.0,
            'available' => 25.0,
            'shortage' => 75.0,
        ],
    ],
    'timestamp' => '2026-09-04 12:00:00',
];
```

### Acceptance Criteria

| ID | Criteria | Test Scenario |
|----|----------|---------------|
| AC-01 | Hook broadcast when stock insufficient | UT-QA-001-003-001 |
| AC-02 | Hook contains correct payload structure | UT-QA-001-003-002 |
| AC-03 | Warning displayed to user | UT-QA-001-003-003 |

### Implementation Notes

- Uses FA's `hook_invoke_all()` for broadcast
- Other modules (SuggestedPO) listen via `stock_reservation_insufficient()` method
- No direct module-to-module coupling