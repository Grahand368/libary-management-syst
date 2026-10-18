<?php
/**
 * Aurora Library — Validation shim
 *
 * All validators live in /aurora_validation.php. This shim gives new
 * pages the cleaner "/includes/validation.php" path without
 * duplicating any of the helpers.
 */

require_once __DIR__ . '/../aurora_validation.php';
