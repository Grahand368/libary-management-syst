<?php
    /**
     * Aurora Library — Admin: Respond to a Purchase Request
     *
     * POST only, CSRF protected, admin only.
     *
     * State machine:
     *   Pending  -> Approved   (librarian accepted the suggestion)
     *   Pending  -> Rejected   (librarian declined)
     *   Approved -> Purchased  (book has actually been bought)
     *   Approved -> Rejected   (reversal)
     *   Rejected | Purchased | Cancelled -> terminal (no further action)
     *
     * The state column is the source of truth; every UPDATE is guarded by
     * the previous status to prevent double-processing.
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: manage_purchase_requests.php');
        exit;
    }
    require_once __DIR__ . '/../admin_auth_check.php';

    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        aurora_flash_set('error', 'Invalid security token.');
        header('Location: manage_purchase_requests.php');
        exit;
    }

    $reqId  = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    $note   = trim((string)($_POST['note'] ?? ''));
    $note   = mb_substr($note, 0, 500);

    if (!$reqId || !in_array($action, ['approve', 'reject', 'purchase'], true)) {
        aurora_flash_set('error', 'Invalid request.');
        header('Location: manage_purchase_requests.php');
        exit;
    }

    $adminId = (int)($_SESSION['id'] ?? 0);
    $conn    = aurora_db();
    mysqli_report(MYSQLI_REPORT_OFF);

    $ok = false;
    $err = '';
    $newStatus = '';

    // Map action -> (required previous status, new status)
    $transitions = [
        'approve'  => ['Pending',  'Approved'],
        'reject'   => ['Pending',  'Declined'],  // reject can also come from Approved (reversal)
        'purchase' => ['Approved', 'Purchased'],
    ];

    [$fromStatus, $toStatus] = $transitions[$action];

    // Allow Pending|Approved -> Rejected for both initial decline and reversal.
    if ($action === 'reject') {
        $whereFrom = "status IN ('Pending','Approved')";
    } else {
        $whereFrom = "status = '$fromStatus'";
    }

    try {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE purchase_requests
                SET status = ?,
                    admin_response_by = ?,
                    admin_response_note = CONCAT(COALESCE(admin_response_note, ''),
                                                CASE WHEN admin_response_note IS NULL OR admin_response_note = '' THEN '' ELSE '\n' END,
                                                CASE WHEN ? = '' THEN '' ELSE CONCAT('[', ?, '] ', ?) END)
              WHERE request_id = ? AND $whereFrom"
        );
        if (!$stmt) {
            throw new RuntimeException(aurora_user_facing_error());
        }
        // Build a richer note with timestamp + admin id so the audit trail is preserved.
        $now = date('Y-m-d H:i:s');
        $noteLine = trim(($now) . ' admin#' . $adminId . ': ' . $note);
        mysqli_stmt_bind_param(
            $stmt,
            'sisssi',
            $toStatus,
            $adminId,
            $note,
            $now,
            $noteLine,
            $reqId
        );
        if (!mysqli_stmt_execute($stmt)) {
            // Don't propagate raw mysqli text to the user — log it (dev) and
            // raise a generic message instead.
            $rawErr = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            aurora_log_error('respond_purchase_request.' . $action . '.execute', $rawErr);
            throw new RuntimeException(aurora_user_facing_error());
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected === 1) {
            $ok = true;
            $newStatus = $toStatus;
        } else {
            // Diagnose: did the row exist at all?
            $check = mysqli_prepare($conn, 'SELECT status FROM purchase_requests WHERE request_id = ?');
            mysqli_stmt_bind_param($check, 'i', $reqId);
            mysqli_stmt_execute($check);
            $res = mysqli_stmt_get_result($check);
            $cur = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($check);
            if (!$cur) {
                $err = 'Request not found.';
            } else {
                $err = 'Cannot move from "' . $cur['status'] . '" using action "' . $action . '".';
            }
        }
    } catch (Throwable $e) {
        // Log the real cause in dev; show a safe message to the user.
        aurora_log_error('respond_purchase_request.' . $action, $e);
        if ($err === '') $err = aurora_user_facing_error();
    }

    if ($ok) {
        $msg = match ($newStatus) {
            'Approved'  => 'Suggestion approved.',
            'Purchased' => 'Marked as Purchased.',
            'Declined'  => 'Suggestion declined.',
            default     => 'Updated.',
        };
        aurora_flash_set('success', $msg);
    } else {
        aurora_flash_set('error', $err ?: aurora_user_facing_error());
    }

    header('Location: manage_purchase_requests.php');
    exit;