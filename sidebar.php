<?php
$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'Tidak Diketahui';
function sidebarLink($page, $icon, $label) {
    global $currentPage;
    $active = $currentPage === $page ? ' active' : '';
    return '<a class="nav-link' . $active . '" href="' . $page . '"><span class="nav-icon">' . $icon . '</span><span class="nav-text">' . $label . '</span></a>';
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">N</div>
            <div class="brand-text">
                <span class="sidebar-title">Aplikasi Nota</span>
                <small class="sidebar-subtitle">Panel admin</small>
            </div>
        </div>
        <button class="sidebar-toggle" id="toggleSidebar" type="button" aria-label="Toggle sidebar">☰</button>
    </div>
    <div class="sidebar-account px-3 pb-2">
        <div class="account-card">
            <div class="account-avatar"><?php echo htmlspecialchars(strtoupper(substr($username, 0, 1))); ?></div>
            <div class="account-info">
                <div class="account-label">Login sebagai</div>
                <div class="account-user"><?php echo htmlspecialchars($username); ?></div>
                <div class="account-role"><?php echo ucfirst(htmlspecialchars($userRole)); ?></div>
            </div>
        </div>
    </div>
    <nav class="nav flex-column mt-2">
        <div class="nav-section-label">Menu utama</div>
        <?php echo sidebarLink('index.php', '▣', 'Dashboard'); ?>
        <?php echo sidebarLink('input.php', '✎', 'Input Nota'); ?>
        <?php echo sidebarLink('lihat_nota.php', '▤', 'Lihat Nota'); ?>
        <?php echo sidebarLink('rekap_nota.php', '◫', 'Rekap Nota'); ?>
        <?php echo sidebarLink('pengaturan_project.php', '⚙', 'Pengaturan Project'); ?>
        <?php if ($userRole === 'superadmin'): ?>
            <?php echo sidebarLink('manajement_user.php', '👤', 'Manajement User'); ?>
        <?php endif; ?>
        <div class="nav-section-label mt-2">Akun</div>
        <a class="nav-link text-danger" href="logout.php"><span class="nav-icon">↩</span><span class="nav-text">Log Out</span></a>
    </nav>
</div>
