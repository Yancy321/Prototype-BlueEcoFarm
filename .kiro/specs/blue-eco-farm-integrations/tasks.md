# Implementation Plan: Blue Eco Farm Integrations

## Overview

Incremental PHP implementation of three integrations (SMS alerts, PDF reports, Calendar forecasts) on top of the existing inventory system. Each integration is self-contained and additive — no existing files are modified until the wiring step.

## Tasks

- [x] 1. Database schema and configuration setup
  - Add `alert_rules` and `sms_alert_log` tables to `db/schema.sql`
  - Create `config/integrations.php` with SMS gateway credentials (template with placeholder values)
  - _Requirements: 1.1, 2.5, 3.1_

- [x] 2. Implement SMS Integration
  - [x] 2.1 Implement `src/SmsService.php`
    - `composeMessage(productName, warehouseName, currentStock, threshold): string` — builds the alert message body
    - `send(to, message): bool` — calls the SMS gateway REST API via `curl`; returns true on success
    - `checkAndAlert(productId, warehouseId, currentStock): void` — queries `alert_rules`, checks cooldown via `sms_alert_log`, calls `send()` per recipient, writes result to `sms_alert_log`
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [ ]* 2.2 Write property test for alert message content (Property 5)
    - **Property 5: Alert message contains required fields**
    - **Validates: Requirements 2.2**
    - `// Feature: blue-eco-farm-integrations, Property 5: alert message contains required fields`

  - [ ]* 2.3 Write property test for SMS dispatch log completeness (Property 6)
    - **Property 6: SMS dispatch log completeness**
    - **Validates: Requirements 2.3, 2.5, 3.1**
    - Mock gateway to return success/failure randomly; assert every attempt has a log entry
    - `// Feature: blue-eco-farm-integrations, Property 6: SMS dispatch log completeness`

  - [ ]* 2.4 Write property test for cooldown deduplication (Property 7)
    - **Property 7: Cooldown deduplication**
    - **Validates: Requirements 2.4**
    - `// Feature: blue-eco-farm-integrations, Property 7: cooldown deduplication`

  - [ ]* 2.5 Write property test for SMS alert fires for all recipients (Property 4)
    - **Property 4: SMS alert fires for all recipients when threshold is breached**
    - **Validates: Requirements 2.1**
    - `// Feature: blue-eco-farm-integrations, Property 4: SMS alert fires for all recipients`

- [x] 3. Implement Alert_Rule CRUD API
  - [x] 3.1 Implement `api/alerts.php` with actions: `list`, `create`, `update`, `delete`
    - Validate E.164 phone numbers using regex `^\+[1-9]\d{7,14}$`
    - Validate threshold > 0, product_id exists, warehouse_id in [1, 2]
    - Return JSON; use HTTP 400 for validation errors, 404 for not-found
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [ ]* 3.2 Write property test for Alert_Rule validation (Property 1)
    - **Property 1: Alert_Rule validation rejects invalid inputs**
    - **Validates: Requirements 1.2, 1.3**
    - `// Feature: blue-eco-farm-integrations, Property 1: alert rule validation rejects invalid inputs`

  - [ ]* 3.3 Write property test for multiple rules per product-warehouse (Property 2)
    - **Property 2: Multiple Alert_Rules per product-warehouse pair**
    - **Validates: Requirements 1.4**
    - `// Feature: blue-eco-farm-integrations, Property 2: multiple alert rules per product-warehouse`

  - [ ]* 3.4 Write property test for deleted rule not evaluated (Property 3)
    - **Property 3: Deleted Alert_Rule is not evaluated**
    - **Validates: Requirements 1.5**
    - `// Feature: blue-eco-farm-integrations, Property 3: deleted alert rule not evaluated`

- [x] 4. Implement SMS alert log API and hook into InventoryManager
  - [x] 4.1 Implement `api/sms_log.php?action=list` — paginated, sorted by `dispatched_at` DESC
    - _Requirements: 3.1, 3.2_

  - [ ]* 4.2 Write property test for SMS log sort order (Property 8)
    - **Property 8: SMS log entries sorted descending by timestamp**
    - **Validates: Requirements 3.2**
    - `// Feature: blue-eco-farm-integrations, Property 8: SMS log sorted descending`

  - [x] 4.3 Call `SmsService::checkAndAlert()` in `src/InventoryManager.php` after `addOutgoing()` and in `src/TransferManager.php` after `transfer()`
    - Inject `SmsService` via constructor or call statically; do not break existing method signatures
    - _Requirements: 2.1_

- [ ] 5. Checkpoint — Ensure all SMS integration tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement PDF Report Integration
  - [x] 6.1 Install TCPDF via Composer (`composer require tecnickcom/tcpdf`) or include FPDF manually
    - Add autoloader or require in `src/PdfGenerator.php`

  - [x] 6.2 Implement `src/PdfGenerator.php`
    - `generateStockMovementReport(dateFrom, dateTo, warehouseId?, productId?): string` — returns raw PDF bytes
    - Query `stock_records` joined with `products` for the date range and filters
    - Build report data structure: title, generation timestamp, filters, transaction rows, per-product summary
    - Render via TCPDF: header (farm name + logo), transaction table with repeated header row, summary section, page-number footer
    - Format quantities with `number_format($qty, 0)`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3, 5.4_

  - [ ]* 6.3 Write property test for report data completeness and filter correctness (Property 9)
    - **Property 9: Report data completeness and filter correctness**
    - **Validates: Requirements 4.1, 4.2**
    - `// Feature: blue-eco-farm-integrations, Property 9: report data completeness and filter correctness`

  - [ ]* 6.4 Write property test for report data structure completeness (Property 10)
    - **Property 10: Report data structure completeness**
    - **Validates: Requirements 4.3**
    - `// Feature: blue-eco-farm-integrations, Property 10: report data structure completeness`

  - [ ]* 6.5 Write property test for thousand-separator formatting (Property 12)
    - **Property 12: Thousand-separator formatting**
    - **Validates: Requirements 5.4**
    - `// Feature: blue-eco-farm-integrations, Property 12: thousand-separator formatting`

- [ ] 7. Implement PDF Report API endpoint
  - [x] 7.1 Implement `api/reports.php?action=generate`
    - Accept GET params: `date_from`, `date_to`, optional `warehouse_id`, `product_id`
    - Validate that `date_from <= date_to`; return HTTP 400 with JSON error if not
    - Call `PdfGenerator::generateStockMovementReport()` and stream the result with headers:
      - `Content-Type: application/pdf`
      - `Content-Disposition: attachment; filename="stock-report-{date_from}-to-{date_to}.pdf"`
    - _Requirements: 4.4, 4.6_

  - [ ]* 7.2 Write property test for invalid date range rejection (Property 11)
    - **Property 11: Invalid date range is rejected**
    - **Validates: Requirements 4.6**
    - `// Feature: blue-eco-farm-integrations, Property 11: invalid date range rejected`

- [ ] 8. Checkpoint — Ensure all PDF integration tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Implement Calendar Service Integration
  - [x] 9.1 Implement `src/CalendarService.php`
    - `getForecastEvents(year, month, productId?): array` — returns FullCalendar-compatible event objects
    - For each product (or the filtered product), call `ForecastingEngine::predict()`
    - If predict() throws insufficient-data error, return an indicator event with `extendedProps.insufficientData = true`
    - Map predicted quantities to dates within the requested month
    - Each event: `id`, `title` (product name + quantity), `start` (YYYY-MM-DD), `extendedProps` (productId, predictedQty, dataPoints)
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.3_

  - [ ]* 9.2 Write property test for calendar events scoped to month (Property 13)
    - **Property 13: Calendar events scoped to requested month**
    - **Validates: Requirements 6.1, 7.1**
    - `// Feature: blue-eco-farm-integrations, Property 13: calendar events scoped to month`

  - [ ]* 9.3 Write property test for forecast event date mapping (Property 14)
    - **Property 14: Forecast event date mapping correctness**
    - **Validates: Requirements 6.2**
    - `// Feature: blue-eco-farm-integrations, Property 14: forecast event date mapping correctness`

  - [ ]* 9.4 Write property test for product filter on calendar events (Property 15)
    - **Property 15: Product filter on calendar events**
    - **Validates: Requirements 6.3**
    - `// Feature: blue-eco-farm-integrations, Property 15: product filter on calendar events`

  - [ ]* 9.5 Write property test for forecast event payload completeness (Property 16)
    - **Property 16: Forecast event payload completeness**
    - **Validates: Requirements 6.4**
    - `// Feature: blue-eco-farm-integrations, Property 16: forecast event payload completeness`

  - [ ]* 9.6 Write property test for insufficient data indicator event (Property 17)
    - **Property 17: Insufficient data produces indicator event**
    - **Validates: Requirements 6.5**
    - `// Feature: blue-eco-farm-integrations, Property 17: insufficient data indicator event`

  - [ ]* 9.7 Write property test for multi-product same-date completeness (Property 18)
    - **Property 18: Multi-product same-date completeness**
    - **Validates: Requirements 7.3**
    - `// Feature: blue-eco-farm-integrations, Property 18: multi-product same-date completeness`

- [x] 10. Implement Calendar API endpoint
  - [x] 10.1 Implement `api/calendar.php?action=events`
    - Accept GET params: `year`, `month`, optional `product_id`
    - Call `CalendarService::getForecastEvents()` and return JSON array
    - _Requirements: 6.1, 6.3, 7.1_

- [x] 11. Build frontend pages
  - [x] 11.1 Build `alerts.php` — management page for Alert_Rules
    - Table listing existing rules (product, warehouse, threshold, recipients, cooldown)
    - Inline form to create a new rule; edit/delete buttons per row
    - Fetch from `api/alerts.php?action=list`; POST to `create`, `update`, `delete`
    - _Requirements: 1.1, 1.2, 1.3_

  - [x] 11.2 Build `sms_log.php` — read-only SMS alert history page
    - Paginated table: rule ID, recipient, message preview, status, timestamp
    - Fetch from `api/sms_log.php?action=list`
    - _Requirements: 3.1, 3.2_

  - [x] 11.3 Build `reports.php` — PDF report request page
    - Date range pickers (`date_from`, `date_to`), optional warehouse and product dropdowns
    - "Generate Report" button that opens `api/reports.php?action=generate&...` in a new tab
    - _Requirements: 4.1, 4.2, 4.4, 4.6_

  - [x] 11.4 Build `calendar.php` — FullCalendar forecast view
    - Include FullCalendar.js (CDN or local)
    - Configure `events` source to call `api/calendar.php?action=events` with current year/month
    - Add product filter dropdown that re-fetches events on change
    - Render event detail panel on click showing product name, predicted quantity, data point count
    - Style indicator events (insufficient data) differently from forecast events
    - _Requirements: 6.1, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4_

  - [x] 11.5 Add navigation links to the existing sidebar/nav for the three new pages
    - Link to `alerts.php`, `sms_log.php`, `reports.php`, `calendar.php`

- [ ] 12. Final checkpoint — Ensure all tests pass and pages are wired correctly
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Property tests require minimum 100 iterations each
- SMS gateway credentials must never be committed; use `config/integrations.php` (gitignored)
- `SmsService` should accept a gateway adapter interface so the HTTP call can be mocked in tests
- All new API endpoints return `Content-Type: application/json` except `api/reports.php` which returns `application/pdf`
- No existing tables or core classes are modified until task 4.3 (the hook wiring step)
