<?php

/**
 * SmsService — composes and dispatches SMS alerts based on Alert_Rules.
 * Uses Semaphore (semaphore.co) as the SMS gateway — Philippine-based,
 * supports Globe, Smart, and Sun numbers.
 *
 * Accepts an optional gateway adapter (callable) for testability:
 *   callable(string $to, string $message, array $config): array{success: bool, error: ?string}
 */
class SmsService
{
    private PDO $db;
    private array $config;
    /** @var callable|null */
    private $gatewayAdapter;

    /**
     * @param PDO|null      $db             PDO instance (defaults to Database::getInstance())
     * @param array|null    $config         SMS config (defaults to config/integrations.php)
     * @param callable|null $gatewayAdapter Optional adapter: fn(to, message, config): {success, error}
     */
    public function __construct(?PDO $db = null, ?array $config = null, ?callable $gatewayAdapter = null)
    {
        $this->db             = $db     ?? Database::getInstance();
        $this->config         = $config ?? (require __DIR__ . '/../config/integrations.php')['sms'];
        $this->gatewayAdapter = $gatewayAdapter;
    }

    // -------------------------------------------------------------------------
    // Public interface
    // -------------------------------------------------------------------------

    /**
     * Compose the alert message body.
     * Requirement 2.2: must contain product name, warehouse name, current stock, threshold.
     */
    public function composeMessage(
        string $productName,
        string $warehouseName,
        int $currentStock,
        int $threshold
    ): string {
        return sprintf(
            '[Blue Eco Farm] LOW STOCK ALERT: %s at %s — current stock: %d (threshold: %d). Please replenish.',
            $productName,
            $warehouseName,
            $currentStock,
            $threshold
        );
    }

    /**
     * Send a single SMS via the configured gateway.
     * Returns true on success, false on gateway error.
     * Requirement 2.3: caller is responsible for logging; this method only dispatches.
     */
    public function send(string $to, string $message): bool
    {
        if ($this->gatewayAdapter !== null) {
            $result = ($this->gatewayAdapter)($to, $message, $this->config);
            return (bool) $result['success'];
        }

        return $this->sendViaCurl($to, $message);
    }

    /**
     * Evaluate all active Alert_Rules for a product+warehouse after a stock change.
     * Requirements 2.1, 2.3, 2.4, 2.5.
     */
    public function checkAndAlert(int $productId, int $warehouseId, int $currentStock): void
    {
        // 1. Fetch matching active rules where threshold >= currentStock
        $rules = $this->fetchMatchingRules($productId, $warehouseId, $currentStock);

        foreach ($rules as $rule) {
            // 2. Check cooldown — skip if a recent dispatch exists within the window
            if ($this->isInCooldown((int) $rule['id'], (int) $rule['cooldown_minutes'])) {
                continue;
            }

            // 3. Resolve product and warehouse names for the message
            $productName   = $this->resolveProductName($productId);
            $warehouseName = $this->resolveWarehouseName($warehouseId);
            $message       = $this->composeMessage(
                $productName,
                $warehouseName,
                $currentStock,
                (int) $rule['threshold']
            );

            // 4. Send to each recipient from alert_recipients table
            $recStmt = $this->db->prepare(
                'SELECT phone FROM alert_recipients WHERE product_id = :product_id'
            );
            $recStmt->execute([':product_id' => $productId]);
            $recipients = $recStmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($recipients as $recipient) {
                $success = false;
                $errorDetail = null;

                try {
                    $success = $this->send($recipient, $message);
                } catch (\Throwable $e) {
                    $errorDetail = $e->getMessage();
                }

                $this->logDispatch(
                    (int) $rule['id'],
                    $recipient,
                    $message,
                    $success,
                    $errorDetail
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Query alert_rules for active rules matching product+warehouse where threshold >= currentStock.
     */
    private function fetchMatchingRules(int $productId, int $warehouseId, int $currentStock): array
    {
        $stmt = $this->db->prepare(
            'SELECT ar.id, ar.threshold, ar.recipients, ar.cooldown_minutes
               FROM alert_rules ar
              WHERE ar.product_id   = :product_id
                AND ar.warehouse_id = :warehouse_id
                AND ar.threshold    >= :current_stock
                AND ar.is_active    = 1'
        );
        $stmt->execute([
            ':product_id'    => $productId,
            ':warehouse_id'  => $warehouseId,
            ':current_stock' => $currentStock,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Returns true if a successful dispatch for this rule exists within the cooldown window.
     * Requirement 2.4.
     */
    private function isInCooldown(int $ruleId, int $cooldownMinutes): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt
               FROM sms_alert_log
              WHERE alert_rule_id = :rule_id
                AND dispatched_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':minutes' => $cooldownMinutes,
        ]);
        $row = $stmt->fetch();
        return (int) $row['cnt'] > 0;
    }

    /**
     * Write a dispatch attempt to sms_alert_log.
     * Requirements 2.3, 2.5, 3.1.
     */
    private function logDispatch(
        int $ruleId,
        string $recipient,
        string $message,
        bool $success,
        ?string $errorDetail
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO sms_alert_log
                (alert_rule_id, recipient, message, status, error_detail, dispatched_at)
             VALUES
                (:rule_id, :recipient, :message, :status, :error_detail, NOW())'
        );
        $stmt->execute([
            ':rule_id'      => $ruleId,
            ':recipient'    => $recipient,
            ':message'      => $message,
            ':status'       => $success ? 'success' : 'failure',
            ':error_detail' => $errorDetail,
        ]);
    }

    /**
     * Resolve a human-readable product name from the products table.
     */
    private function resolveProductName(int $productId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM products WHERE id = :id');
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();
        return $row ? $row['name'] : "Product #{$productId}";
    }

    /**
     * Resolve a human-readable warehouse name.
     * Warehouses: 1 = Farm, 2 = Paranaque (per spec glossary).
     */
    private function resolveWarehouseName(int $warehouseId): string
    {
        $map = [1 => 'Farm', 2 => 'Paranaque'];
        return $map[$warehouseId] ?? "Warehouse #{$warehouseId}";
    }

    /**
     * Default gateway: iProgSMS API (iprogsms.com) — Philippine-based.
     * Supports Globe, Smart, Sun numbers.
     */
    private function sendViaCurl(string $to, string $message): bool
    {
        $apiKey = $this->config['api_key'];

        // Normalize to 09xxxxxxxxx format
        $number = $this->normalizePhilippineNumber($to);

        $url     = 'https://www.iprogsms.com/api/v1/sms_messages';
        $payload = json_encode([
            'api_token'    => $apiKey,
            'phone_number' => $number,
            'message'      => $message,
            'sms_provider' => 0,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("curl error: {$curlError}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $decoded = json_decode($response, true);
            $detail  = $decoded['message'] ?? $response;
            throw new \RuntimeException("iProgSMS error ({$httpCode}): {$detail}");
        }

        return true;
    }

    /**
     * Normalize a Philippine phone number to 09xxxxxxxxx format.
     * Accepts: +639xxxxxxxxx, 639xxxxxxxxx, 09xxxxxxxxx
     */
    private function normalizePhilippineNumber(string $number): string
    {
        $number = preg_replace('/\s+/', '', $number);

        if (str_starts_with($number, '+63')) {
            return '0' . substr($number, 3);
        }
        if (str_starts_with($number, '63') && strlen($number) === 12) {
            return '0' . substr($number, 2);
        }

        return $number;
    }
}
