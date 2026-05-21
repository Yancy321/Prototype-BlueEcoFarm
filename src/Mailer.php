<?php

class Mailer {

    // Gmail SMTP Configuration
    private static string $gmailAddress = 'sakuragardensystem@gmail.com';
    private static string $gmailPassword = 'ykzc sdew ltev bqaw';  // App password
    private static string $senderName = 'Blue Eco Farm';

    /**
     * Send email via Gmail SMTP.
     */
    private static function sendViaGmailSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {

        $sock = fsockopen('smtp.gmail.com', 587, $errno, $errstr, 10);

        if (!$sock) {
            error_log("SMTP Error: $errstr ($errno)");
            return false;
        }

        $out = '';
        $out .= "EHLO localhost\r\n";
        $out .= "STARTTLS\r\n";

        fwrite($sock, $out);
        stream_set_blocking($sock, true);

        while ($line = fgets($sock, 515)) {
            if (strpos($line, '220 ') === 0 || strpos($line, '250 ') === 0) {
                break;
            }
        }

        $ssl_context = stream_context_create();
        stream_context_set_option($ssl_context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($ssl_context, 'ssl', 'verify_peer', false);

        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        stream_set_blocking($sock, true);

        $commands = [
            "EHLO localhost\r\n",
            "AUTH LOGIN\r\n",
            base64_encode(self::$gmailAddress) . "\r\n",
            base64_encode(self::$gmailPassword) . "\r\n",
            "MAIL FROM:<" . self::$gmailAddress . ">\r\n",
            "RCPT TO:<$toEmail>\r\n",
            "DATA\r\n",
        ];

        foreach ($commands as $command) {
            fwrite($sock, $command);
            while ($line = fgets($sock, 515)) {
                if (!empty($line) && ctype_digit($line[0])) {
                    break;
                }
            }
        }

        $headers = "From: " . self::$senderName . " <" . self::$gmailAddress . ">\r\n";
        $headers .= "To: $toName <$toEmail>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";

        $fullBody = $headers . $htmlBody . "\r\n.\r\n";

        fwrite($sock, $fullBody);

        while ($line = fgets($sock, 515)) {
            if (!empty($line) && ctype_digit($line[0])) {
                break;
            }
        }

        fwrite($sock, "QUIT\r\n");
        fclose($sock);

        return true;
    }

    /**
     * Send verification code email.
     */
    public static function sendVerificationEmail(
        string $email,
        string $fullName,
        string $code
    ): bool {

        $subject = 'Email Verification - Blue Eco Farm';

        $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 500px; margin: 0 auto; padding: 20px; }
                    .header { color: #2e7d32; margin-bottom: 20px; }
                    .code { background: #f0f4f0; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 2px; margin: 20px 0; border-radius: 8px; }
                    .footer { color: #888; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class=\"container\">
                    <h1 class=\"header\">🌿 Blue Eco Farm</h1>
                    <p>Hi " . htmlspecialchars($fullName) . ",</p>
                    <p>Welcome to Blue Eco Farm! To complete your account setup, please verify your email address using the code below:</p>
                    <div class=\"code\">$code</div>
                    <p>This code will expire in 24 hours.</p>
                    <p>If you did not create this account, please ignore this email.</p>
                    <div class=\"footer\">
                        <p>© 2026 Blue Eco Farm. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        return self::sendViaGmailSmtp($email, $fullName, $subject, $body);
    }

    /**
     * Send account created notification.
     */
    public static function sendAccountCreatedEmail(
        string $email,
        string $fullName,
        string $username
    ): bool {

        $subject = 'Your Blue Eco Farm Account Created';

        $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 500px; margin: 0 auto; padding: 20px; }
                    .header { color: #2e7d32; margin-bottom: 20px; }
                    .footer { color: #888; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class=\"container\">
                    <h1 class=\"header\">🌿 Blue Eco Farm</h1>
                    <p>Hi " . htmlspecialchars($fullName) . ",</p>
                    <p>Your account has been created successfully!</p>
                    <p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                    <p>A verification email has been sent to you. Please check your inbox and verify your email to access your account.</p>
                    <p>Questions? Contact your system administrator.</p>
                    <div class=\"footer\">
                        <p>© 2026 Blue Eco Farm. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        return self::sendViaGmailSmtp($email, $fullName, $subject, $body);
    }
}
