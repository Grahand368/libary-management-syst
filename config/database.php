<?php
/**
 * Aurora Library — Database bootstrap
 *
 * Thin shim. The real connection lives in /aurora_db.php so we never
 * duplicate logic or split it between two files. This file is the
 * "friendly" entry point for any new page:
 *
 *     require_once __DIR__ . '/../config/database.php';
 *     $conn = aurora_db();
 *
 * Existing pages that already use /aurora_db.php directly are unchanged.
 */

if (!defined('AURORA_DB_BOOTSTRAPPED')) {
    define('AURORA_DB_BOOTSTRAPPED', true);
}

require_once __DIR__ . '/../aurora_db.php';
