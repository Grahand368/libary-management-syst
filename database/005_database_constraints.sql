-- ============================================================================
-- 005_database_constraints.sql
-- ----------------------------------------------------------------------------
-- Final consolidation: UNIQUE indexes + foreign keys + supporting indexes.
--
-- PRE-REQUISITE: run 000_preflight_duplicates.sql first and address any
-- duplicates/orphans BEFORE running this file. If a UNIQUE or FK can't be
--   added because of bad data, MySQL will reject the ALTER and you'll need
--   to investigate the rows it points at.
--
--   mysql -u root lms < database/000_preflight_duplicates.sql     -- inspect
--   -- ... clean up as needed ...
--   mysql -u root lms < database/005_database_constraints.sql
--
-- Idempotent where possible. Each ALTER is guarded so re-running on a
-- fully-migrated DB is a no-op.
-- ========================================================================== */

-- -----------------------------------------------------------------------
-- 1. UNIQUE: users.email
-- -----------------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'users'
               AND index_name   = 'uniq_users_email');
SET @sql := IF(@c = 0,
  'ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email)',
  'SELECT "uniq_users_email exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Helpful secondary index for case-insensitive lookups during sign-in.
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'users'
               AND index_name   = 'idx_users_active');
SET @sql := IF(@c = 0,
  'ALTER TABLE users ADD INDEX idx_users_active (is_active, deleted_at)',
  'SELECT "idx_users_active exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 2. UNIQUE: admins.email
-- -----------------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'admins'
               AND index_name   = 'uniq_admins_email');
SET @sql := IF(@c = 0,
  'ALTER TABLE admins ADD UNIQUE KEY uniq_admins_email (email)',
  'SELECT "uniq_admins_email exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 3. UNIQUE: books.book_no  (after duplicates are cleaned)
-- -----------------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'books'
               AND index_name   = 'uniq_books_book_no');
SET @sql := IF(@c = 0,
  'ALTER TABLE books ADD UNIQUE KEY uniq_books_book_no (book_no)',
  'SELECT "uniq_books_book_no exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'books'
               AND index_name   = 'idx_books_author_cat');
SET @sql := IF(@c = 0,
  'ALTER TABLE books ADD INDEX idx_books_author_cat (author_id, cat_id)',
  'SELECT "idx_books_author_cat exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'books'
               AND index_name   = 'idx_books_qty');
SET @sql := IF(@c = 0,
  'ALTER TABLE books ADD INDEX idx_books_qty (book_qty)',
  'SELECT "idx_books_qty exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 4. UNIQUE: authors.author_name (case-insensitive collation)
-- -----------------------------------------------------------------------
-- authors.author_name: enforce case-insensitive uniqueness via a
-- generated column. We add a generated stored column author_name_lc
-- and put the UNIQUE on it. Older duplicate rows must already be cleaned.

SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'authors'
              AND column_name  = 'author_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE authors ADD COLUMN author_name_lc VARCHAR(255) GENERATED ALWAYS AS (LOWER(author_name)) STORED',
  'SELECT "author_name_lc exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'authors'
               AND index_name   = 'uniq_authors_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE authors ADD UNIQUE KEY uniq_authors_name_lc (author_name_lc)',
  'SELECT "uniq_authors_name_lc exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 5. UNIQUE: category.cat_name (case-insensitive)
-- -----------------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'category'
              AND column_name  = 'cat_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE category ADD COLUMN cat_name_lc VARCHAR(255) GENERATED ALWAYS AS (LOWER(cat_name)) STORED',
  'SELECT "cat_name_lc exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'category'
               AND index_name   = 'uniq_category_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE category ADD UNIQUE KEY uniq_category_name_lc (cat_name_lc)',
  'SELECT "uniq_category_name_lc exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 6. FOREIGN KEYS  (all use RESTRICT — no cascading deletes)
-- -----------------------------------------------------------------------

-- issued_books.student_id -> users.id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'issued_books'
               AND constraint_name = 'fk_issued_student');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books
     ADD CONSTRAINT fk_issued_student
         FOREIGN KEY (student_id) REFERENCES users(id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_issued_student exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- issued_books.book_no -> books.book_no (so stock math stays consistent)
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'issued_books'
               AND constraint_name = 'fk_issued_book');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books
     ADD CONSTRAINT fk_issued_book
         FOREIGN KEY (book_no) REFERENCES books(book_no)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_issued_book exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- book_requests.user_id -> users.id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'book_requests'
               AND constraint_name = 'fk_bkreq_user');
SET @sql := IF(@c = 0,
  'ALTER TABLE book_requests
     ADD CONSTRAINT fk_bkreq_user
         FOREIGN KEY (user_id) REFERENCES users(id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_bkreq_user exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- book_requests.book_id -> books.book_id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'book_requests'
               AND constraint_name = 'fk_bkreq_book');
SET @sql := IF(@c = 0,
  'ALTER TABLE book_requests
     ADD CONSTRAINT fk_bkreq_book
         FOREIGN KEY (book_id) REFERENCES books(book_id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_bkreq_book exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- purchase_requests.user_id -> users.id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'purchase_requests'
               AND constraint_name = 'fk_purreq_user');
SET @sql := IF(@c = 0,
  'ALTER TABLE purchase_requests
     ADD CONSTRAINT fk_purreq_user
         FOREIGN KEY (user_id) REFERENCES users(id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_purreq_user exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- books.author_id -> authors.author_id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'books'
               AND constraint_name = 'fk_books_author');
SET @sql := IF(@c = 0,
  'ALTER TABLE books
     ADD CONSTRAINT fk_books_author
         FOREIGN KEY (author_id) REFERENCES authors(author_id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_books_author exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- books.cat_id -> category.cat_id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema   = DATABASE()
               AND table_name    = 'books'
               AND constraint_name = 'fk_books_cat');
SET @sql := IF(@c = 0,
  'ALTER TABLE books
     ADD CONSTRAINT fk_books_cat
         FOREIGN KEY (cat_id) REFERENCES category(cat_id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_books_cat exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 7. Final ANALYZE — refresh the optimiser's stats after all the work.
-- -----------------------------------------------------------------------
ANALYZE TABLE users, admins, books, authors, category,
              issued_books, book_requests, purchase_requests, login_attempts;