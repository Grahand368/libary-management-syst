-- =====================================================================
-- Aurora Library — Migration 029: Defensive Unique Indexes
-- ---------------------------------------------------------------------
-- WHAT IT DOES
--   Adds UNIQUE indexes on the email columns of users / admins and on
--   books.book_no.  These are the last line of defence against the
--   duplicate-record bugs that an app-level pre-check could miss.
--
--   Re-running this file is SAFE: every ALTER is guarded with a
--   pre-check that skips the operation when the index already exists.
--
-- HOW TO RUN IN PHPMYADMIN
--   1. Open phpMyAdmin and select the `lms` database.
--   2. Click the "Import" tab.
--   3. Choose this file (029_defensive_indexes.sql).
--   4. Click "Go".
--
-- HOW TO VERIFY IT WORKED
--   Run these three queries in the SQL tab. Each should return the
--   name of the index, not an error:
--
--     SHOW INDEX FROM users  WHERE Key_name = 'uniq_email';
--     SHOW INDEX FROM admins WHERE Key_name = 'uniq_email';
--     SHOW INDEX FROM books  WHERE Key_name = 'uniq_book_no';
-- =====================================================================

-- ----- users.email ---------------------------------------------------
SET @ix := (
    SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name   = 'users'
       AND index_name   = 'uniq_email'
);
SET @sql := IF(@ix = 0,
    'ALTER TABLE users ADD UNIQUE KEY uniq_email (email)',
    'SELECT "users.uniq_email already exists" AS note');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- admins.email --------------------------------------------------
SET @ix := (
    SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name   = 'admins'
       AND index_name   = 'uniq_email'
);
SET @sql := IF(@ix = 0,
    'ALTER TABLE admins ADD UNIQUE KEY uniq_email (email)',
    'SELECT "admins.uniq_email already exists" AS note');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- books.book_no -------------------------------------------------
SET @ix := (
    SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name   = 'books'
       AND index_name   = 'uniq_book_no'
);
SET @sql := IF(@ix = 0,
    'ALTER TABLE books ADD UNIQUE KEY uniq_book_no (book_no)',
    'SELECT "books.uniq_book_no already exists" AS note');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
