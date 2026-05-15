-- ============================================================
-- Migration: SMS distributor support
-- Run this once in phpMyAdmin on the blue_eco_farm database.
-- ============================================================

-- 1. Create distributors table if it doesn't exist yet
CREATE TABLE IF NOT EXISTS distributors (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    business_name  VARCHAR(150) NOT NULL,
    tier           ENUM('Silver','Gold','Platinum') NOT NULL DEFAULT 'Silver',
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    region         VARCHAR(100) NULL,
    contact_number VARCHAR(20)  NULL,
    phone          VARCHAR(20)  NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 0,
    notes          TEXT         NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 2. Add missing columns to distributors if the table already exists
ALTER TABLE distributors
    ADD COLUMN IF NOT EXISTS phone      VARCHAR(20) NULL AFTER contact_number,
    ADD COLUMN IF NOT EXISTS is_active  TINYINT(1)  NOT NULL DEFAULT 0 AFTER phone,
    ADD COLUMN IF NOT EXISTS notes      TEXT        NULL AFTER is_active;

-- 3. Copy contact_number into phone for existing rows that have no phone yet
UPDATE distributors
SET phone = contact_number
WHERE phone IS NULL AND contact_number IS NOT NULL;

-- 4. Auto-approve distributors whose status is 'approved' (set is_active = 1)
UPDATE distributors SET is_active = 1 WHERE status = 'approved';

-- 5. Create distributor_sms_log table
CREATE TABLE IF NOT EXISTS distributor_sms_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    distributor_id INT NOT NULL,
    recipient      VARCHAR(20)  NOT NULL,
    message        TEXT         NOT NULL,
    status         ENUM('success','failure') NOT NULL,
    error_detail   TEXT         NULL,
    dispatched_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE
);
