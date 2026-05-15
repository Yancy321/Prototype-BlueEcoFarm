-- ============================================================
-- Migration: Email support for distributors + password reset
-- Run once in phpMyAdmin on the blue_eco_farm database.
-- ============================================================

-- 1. Add email column to users (nullable for existing staff/admin accounts)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email VARCHAR(180) NULL AFTER full_name;

-- 2. Add unique index on email (only for non-null values)
ALTER TABLE users
    ADD UNIQUE INDEX IF NOT EXISTS idx_users_email (email);

-- 3. Password reset tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token      VARCHAR(6)   NOT NULL,   -- 6-digit OTP
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
