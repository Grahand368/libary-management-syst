<?php
/**
 * Aurora Library — One-line core bootstrap
 *
 * Include this file from any new page to get the full Aurora core
 * loaded in the correct order:
 *
 *     require_once __DIR__ . '/lms.php';
 *
 * Order is important:
 *   1. session  (must be first — auth + CSRF use $_SESSION)
 *   2. auth     (depends on session)
 *   3. database (used by auth helpers; cheap to call early)
 *   4. validation (no dependencies)
 *   5. error / flash helpers (no dependencies, but useful everywhere)
 *
 * Pages written before this aggregator existed are unaffected because
 * each shim simply re-includes the canonical aurora_*.php file. The
 * `if (!defined('AURORA_BOOTSTRAPPED'))` guard in the canonical files
 * makes repeated includes safe.
 *
 * The canonical helpers still live in /aurora_*.php at the project root
 * so existing pages, migration scripts, and Part 24 retrofit code keep
 * working without any path changes.
 */

if (!defined('AURORA_BOOTSTRAPPED')) {
    define('AURORA_BOOTSTRAPPED', true);
}

// Each of these is a thin shim that re-includes the canonical file.
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/functions.php';
