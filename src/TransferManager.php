<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/InventoryManager.php';
require_once __DIR__ . '/SmsService.php';

class TransferManager {

    private PDO $pdo;
    private InventoryManager $inventory;
    private ?SmsService $smsService;

    public function __construct(?SmsService $smsService = null) {
        $this->pdo        = Database::getInstance();
        $this->inventory  = new InventoryManager();
        $this->smsService = $smsService;
    }

    /**
     * Transfer stock from Farm (warehouse 1) to Paranaque (warehouse 2).
     * Wrapped in a single atomic MySQL transaction.
     */
    public function transfer(int $productId, int $quantity, string $date): int {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity must be greater than zero", 400);
        }
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException("Invalid date format, expected YYYY-MM-DD", 400);
        }

        $this->pdo->beginTransaction();
        try {
            $available = $this->inventory->getCurrentStock($productId, 1);
            if ($quantity > $available) {
                throw new RuntimeException("Insufficient stock: available {$available}, requested {$quantity}", 409);
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO transfers (product_id, quantity, transfer_date, source_warehouse_id, destination_warehouse_id)
                 VALUES (?, ?, ?, 1, 2)"
            );
            $stmt->execute([$productId, $quantity, $date]);
            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();

            // Trigger SMS alert check for the source warehouse (Farm = 1) after stock is reduced
            if ($this->smsService !== null) {
                $remainingStock = $this->inventory->getCurrentStock($productId, 1);
                $this->smsService->checkAndAlert($productId, 1, $remainingStock);
            }

            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * List all transfer records with product names.
     */
    public function listTransfers(): array {
        $stmt = $this->pdo->query(
            "SELECT t.*, p.name AS product_name
             FROM transfers t
             JOIN products p ON p.id = t.product_id
             ORDER BY t.transfer_date DESC, t.id DESC"
        );
        return $stmt->fetchAll();
    }
}
