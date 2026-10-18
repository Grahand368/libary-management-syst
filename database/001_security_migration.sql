-- ============================================================================
-- 001_security_migration.sql
-- ----------------------------------------------------------------------------
-- Adds the login_attempts table used for by the rate limiter.
-- Idempotent: safe to re-run.
--
--   mysql -u root lms < database/001_security_migration.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    bucket_key    VARCHAR(190) NOT NULL,
    attempts      INT UNSIGNED NOT NULL DEFAULT 0,
    first_try_at  INT UNSIGNED NOT NULL,
    blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_bucket (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;