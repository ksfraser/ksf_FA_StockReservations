# UT-QA-001-001-001: Create Reservation - Success

## Version
1.0.0

## Author
KSF Development Team

## Created
2026-09-04

## Status
Approved

## Unit Test

### Related FR
FR-QA-001-001

### Description
Test successful creation of a stock reservation when order is confirmed

### Preconditions
- Stock item exists with quantity_on_hand = 100
- No existing reservations for item

### Test Data
- item_code: TEST-001
- order_no: SO-001
- quantity: 10

### Expected Result
- Reservation created with status 'reserved'
- Available stock = 90

### Test Steps
1. Create reservation service
2. Call createReservation(item, order, qty)
3. Assert reservation record exists
4. Assert status = 'reserved'
5. Assert reserved_qty = 10