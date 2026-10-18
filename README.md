# Library Management System (LMS) — Aurora

A web-based Library Management System built with PHP and MySQL that lets administrators manage books, categories, authors, fines, and member accounts, while providing members with a portal to view issued books, request borrows, return books, and manage their profile.

The project is now organized around the **Aurora** theme: a shared auth/session library (`aurora_*.php`), a hardened login flow with rate-limiting, prepared statements, and a custom Aurora UI.

## Features

### Admin Panel
- **Dashboard** — Overview statistics (users, books, categories, authors, issued books, fines, requests)
- **Book Management** — Add, edit, delete, list, and view books
- **Category Management** — Add, edit, delete, and view categories
- **Author Management** — Add, edit, delete, and view authors
- **Book Issuing** — Issue books to registered members
- **Return Books** — Process returns, set due/return dates, calculate fines
- **Fine Management** — View, update, and resolve fines (`view_fines.php`, `manage_fines.php`, `update_fine.php`, `update_fine_status.php`)
- **User Management** — View, suspend, reactivate, and remove users (`manage_users.php`, `user_action.php`, `view_user.php`)
- **Book Requests** — Review and approve/decline member borrow requests (`manage_requests.php`, `respond_request.php`)
- **Purchase Requests** — Review and approve/decline library-purchase suggestions (`manage_purchase_requests.php`, `respond_purchase_request.php`)
- **Issued Books** — View all currently issued books
- **Profile Management** — View, edit profile, change password

### User Panel
- **Dashboard** — Issued book count, active fines, request status
- **Books Catalog** — Browse available books (`books.php`)
- **Issued Books** — View books currently issued to the member
- **Return Book** — Self-service return flow
- **Request Book** — Request a book that is currently unavailable (`request_book.php`, `cancel_request.php`, `my_requests.php`)
- **Suggest Purchase** — Suggest a new title for the library (`suggest_book.php`, `my_purchase_suggestions.php`)
- **My Fines** — View outstanding and paid fines (`my_fines.php`)
- **Profile Management** — View, edit profile, change password

## Technology Stack

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+ / MariaDB 10.3+ (MySQLi with prepared statements)
- **Frontend:** Custom Aurora CSS (`static/css/aurora.css`) and JS (`static/js/aurora.js`)
- **Session/Security:** Custom Aurora helpers — `aurora_auth.php`, `aurora_db.php`, `aurora_error.php`, `aurora_validation.php`, `includes/auth.php`, `includes/csrf.php`, `includes/session.php`

## Security

- Passwords are stored using `password_hash()` (bcrypt/argon2). Legacy plain-text passwords are auto-upgraded on first successful login (`aurora_verify_and_maybe_upgrade`).
- Sessions use hardened cookie flags and are regenerated on login.
- All forms protect against CSRF via `includes/csrf.php`.
- All database access uses **prepared statements** via the `aurora_*` helpers — direct interpolation has been removed.
- **Login rate-limiting** (`login_attempts` table) blocks brute-force attempts on both member and admin endpoints, with a live countdown UI.
- Email uniqueness enforced at the DB level (migration `005_database_constraints.sql`).
- **Note:** Some admin legacy modules that still use string interpolation have been isolated behind `aurora_*` wrappers; do not add new code that bypasses these helpers.

## Database Schema

The system uses a MySQL database named `lms`. Numbered SQL migrations under `database/` bring an existing schema up to date. Files are idempotent and run in numeric order:

| File | Purpose |
|---|---|
| `000_preflight_duplicates.sql` | Diagnostic only — detects duplicate/orphan rows that would block constraints |
| `001_security_migration.sql` | Creates `login_attempts` (rate-limit store) |
| `002_fine_system.sql` | Adds `due_date`, `return_date`, `fine_amount`, `fine_status` to `issued_books`; backfills due dates |
| `003_book_requests.sql` | Creates `book_requests` (member borrow flow) |
| `004_purchase_requests.sql` | Creates `purchase_requests` (member purchase-suggestion flow) |
| `005_database_constraints.sql` | Adds UNIQUE indexes on `users.email`, `admins.email`, `books.book_no`, `authors.author_name_lc`, `category.cat_name_lc`; adds RESTRICT foreign keys |

Tables:

| Table | Description |
|---|---|
| `users` | Registered members (id, name, email, password, mobile, address, is_active, deleted_at) |
| `admins` | Admin users (id, name, email, password, mobile) |
| `books` | Catalog (book_id, book_name, author_id, cat_id, book_no, book_price, book_qty) |
| `authors` | Authors (author_id, author_name, author_name_lc) |
| `category` | Categories (cat_id, cat_name, cat_name_lc) |
| `issued_books` | Issued books (id, book_no, book_name, book_author, student_id, status, issue_date, due_date, return_date, fine_amount, fine_status) |
| `book_requests` | Member borrow requests (request_id, user_id, book_id, status, request_date) |
| `purchase_requests` | Member purchase suggestions (purchase_request_id, user_id, book_title, status, created_at) |
| `login_attempts` | Rate-limit buckets (id, bucket_key, attempts, blocked_until, last_attempt) |

See `database/README.md` for full migration instructions and pre-flight cleanup recipes.

## Project Structure

```
Library Management System/
├── aurora_auth.php            # Aurora auth/session/guards (included by all pages)
├── aurora_db.php              # Aurora DB connection + prepared-statement helpers
├── aurora_error.php           # Aurora error/toast renderer
├── aurora_validation.php      # Aurora form-validation helpers
├── create_admin.php           # CLI/web tool to create the first admin
├── migrate_email_unique.php   # One-off migration: enforce unique emails
├── migrate_passwords.php      # One-off migration: hash legacy plain-text passwords
├── logout.php                 # Global logout (admin + member)
├── lms.php                    # Entry router
│
├── config/
│   └── database.php           # DB credentials
│
├── includes/                  # Shared auth/CSRF/session/function helpers
│   ├── auth.php
│   ├── csrf.php
│   ├── functions.php
│   ├── session.php
│   └── validation.php
│
├── database/                  # Numbered, idempotent SQL migrations
│   ├── 000_preflight_duplicates.sql
│   ├── 001_security_migration.sql
│   ├── 002_fine_system.sql
│   ├── 003_book_requests.sql
│   ├── 004_purchase_requests.sql
│   ├── 005_database_constraints.sql
│   ├── 029_defensive_indexes.sql
│   ├── migrate.sh
│   └── README.md
│
├── login module/              # Member + admin sign-in
│   ├── index.php              # Member login
│   ├── admin_login.php        # Admin login (rate-limited)
│   ├── signup.php             # Member registration form
│   ├── register.php           # Registration handler
│   └── logout.php
│
├── Admin Dashboard Module/
│   ├── admin_dashboard.php
│   ├── functions.php
│   ├── manage_users.php       # Suspend / reactivate / delete users
│   ├── user_action.php        # POST handler for user actions
│   ├── view_user.php
│   ├── manage_fines.php       # Fine listing + actions
│   ├── view_fines.php
│   ├── update_fine.php
│   ├── update_fine_status.php
│   ├── manage_requests.php    # Book requests
│   ├── respond_request.php
│   ├── manage_purchase_requests.php
│   ├── respond_purchase_request.php
│   ├── view_issued_book.php
│   ├── return_book.php
│   ├── view_profile.php
│   ├── edit_profile.php
│   ├── update.php
│   ├── change_password.php
│   ├── update_password.php
│   ├── registered_user.php
│   └── regusers.php
│
├── dashboardmainpage/         # Member portal
│   ├── user-dashboard.php
│   ├── books.php              # Catalog browser
│   ├── view_issued_book.php
│   ├── return_book.php
│   ├── request_book.php
│   ├── my_requests.php
│   ├── cancel_request.php
│   ├── suggest_book.php
│   ├── my_purchase_suggestions.php
│   ├── my_fines.php
│   ├── view_profile.php
│   ├── edit_profile.php
│   ├── update.php
│   ├── change_password.php
│   └── update_password.php
│
├── Add_books_lms/
│   ├── add_book.php
│   ├── manage_book.php
│   ├── edit_book.php
│   ├── delete_book.php
│   ├── regbooks.php
│   └── registered_book.php
│
├── Manage_Book_Categories/
│   ├── add_cat.php
│   ├── manage_cat.php
│   ├── edit_cat.php
│   ├── delete_cat.php
│   ├── manage_authors.php
│   └── Regcat.php
│
├── Issue_book_Module/
│   └── issue_book.php
│
└── static/
    ├── css/aurora.css
    ├── js/aurora.js
    └── img/
```

## Installation

1. **Prerequisites**
   - PHP 7.4 or higher (PHP 8.x recommended)
   - MySQL 5.7+ / MariaDB 10.3+
   - A web server (Apache/Nginx) **or** PHP's built-in dev server, **or** XAMPP/WAMP/Laragon

2. **Setup**
   - Place the project in your web server's document root (e.g., `htdocs/lms` for XAMPP, `www/lms` for Laragon).
   - Create a MySQL database named `lms`.
   - Apply the numbered migrations in order:

     ```bash
     # Linux/macOS/WSL
     ./database/migrate.sh
     # or manually:
     mysql -u root -p lms < database/001_security_migration.sql
     mysql -u root -p lms < database/002_fine_system.sql
     mysql -u root -p lms < database/003_book_requests.sql
     mysql -u root -p lms < database/004_purchase_requests.sql
     mysql -u root -p lms < database/005_database_constraints.sql
     ```

     See `database/README.md` for pre-flight (`000_preflight_duplicates.sql`) and Windows PowerShell instructions.

   - Update DB credentials in `config/database.php` (default: `localhost`, `root`, no password, db `lms`).

3. **Create the first admin**
   - Open `http://localhost/lms/create_admin.php` in your browser and follow the form, **or**
   - Run from CLI:

     ```bash
     php create_admin.php
     ```

   - You can also run `migrate_passwords.php` once to hash any legacy plain-text passwords already in the DB.

## Usage

1. **Admin sign-in**
   - Navigate to `login module/admin_login.php`
   - Enter the admin email + password you created above
   - On success you'll be redirected to `Admin Dashboard Module/admin_dashboard.php`
   - Too many failed attempts trigger a temporary lockout with a live countdown

2. **Member sign-in / sign-up**
   - Navigate to `login module/index.php`
   - Sign in with an existing account, or click the **Sign up** tab to register
   - On success you'll be redirected to `dashboardmainpage/user-dashboard.php`

3. **Member self-service**
   - Browse the catalog (`books.php`)
   - Request an unavailable book (`request_book.php`)
   - Suggest a new title for the library (`suggest_book.php`)
   - Track requests (`my_requests.php`, `my_purchase_suggestions.php`)
   - View fines and return books (`my_fines.php`, `return_book.php`)

4. **Logout**
   - Click any **Logout** link — both admin and member routes share `logout.php`.

## Quick Smoke-Test (local dev)

```powershell
# From the project root, with PHP installed:
php -S localhost:8000

# Then in your browser:
#  http://localhost:8000/login%20module/admin_login.php
#  http://localhost:8000/login%20module/index.php
```

If `php` is not on PATH, install PHP, XAMPP, or Laragon (see `database/README.md` for the same advice adapted for DB migrations).

## Notes

- All new code must use the `aurora_*` helpers and `includes/csrf.php` for form tokens.
- Do **not** commit DB credentials; override `config/database.php` locally.
- The Aurora UI is intentionally framework-free (no Bootstrap) — keep new styles in `static/css/aurora.css` to stay consistent.