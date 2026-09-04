# UC-QA-001: Manage Stock Reservations

## Version
1.0.0

## Author
KSF Development Team

## Created
2026-09-04

## Status
Approved

## Use Case

## UC-QA-001: Manage Stock Reservations

### Primary Actor
Warehouse Manager / Order Fulfillment Staff

### Goal
Ensure stock quantities are properly reserved for sales orders to prevent overselling

### Stakeholders

| Stakeholder | Interest |
|-------------|----------|
| Sales Team | Orders can be fulfilled as promised |
| Warehouse | Knows exact stock to pick |
| Finance | Revenue recognized on shipped orders |
| Customer | Receives what was promised |

### Trigger
Sales order confirmation or edit

### Main Flow

1. Sales order created/edited
2. System checks available stock for each line item
3. System creates reservation records
4. Order proceeds to fulfillment

### Alternative Flows

**A1: Insufficient Stock**
1. System identifies shortfall
2. System broadcasts `stock_reservation_insufficient` hook
3. SuggestedPO (or other module) creates suggested PO
4. Warning displayed to user
5. User proceeds or cancels

**A2: Order Modification**
1. User reduces ordered quantity
2. System releases excess reservation
3. Reservation adjusted

**A3: Order Cancellation**
1. User cancels order
2. System releases all reservations
3. Stock restored to available

### Extensions

**E1: Pick/Ship Conversion**
1. Reserved quantity picked
2. Reservation status → 'picked'
3. Shipment processes reservation

**E2: Hook-based Inter-Module Communication**
1. Insufficient stock detected
2. `stock_reservation_insufficient` hook broadcast via `hook_invoke_all()`
3. ksf_FA_SuggestedPurchaseOrder listens and creates PO suggestion
4. Future: other modules can react (e.g., email notification)

### Related FR
FR-QA-001-001, FR-QA-001-002, FR-QA-001-003