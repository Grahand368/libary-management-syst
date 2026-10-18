<?php
/**
 * Aurora Library — CSRF shim
 *
 * CSRF helpers live in /aurora_auth.php (they are session-bound, so
 * keeping them next to the other session helpers is intentional).
 * This file gives new pages the cleaner "/includes/csrf.php" path.
 */

require_once __DIR__ . '/../aurora_auth.php';
