# UAT-QA-001: Stock Reservations - User Acceptance Test

## Version
1.0.0

## Author
KSF Development Team

## Created
2026-09-04

## Status
Approved

## UAT Cases

### UAT-QA-001-01: Reserve Stock on Order Confirmation

| Field | Value |
|-------|-------|
| Purpose | Verify stock is reserved when sales order is confirmed |
| Prereqs | Stock item with qty 100, no existing reservations |
| Actor | Sales Clerk |
| Steps | 1. Create SO for item TEST-001, qty 10<br>2. Confirm order<br>3. View reservation report |
| Expected | Reservation created, available stock = 90 |
| PASS Criteria | Reservation visible, stock correct |

### UAT-QA-001-02: Release Reservation on Cancellation

| Field | Value |
|-------|-------|
| Purpose | Verify stock returns when order cancelled |
| Prereqs | Existing reservation for TEST-001 qty 10 |
| Actor | Sales Clerk |
| Steps | 1. Cancel sales order SO-001<br>2. Check available stock |
| Expected | Reservation removed, available stock = 100 |
| PASS Criteria | Stock restored to original |

### UAT-QA-001-03: Prevent Overselling

| Field | Value |
|-------|-------|
| Purpose | Verify order rejected when stock insufficient |
| Prereqs | Stock item with qty 5, no reservations |
| Actor | Sales Clerk |
| Steps | 1. Attempt to create SO for TEST-001, qty 10 |
| Expected | Order rejected with insufficient stock message |
| PASS Criteria | No reservation created, error displayed |

### UAT-QA-001-04: View Reservation Report

| Field | Value |
|-------|-------|
| Purpose | Verify reservation status report shows all reservations |
| Prereqs | Multiple reservations exist |
| Actor | Warehouse Manager |
| Steps | 1. Navigate to Stock > Reservations<br>2. View report |
| Expected | All reservations displayed with item, order, qty, status |
| PASS Criteria | Report matches actual reservations |