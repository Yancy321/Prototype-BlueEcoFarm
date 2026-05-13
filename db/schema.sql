-- Blue Eco Farm Inventory System Schema

CREATE TABLE IF NOT EXISTS products (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(50) NOT NULL UNIQUE,
    pack_size ENUM('big','small') NOT NULL,
    form      ENUM('granules','tablet','powder') NOT NULL
);

-- Batch tracking table
CREATE TABLE IF NOT EXISTS batches (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    batch_number      VARCHAR(50) NOT NULL UNIQUE,
    product_id        INT NOT NULL,
    manufactured_date DATE NULL,
    notes             TEXT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS stock_records (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    product_id       INT NOT NULL,
    warehouse_id     TINYINT NOT NULL,
    record_type      ENUM('incoming','outgoing') NOT NULL,
    quantity         INT NOT NULL,
    transaction_date DATE NOT NULL,
    notes            TEXT,
    batch_number     VARCHAR(50) NULL,
    batch_id         INT NULL,
    is_deleted       TINYINT(1) DEFAULT 0,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES batches(id)
);

CREATE TABLE IF NOT EXISTS transfers (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    product_id               INT NOT NULL,
    quantity                 INT NOT NULL,
    transfer_date            DATE NOT NULL,
    source_warehouse_id      TINYINT NOT NULL DEFAULT 1,
    destination_warehouse_id TINYINT NOT NULL DEFAULT 2,
    created_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS transaction_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    operation   ENUM('create','update','delete') NOT NULL,
    table_name  VARCHAR(50) NOT NULL,
    record_id   INT NOT NULL,
    snapshot    JSON,
    changed_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- User accounts for login
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,  -- bcrypt hash
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    role       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_verified TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account (password: Admin@2024 — change after first login)
INSERT IGNORE INTO users (username, password, full_name, email, role, is_verified) VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator',
    'admin@blueecoform.local',
    'admin',
    1
);

-- Email verification tokens
CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    code       VARCHAR(10) NOT NULL,
    token      VARCHAR(64),
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_pending (user_id, code)
);

-- Forecast generation log
CREATE TABLE IF NOT EXISTS forecast_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    periods         INT NOT NULL,
    predicted_qty   DECIMAL(10,2) NOT NULL,
    data_points     INT NOT NULL,
    forecast_date   DATE NOT NULL,
    generated_by    INT NULL,
    generated_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)   REFERENCES products(id),
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Report generation log
CREATE TABLE IF NOT EXISTS report_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    generated_by INT NULL,
    date_from    DATE NOT NULL,
    date_to      DATE NOT NULL,
    warehouse_id TINYINT NULL,
    product_id   INT NULL,
    filename     VARCHAR(150) NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id)   REFERENCES products(id) ON DELETE SET NULL
);

-- SMS alert rules: one row per threshold configuration
CREATE TABLE IF NOT EXISTS alert_rules (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    product_id       INT NOT NULL,
    warehouse_id     TINYINT NOT NULL,
    threshold        INT NOT NULL,            -- alert when stock <= threshold
    recipients       TEXT NOT NULL,           -- JSON array of E.164 phone numbers
    cooldown_minutes INT NOT NULL DEFAULT 60,
    is_active        TINYINT(1) DEFAULT 1,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Log of every SMS dispatch attempt
CREATE TABLE IF NOT EXISTS sms_alert_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    alert_rule_id INT NOT NULL,
    recipient     VARCHAR(20) NOT NULL,    -- E.164 phone number
    message       TEXT NOT NULL,
    status        ENUM('success','failure') NOT NULL,
    error_detail  TEXT,
    dispatched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_rule_id) REFERENCES alert_rules(id)
);
