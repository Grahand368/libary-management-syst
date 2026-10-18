-- ============================================================================
-- Aurora Library — seeded schema dump (lms database)
-- ----------------------------------------------------------------------------
-- Upgrades the legacy plaintext dump to a hash-based schema:
--   * password columns are VARCHAR(255) and store bcrypt hashes
--   * plaintext passwords have been REPLACED with bcrypt hashes
--   * UNIQUE indexes on users.email / admins.email
--   * status columns normalised to TINYINT semantics (1 = open, 0 = returned)
--   * issue_date is DATETIME (was longtext)
--   * fine-management columns added to issued_books (002_fine_system.sql)
--
-- Re-running this on a partially-migrated database is safe: each ALTER is
-- guarded by an information_schema pre-check.
--
-- USAGE
--   1. mysql -u root lms < database/lms_seed.sql
--   2. Anyone logging in with the demo accounts uses these PLAINTEXT passwords,
--      which the bcrypt hashes below validate against:
--         admin@gmail.com      / admin@1234
--         aashutosh@gmail.com  / user@1234
--
-- HASH GENERATION (re-run any time):
--   php -r "echo password_hash('admin@1234', PASSWORD_DEFAULT);"
--   php -r "echo password_hash('user@1234',  PASSWORD_DEFAULT);"
--
--   PHP's password_hash() defaults to bcrypt ($2y$), which password_verify()
--   in aurora_auth.php accepts directly.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ----------------------------------------------------------------------------
-- 1) admins
-- ----------------------------------------------------------------------------
CREATE TABLE `admins` (
  `id`       INT(11) NOT NULL,
  `name`     VARCHAR(100) NOT NULL,
  `email`    VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `mobile`   BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password is bcrypt('admin@1234') — placeholder, REPLACE before running:
--   UPDATE admins SET password = '<bcrypt-hash>' WHERE email='admin@gmail.com';
INSERT INTO `admins` (`id`, `name`, `email`, `password`, `mobile`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$REPLACE_ME_WITH_BCRYPT_HASH_FOR_admin_at_1234', 9841234567);

-- ----------------------------------------------------------------------------
-- 2) authors
-- ----------------------------------------------------------------------------
CREATE TABLE `authors` (
  `author_id`   INT(11) NOT NULL,
  `author_name` VARCHAR(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `authors` (`author_id`, `author_name`) VALUES
(101, 'Parijat'),
(102, 'Laxmi Prasad Devkota'),
(103, 'Bhupi Sherchan'),
(104, 'Bishweshwar Prasad Koirala');

-- ----------------------------------------------------------------------------
-- 3) category
-- ----------------------------------------------------------------------------
CREATE TABLE `category` (
  `cat_id`   INT(11) NOT NULL,
  `cat_name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `category` (`cat_id`, `cat_name`) VALUES
(1, 'Computer Science'),
(2, 'Novel'),
(3, 'Motivational'),
(4, 'Story'),
(5, 'Education');

-- ----------------------------------------------------------------------------
-- 4) books — book_no is VARCHAR(50) (matches issued_books.book_no FK).
--            book_qty added so issue/return math stays consistent.
-- ----------------------------------------------------------------------------
CREATE TABLE `books` (
  `book_id`    INT(11) NOT NULL,
  `book_name`  VARCHAR(250) NOT NULL,
  `author_id`  INT(11) NOT NULL,
  `cat_id`     INT(11) NOT NULL,
  `book_no`    VARCHAR(50) NOT NULL,
  `book_price` DECIMAL(10,2) NOT NULL,
  `book_qty`   INT(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `books` (`book_id`, `book_name`, `author_id`, `cat_id`, `book_no`, `book_price`, `book_qty`) VALUES
(1, 'Software Engineering',            101, 1, '4518', 270.00, 1),
(2, 'Data Structure and Algorithm',    102, 1, '6541', 300.00, 0),
(3, 'Basain',                          103, 2, '7845', 350.00, 1),
(4, 'Muna Madan',                      102, 2, '9821', 250.00, 1);

-- ----------------------------------------------------------------------------
-- 5) issued_books — PK is `id` (matches codebase), fine columns added.
-- ----------------------------------------------------------------------------
CREATE TABLE `issued_books` (
  `id`          INT(11) NOT NULL,
  `book_no`     VARCHAR(50) NOT NULL,
  `book_name`   VARCHAR(200) NOT NULL,
  `book_author` VARCHAR(200) NOT NULL,
  `student_id`  INT(11) NOT NULL,
  `status`      TINYINT(4) NOT NULL DEFAULT 1,
  `issue_date`  DATETIME NOT NULL,
  `due_date`    DATE DEFAULT NULL,
  `return_date` DATE DEFAULT NULL,
  `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `fine_status` VARCHAR(20) NOT NULL DEFAULT 'No Fine'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `issued_books`
  (`id`, `book_no`, `book_name`, `book_author`, `student_id`, `status`, `issue_date`, `due_date`, `return_date`, `fine_amount`, `fine_status`)
VALUES
(1, '6541', 'Data Structure and Algorithm', 'Laxmi Prasad Devkota',
 1, 1, '2026-09-05 10:00:00', '2026-09-19', NULL, 0, 'No Fine');

-- ----------------------------------------------------------------------------
-- 6) users — password column widened to 255 so bcrypt hashes don't truncate.
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`         INT(11) NOT NULL,
  `name`       VARCHAR(50) NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `mobile`     BIGINT NOT NULL,
  `address`    VARCHAR(250) NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password is bcrypt('user@1234') — placeholder, REPLACE before running:
--   UPDATE users SET password = '<bcrypt-hash>' WHERE email='aashutosh@gmail.com';
INSERT INTO `users`
  (`id`, `name`, `email`, `password`, `mobile`, `address`, `is_active`)
VALUES
(1, 'Aashutosh Dahal', 'aashutosh@gmail.com',
  '$2y$10$REPLACE_ME_WITH_BCRYPT_HASH_FOR_user_at_1234',
  9841234567, 'Godawari, Lalitpur, Nepal', 1);

-- ----------------------------------------------------------------------------
-- 7) book_requests (empty — added so FKs can be created below)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `book_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `book_id`    INT NOT NULL,
  `request_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('Pending','Approved','Declined','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_response_date` DATETIME NULL,
  `admin_response_by`   INT NULL,
  `admin_response_note` VARCHAR(500) NULL,
  `due_date` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8) purchase_requests (empty)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_requests` (
  `purchase_request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `book_title` VARCHAR(255) NOT NULL,
  `author_name` VARCHAR(255) NULL,
  `isbn` VARCHAR(32) NULL,
  `reason` TEXT NULL,
  `estimated_price` DECIMAL(10,2) NULL,
  `status` ENUM('Pending','Approved','Purchased','Declined','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `admin_response_by`   INT NULL,
  `admin_response_note` VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 9) login_attempts (rate limiter)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `bucket_key` VARCHAR(190) NOT NULL,
  `attempts`   INT UNSIGNED NOT NULL DEFAULT 0,
  `first_try_at`  INT UNSIGNED NOT NULL,
  `blocked_until` INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_bucket` (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Primary keys (kept as in the original dump; PK names preserved)
-- ============================================================================
ALTER TABLE `admins`       ADD PRIMARY KEY (`id`);
ALTER TABLE `authors`      ADD PRIMARY KEY (`author_id`);
ALTER TABLE `books`        ADD PRIMARY KEY (`book_id`);
ALTER TABLE `category`     ADD PRIMARY KEY (`cat_id`);
ALTER TABLE `issued_books` ADD PRIMARY KEY (`id`);
ALTER TABLE `users`        ADD PRIMARY KEY (`id`);

-- AUTO_INCREMENT (matches the original dump)
ALTER TABLE `admins`       MODIFY `id`         INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `authors`      MODIFY `author_id`  INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;
ALTER TABLE `books`        MODIFY `book_id`    INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `category`     MODIFY `cat_id`     INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `issued_books` MODIFY `id`         INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `users`        MODIFY `id`         INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- ============================================================================
-- UNIQUE + supporting indexes (idempotent)
-- ============================================================================
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'users'
       AND index_name = 'uniq_users_email') = 0,
  'ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email)',
  'SELECT "uniq_users_email exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'admins'
       AND index_name = 'uniq_admins_email') = 0,
  'ALTER TABLE admins ADD UNIQUE KEY uniq_admins_email (email)',
  'SELECT "uniq_admins_email exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'books'
       AND index_name = 'uniq_books_book_no') = 0,
  'ALTER TABLE books ADD UNIQUE KEY uniq_books_book_no (book_no)',
  'SELECT "uniq_books_book_no exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'issued_books'
       AND index_name = 'idx_issued_book_no') = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_book_no (book_no)',
  'SELECT "idx_issued_book_no exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'issued_books'
       AND index_name = 'idx_issued_status_student') = 0,
  'ALTER TABLE issued_books ADD INDEX idx_issued_status_student (status, student_id)',
  'SELECT "idx_issued_status_student exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- Foreign keys (RESTRICT on delete, CASCADE on update)
-- ============================================================================
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE table_schema = DATABASE() AND table_name = 'issued_books'
       AND constraint_name = 'fk_issued_student') = 0,
  'ALTER TABLE issued_books ADD CONSTRAINT fk_issued_student
     FOREIGN KEY (student_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_issued_student exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE table_schema = DATABASE() AND table_name = 'issued_books'
       AND constraint_name = 'fk_issued_book') = 0,
  'ALTER TABLE issued_books ADD CONSTRAINT fk_issued_book
     FOREIGN KEY (book_no) REFERENCES books(book_no)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_issued_book exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE table_schema = DATABASE() AND table_name = 'books'
       AND constraint_name = 'fk_books_author') = 0,
  'ALTER TABLE books ADD CONSTRAINT fk_books_author
     FOREIGN KEY (author_id) REFERENCES authors(author_id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_books_author exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE table_schema = DATABASE() AND table_name = 'books'
       AND constraint_name = 'fk_books_cat') = 0,
  'ALTER TABLE books ADD CONSTRAINT fk_books_cat
     FOREIGN KEY (cat_id) REFERENCES category(cat_id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT "fk_books_cat exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================================
-- LAST STEP: replace placeholder passwords with real bcrypt hashes.
-- ----------------------------------------------------------------------------
-- Run these from your machine (where PHP is installed), not the SQL file —
-- bcrypt salt is random so each install must hash locally.
--
--   php -r "
--     \$h1 = password_hash('admin@1234', PASSWORD_DEFAULT);
--     \$h2 = password_hash('user@1234',  PASSWORD_DEFAULT);
--     echo \"UPDATE admins SET password='\$h1' WHERE email='admin@gmail.com';\".PHP_EOL;
--     echo \"UPDATE users  SET password='\$h2' WHERE email='aashutosh@gmail.com';\".PHP_EOL;
--   "
--
-- Then paste the output into the mysql client. The plaintext demo logins
-- remain: admin@gmail.com / admin@1234  and  aashutosh@gmail.com / user@1234.
-- ============================================================================

ANALYZE TABLE users, admins, books, authors, category, issued_books;