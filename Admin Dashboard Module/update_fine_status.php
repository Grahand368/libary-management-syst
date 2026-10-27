<?php
/**
 * Aurora Library — Admin: Update Fine
 *
 * POST-only endpoint that mutates a fine's status / amount. Built to the spec:
 *
 *   - POST only                       (GET redirects to manage_fines.php)
 *   - Admin-only                      (aurora_require_admin)
 *   - CSRF-protected                  (aurora_csrf_check)
 *   - Prepared statements             (mysqli_prepare + bind_param)
 *   - Server-side validation          (filter_var + whitelist + status=0 check)
 *   - Transactional with FOR UPDATE   (no partial writes)
 *
 * Actions:
 *   mark_paid     -> 'Unpaid' | 'Waived'           -> 'Paid'
 *   mark_unpaid   -> 'Paid' | 'Waived'             -> 'Unpaid' (or 'No Fine' if amount == 0)
 *   waive         -> 'Unpaid' | 'Paid'             -> 'Waived' (amount preserved)
 *   unwave        -> 'Waived'                      -> 'Unpaid' (or 'No Fine' if amount == 0)
 *   update_amount -> any (non-Waived)              -> new amount, status recomputed from amount
 *   clear         -> any                           -> 'No Fine'
 */

require_once __DIR__ . '/../aurora_auth.php';
require_once __DIR__ . '/../aurora_db.php';
require_once __DIR__ . '/../aurora_error.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_fines.php');
    exit;
}

require_once __DIR__ . '/../admin_auth_check.php';

$conn = aurora_db();

// ── 1. CSRF ──────────────────────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (!aurora_csrf_check($token)) {
    $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token. Please retry.'];
    header('Location: manage_fines.php');
    exit;
}

// ── 2. issue_id validation (positive int) ───────────────────────────
$issueId = filter_var($_POST['issue_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$issueId) {
    $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid issue reference.'];
    header('Location: manage_fines.php');
    exit;
}

// ── 3. action validation (whitelist) ────────────────────────────────
$action = $_POST['action'] ?? 'mark_paid';
$allowed = ['mark_paid', 'mark_unpaid', 'waive', 'unwave', 'clear', 'update_amount'];
if (!in_array($action, $allowed, true)) {
    $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Unknown action.'];
    header('Location: manage_fines.php');
    exit;
}

// ── 3b. optional new amount (for update_amount) ─────────────────────
$newAmount = null;
if ($action === 'update_amount') {
    $rawAmount = $_POST['fine_amount'] ?? null;
    if ($rawAmount === null || $rawAmount === '') {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'A new amount is required.'];
        header('Location: manage_fines.php');
        exit;
    }
    $validated = filter_var($rawAmount, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
    if ($validated === false) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Fine amount must be a non-negative number.'];
        header('Location: manage_fines.php');
        exit;
    }
    // Cap at AURORA_FINE_CAP to keep amounts sane.
    $newAmount = min((float)$validated, defined('AURORA_FINE_CAP') ? AURORA_FINE_CAP : 1000000.0);
}

mysqli_report(MYSQLI_REPORT_OFF);

$ok = false;
$err = '';
$newStatus = '';

mysqli_begin_transaction($conn);

try {
    // Lock the row.
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, fine_amount, fine_status, status
           FROM issued_books
          WHERE id = ?
          FOR UPDATE'
    );
    if (!$stmt) throw new Exception('Could not read fine record.');
    mysqli_stmt_bind_param($stmt, 'i', $issueId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $issue = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$issue) throw new Exception('Issue not found.');

    // The book must have been returned before we mess with fine status.
    if ((int)$issue['status'] !== 0) {
        throw new Exception('Book has not been returned yet — no fine to update.');
    }

    $currentStatus = aurora_fine_normalize_status($issue['fine_status']);
    $amount        = (float)$issue['fine_amount'];

    switch ($action) {
        case 'mark_paid':
            if ($currentStatus === 'Paid') {
                throw new Exception('Fine is already marked Paid.');
            }
            if ($amount <= 0) {
                throw new Exception('This record has no fine to mark as paid.');
            }
            $newStatus = 'Paid';
            break;

        case 'mark_unpaid':
            if ($currentStatus === 'Unpaid') {
                throw new Exception('Fine is already Unpaid.');
            }
            // Reopening a zero-amount "Paid"/"Waived" should land on No Fine.
            $newStatus = $amount > 0 ? 'Unpaid' : 'No Fine';
            break;

        case 'waive':
            if ($currentStatus === 'Waived') {
                throw new Exception('Fine is already Waived.');
            }
            if ($amount <= 0) {
                throw new Exception('Cannot waive a zero-amount fine.');
            }
            $newStatus = 'Waived';
            break;

        case 'unwave':
            if ($currentStatus !== 'Waived') {
                throw new Exception('Fine is not currently Waived.');
            }
            $newStatus = $amount > 0 ? 'Unpaid' : 'No Fine';
            break;

        case 'update_amount':
            if ($currentStatus === 'Waived') {
                throw new Exception('Cannot edit the amount of a Waived fine — unwave it first.');
            }
            if ($currentStatus === 'Paid') {
                throw new Exception('Cannot edit the amount of a Paid fine — reopen it first.');
            }
            $amount = $newAmount;
            $newStatus = $amount > 0 ? 'Unpaid' : 'No Fine';
            break;

        case 'clear':
            $newStatus = 'No Fine';
            break;
    }

    // Apply update — amount too when needed.
    if ($action === 'update_amount') {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE issued_books
                SET fine_status = ?,
                    fine_amount = ?
              WHERE id = ?'
        );
        if (!$stmt) throw new Exception('Could not update fine.');
        mysqli_stmt_bind_param($stmt, 'sdi', $newStatus, $amount, $issueId);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE issued_books
                SET fine_status = ?
              WHERE id = ?'
        );
        if (!$stmt) throw new Exception('Could not update fine status.');
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $issueId);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    $ok = true;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $err = $e->getMessage();
}

if ($ok) {
    $msg = match ($action) {
        'mark_paid'     => "Fine marked Paid.",
        'mark_unpaid'   => "Fine reopened as {$newStatus}.",
        'waive'         => "Fine Waived — member is no longer liable.",
        'unwave'        => "Fine un-waived — restored to {$newStatus}.",
        'update_amount' => 'Fine amount updated to Rs. ' . number_format((float)$newAmount, 2) . " ({$newStatus}).",
        'clear'         => 'Fine cleared (No Fine).',
        default         => "Fine status updated successfully ({$newStatus}).",
    };
    $_SESSION['_flash'] = ['type' => 'success', 'msg' => $msg];
} else {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err ?: aurora_user_facing_error()];
    }

header('Location: manage_fines.php');
exit;