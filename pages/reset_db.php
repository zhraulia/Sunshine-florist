<?php
// pages/reset_db.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Kosongkan Data Uji Coba';
$message = '';
$msg_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    try {
        $pdo->beginTransaction();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $tables = [
            'detail_penjualan_item',
            'detail_penjualan',
            'penjualan',
            'detail_pesanan',
            'pesanan_bahan',
            'pesanan',
            'riwayat_stok',
            'kas_operasional',
            'snapshot_persediaan',
            'resep_produk',
            'produk',
            'bahan_baku',
            'kategori_produk',
            'konversi_satuan',
            'konversi_master'
        ];

        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE $table");
        }

        // Reset settings to default
        $pdo->exec("UPDATE pengaturan SET nama_toko = 'Sunshine Florist', tagline = 'Your moments, our blooms', warna_primary = '#A43B22', warna_aksen = '#EADCD9', logo_url = NULL LIMIT 1");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $pdo->commit();

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Semua data uji coba berhasil dikosongkan! Struktur tabel, relasi database, dan akun login tetap aman.'
        ];
        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Gagal mengosongkan data database: ' . $e->getMessage();
        $msg_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kosongkan Data Uji Coba - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="panel" style="max-width:600px; margin-top:20px; border: 2px solid #A43B22;">
                <h3 style="color:#A43B22; display:flex; align-items:center; gap:8px;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="vertical-align:middle;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Peringatan Keras: Kosongkan Semua Data Uji Coba
                </h3>
                
                <div style="background:#fee2e2; color:#991b1b; padding:16px; border-radius:8px; font-weight:600; margin-bottom:20px; line-height:1.5;">
                    Tindakan ini akan mengosongkan SELURUH isi data berikut secara permanen:
                    <ul style="margin:8px 0 0 20px; font-weight:normal; text-align:left;">
                        <li>Semua data Bahan Baku & Stok Gudang</li>
                        <li>Semua data Kategori & Satuan Konversi</li>
                        <li>Semua data Produk & Resep</li>
                        <li>Semua Riwayat Penjualan & Pesanan Customer</li>
                        <li>Semua Catatan Kas Operasional & Cash Overflow</li>
                        <li>Riwayat Mutasi & Laporan Keuangan</li>
                    </ul>
                </div>

                <div style="background:#e0f2fe; color:#0369a1; padding:12px; border-radius:8px; font-weight:600; margin-bottom:20px; line-height:1.4;">
                    Struktur tabel database, kolom-kolom, relasi database, dan akun login admin saat ini TETAP AMAN dan tidak akan dihapus.
                </div>

                <p style="color:var(--color-text-muted); font-size:13px; line-height:1.6; margin-bottom:20px;">
                    Setelah dikosongkan, profil toko (nama toko, warna, logo) juga akan diatur ulang ke bawaan awal.
                </p>

                <form method="POST" onsubmit="return confirm('APAKAH ANDA YAKIN? Semua isi data katalog dan transaksi akan dihapus selamanya, tetapi struktur tabel database tetap aman.');">
                    <button type="submit" name="confirm_reset" class="btn btn-danger" style="background:#A43B22; border-color:#A43B22; font-weight:700; width:100%; padding:12px;">
                        YA, KOSONGKAN SEMUA ISI DATA SEKARANG
                    </button>
                    <a href="pengaturan.php" class="btn btn-secondary" style="display:block; text-align:center; margin-top:10px;">Batal</a>
                </form>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </main>
    </div>
</body>
</html>
