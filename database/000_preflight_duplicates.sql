-- ============================================================================
-- 000_preflight_duplicates.sql
-- ----------------------------------------------------------------------------
-- Diagnostic ONLY. Does not modify the database.
-- Run this BEFORE applying UNIQUE constraints in 005 to see if duplicates
-- exist that need to be cleaned up.
--
--   mysql -u root lms < database/000_preflight_duplicates.sql
--
-- Each SELECT in this file returns one row per suspected duplicate group.
-- Inspect the output; if any rows appear, follow the cleanup recipe at
-- the bottom of this file or in 005_database_constraints.sql.
-- ========================================================================== */

SELECT 'users.email duplicates' AS check_name, email, COUNT(*) AS n
  FROM users
 GROUP BY email
HAVING COUNT(*) > 1;

SELECT 'admins.email duplicates' AS check_name, email, COUNT(*) AS n
  FROM admins
 GROUP BY email
HAVING COUNT(*) > 1;

SELECT 'books.book_no duplicates' AS check_name, book_no, COUNT(*) AS n
  FROM books
 GROUP BY book_no
HAVING COUNT(*) > 1;

SELECT 'authors.author_name duplicates (case-insensitive)' AS check_name,
       LOWER(author_name) AS author_name_lc, COUNT(*) AS n
  FROM authors
 GROUP BY LOWER(author_name)
HAVING COUNT(*) > 1;

SELECT 'category.cat_name duplicates (case-insensitive)' AS check_name,
       LOWER(cat_name) AS cat_name_lc, COUNT(*) AS n
  FROM category
 GROUP BY LOWER(cat_name)
HAVING COUNT(*) > 1;

-- Foreign-key readiness: every issued_books.student_id must resolve.
SELECT 'issued_books.student_id orphans' AS check_name, student_id, COUNT(*) AS n
  FROM issued_books
 WHERE student_id IS NOT NULL
   AND student_id NOT IN (SELECT id FROM users)
 GROUP BY student_id;

-- book_no must resolve to a book (because we key stock math on book_no).
SELECT 'issued_books.book_no orphans' AS check_name, book_no, COUNT(*) AS n
  FROM issued_books
 WHERE book_no IS NOT NULL
   AND book_no <> ''
   AND book_no NOT IN (SELECT book_no FROM books WHERE book_no IS NOT NULL)
 GROUP BY book_no;

SELECT 'book_requests.user_id orphans' AS check_name, user_id, COUNT(*) AS n
  FROM book_requests
 WHERE user_id NOT IN (SELECT id FROM users)
 GROUP BY user_id;

SELECT 'book_requests.book_id orphans' AS check_name, book_id, COUNT(*) AS n
  FROM book_requests
 WHERE book_id NOT IN (SELECT book_id FROM books)
 GROUP BY book_id;

SELECT 'purchase_requests.user_id orphans' AS check_name, user_id, COUNT(*) AS n
  FROM purchase_requests
 WHERE user_id NOT IN (SELECT id FROM users)
 GROUP BY user_id;

SELECT 'books.author_id orphans' AS check_name, author_id, COUNT(*) AS n
  FROM books
 WHERE author_id IS NOT NULL
   AND author_id NOT IN (SELECT author_id FROM authors)
 GROUP BY author_id;

SELECT 'books.cat_id orphans' AS check_name, cat_id, COUNT(*) AS n
  FROM books
 WHERE cat_id IS NOT NULL
   AND cat_id NOT IN (SELECT cat_id FROM category)
 GROUP BY cat_id;

-- ============================================================================
-- CLEANUP RECIPES (run ONLY if duplicates/orphans were reported above)
-- ----------------------------------------------------------------------------
-- These statements are commented out by default. Uncomment per scenario.
-- ========================================================================== */

-- 1) users.email duplicates: keep the OLDEST row (smallest id), rename
--    later dupes' email to "<original>__dup_<id>@invalid.local" so the
--    UNIQUE index can be applied without losing the row.
-- UPDATE users u
--   JOIN (
--       SELECT email, MIN(id) AS keep_id
--         FROM users GROUP BY email HAVING COUNT(*) > 1
--   ) k ON u.email = k.email AND u.id <> k.keep_id
--    SET u.email = CONCAT(SUBSTRING_INDEX(u.email,'@',1), '__dup_', u.id, '@invalid.local');

-- 2) admins.email duplicates: same approach.
-- UPDATE admins a
--   JOIN (
--       SELECT email, MIN(id) AS keep_id
--         FROM admins GROUP BY email HAVING COUNT(*) > 1
--   ) k ON a.email = k.email AND a.id <> k.keep_id
--    SET a.email = CONCAT(SUBSTRING_INDEX(a.email,'@',1), '__dup_', a.id, '@invalid.local');

-- 3) books.book_no duplicates: append a per-id suffix so each book_no is
--    unique AND the issued_books.book_no values still resolve.
--    After this, ALSO rewrite the corresponding issued_books.book_no rows.
-- UPDATE books b
--   JOIN (
--       SELECT book_no, MIN(book_id) AS keep_id
--         FROM books WHERE book_no IS NOT NULL AND book_no <> ''
--         GROUP BY book_no HAVING COUNT(*) > 1
--   ) k ON b.book_no = k.book_no AND b.book_id <> k.keep_id
--    SET b.book_no = CONCAT(b.book_no, '_dup_', b.book_id);
-- UPDATE issued_books i
--   JOIN books b ON i.book_no <> b.book_no
--               AND b.book_no LIKE CONCAT('%_dup_', b.book_id)
--               AND i.book_no = REPLACE(b.book_no, CONCAT('_dup_', b.book_id), '')
--   SET i.book_no = b.book_no;

-- 4) authors / category: append " (n)" to disambiguate.
-- UPDATE authors SET author_name = CONCAT(author_name, ' (', author_id, ')')
--   WHERE LOWER(author_name) IN (
--       SELECT lname FROM (SELECT LOWER(author_name) AS lname FROM authors GROUP BY lname HAVING COUNT(*) > 1) AS x
--   );

-- 5) Orphan FKs: safest is to NULL them on the referencing table.
-- UPDATE issued_books SET student_id = NULL WHERE student_id IN (...);
-- UPDATE books      SET author_id  = NULL WHERE author_id  IN (...);
-- UPDATE books      SET cat_id     = NULL WHERE cat_id     IN (...);