<?php
/**
 * Aurora Library — Generic helpers shim
 *
 * "functions" historically means dashboard stat helpers in this project
 * (Admin Dashboard Module/functions.php). To avoid breaking the
 * admin_dashboard require, this shim pulls in that file when it's
 * available, plus a curated list of the most commonly used top-level
 * helpers via /aurora_error.php and /aurora_validation.php.
 *
 * Pages that need a specific helper should include its canonical file
 * directly (aurora_db.php, aurora_auth.php, aurora_validation.php,
 * aurora_error.php). This shim is for convenience only.
 */

require_once __DIR__ . '/../aurora_db.php';
require_once __DIR__ . '/../aurora_validation.php';
require_once __DIR__ . '/../aurora_error.php';
require_once __DIR__ . '/../aurora_auth.php';
