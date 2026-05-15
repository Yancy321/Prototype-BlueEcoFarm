<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';
require_once __DIR__ . '/../lib/phpmailer/Exception.php';
require_once __DIR__ . '/Database.php';

/**
 * EmailService — sends OTP codes via Gmail SMTP for distributor password reset.
 */
class EmailService
{
    private array $config;
    private PDO   $pdo;

    public function __construct()
    {
        $cfg          = require __DIR__ . '/../config/integrations.php';
        $this->config = $cfg['email'];
        $this->pdo    = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a 6-digit OTP, store it, and email it to the user.
     * Returns true on success, false if the email address is not found
     * or belongs to a non-distributor account.
     *
     * @throws RuntimeException on SMTP failure
     */
    public function sendPasswordResetOtp(string $email): bool
    {
        $email = strtolower(trim($email));

        // Only allow distributors to use email reset
        $stmt = $this->pdo->prepare(
            "SELECT id, full_name FROM users
             WHERE LOWER(email) = ? AND role = 'distributor'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        // Invalidate any existing unused tokens for this user
        $this->pdo->prepare(
            "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0"
        )->execute([$user['id']]);

        // Generate 6-digit OTP — let MySQL handle the expiry time to avoid timezone issues
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->pdo->prepare(
            "INSERT INTO password_reset_tokens (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
        )->execute([$user['id'], $otp]);

        $this->sendOtpEmail($email, $user['full_name'], $otp);

        return true;
    }

    /**
     * Verify an OTP and return the user_id if valid, null otherwise.
     * Does NOT mark the token as used — call markOtpUsed() after password reset.
     */
    public function verifyOtp(string $email, string $otp): ?int
    {
        $email = strtolower(trim($email));
        $otp   = trim($otp);

        $stmt = $this->pdo->prepare(
            "SELECT prt.id, prt.user_id
             FROM password_reset_tokens prt
             JOIN users u ON u.id = prt.user_id
             WHERE LOWER(u.email) = ?
               AND prt.token      = ?
               AND prt.used       = 0
               AND prt.expires_at > NOW()
             ORDER BY prt.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$email, $otp]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Store token ID in session so we can mark it used after password reset
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['otp_token_id'] = (int) $row['id'];

        return (int) $row['user_id'];
    }

    /**
     * Mark the verified OTP token as used (call after successful password reset).
     */
    public function markOtpUsed(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $tokenId = $_SESSION['otp_token_id'] ?? null;
        if ($tokenId) {
            $this->pdo->prepare(
                "UPDATE password_reset_tokens SET used = 1 WHERE id = ?"
            )->execute([$tokenId]);
            unset($_SESSION['otp_token_id']);
        }
    }

    /**
     * Reset a user's password by user_id.
     */
    public function resetPassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters.');
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->pdo->prepare(
            "UPDATE users SET password = ? WHERE id = ?"
        )->execute([$hash, $userId]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function sendOtpEmail(string $toEmail, string $toName, string $otp): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->config['username'];
        $mail->Password   = $this->config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $this->config['port'];

        $mail->setFrom($this->config['from_email'], $this->config['from_name']);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Blue Eco Farm — Password Reset Code';
        $mail->Body    = $this->buildEmailHtml($toName, $otp);
        $mail->AltBody = "Hi {$toName},\n\nYour password reset code is: {$otp}\n\nThis code expires in 15 minutes.\n\nIf you did not request this, ignore this email.\n\n— Blue Eco Farm";

        $mail->send();
    }

    private function buildEmailHtml(string $name, string $otp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f0;font-family:'Segoe UI',sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 16px;">
      <table width="480" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#1e3a1a,#2d5a27);padding:32px 40px;text-align:center;">
            <div style="font-size:1.5rem;font-weight:700;color:#fff;letter-spacing:0.02em;">🌿 Blue Eco Farm</div>
            <div style="font-size:0.85rem;color:#a8d5a2;margin-top:4px;">Distributor Portal</div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:40px 40px 32px;">
            <p style="margin:0 0 8px;font-size:1rem;color:#1a2e18;font-weight:600;">Hi {$name},</p>
            <p style="margin:0 0 28px;font-size:0.9rem;color:#6b7c69;line-height:1.6;">
              We received a request to reset your password. Use the code below — it expires in <strong>15 minutes</strong>.
            </p>

            <!-- OTP Box -->
            <div style="text-align:center;margin:0 0 28px;">
              <div style="display:inline-block;background:#f4faf2;border:2px dashed #4a8c42;
                          border-radius:12px;padding:20px 40px;">
                <div style="font-size:2.5rem;font-weight:800;letter-spacing:0.3em;color:#2d5a27;
                             font-family:'Courier New',monospace;">{$otp}</div>
                <div style="font-size:0.75rem;color:#9aab98;margin-top:6px;">One-time password</div>
              </div>
            </div>

            <p style="margin:0;font-size:0.82rem;color:#9aab98;line-height:1.6;">
              If you did not request a password reset, you can safely ignore this email.
              Your password will not be changed.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f7fbf5;padding:20px 40px;border-top:1px solid #e2ece0;
                     text-align:center;font-size:0.75rem;color:#b0bfb0;">
            © Blue Eco Farm · This is an automated message, please do not reply.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
