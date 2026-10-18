-- ============================================================================
-- Aurora Library — full schema dump (lms database)
-- ----------------------------------------------------------------------------
-- Source-of-truth tables: create_admin.php + database/00x migrations.
-- This file creates every table with the SAME shape the application uses,
-- then adds the UNIQUE / FK / supporting indexes that the migrations add
-- incrementally in 001..005 + 029.
--
-- Re-runnable: every statement uses CREATE TABLE IF NOT EXISTS /
-- ADD … guarded by information_schema pre-checks.
--
--   mysql -u root lms < database/lms_schema.sql
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ----------------------------------------------------------------------------
-- 1) admins — staff / librarian accounts
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    email    VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    mobile   BIGINT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2) users — library members
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL,
    password   VARCHAR(255) NOT NULL,
    mobile     BIGINT,
    address    VARCHAR(255),
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3) authors
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS authors (
    author_id   INT AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4) category
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS category (
    cat_id   INT AUTO_INCREMENT PRIMARY KEY,
    cat_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5) books — catalog (author + category references added below as FKs)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    book_id          INT AUTO_INCREMENT PRIMARY KEY,
    book_name        VARCHAR(255) NOT NULL,
    author_id        INT,
    cat_id           INT,
    book_no          VARCHAR(50),
    book_price       DECIMAL(10,2),
    book_qty         INT NOT NULL DEFAULT 1,
    book_image       VARCHAR(500) DEFAULT NULL,
    book_description TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6) issued_books — circulation
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS issued_books (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    book_no     VARCHAR(50),
    book_name   VARCHAR(255),
    book_author VARCHAR(255),
    student_id  INT,
    status      TINYINT DEFAULT 1,
    issue_date  DATE,
    due_date    DATE DEFAULT NULL,
    return_date DATE DEFAULT NULL,
    fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    fine_status VARCHAR(20)   NOT NULL DEFAULT 'No Fine'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7) book_requests — member → admin borrow-request workflow
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS book_requests (
    request_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    book_id             INT NOT NULL,
    request_date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status              ENUM('Pending','Approved','Declined','Cancelled')
                        NOT NULL DEFAULT 'Pending',
    admin_response_date DATETIME NULL,
    admin_response_by   INT NULL,
    admin_response_note VARCHAR(500) NULL,
    due_date            DATE NULL,
    KEY idx_status_date (status, request_date),
    KEY idx_user        (user_id),
    KEY idx_book        (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8) purchase_requests — member "suggest a title for the library" workflow
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_requests (
    purchase_request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    book_title          VARCHAR(255) NOT NULL,
    author_name         VARCHAR(255) NULL,
    isbn                VARCHAR(32) NULL,
    reason              TEXT NULL,
    estimated_price     DECIMAL(10,2) NULL,
    status              ENUM('Pending','Approved','Purchased','Declined','Rejected')
                        NOT NULL DEFAULT 'Pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    admin_response_by   INT NULL,
    admin_response_note VARCHAR(500) NULL,
    KEY idx_user        (user_id),
    KEY idx_status_date (status, created_at),
    KEY idx_title       (book_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 9) login_attempts — used by the rate limiter in aurora_auth.php
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    bucket_key    VARCHAR(190) NOT NULL,
    attempts      INT UNSIGNED NOT NULL DEFAULT 0,
    first_try_at  INT UNSIGNED NOT NULL,
    blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_bucket (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- UNIQUE indexes (from 005 + 029). Each ALTER is guarded so the script is
-- safe to re-run on a partially-migrated database.
-- ============================================================================

-- users.email
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'users'
              AND index_name   = 'uniq_users_email');
SET @sql := IF(@c = 0,
  'ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email)',
  'SELECT "uniq_users_email exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- admins.email
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'admins'
              AND index_name   = 'uniq_admins_email');
SET @sql := IF(@c = 0,
  'ALTER TABLE admins ADD UNIQUE KEY uniq_admins_email (email)',
  'SELECT "uniq_admins_email exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- books.book_no
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'books'
              AND index_name   = 'uniq_books_book_no');
SET @sql := IF(@c = 0,
  'ALTER TABLE books ADD UNIQUE KEY uniq_books_book_no (book_no)',
  'SELECT "uniq_books_book_no exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- authors — case-insensitive uniqueness via a generated column
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'authors'
              AND column_name  = 'author_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE authors ADD COLUMN author_name_lc VARCHAR(255)
       GENERATED ALWAYS AS (LOWER(author_name)) STORED',
  'SELECT "author_name_lc exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'authors'
              AND index_name   = 'uniq_authors_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE authors ADD UNIQUE KEY uniq_authors_name_lc (author_name_lc)',
  'SELECT "uniq_authors_name_lc exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- category — case-insensitive uniqueness via a generated column
SET @c := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'category'
              AND column_name  = 'cat_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE category ADD COLUMN cat_name_lc VARCHAR(255)
       GENERATED ALWAYS AS (LOWER(cat_name)) STORED',
  'SELECT "cat_name_lc exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'category'
              AND index_name   = 'uniq_category_name_lc');
SET @sql := IF(@c = 0,
  'ALTER TABLE category ADD UNIQUE KEY uniq_category_name_lc (cat_name_lc)',
  'SELECT "uniq_category_name_lc exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- Supporting indexes (from 002 + 005)
-- ============================================================================

-- users: sign-in filter
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'users'
              AND index_name   = 'idx_users_active');
SET @sql := IF(@c = 0,
  'ALTER TABLE users ADD INDEX idx_users_active (is_active, deleted_at)',
  'SELECT "idx_users_active exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- books: lookup & qty
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'books'
              AND index_name   = 'idx_books_author_cat');
SET @sql := IF(@c = 0,
  'ALTER TABLE books ADD INDEX idx_books_author_cat (author_id, cat_id)',
  'SELECT "idx_books_author_cat exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'books'
              AND index_name   = 'idx_books_qty');
SET @sql := IF(@c = 0,
  'ALTER TABLE books ADD INDEX idx_books_qty (book_qty)',
  'SELECT "idx_books_qty exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- issued_books: filter + lookups
SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND index_name   = 'idx_issued_fine_status');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_fine_status (fine_status)',
  'SELECT "idx_issued_fine_status exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND index_name   = 'idx_issued_status_student');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_status_student (status, student_id)',
  'SELECT "idx_issued_status_student exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'issued_books'
              AND index_name   = 'idx_issued_book_no');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_book_no (book_no)',
  'SELECT "idx_issued_book_no exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- Foreign keys (RESTRICT on delete, CASCADE on update)
-- ============================================================================

-- issued_books.student_id -> users.id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'issued_books'
              AND constraint_name = 'fk_issued_student');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books
     ADD CONSTRAINT fk_issued_student
         FOREIGN KEY (student_id) REFERENCES users(id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_issued_student exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- issued_books.book_no -> books.book_no
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'issued_books'
              AND constraint_name = 'fk_issued_book');
SET @sql := IF(@c = 0,
  'ALTER TABLE issued_books
     ADD CONSTRAINT fk_issued_book
         FOREIGN KEY (book_no) REFERENCES books(book_no)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_issued_book exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- book_requests.user_id -> users.id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'book_requests'
              AND constraint_name = 'fk_bkreq_user');
SET @sql := IF(@c = 0,
  'ALTER TABLE book_requests
     ADD CONSTRAINT fk_bkreq_user
         FOREIGN KEY (user_id) REFERENCES users(id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_bkreq_user exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- book_requests.book_id -> books.book_id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'book_requests'
              AND constraint_name = 'fk_bkreq_book');
SET @sql := IF(@c = 0,
  'ALTER TABLE book_requests
     ADD CONSTRAINT fk_bkreq_book
         FOREIGN KEY (book_id) REFERENCES books(book_id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_bkreq_book exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- purchase_requests.user_id -> users.id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'purchase_requests'
              AND constraint_name = 'fk_purreq_user');
SET @sql := IF(@c = 0,
  'ALTER TABLE purchase_requests
     ADD CONSTRAINT fk_purreq_user
         FOREIGN KEY (user_id) REFERENCES users(id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_purreq_user exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- books.author_id -> authors.author_id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'books'
              AND constraint_name = 'fk_books_author');
SET @sql := IF(@c = 0,
  'ALTER TABLE books
     ADD CONSTRAINT fk_books_author
         FOREIGN KEY (author_id) REFERENCES authors(author_id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_books_author exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- books.cat_id -> category.cat_id
SET @c := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema    = DATABASE()
              AND table_name     = 'books'
              AND constraint_name = 'fk_books_cat');
SET @sql := IF(@c = 0,
  'ALTER TABLE books
     ADD CONSTRAINT fk_books_cat
         FOREIGN KEY (cat_id) REFERENCES category(cat_id)
         ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_books_cat exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- Refresh optimiser stats after all the schema work.
-- ============================================================================
ANALYZE TABLE users, admins, books, authors, category,
              issued_books, book_requests, purchase_requests, login_attempts;