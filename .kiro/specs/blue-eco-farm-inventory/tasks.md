# Implementation Plan: Blue Eco Farm Inventory and Sales Forecasting System

## Overview

Incremental PHP implementation starting with the database and core classes, then building each feature layer (stock management, transfers, forecasting, dashboard), and wiring everything together with the frontend.

## Tasks

- [x] 1. Set up project structure, database schema, and seed data
  - Create directory structure: `src/`, `api/`, `public/`, `tests/`
  - Write `db/schema.sql` with all four tables: `products`, `stock_records`, `transfers`, `transaction_logs`
  - Write `db/seed.sql` to insert the 6 product types (Big/Small × Granules/Tablet/Powder)
  - Write `src/Database.php` as a PDO singleton that reads DB credentials from a config file
  - _Requirements: 1.1, 9.1_

- [x] 2. Implement core inventory management
  - [x] 2.1 Implement `src/InventoryManager.php` with `addIncoming()`, `addOutgoing()`, `updateRecord()`, `softDelete()`, and `getCurrentStock()` methods
    - `getCurrentStock(productId, warehouseId)` must use the SQL aggregation formula from the design (no stored counter)
    - All write methods must call `TransactionLogger::log()` after each operation
    - _Requirements: 3.1, 4.1, 5.1, 9.3_
  - [x] 2.2 Implement `src/TransactionLogger.php` with a `log(operation, tableName, recordId, snapshot)` method that inserts into `transaction_logs`
    - _Requirements: 9.3_
  - [ ]* 2.3 Write PHPUnit unit tests for `InventoryManager` covering: add incoming increases stock, add outgoing decreases stock, update recalculates total, soft-delete excludes from total, missing-field validation returns error, quantity ≤ 0 returns error
    - _Requirements: 3.1, 3.2, 3.3, 4.1, 4.3, 5.1_
  - [ ]* 2.4 Write property test for stock addition correctness
    - **Property 3: Incoming stock addition correctness**
    - **Validates: Requirements 3.1**
    - `// Feature: blue-eco-farm-inventory, Property 3: incoming stock addition correctness`
  - [ ]* 2.5 Write property test for outgoing stock deduction correctness
    - **Property 4: Outgoing stock deduction correctness**
    - **Validates: Requirements 4.1**
    - `// Feature: blue-eco-farm-inventory, Property 4: outgoing stock deduction correctness`
  - [ ]* 2.6 Write property test for update recalculation consistency
    - **Property 5: Update recalculation consistency**
    - **Validates: Requirements 5.1**
    - `// Feature: blue-eco-farm-inventory, Property 5: update recalculation consistency`
  - [ ]* 2.7 Write property test for soft-delete exclusion from totals
    - **Property 7: Soft-delete exclusion from totals**
    - **Validates: Requirements 4.1, 5.1**
    - `// Feature: blue-eco-farm-inventory, Property 7: soft-delete exclusion from totals`
  - [ ]* 2.8 Write property test for transaction log completeness
    - **Property 8: Transaction log completeness**
    - **Validates: Requirements 9.3**
    - `// Feature: blue-eco-farm-inventory, Property 8: transaction log completeness`

- [ ] 3. Checkpoint — Ensure all inventory manager tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implement stock transfer
  - [x] 4.1 Implement `src/TransferManager.php` with a `transfer(productId, quantity, date)` method
    - Must wrap the Farm deduction and Paranaque addition in a single MySQL transaction (BEGIN/COMMIT/ROLLBACK)
    - Must call `getCurrentStock()` before deducting to enforce the insufficient-stock guard
    - _Requirements: 6.1, 6.2, 6.3_
  - [ ]* 4.2 Write PHPUnit unit tests for `TransferManager` covering: valid transfer updates both warehouses, transfer exceeding stock is rejected, missing fields return error
    - _Requirements: 6.1, 6.2, 6.4_
  - [ ]* 4.3 Write property test for transfer atomicity — total conservation
    - **Property 2: Transfer atomicity — total conservation**
    - **Validates: Requirements 6.1**
    - `// Feature: blue-eco-farm-inventory, Property 2: transfer atomicity total conservation`
  - [ ]* 4.4 Write property test for stock non-negativity invariant across all operation types
    - **Property 1: Stock total non-negativity invariant**
    - **Validates: Requirements 4.2, 5.2, 6.2**
    - `// Feature: blue-eco-farm-inventory, Property 1: stock total non-negativity invariant`

- [x] 5. Implement API endpoints
  - [x] 5.1 Implement `api/stock.php` handling actions: `add_incoming`, `add_outgoing`, `update`, `delete`, `get_totals`
    - All responses must be `Content-Type: application/json`
    - Use the error response format from the design: `{"error": "...", "detail": "..."}`
    - _Requirements: 3.1, 3.2, 4.1, 4.2, 5.1, 5.3_
  - [x] 5.2 Implement `api/transfer.php` handling actions: `create`, `list`
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  - [x] 5.3 Implement `api/chart_data.php` returning time-series JSON for stock-in and stock-out per product per warehouse
    - _Requirements: 7.2, 7.3_
  - [ ]* 5.4 Write PHPUnit tests for API endpoints covering HTTP status codes and JSON response shapes for all error scenarios
    - _Requirements: 3.2, 3.3, 4.2, 4.3, 5.3, 6.2, 6.4_

- [x] 6. Implement forecasting engine
  - [x] 6.1 Implement `src/ForecastingEngine.php` with `predict(productId, periodsAhead)` method
    - Use php-ml `LeastSquares` regression or implement a minimal OLS (Ordinary Least Squares) linear regression directly in PHP
    - Must enforce the minimum 5 data points guard before training
    - Input: array of (time_index, outgoing_quantity) pairs from `stock_records`
    - Output: predicted quantity for the requested future period
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 6.2 Implement `api/forecast.php` handling action `predict`, returning JSON with historical data and forecast values
    - _Requirements: 8.1, 8.4_
  - [ ]* 6.3 Write PHPUnit unit tests for `ForecastingEngine` covering: returns numeric forecast for ≥5 data points, returns error for <5 data points, forecast value is a number
    - _Requirements: 8.1, 8.2, 8.3_
  - [ ]* 6.4 Write property test for forecast minimum data guard
    - **Property 6: Forecast minimum data guard**
    - **Validates: Requirements 8.2, 8.3**
    - `// Feature: blue-eco-farm-inventory, Property 6: forecast minimum data guard`

- [ ] 7. Checkpoint — Ensure all tests pass including forecasting
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Build frontend pages and wire to API
  - [x] 8.1 Build `index.php` (Dashboard): display a summary table of current stock per product per warehouse, with a warehouse filter dropdown
    - Fetch stock totals from `api/stock.php?action=get_totals` via AJAX on page load and on filter change
    - _Requirements: 7.1, 7.4_
  - [x] 8.2 Add Chart.js stock-in and stock-out line graphs to `index.php`
    - Fetch time-series data from `api/chart_data.php` and render two Chart.js line charts
    - _Requirements: 7.2, 7.3_
  - [x] 8.3 Build `stock_in.php` form: product type dropdown, warehouse selector, quantity input, date picker, submit button
    - On submit, POST to `api/stock.php?action=add_incoming` and display success/error inline
    - _Requirements: 3.1, 3.2, 3.3_
  - [x] 8.4 Build `stock_out.php` form: same fields as stock_in, POST to `api/stock.php?action=add_outgoing`
    - Display current available stock for the selected product/warehouse before submission
    - _Requirements: 4.1, 4.2, 4.3_
  - [x] 8.5 Build `update_record.php` form: load existing record by ID, allow editing quantity/date/notes, POST to `api/stock.php?action=update`
    - _Requirements: 5.1, 5.2, 5.3_
  - [x] 8.6 Build `transfer.php` form: product type dropdown, quantity input, date picker, POST to `api/transfer.php?action=create`
    - Display current Farm stock for the selected product before submission
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  - [x] 8.7 Build `forecast.php` page: product type selector, fetch from `api/forecast.php?action=predict`, render a Chart.js line chart with historical outgoing data and the forecast line
    - _Requirements: 8.1, 8.4_
  - [x] 8.8 Build `transaction_log.php` page: paginated table of all entries from `transaction_logs`, sortable by date
    - _Requirements: 9.3_

- [x] 9. Final checkpoint — Ensure all tests pass and pages are wired correctly
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Each task references specific requirements for traceability
- Property tests require minimum 100 iterations each
- The stock total is always computed via SQL aggregation — never update a stored counter directly
- All API responses use `Content-Type: application/json`
- MySQL transactions (BEGIN/COMMIT/ROLLBACK) are mandatory for the transfer operation
