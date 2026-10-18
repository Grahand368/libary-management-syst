<?php
/**
 * Aurora Library — Auth shim
 *
 * Re-exports the helpers from /aurora_auth.php under a friendly
 * "/includes/auth.php" path. The canonical definitions are still
 * in aurora_auth.php, so every existing require_once keeps working.
 */

require_once __DIR__ . '/../aurora_auth.php';
