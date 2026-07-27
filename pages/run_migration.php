<?php
// pages/run_migration.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Sinkronisasi Database';
$migration_status = '';
$is_success = true;

try {
    // Jalankan migrations
    require_once __DIR__ . '/../config/migrations.php';
    $migration_status = "Migration berhasil dijalankan pada " . date('d-m-Y H:i:s');
} catch (Exception $e) {
    $migration_status = "Gagal menjalankan migration: " . $e->getMessage();
    $is_success = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sinkronisasi Database - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="panel" style="max-width:600px; margin-top:20px;">
                <h3>Status Sinkronisasi</h3>
                <div class="alert <?= $is_success ? 'alert-success' : 'alert-error' ?>" style="<?= $is_success ? 'background:#d1fae5; color:#065f46;' : 'background:#fee2e2; color:#991b1b;' ?> font-weight:700; margin-bottom:20px; padding:12px; border-radius:8px;">
                    <?= htmlspecialchars($migration_status) ?>
                </div>
                <p style="color:var(--color-text-muted); font-size:13px; line-height:1.5; margin-bottom:20px;">
                    Struktur database, penyesuaian kolom tabel, dan sinkronisasi data telah berhasil diperiksa dan diselaraskan ke versi terbaru yang didukung oleh sistem.
                </p>
                <a href="pengaturan.php" class="btn btn-primary">← Kembali ke Pengaturan</a>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </main>
    </div>
</body>
</html>
