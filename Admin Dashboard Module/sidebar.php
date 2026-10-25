<?php
function admin_sidebar($active) {
    ?>
    <aside class="sidebar">
        <div class="sidebar__brand">
            <a href="../Admin_Dashboard_Module/admin_dashboard.php" class="brand">
                <span class="brand__mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
                <span class="brand__text"><strong>Aurora Library</strong><span>Admin Console</span></span>
            </a>
        </div>

        <div class="sidebar__group">
            <span class="sidebar__label">Overview</span>
            <a href="../Admin_Dashboard_Module/admin_dashboard.php" class="sidebar__link<?php echo $active==='dashboard'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                📊 Dashboard
            </a>
            <a href="../Admin_Dashboard_Module/view_issued_book.php" class="sidebar__link<?php echo $active==='issued'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                📚 Issued Books
            </a>
            <a href="../Admin_Dashboard_Module/manage_requests.php" class="sidebar__link<?php echo $active==='requests'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                📋 Book Requests
            </a>
            <a href="../Admin_Dashboard_Module/manage_fines.php" class="sidebar__link<?php echo $active==='fines'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a2.5 2.5 0 0 0 0 5h5a2.5 2.5 0 0 1 0 5H6"/></svg>
                💰 Fine Management
            </a>
            <a href="../Admin_Dashboard_Module/regusers.php" class="sidebar__link<?php echo $active==='members'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                👥 Members
            </a>
        </div>

        <div class="sidebar__group">
            <span class="sidebar__label">Catalog</span>
            <a href="../Add_books_lms/add_book.php" class="sidebar__link<?php echo $active==='addbook'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                ➕ Add Book
            </a>
            <a href="../Add_books_lms/manage_book.php" class="sidebar__link<?php echo $active==='managebook'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                📚 Manage Books
            </a>
            <a href="../Add_books_lms/regbooks.php" class="sidebar__link<?php echo $active==='regbooks'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                📖 Book Catalog
            </a>
            <a href="../Manage_Authors/add_author.php" class="sidebar__link<?php echo $active==='addauthor'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                ✍️ Add Author
            </a>
            <a href="../Manage_Authors/manage_author.php" class="sidebar__link<?php echo $active==='manageauthor'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                👨‍💼 Manage Authors
            </a>
            <a href="../Manage_Authors/Regauthor.php" class="sidebar__link<?php echo $active==='regauthor'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                👥 All Authors
            </a>
            <a href="../Manage_Book_Categories/add_cat.php" class="sidebar__link<?php echo $active==='addcat'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                🏷️ Add Category
            </a>
            <a href="../Manage_Book_Categories/manage_cat.php" class="sidebar__link<?php echo $active==='managecat'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                🗂️ Manage Categories
            </a>
            <a href="../Issue_book_Module/issue_book.php" class="sidebar__link<?php echo $active==='issue'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                📤 Issue Book
            </a>
        </div>

        <div class="sidebar__group">
            <span class="sidebar__label">Account</span>
            <a href="../Admin_Dashboard_Module/view_profile.php" class="sidebar__link<?php echo $active==='view'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                👤 My Profile
            </a>
            <a href="../Admin_Dashboard_Module/edit_profile.php" class="sidebar__link<?php echo $active==='edit'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                ✏️ Edit Profile
            </a>
            <a href="../Admin_Dashboard_Module/change_password.php" class="sidebar__link<?php echo $active==='password'?' is-active':''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                🔒 Change Password
            </a>
        </div>

        <div class="sidebar__foot">
            <div class="sidebar__user">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)); ?></div>
                <div class="sidebar__user-meta"><strong><?php echo $_SESSION['name']; ?></strong><span><?php echo $_SESSION['email']; ?></span></div>
            </div>
            <a href="/lms/logout.php" class="btn btn--ghost btn--sm" style="margin-top:14px;width:100%;border-color:rgba(255,255,255,0.12);color:rgba(255,255,255,0.7);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                🚪 Sign Out
            </a>
        </div>
    </aside>
    <?php
}
?>