-- ============================================================================
-- 002_fine_system.sql
-- ----------------------------------------------------------------------------
-- Adds the fine-management columns to issued_books.
-- Idempotent: each ALTER is wrapped so it skips if the column already exists.
--
--   mysql -u root lms < database/002_fine_system.sql
-- ============================================================================

-- issued_books.due_date
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND column_name  = 'due_date');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD COLUMN due_date DATE DEFAULT NULL',
  'SELECT "due_date already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- issued_books.return_date
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND column_name  = 'return_date');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD COLUMN return_date DATE DEFAULT NULL',
  'SELECT "return_date already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- issued_books.fine_amount (was previously "fine")
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND column_name  = 'fine_amount');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD COLUMN fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0',
  'SELECT "fine_amount already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- issued_books.fine_status
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND column_name  = 'fine_status');
SET @sql := IF(@c = 0,
  "ALTER TABLE issued_books ADD COLUMN fine_status VARCHAR(20) NOT NULL DEFAULT 'No Fine'",
  'SELECT "fine_status already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migrate legacy "fine" column into fine_amount (if it still exists).
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND column_name  = 'fine');
SET @sql := IF(@c = 1,
  'UPDATE issued_books SET fine_amount = COALESCE(fine_amount,0) + COALESCE(fine,0)
     WHERE fine_amount = 0 OR fine_amount IS NULL',
  'SELECT "no legacy fine column"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@c = 1,
  'ALTER TABLE issued_books DROP COLUMN fine',
  'SELECT "fine column already dropped"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill due_date for rows that have an issue_date but no due_date
-- (14-day loan period, must match AURORA_LOAN_DAYS in aurora_auth.php).
UPDATE issued_books
   SET due_date = DATE_ADD(issue_date, INTERVAL 14 DAY)
 WHERE due_date IS NULL
   AND issue_date IS NOT NULL;

-- Normalise blank fine_status.
UPDATE issued_books
   SET fine_status = 'No Fine'
 WHERE fine_status IS NULL OR fine_status = '';

-- Helpful index for the admin filter and the user my_fines query.
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'issued_books'
               AND index_name   = 'idx_issued_fine_status');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_fine_status (fine_status)',
  'SELECT "idx_issued_fine_status exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'issued_books'
               AND index_name   = 'idx_issued_status_student');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_status_student (status, student_id)',
  'SELECT "idx_issued_status_student exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'issued_books'
               AND index_name   = 'idx_issued_book_no');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_book_no (book_no)',
  'SELECT "idx_issued_book_no exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;