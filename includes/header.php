<?php
// includes/header.php
// Bagian atas: judul halaman + info user
require_once __DIR__ . '/../config/database.php';

$page_title = $page_title ?? 'Dashboard';
$pengaturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();
?>
<style>
    :root {
        --color-primary: <?= htmlspecialchars($pengaturan['warna_primary'] ?? '#D4A5AA') ?>;
        --color-accent: <?= htmlspecialchars($pengaturan['warna_aksen'] ?? '#B76E79') ?>;
    }
</style>
<div class="topbar">
    <h1 class="topbar-title"><?= htmlspecialchars($page_title) ?></h1>
    <div class="topbar-user">
        <div class="avatar"><?= strtoupper(substr($pengaturan['nama_toko'] ?? 'S', 0, 1)) ?></div>
        <div class="topbar-user-info">
            <strong><?= htmlspecialchars($pengaturan['nama_toko'] ?? 'Sunshine Florist') ?></strong>
            <span>Admin</span>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">