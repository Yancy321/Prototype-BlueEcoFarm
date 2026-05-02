# Requirements Document

## Introduction

The Blue Eco Farm Inventory and Sales Forecasting System is a PHP-based web application that enables Blue Eco Farm staff to manage product stocks across two warehouse locations (Farm and Paranaque), perform stock management operations, visualize inventory data through a dashboard with graphs, transfer stock between warehouses, and forecast future sales using Linear Regression.

## Glossary

- **System**: The Blue Eco Farm Inventory and Sales Forecasting System
- **Inventory_Manager**: The System component responsible for managing stock records
- **Forecasting_Engine**: The System component that applies Linear Regression to predict future sales
- **Dashboard**: The System component that renders visual graphs and stock summaries
- **Warehouse**: A physical storage location tracked by the System (Farm or Paranaque)
- **Product**: A sellable item with a specific pack size (Big or Small) and form (Granules, Tablet, or Powder)
- **Stock_Record**: A database row representing a stock transaction (incoming or outgoing) for a Product at a Warehouse
- **Transfer**: A movement of stock from the Farm Warehouse to the Paranaque Warehouse
- **Outgoing_Stock**: Stock removed from a Warehouse due to a sale or dispatch
- **Incoming_Stock**: Stock added to a Warehouse due to a new delivery

---

## Requirements

### Requirement 1: Product Catalog

**User Story:** As a staff member, I want a defined catalog of product types, so that all inventory entries are consistent and categorized correctly.

#### Acceptance Criteria

1. THE System SHALL support exactly six product types: Big Pack Granules, Big Pack Tablet, Big Pack Powder, Small Pack Granules, Small Pack Tablet, and Small Pack Powder.
2. WHEN a staff member creates a Stock_Record, THE Inventory_Manager SHALL require the selection of one of the six defined product types.
3. IF a product type outside the defined six is submitted, THEN THE Inventory_Manager SHALL reject the entry and return a validation error message.

---

### Requirement 2: Dual Warehouse Management

**User Story:** As a staff member, I want to track inventory separately for the Farm and Paranaque warehouses, so that I know the exact stock levels at each location.

#### Acceptance Criteria

1. THE System SHALL maintain separate Stock_Records for the Farm Warehouse and the Paranaque Warehouse.
2. WHEN a staff member views stock, THE Dashboard SHALL display stock quantities per product type for each Warehouse independently.
3. THE Inventory_Manager SHALL associate every Stock_Record with exactly one Warehouse.

---

### Requirement 3: Add Incoming Stock

**User Story:** As a staff member, I want to add incoming stock entries, so that new deliveries are reflected in the inventory.

#### Acceptance Criteria

1. WHEN a staff member submits an incoming stock entry with a valid product type, warehouse, quantity, and date, THE Inventory_Manager SHALL create a new Stock_Record and increase the total stock for that product at that Warehouse by the submitted quantity.
2. IF a submitted incoming stock entry is missing a required field (product type, warehouse, quantity, or date), THEN THE Inventory_Manager SHALL reject the entry and return a descriptive validation error.
3. IF a submitted quantity is less than or equal to zero, THEN THE Inventory_Manager SHALL reject the entry and return a validation error.

---

### Requirement 4: Record Outgoing Stock

**User Story:** As a staff member, I want to record outgoing stock, so that sales and dispatches are accurately reflected in the inventory.

#### Acceptance Criteria

1. WHEN a staff member submits an outgoing stock entry with a valid product type, warehouse, quantity, and date, THE Inventory_Manager SHALL deduct the quantity from the current stock total for that product at that Warehouse and record the transaction.
2. IF the requested outgoing quantity exceeds the current available stock for that product at that Warehouse, THEN THE Inventory_Manager SHALL reject the transaction and return an insufficient stock error.
3. IF a submitted outgoing stock entry is missing a required field, THEN THE Inventory_Manager SHALL reject the entry and return a descriptive validation error.

---

### Requirement 5: Update Stock Records

**User Story:** As a staff member, I want to correct previously entered stock records, so that data entry errors can be fixed.

#### Acceptance Criteria

1. WHEN a staff member submits an update to an existing Stock_Record with valid corrected values, THE Inventory_Manager SHALL update the record and recalculate the affected stock totals.
2. IF the updated quantity would result in a negative total stock for that product at that Warehouse, THEN THE Inventory_Manager SHALL reject the update and return a validation error.
3. IF a Stock_Record with the specified identifier does not exist, THEN THE Inventory_Manager SHALL return a not-found error.

---

### Requirement 6: Stock Transfer Between Warehouses

**User Story:** As a staff member, I want to transfer stock from the Farm warehouse to the Paranaque warehouse, so that client requests from Paranaque can be fulfilled.

#### Acceptance Criteria

1. WHEN a staff member initiates a transfer with a valid product type, quantity, and date, THE Inventory_Manager SHALL deduct the specified quantity from the Farm Warehouse and add it to the Paranaque Warehouse as a single atomic database transaction.
2. IF the transfer quantity exceeds the available stock at the Farm Warehouse for the specified product, THEN THE Inventory_Manager SHALL reject the transfer and return an insufficient stock error.
3. THE Inventory_Manager SHALL record each Transfer with the product type, quantity, date, source warehouse, and destination warehouse.
4. IF a transfer entry is missing a required field (product type, quantity, or date), THEN THE Inventory_Manager SHALL reject the transfer and return a descriptive validation error.

---

### Requirement 7: Inventory Dashboard

**User Story:** As a staff member, I want a dashboard that shows current stock levels and transaction graphs, so that I can monitor inventory at a glance.

#### Acceptance Criteria

1. WHEN a staff member opens the Dashboard, THE Dashboard SHALL display the current total stock for each of the six product types for both warehouses.
2. THE Dashboard SHALL display a Chart.js graph of incoming stock quantities over time per product type.
3. THE Dashboard SHALL display a Chart.js graph of outgoing stock quantities over time per product type.
4. WHEN a staff member selects a warehouse filter, THE Dashboard SHALL update the displayed stock totals and graphs to show only data for the selected Warehouse.

---

### Requirement 8: Sales Forecasting

**User Story:** As a manager, I want the system to forecast future sales using historical data, so that I can plan restocking in advance.

#### Acceptance Criteria

1. WHEN a manager requests a sales forecast for a product type, THE Forecasting_Engine SHALL apply a Linear Regression model trained on historical outgoing stock data for that product to produce a predicted sales quantity for the requested future period.
2. THE Forecasting_Engine SHALL require a minimum of 5 historical outgoing stock data points per product type before generating a forecast.
3. IF fewer than 5 historical data points exist for a product type, THEN THE Forecasting_Engine SHALL return an insufficient data error and indicate how many more data points are required.
4. THE Dashboard SHALL display the forecasted sales values alongside historical outgoing stock data in a Chart.js graph for each product type.

---

### Requirement 9: Data Persistence

**User Story:** As a staff member, I want all inventory data stored in a MySQL database, so that records are not lost between sessions.

#### Acceptance Criteria

1. THE System SHALL persist all Stock_Records and Transfer records in a MySQL relational database.
2. WHEN the System is accessed after a restart, THE Inventory_Manager SHALL retrieve all previously committed Stock_Records and Transfer records from the database without data loss.
3. THE System SHALL maintain a transaction log of all create, update, and delete operations on Stock_Records.
