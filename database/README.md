# Aurora Library — Database Migrations

Numbered SQL files under this folder bring an existing `lms` database up to the
current schema. Each file is idempotent (safe to re-run). Files are intended to
run in numeric order.

## Files

| File | Purpose |
|---|---|
| `000_preflight_duplicates.sql` | **Diagnostic only.** Detects duplicate values that would block UNIQUE indexes, and orphan rows that would block foreign keys. Does not modify the database. Includes commented-out cleanup recipes. |
| `001_security_migration.sql` | Creates `login_attempts` (rate-limit bucket store). |
| `002_fine_system.sql` | Adds `due_date`, `return_date`, `fine_amount`, `fine_status` to `issued_books`; folds legacy `fine` column into `fine_amount`; backfills due dates; adds supporting indexes. |
| `003_book_requests.sql` | Creates `book_requests` (member → admin borrow flow). |
| `004_purchase_requests.sql` | Creates `purchase_requests` (member → admin library-purchase suggestion flow). |
| `005_database_constraints.sql` | Adds UNIQUE indexes on `users.email`, `admins.email`, `books.book_no`, `authors.author_name_lc`, `category.cat_name_lc`. Adds foreign keys (RESTRICT, no cascade) for every referential column. Adds secondary indexes. |

## How to run

### Linux / macOS / WSL

```bash
# 1. Inspect preflight (read-only) — see if anything needs cleaning up
mysql -u root -p lms < database/000_preflight_duplicates.sql

# 2. Address any duplicates/orphans that showed up in step 1.
#    Open 000_preflight_duplicates.sql, uncomment the relevant UPDATE block,
#    and re-run. Repeat until preflight reports zero rows.

# 3. Apply the rest of the migrations
mysql -u root -p lms < database/001_security_migration.sql
mysql -u root -p lms < database/002_fine_system.sql
mysql -u root -p lms < database/003_book_requests.sql
mysql -u root -p lms < database/004_purchase_requests.sql
mysql -u root -p lms < database/005_database_constraints.sql
```

Or use the bundled runner:

```bash
chmod +x database/migrate.sh
./database/migrate.sh                       # applies all
./database/migrate.sh --dry-run             # prints what would run
```

### Windows (PowerShell)

```powershell
# 1. Pre-flight
Get-Content database\000_preflight_duplicates.sql | mysql -u root -p lms

# 2. Apply migrations (one at a time so you can see any errors)
Get-Content database\001_security_migration.sql   | mysql -u root -p lms
Get-Content database\002_fine_system.sql          | mysql -u root -p lms
Get-Content database\003_book_requests.sql        | mysql -u root -p lms
Get-Content database\004_purchase_requests.sql    | mysql -u root -p lms
Get-Content database\005_database_constraints.sql | mysql -u root -p lms
```

## Why pre-flight first?

`005_database_constraints.sql` adds UNIQUE indexes and foreign keys. MySQL will
**refuse** to apply either when the table already contains values that violate
them. Running `000_preflight_duplicates.sql` first lets you see the offending
rows and clean them up using the supplied recipes (commented out in the SQL) **before**
the constraint is applied. This way you never lose data silently.

## Cleanup recipe reference

The pre-flight file contains ready-to-use UPDATE statements for:

1. `users.email` duplicates — keep the oldest row, rename later dupes' email to
   `<local>__dup_<id>@invalid.local`.
2. `admins.email` duplicates — same approach.
3. `books.book_no` duplicates — append `_dup_<book_id>` to later rows; also
   rewrites matching `issued_books.book_no` so the foreign key still resolves.
4. `authors.author_name` duplicates (case-insensitive) — append `(id)`.
5. `category.cat_name` duplicates (case-insensitive) — append `(id)`.
6. Orphan foreign keys — set the referencing column to NULL on the child row.

Each block is commented out by default; uncomment only the ones you need.

## Index / constraint summary (after 005)

| Table | PK | UNIQUE | FK | Indexes |
|---|---|---|---|---|
| `users` | `id` | `email` | — | `is_active, deleted_at` |
| `admins` | `id` | `email` | — | — |
| `books` | `book_id` | `book_no` | `author_id → authors`, `cat_id → category` | `author_id, cat_id`; `book_qty` |
| `authors` | `author_id` | `author_name_lc` (generated) | — | — |
| `category` | `cat_id` | `cat_name_lc` (generated) | — | — |
| `issued_books` | `id` | — | `student_id → users`, `book_no → books` | `fine_status`; `(status, student_id)`; `book_no` |
| `book_requests` | `request_id` | — | `user_id → users`, `book_id → books` | `(status, request_date)`; `user_id`; `book_id` |
| `purchase_requests` | `purchase_request_id` | — | `user_id → users` | `user_id`; `(status, created_at)`; `book_title` |
| `login_attempts` | `id` | `bucket_key` | — | — |

All foreign keys use `ON DELETE RESTRICT` and `ON UPDATE CASCADE`. The system
will never silently cascade-delete a parent row that children depend on;
explicit admin action is required.