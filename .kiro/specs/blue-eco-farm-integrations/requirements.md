# Requirements Document

## Introduction

This document specifies the requirements for three integrations added to the existing Blue Eco Farm Inventory and Sales Forecasting System:

1. **SMS Integration** — Automatically notify management via SMS when stock levels fall below a configurable threshold, preventing stockouts and supporting timely replenishment decisions.
2. **PDF Report Integration** — Generate formal, downloadable PDF reports summarizing stock movements for record-keeping, audits, and management review.
3. **Calendar Service Integration** — Visualize sales forecasts in a calendar format so users can anticipate high-demand periods and plan inventory levels accordingly.

These integrations extend the existing PHP system without altering its core inventory logic.

## Glossary

- **System**: The Blue Eco Farm Inventory and Sales Forecasting System (existing + new integrations)
- **SMS_Service**: The System component responsible for composing and dispatching SMS alert messages via a third-party SMS gateway API
- **Alert_Rule**: A configuration record that defines a product, a warehouse, a stock threshold quantity, and one or more recipient phone numbers
- **PDF_Generator**: The System component responsible for composing and rendering PDF report documents
- **Stock_Movement_Report**: A PDF document summarizing incoming and outgoing stock transactions for a specified date range and optional warehouse/product filters
- **Calendar_Service**: The System component responsible for mapping sales forecast data onto calendar date entries
- **Forecast_Event**: A calendar entry representing a predicted sales quantity for a product on a specific date
- **Inventory_Manager**: The existing component managing stock records (defined in the base system spec)
- **Forecasting_Engine**: The existing component producing Linear Regression sales forecasts (defined in the base system spec)
- **Warehouse**: A physical storage location (Farm = 1, Paranaque = 2)
- **Product**: One of the six defined product types (Big/Small × Granules/Tablet/Powder)

---

## Requirements

### Requirement 1: Stock Threshold Configuration

**User Story:** As a manager, I want to define stock alert thresholds per product per warehouse, so that the system knows when to send SMS notifications.

#### Acceptance Criteria

1. THE System SHALL provide a management interface for creating, updating, and deleting Alert_Rules.
2. WHEN a manager creates an Alert_Rule, THE System SHALL require a valid product, a valid warehouse, a threshold quantity greater than zero, and at least one recipient phone number in E.164 format.
3. IF a submitted Alert_Rule is missing a required field or contains an invalid phone number format, THEN THE System SHALL reject the entry and return a descriptive validation error.
4. THE System SHALL allow multiple Alert_Rules for the same product and warehouse, each with different thresholds and recipient lists.
5. WHEN an Alert_Rule is deleted, THE System SHALL cease evaluating that rule for future stock checks.

---

### Requirement 2: Automatic SMS Alerts on Low Stock

**User Story:** As a manager, I want to receive an SMS when stock falls below a threshold, so that I can act before a stockout occurs.

#### Acceptance Criteria

1. WHEN a stock-out or transfer operation causes the current stock of a product at a warehouse to fall at or below the threshold defined in an Alert_Rule, THE SMS_Service SHALL send an SMS alert to all recipient phone numbers listed in that Alert_Rule.
2. THE SMS_Service SHALL compose each alert message to include the product name, warehouse name, current stock quantity, and the threshold value that was breached.
3. IF the SMS gateway API returns an error for a recipient, THEN THE SMS_Service SHALL log the failure with the recipient number, error code, and timestamp, and SHALL continue attempting delivery to remaining recipients in the Alert_Rule.
4. THE System SHALL not send duplicate SMS alerts for the same Alert_Rule within a configurable cooldown period (default: 60 minutes).
5. WHEN an SMS alert is successfully dispatched, THE System SHALL record the alert event with the Alert_Rule identifier, timestamp, and delivery status.

---

### Requirement 3: SMS Delivery Log

**User Story:** As a manager, I want to review a history of sent SMS alerts, so that I can audit notification activity.

#### Acceptance Criteria

1. THE System SHALL maintain a persistent log of all SMS alert dispatch attempts, including Alert_Rule identifier, recipient phone number, message content, dispatch timestamp, and delivery status (success or failure with error detail).
2. WHEN a manager views the SMS alert log, THE System SHALL display entries sorted by dispatch timestamp in descending order.
3. THE System SHALL retain SMS alert log entries for a minimum of 90 days.

---

### Requirement 4: PDF Stock Movement Report Generation

**User Story:** As a manager, I want to generate a PDF report of stock movements, so that I have a formal document for audits and management review.

#### Acceptance Criteria

1. WHEN a manager requests a Stock_Movement_Report with a valid date range, THE PDF_Generator SHALL produce a PDF document containing all stock transactions (incoming and outgoing) within that date range.
2. THE PDF_Generator SHALL support optional filters for warehouse and product type when generating a Stock_Movement_Report.
3. THE Stock_Movement_Report SHALL include: report title, generation timestamp, applied filters, a tabular listing of transactions (date, product, warehouse, type, quantity, notes), and a summary section with total incoming and total outgoing quantities per product.
4. WHEN a manager downloads the generated PDF, THE System SHALL serve the file with the correct MIME type (`application/pdf`) and a descriptive filename including the date range (e.g., `stock-report-2025-01-01-to-2025-01-31.pdf`).
5. IF the requested date range returns no transactions, THEN THE PDF_Generator SHALL produce a PDF that clearly states no transactions were found for the specified filters.
6. IF the date range start is after the date range end, THEN THE System SHALL reject the request and return a validation error.

---

### Requirement 5: PDF Report Formatting

**User Story:** As a manager, I want the PDF report to be professionally formatted, so that it is suitable for sharing with stakeholders.

#### Acceptance Criteria

1. THE Stock_Movement_Report SHALL include the Blue Eco Farm name and logo in the report header on every page.
2. THE Stock_Movement_Report SHALL include page numbers in the footer of every page.
3. WHEN a Stock_Movement_Report spans multiple pages, THE PDF_Generator SHALL repeat the transaction table header row on each page.
4. THE PDF_Generator SHALL render numeric quantities with thousand-separator formatting (e.g., 1,250).

---

### Requirement 6: Calendar Forecast Visualization

**User Story:** As a manager, I want to view sales forecasts on a calendar, so that I can identify high-demand periods and plan inventory accordingly.

#### Acceptance Criteria

1. WHEN a manager opens the calendar view, THE Calendar_Service SHALL display a monthly calendar populated with Forecast_Events for each product type.
2. WHEN the Forecasting_Engine produces a forecast for a product, THE Calendar_Service SHALL map each predicted sales quantity to the corresponding future date as a Forecast_Event.
3. WHEN a manager selects a product filter on the calendar view, THE Calendar_Service SHALL display only Forecast_Events for the selected product type.
4. WHEN a manager clicks on a Forecast_Event, THE Calendar_Service SHALL display a detail panel showing the product name, predicted quantity, and the confidence context (number of historical data points used).
5. IF the Forecasting_Engine returns an insufficient data error for a product, THEN THE Calendar_Service SHALL display a visual indicator on the calendar for that product instead of a Forecast_Event, noting that insufficient data is available.

---

### Requirement 7: Calendar Navigation and Display

**User Story:** As a manager, I want to navigate between months on the calendar, so that I can review forecasts for upcoming periods.

#### Acceptance Criteria

1. WHEN a manager navigates to the next or previous month, THE Calendar_Service SHALL fetch and display Forecast_Events for the newly selected month.
2. THE Calendar_Service SHALL visually distinguish between dates in the past, the current date, and future forecast dates.
3. WHEN multiple products have Forecast_Events on the same calendar date, THE Calendar_Service SHALL display all of them on that date, with each product visually differentiated by color or label.
4. THE Calendar_Service SHALL display the calendar in the user's local timezone as reported by the browser.
