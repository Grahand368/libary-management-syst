<?php
    /**
     * Aurora Library — Admin sidebar (shared).
     *
     * Renders the standard admin sidebar for every admin page so navigation,
     * active highlighting, and mobile responsiveness stay consistent.
     *
     * Usage from an admin page:
     *   $current = basename($_SERVER['SCRIPT_NAME']);
     *   require __DIR__ . '/_admin_sidebar.php';
     *   render_admin_sidebar($current);
     *
     * Mobile: aurora.css already turns the sidebar into a slide-in drawer
     * at <=991.98px with the data-menu-toggle button.
     */
    if (!function_exists('render_admin_sidebar')) {
        function render_admin_sidebar(string $current): void {
            $main = [
                ['label' => 'Dashboard', 'href' => 'admin_dashboard.php', 'icon' => '🏠'],
            ];

            $library = [
                ['label' => 'Books',      'href' => '../Add_books_lms/manage_book.php',         'icon' => '📚'],
                ['label' => 'Categories', 'href' => '../Manage_Book_Categories/manage_cat.php', 'icon' => '🗂'],
                ['label' => 'Authors',    'href' => '../Manage_Book_Categories/manage_authors.php', 'icon' => '✍'],
            ];

            $transactions = [
                ['label' => 'Issue Book',   'href' => '../Issue_book_Module/issue_book.php', 'icon' => '📤'],
                ['label' => 'Issued Books', 'href' => 'view_issued_book.php',               'icon' => '📖'],
                ['label' => 'Return Book',  'href' => 'return_book.php',                    'icon' => '📥'],
                ['label' => 'Fines',        'href' => 'manage_fines.php',                   'icon' => '💸'],
            ];

            $requests = [
                ['label' => 'Book Requests',        'href' => 'manage_requests.php',             'icon' => '📨'],
                ['label' => 'Purchase Requests',    'href' => 'manage_purchase_requests.php',   'icon' => '💡'],
            ];

            $users = [
                ['label' => 'Manage Users', 'href' => 'manage_users.php', 'icon' => '👥'],
            ];

            $account = [
                ['label' => 'Profile',         'href' => 'view_profile.php',   'icon' => '👤'],
                ['label' => 'Edit Profile',    'href' => 'edit_profile.php',   'icon' => '✏'],
                ['label' => 'Change Password', 'href' => 'change_password.php','icon' => '🔒'],
            ];
            ?>
            <aside class="app-sidebar" data-sidebar aria-label="Admin navigation">
                <div class="brand">⚜ Aurora Admin</div>

                <div class="section-label">Main</div>
                <?php foreach ($main as $l):
                    $active = ($current === basename($l['href'])) ? 'active' : ''; ?>
                    <a class="<?php echo $active; ?>" href="<?php echo $l['href']; ?>">
                        <span style="margin-right:6px;opacity:.85;"><?php echo $l['icon']; ?></span><?php echo $l['label']; ?>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Library Management</div>
                <?php foreach ($library as $l):
                    $active = ($current === basename($l['href'])) ? 'active' : ''; ?>
                    <a class="<?php echo $active; ?>" href="<?php echo $l['href']; ?>">
                        <span style="margin-right:6px;opacity:.85;"><?php echo $l['icon']; ?></span><?php echo $l['label']; ?>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Transactions</div>
                <?php foreach ($transactions as $l):
                    $active = ($current === basename($l['href'])) ? 'active' : ''; ?>
                    <a class="<?php echo $active; ?>" href="<?php echo $l['href']; ?>">
                        <span style="margin-right:6px;opacity:.85;"><?php echo $l['icon']; ?></span><?php echo $l['label']; ?>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Requests</div>
                <?php foreach ($requests as $l):
                    $active = ($current === basename($l['href'])) ? 'active' : ''; ?>
                    <a class="<?php echo $active; ?>" href="<?php echo $l['href']; ?>">
                        <span style="margin-right:6px;opacity:.85;"><?php echo $l['icon']; ?></span><?php echo $l['label']; ?>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Users</div>
                <?php foreach ($users as $l):
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
    if (!function_exists('render_admin_header')) {
        function render_admin_header(string $title): void {
            ?>
            <header class="app-header">
                <div style="display:flex;align-items:center;gap:10px;">
                    <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                    <div class="header-title"><?php echo htmlspecialchars($title); ?></div>
                </div>
                <div class="text-muted" style="font-size:13px;">
                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></strong>
                </div>
            </header>
            <?php
        }
    }
