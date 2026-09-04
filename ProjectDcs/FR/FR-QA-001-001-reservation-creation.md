# FR-QA-001-001: Create Stock Reservation

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

When a sales order is confirmed or edited, the system SHALL reserve the ordered quantity against available stock. If insufficient stock is available, the system SHALL either reject the order or allow negative reservation based on configuration.

### Preconditions

1. Sales order exists with line items
2. Stock item has `quantity_on_hand > 0`
3. User has SA_SALESORDER permission

### Postconditions

1. Reservation record created for each line item
2. Available stock reduced by reserved quantity
3. Order status updated to reflect reservation

### User Interactions

1. User confirms sales order
2. System calculates available = on_hand - reserved
3. System creates reservation records
4. System commits transaction

### Data Flows

```
[Sales Order Confirmation]
         │
         ▼
┌─────────────────────────┐
│ Check Available Stock    │
│ for each line item       │
└─────────────────────────┘
         │
    ┌────┴────┐
    │         │
 ▼         ▼
[Stock OK]  [Insufficient Stock]
    │              │
    ▼              ▼
┌─────────────┐  ┌─────────────────────────┐
│Create       │  │ Broadcast Hook:          │
│Reservation  │  │ stock_reservation_      │
│INSERT      │  │ insufficient            │
└─────────────┘  └─────────────────────────┘
                          │
                          ▼
                 ┌─────────────────────────┐
                 │ Display Warning to User   │
                 └─────────────────────────┘
```

### Acceptance Criteria

| ID | Criteria | Test Scenario |
|----|----------|---------------|
| AC-01 | Reservation created when order confirmed | UT-QA-001-001-001 |
| AC-02 | Available stock reduced correctly | UT-QA-001-001-002 |
| AC-03 | Partial reservation when stock insufficient | UT-QA-001-001-003 |
| AC-04 | Reservation uses correct status (reserved) | UT-QA-001-001-004 |
| AC-05 | Transaction rollback on failure | UT-QA-001-001-005 |
| AC-06 | Insufficient stock broadcasts hook | UT-QA-001-001-006 |

### Edge Cases

1. **Insufficient stock**: Broadcast `stock_reservation_insufficient` hook for other modules (e.g., SuggestedPO), display warning to user
2. **Duplicate reservation**: Check existing before insert
3. **Concurrent orders**: Use row-level locking
4. **Zero quantity**: Skip reservation creation

### Implementation Notes

- Uses `ksfraser\ksf-common-db` for database abstraction
- `SalesOrderReservationHandler` (SRP class) handles SO→reservation integration
- Hook-based communication: `stock_reservation_insufficient` broadcast for other modules
- FA adapter delegates to native `db_*` functions per architecture
- Cart object is NEVER modified (read-only access)