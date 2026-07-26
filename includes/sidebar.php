<?php
// includes/sidebar.php
// Sidebar menu, dipakai di semua halaman internal
require_once __DIR__ . '/../config/database.php';

$current = basename($_SERVER['PHP_SELF']);
$pengaturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();
$logo_path = !empty($pengaturan['logo_url']) ? '../' . $pengaturan['logo_url'] : null;
$nama_toko_sidebar = $pengaturan['nama_toko'] ?? 'Sunshine Florist';

function nav_active($files, $current) {
    if (is_array($files)) {
        return in_array($current, $files) ? 'active' : '';
    }
    return $files === $current ? 'active' : '';
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <?php if ($logo_path): ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" class="sidebar-logo" style="width:38px; height:38px; border-radius:12px; object-fit:cover;">
        <?php else: ?>
            <div class="sidebar-logo-square">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                    <path d="M12 6v12M6 12h12"/>
                </svg>
            </div>
        <?php endif; ?>
        <span><?= htmlspecialchars($nama_toko_sidebar) ?></span>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= nav_active('dashboard.php', $current) ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Dashboard
        </a>
        <a href="jual_langsung.php" class="nav-item <?= nav_active('jual_langsung.php', $current) ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Input Penjualan
        </a>
        <a href="pesanan.php" class="nav-item <?= nav_active(['pesanan.php', 'pesanan_tambah.php'], $current) ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
            Pesanan
        </a>
        <a href="produk_resep.php" class="nav-item <?= nav_active('produk_resep.php', $current) ?>">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            Produk & Resep
        </a>
        <a href="stok_bahan.php" class="nav-item <?= nav_active(['stok_bahan.php', 'tambah_stok.php'], $current) ?>">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Stok Bahan
        </a>
        <a href="operasional.php" class="nav-item <?= nav_active('operasional.php', $current) ?>">
            <svg viewBox="0 0 24 24"><path d="M21 4H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            Kas Operasional
        </a>
        <a href="cash_overflow.php" class="nav-item <?= nav_active('cash_overflow.php', $current) ?>">
            <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
            Cash Overflow
        </a>
        <a href="laporan.php" class="nav-item <?= nav_active('laporan.php', $current) ?>">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            Laporan
        </a>
        <a href="pengaturan.php" class="nav-item <?= nav_active('pengaturan.php', $current) ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            Pengaturan
        </a>
    </nav>

    <a href="../logout.php" class="nav-item nav-logout">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        Logout
    </a>
</aside>