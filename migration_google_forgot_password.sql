USE university_portal;

-- Allow NULL passwords (for Google-only accounts)
ALTER TABLE users MODIFY password VARCHAR(255) NULL;

-- Add google_id column only if it doesn't already exist (safe to re-run)
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'university_portal'
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'google_id'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL UNIQUE AFTER phone',
    'SELECT "google_id column already exists, skipping" AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Password reset tokens table
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
