<?php
    /**
     * Aurora Library — Member sidebar (shared).
     *
     * Renders the standard user-side sidebar for every member page so
     * navigation, active highlighting, and mobile responsiveness stay
     * consistent.
     *
     * Usage from a member page:
     *   $current = basename($_SERVER['SCRIPT_NAME']);
     *   require __DIR__ . '/_sidebar.php';
     *   render_user_sidebar($current);
     *
     * Mobile: aurora.css already turns the sidebar into a slide-in drawer
     * at <=991.98px with the data-menu-toggle button.
     */
    if (!function_exists('render_user_sidebar')) {
        function render_user_sidebar(string $current): void {
            $items = [
                ['label' => 'Dashboard',       'href' => 'user-dashboard.php',          'icon' => '🏠'],
                ['label' => 'Browse Books',    'href' => 'books.php',                   'icon' => '📚'],
                ['label' => 'My Issued Books', 'href' => 'view_issued_book.php',        'icon' => '📖'],
                ['label' => 'Request Book',    'href' => 'books.php',                   'icon' => '✉'],
                ['label' => 'My Requests',     'href' => 'my_requests.php',             'icon' => '📨'],
                ['label' => 'Suggest Purchase','href' => 'suggest_book.php',            'icon' => '💡'],
                ['label' => 'My Suggestions',  'href' => 'my_purchase_suggestions.php', 'icon' => '🛒'],
                ['label' => 'My Fines',        'href' => 'my_fines.php',                'icon' => '💸'],
            ];
            $account = [
                ['label' => 'Profile',         'href' => 'view_profile.php',  'icon' => '👤'],
                ['label' => 'Edit Profile',    'href' => 'edit_profile.php',  'icon' => '✏'],
                ['label' => 'Change Password', 'href' => 'change_password.php','icon' => '🔒'],
            ];
            ?>
            <aside class="app-sidebar" data-sidebar aria-label="Main navigation">
                <div class="brand">⚜ Aurora Library</div>

                <div class="section-label">Browse</div>
                <?php foreach ($items as $l):
                    $active = ($current === basename($l['href'])) ? 'active' : ''; ?>
                    <a class="<?php echo $active; ?>" href="<?php echo $l['href']; ?>">
                        <span style="margin-right:6px;opacity:.85;"><?php echo $l['icon']; ?></span><?php echo $l['label']; ?>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Account</div>
                <?php foreach ($account as $l):
                    $active = ($current === basename($l['href'])) ? 'active' : ''; ?>
                    <a class="<?php echo $active; ?>" href="<?php echo $l['href']; ?>">
                        <span style="margin-right:6px;opacity:.85;"><?php echo $l['icon']; ?></span><?php echo $l['label']; ?>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Session</div>
                <a href="/lms/logout.php">⎋ Logout</a>
            </aside>
            <div class="sidebar-overlay" data-sidebar-overlay aria-hidden="true"></div>
            <?php
        }
    }

    /**
     * Renders the small top header (hamburger + page title + user label).
     * Use inside <main class="app-main">.
     */
    if (!function_exists('render_user_header')) {
        function render_user_header(string $title): void {
            ?>
            <header class="app-header">
                <div style="display:flex;align-items:center;gap:10px;">
                    <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                    <div class="header-title"><?php echo htmlspecialchars($title); ?></div>
                </div>
                <div class="text-muted" style="font-size:13px;">
                    <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>
                </div>
            </header>
            <?php
        }
    }