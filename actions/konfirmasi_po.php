<?php
// actions/konfirmasi_po.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/stok_bahan.php');
    exit;
}

$riwayat_id = (int) ($_POST['riwayat_id'] ?? 0);

if ($riwayat_id > 0) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM riwayat_stok WHERE id = ? AND status_po = 'pending_po'");
        $stmt->execute([$riwayat_id]);
        $po = $stmt->fetch();

        if ($po) {
            // Tambah stok fisik bahan
            $stmt = $pdo->prepare("UPDATE bahan_baku SET stok_fisik = stok_fisik + ? WHERE id = ?");
            $stmt->execute([$po['jumlah'], $po['bahan_baku_id']]);

            // Update status PO menjadi selesai_po dan perbarui waktu ke waktu saat ini (barang sampai)
            $stmt = $pdo->prepare("UPDATE riwayat_stok SET status_po = 'selesai_po', created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$riwayat_id]);

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Barang PO berhasil dikonfirmasi diterima! Stok fisik telah bertambah.'];
        } else {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Data PO tidak ditemukan atau sudah dikonfirmasi.'];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal mengonfirmasi PO: ' . $e->getMessage()];
    }
}

header('Location: ../pages/stok_bahan.php');
exit;
