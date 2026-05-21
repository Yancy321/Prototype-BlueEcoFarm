<?php

require_once __DIR__ . '/Database.php';

class TransactionLogger {
    public static function log(string $operation, string $tableName, int $recordId, array $snapshot = []): void {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            "INSERT INTO transaction_logs (operation, table_name, record_id, snapshot) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$operation, $tableName, $recordId, json_encode($snapshot)]);
    }
}
