# FR-QA-001-002: Release Stock Reservation

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

When a sales order is cancelled or modified to reduce quantities, the system SHALL release the reserved quantities back to available stock.

### Preconditions

1. Reservation exists for item + order combination
2. Reservation status is not yet 'shipped'

### Postconditions

1. Reservation record deleted or status set to 'released'
2. Available stock restored by released quantity

### Acceptance Criteria

| ID | Criteria | Test Scenario |
|----|----------|---------------|
| AC-01 | Full release on order cancellation | UT-QA-001-002-001 |
| AC-02 | Partial release on quantity reduction | UT-QA-001-002-002 |
| AC-03 | No release for shipped reservations | UT-QA-001-002-003 |
| AC-04 | Available stock restored correctly | UT-QA-001-002-004 |