<?php
/**
 * Aurora Library — Logout shim
 *
 * The canonical logout logic lives in /login module/logout.php. Many
 * pages across the project link to "../logout.php" (one level up from
 * any module folder), so this top-level shim keeps every existing link
 * working without having to edit 28 individual pages.
 *
 * All it does is delegate to the canonical handler. The session,
 * cookie, and CSRF state are torn down there.
 */
require_once __DIR__ . '/login module/logout.php';
