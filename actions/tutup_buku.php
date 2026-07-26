<?php
// actions/tutup_buku.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$bulan = trim($_POST['bulan'] ?? $_GET['bulan'] ?? '');

if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Format bulan tidak valid.'];
    header('Location: ../pages/laporan.php');
    exit;
}

try {
    // Hitung total nilai stok saat ini
    $stmt = $pdo->query("SELECT SUM(stok_fisik * harga_beli) FROM bahan_baku");
    $total_nilai = (float)$stmt->fetchColumn();

    // Simpan ke snapshot_persediaan
    $stmt_save = $pdo->prepare("
        INSERT INTO snapshot_persediaan (bulan, nilai_persediaan, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE nilai_persediaan = ?, created_at = NOW()
    ");
    $stmt_save->execute([$bulan, $total_nilai, $total_nilai]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => "Tutup buku bulan " . date('F Y', strtotime($bulan . '-01')) . " berhasil dikunci dengan nilai persediaan Rp" . number_format($total_nilai, 0, ',', '.')];
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal melakukan tutup buku: ' . $e->getMessage()];
}

header('Location: ../pages/laporan.php?mode=bulanan&bulan=' . urlencode($bulan));
exit;
