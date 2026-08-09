<?php
// actions/batal_po.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $riwayat_id = $_POST['riwayat_id'] ?? null;

    if ($riwayat_id) {
        $stmt = $pdo->prepare("SELECT * FROM riwayat_stok WHERE id = ? AND status_po = 'pending_po'");
        $stmt->execute([$riwayat_id]);
        $po = $stmt->fetch();

        if ($po) {
            $stmt_del = $pdo->prepare("DELETE FROM riwayat_stok WHERE id = ?");
            $stmt_del->execute([$riwayat_id]);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'PO Belanja Bahan berhasil dibatalkan. Pengeluaran kas otomatis dikembalikan.'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Data PO tidak ditemukan atau statusnya sudah berubah.'
            ];
        }
    }
}

header('Location: ../pages/stok_bahan.php');
exit;
