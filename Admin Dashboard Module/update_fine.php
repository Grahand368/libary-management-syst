<?php
    /**
     * Aurora Library — Admin: Update Fine Status
     *
     * POST-only, CSRF-protected, admin-only. Flips an issue's
     * fine_status between 'Unpaid' and 'Paid' (or clears to 'No Fine').
     *
     * The full implementation lives in update_fine_status.php. This
     * file remains so any existing link keeps working.
     */
    require_once __DIR__ . '/update_fine_status.php';
