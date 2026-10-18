<?php
    /**
     * Aurora Library — Admin Dashboard Helpers
     *
     * Reusable stat helpers used by the admin dashboard and any future
     * admin reporting page. Each function returns a single typed value
     * (int / float) so the view layer stays simple.
     *
     * Definitions:
     *   - Total Users      : every non-deleted user (count)
     *   - Active Users     : non-deleted AND is_active = 1
     *   - Inactive Users   : non-deleted AND is_active = 0
     *   - Deleted Users    : deleted_at IS NOT NULL (returned separately)
     *   - Total Books      : COUNT(*) from books
     *   - Available Books  : COUNT(*) WHERE book_qty > 0
     *   - Issued Books     : COUNT(*) FROM issued_books WHERE status = 1
     *   - Low Stock Books  : COUNT(*) WHERE book_qty <= 1 (configurable)
     *   - Pending Book Requests            : book_requests status='Pending'
     *   - Approved Requests                : book_requests status='Approved'
     *   - Pending Purchase Suggestions     : purchase_requests status='Pending'
     *   - Total Fine Amount                : SUM(fine_amount)
     *   - Fine Collected                   : SUM WHERE fine_status='Paid'
     *   - Outstanding Fine                 : SUM WHERE fine_status='Unpaid'
     *   - Users With Unpaid Fines          : COUNT DISTINCT student_id
     *                                        FROM issued_books
     *                                        WHERE fine_status='Unpaid'
     */

require_once __DIR__ . '/../aurora_db.php';

/* ------------------------------------------------------------------ */
/*  Users                                                               */
/* ------------------------------------------------------------------ */

if (!function_exists('get_total_user_count')) {
    function get_total_user_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NULL');
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_active_user_count')) {
    function get_active_user_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NULL AND is_active = 1');
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_inactive_user_count')) {
    function get_inactive_user_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NULL AND is_active = 0');
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_deleted_user_count')) {
    function get_deleted_user_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NOT NULL');
        return (int)($r['c'] ?? 0);
    }
}

// Backward-compat alias used elsewhere in the codebase.
if (!function_exists('get_user_count')) {
    function get_user_count() {
        return get_total_user_count();
    }
}

/* ------------------------------------------------------------------ */
/*  Books                                                               */
/* ------------------------------------------------------------------ */

if (!function_exists('get_total_book_count')) {
    function get_total_book_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM books');
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_available_book_count')) {
    function get_available_book_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM books WHERE book_qty > 0');
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_low_stock_book_count')) {
    /**
     * Books whose stock has fallen to or below $threshold
     * copies. Default is 1 — anything at "1 left" or "out of
     * stock" appears here so the admin can restock.
     */
    function get_low_stock_book_count($threshold = 1) {
        $r = aurora_fetch_one(
            'SELECT COUNT(*) AS c FROM books WHERE book_qty <= ?',
            'i',
            [$threshold]
        );
        return (int)($r['c'] ?? 0);
    }
}

// Backward-compat alias.
if (!function_exists('get_book_count')) {
    function get_book_count() {
        return get_total_book_count();
    }
}

if (!function_exists('get_issue_book_count')) {
    function get_issue_book_count() {
        $r = aurora_fetch_one("SELECT COUNT(*) AS c FROM issued_books WHERE status = 1");
        return (int)($r['c'] ?? 0);
    }
}

/* ------------------------------------------------------------------ */
/*  Catalog metadata                                                    */
/* ------------------------------------------------------------------ */

if (!function_exists('get_category_count')) {
    function get_category_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM category');
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_author_count')) {
    function get_author_count() {
        $r = aurora_fetch_one('SELECT COUNT(*) AS c FROM authors');
        return (int)($r['c'] ?? 0);
    }
}

/* ------------------------------------------------------------------ */
/*  Requests                                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('get_pending_borrow_request_count')) {
    function get_pending_borrow_request_count() {
        $r = aurora_fetch_one("SELECT COUNT(*) AS c FROM book_requests WHERE status = 'Pending'");
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_approved_borrow_request_count')) {
    function get_approved_borrow_request_count() {
        $r = aurora_fetch_one("SELECT COUNT(*) AS c FROM book_requests WHERE status = 'Approved'");
        return (int)($r['c'] ?? 0);
    }
}

if (!function_exists('get_pending_purchase_suggestion_count')) {
    function get_pending_purchase_suggestion_count() {
        $r = aurora_fetch_one("SELECT COUNT(*) AS c FROM purchase_requests WHERE status = 'Pending'");
        return (int)($r['c'] ?? 0);
    }
}

/* ------------------------------------------------------------------ */
/*  Fines                                                               */
/* ------------------------------------------------------------------ */

if (!function_exists('get_total_fine_amount')) {
    function get_total_fine_amount() {
        $r = aurora_fetch_one('SELECT COALESCE(SUM(fine_amount), 0) AS s FROM issued_books');
        return (float)($r['s'] ?? 0);
    }
}

if (!function_exists('get_fine_collected_total')) {
    function get_fine_collected_total() {
        $r = aurora_fetch_one("SELECT COALESCE(SUM(fine_amount), 0) AS s FROM issued_books WHERE fine_status = 'Paid'");
        return (float)($r['s'] ?? 0);
    }
}

if (!function_exists('get_outstanding_fine_total')) {
    function get_outstanding_fine_total() {
        $r = aurora_fetch_one("SELECT COALESCE(SUM(fine_amount), 0) AS s FROM issued_books WHERE fine_status = 'Unpaid'");
        return (float)($r['s'] ?? 0);
    }
}

if (!function_exists('get_users_with_unpaid_fines_count')) {
    function get_users_with_unpaid_fines_count() {
        $r = aurora_fetch_one(
            "SELECT COUNT(DISTINCT student_id) AS c
                           FROM issued_books
                          WHERE fine_status = 'Unpaid'"
        );
        return (int)($r['c'] ?? 0);
    }
}

/* ------------------------------------------------------------------ */
/*  Convenience aggregator                                              */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_admin_dashboard_stats')) {
    /**
     * Returns one associative array with every dashboard metric.
     * Saves a few round-trips when the view wants many counters.
     */
    function aurora_admin_dashboard_stats() {
        $u = aurora_fetch_one(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 AND deleted_at IS NULL THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 AND deleted_at IS NULL THEN 1 ELSE 0 END) AS suspended,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS deleted
               FROM users"
        ) ?? ['total' => 0, 'active' => 0, 'suspended' => 0, 'deleted' => 0];

        $b = aurora_fetch_one(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN book_qty >  0 THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN book_qty <= 1 AND book_qty > 0 THEN 1 ELSE 0 END) AS low_stock
               FROM books"
        ) ?? ['total' => 0, 'available' => 0, 'low_stock' => 0];

        $r = aurora_fetch_one(
            "SELECT
                SUM(CASE WHEN status = 'Pending'  then 1 else 0 end) AS pending,
                SUM(CASE WHEN status = 'Approved' then 1 else 0 end) AS approved
               FROM book_requests"
        ) ?? ['pending' => 0, 'approved' => 0];

        $issuedStats = aurora_fetch_one(
            "SELECT
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS issued_total,
                SUM(CASE WHEN status = 1 AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
               FROM issued_books"
        ) ?? ['issued_total' => 0, 'overdue' => 0];

        $f = aurora_fetch_one(
            "SELECT
                COALESCE(SUM(fine_amount), 0) AS total,
                COALESCE(SUM(CASE WHEN fine_status = 'Paid'   THEN fine_amount ELSE 0 END), 0) AS collected,
                COALESCE(SUM(CASE WHEN fine_status = 'Unpaid' THEN fine_amount ELSE 0 END), 0) AS outstanding,
                COUNT(DISTINCT CASE WHEN fine_status = 'Unpaid' THEN student_id END) AS users_with_unpaid
               FROM issued_books"
        ) ?? ['total' => 0, 'collected' => 0, 'outstanding' => 0, 'users_with_unpaid' => 0];

        $p = aurora_fetch_one(
            "SELECT COUNT(*) AS c FROM purchase_requests WHERE status = 'Pending'"
        ) ?? ['c' => 0];

        return [
            'users' => [
                'total'    => (int)$u['total'],
                'active'   => (int)$u['active'],
                'suspended'=> (int)$u['suspended'],
                'deleted'  => (int)$u['deleted'],
            ],
            'books' => [
                'total'    => (int)$b['total'],
                'available'=> (int)$b['available'],
                'issued'   => (int)$issuedStats['issued_total'],
                'overdue'  => (int)$issuedStats['overdue'],
                'low_stock'=> (int)$b['low_stock'],
            ],
            'requests' => [
                'pending'  => (int)$r['pending'],
                'approved' => (int)$r['approved'],
                'pending_purchase_suggestions' => (int)$p['c'],
            ],
            'fines' => [
                'total'             => (float)$f['total'],
                'collected'         => (float)$f['collected'],
                'outstanding'       => (float)$f['outstanding'],
                'users_with_unpaid' => (int)$f['users_with_unpaid'],
            ],
        ];
    }
}