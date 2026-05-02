<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TransactionLogger.php';
require_once __DIR__ . '/SmsService.php';

class InventoryManager {

    private PDO $pdo;
    private ?SmsService $smsService;

    public function __construct(?SmsService $smsService = null) {
        $this->pdo        = Database::getInstance();
        $this->smsService = $smsService;
    }

    /**
     * Returns current stock for a product at a warehouse.
     * Computed via SQL aggregation (no stored counter).
     */
    public function getCurrentStock(int $productId, int $warehouseId): int {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN record_type = 'incoming' THEN quantity ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN record_type = 'outgoing' THEN quantity ELSE 0 END), 0)
              + COALESCE((SELECT SUM(quantity) FROM transfers
                          WHERE product_id = :pid1 AND destination_warehouse_id = :wid1), 0)
              - COALESCE((SELECT SUM(quantity) FROM transfers
                          WHERE product_id = :pid2 AND source_warehouse_id = :wid2), 0)
            AS current_stock
            FROM stock_records
            WHERE product_id = :pid3 AND warehouse_id = :wid3 AND is_deleted = 0
        ");
        $stmt->execute([
            ':pid1' => $productId, ':wid1' => $warehouseId,
            ':pid2' => $productId, ':wid2' => $warehouseId,
            ':pid3' => $productId, ':wid3' => $warehouseId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Add incoming stock record.
     */
    public function addIncoming(int $productId, int $warehouseId, int $quantity, string $date, string $notes = '', string $batchNumber = ''): int {
        $this->validateProduct($productId);
        $this->validateWarehouse($warehouseId);
        $this->validateQuantity($quantity);
        $this->validateDate($date);

        $batchId = $batchNumber ? $this->resolveBatchId($batchNumber, $productId) : null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO stock_records (product_id, warehouse_id, record_type, quantity, transaction_date, notes, batch_number, batch_id)
             VALUES (?, ?, 'incoming', ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$productId, $warehouseId, $quantity, $date, $notes, $batchNumber ?: null, $batchId]);
        $id = (int) $this->pdo->lastInsertId();
        TransactionLogger::log('create', 'stock_records', $id, [
            'product_id' => $productId, 'warehouse_id' => $warehouseId,
            'record_type' => 'incoming', 'quantity' => $quantity, 'date' => $date, 'batch_number' => $batchNumber,
        ]);
        return $id;
    }

    /**
     * Record outgoing stock. Rejects if quantity exceeds available stock.
     */
    public function addOutgoing(int $productId, int $warehouseId, int $quantity, string $date, string $notes = '', string $batchNumber = ''): int {
        $this->validateProduct($productId);
        $this->validateWarehouse($warehouseId);
        $this->validateQuantity($quantity);
        $this->validateDate($date);

        $available = $this->getCurrentStock($productId, $warehouseId);
        if ($quantity > $available) {
            throw new RuntimeException("Insufficient stock: available {$available}, requested {$quantity}", 409);
        }

        $batchId = $batchNumber ? $this->resolveBatchId($batchNumber, $productId) : null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO stock_records (product_id, warehouse_id, record_type, quantity, transaction_date, notes, batch_number, batch_id)
             VALUES (?, ?, 'outgoing', ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$productId, $warehouseId, $quantity, $date, $notes, $batchNumber ?: null, $batchId]);
        $id = (int) $this->pdo->lastInsertId();
        TransactionLogger::log('create', 'stock_records', $id, [
            'product_id' => $productId, 'warehouse_id' => $warehouseId,
            'record_type' => 'outgoing', 'quantity' => $quantity, 'date' => $date, 'batch_number' => $batchNumber,
        ]);

        // Trigger SMS alert check after stock is reduced
        if ($this->smsService !== null) {
            $currentStock = $this->getCurrentStock($productId, $warehouseId);
            $this->smsService->checkAndAlert($productId, $warehouseId, $currentStock);
        }

        return $id;
    }

    /**
     * Update an existing stock record. Rejects if result would be negative stock.
     */
    public function updateRecord(int $recordId, int $quantity, string $date, string $notes = ''): void {
        $this->validateQuantity($quantity);
        $this->validateDate($date);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM stock_records WHERE id = ? AND is_deleted = 0"
        );
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        if (!$record) {
            throw new RuntimeException("Stock record {$recordId} not found", 404);
        }

        // Check that updating won't cause negative stock
        $before = $this->getCurrentStock($record['product_id'], $record['warehouse_id']);
        $delta = ($record['record_type'] === 'incoming')
            ? ($quantity - $record['quantity'])
            : ($record['quantity'] - $quantity);
        if ($before + $delta < 0) {
            throw new RuntimeException("Update would result in negative stock", 400);
        }

        $snapshot = ['before' => $record, 'after' => ['quantity' => $quantity, 'date' => $date, 'notes' => $notes]];
        $upd = $this->pdo->prepare(
            "UPDATE stock_records SET quantity = ?, transaction_date = ?, notes = ? WHERE id = ?"
        );
        $upd->execute([$quantity, $date, $notes, $recordId]);
        TransactionLogger::log('update', 'stock_records', $recordId, $snapshot);
    }

    /**
     * Soft-delete a stock record.
     */
    public function softDelete(int $recordId): void {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM stock_records WHERE id = ? AND is_deleted = 0"
        );
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        if (!$record) {
            throw new RuntimeException("Stock record {$recordId} not found", 404);
        }

        $del = $this->pdo->prepare("UPDATE stock_records SET is_deleted = 1 WHERE id = ?");
        $del->execute([$recordId]);
        TransactionLogger::log('delete', 'stock_records', $recordId, ['before' => $record]);
    }

    // --- Validation helpers ---

    private function validateProduct(int $productId): void {
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException("Invalid product type", 400);
        }
    }

    private function validateWarehouse(int $warehouseId): void {
        if (!in_array($warehouseId, [1, 2], true)) {
            throw new InvalidArgumentException("Invalid warehouse", 400);
        }
    }

    private function validateQuantity(int $quantity): void {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity must be greater than zero", 400);
        }
    }

    private function validateDate(string $date): void {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException("Invalid date format, expected YYYY-MM-DD", 400);
        }
    }

    /**
     * Find or create a batch record and return its ID.
     */
    private function resolveBatchId(string $batchNumber, int $productId): int {
        $stmt = $this->pdo->prepare("SELECT id FROM batches WHERE batch_number = ?");
        $stmt->execute([$batchNumber]);
        $row = $stmt->fetch();
        if ($row) return (int) $row['id'];

        $ins = $this->pdo->prepare("INSERT INTO batches (batch_number, product_id) VALUES (?, ?)");
        $ins->execute([$batchNumber, $productId]);
        return (int) $this->pdo->lastInsertId();
    }
}
