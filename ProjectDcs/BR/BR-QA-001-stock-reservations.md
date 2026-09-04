# BR-QA-001: Stock Reservations

## Version
1.0.0

## Author
KSF Development Team

## Created
2026-09-04

## Status
Approved

## Business Requirement

webERP has a `stock_reservations` table that allows sales orders to reserve specific stock quantities, preventing overselling during picking/packing. FrontAccounting lacks this feature, resulting in negative stock occurrences.

### Problem Statement

When multiple sales orders reference the same stock item, there is no mechanism to reserve quantities, leading to:
1. Overselling when orders exceed available stock
2. Inability to track promised vs available quantities
3. Manual intervention required during fulfillment

### Solution

Implement a stock reservation system that:
- Allows sales orders to reserve specific quantities
- Tracks reservation status (pending, picked, shipped, cancelled)
- Integrates with FA's sales order workflow
- Provides real-time availability calculations

### Scope

**In Scope:**
- Reserve stock when sales order is confirmed
- Release reservations on order cancellation
- Convert reservations to picking/shipping
- View reservation status by item/order
- Negative stock prevention

**Out of Scope:**
- Multi-warehouse reservations (future)
- Automatic reservation optimization (future)
- Integration with external WMS (future)

### Business Value

- **High**: Prevents overselling, improves customer satisfaction
- **Medium**: Reduces manual intervention during fulfillment
- **Low**: Technical enabler for advanced inventory features

### Dependencies

- ksf_FA_SalesOrders or core FA sales order module
- `stock_master` table
- `sales_orders` table
- `sales_order_details` table

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Performance impact on order creation | Medium | Medium | Index on (item_code, order_no) |
| Deadlock during concurrent reservations | Low | High | Use transaction with row locking |

### Regulatory Considerations

None - inventory tracking is internal business logic.

### Success Metrics

- Zero oversells on reserved items
- Reservation lookup < 100ms
- 100% reservation accuracy vs orders