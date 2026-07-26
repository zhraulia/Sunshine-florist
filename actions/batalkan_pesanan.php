<?php
// actions/batalkan_pesanan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$pesanan_id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ? AND status = 'menunggu'");
$stmt->execute([$pesanan_id]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Pesanan tidak ditemukan atau sudah diproses.'];
    header('Location: ../pages/pesanan.php');
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT * FROM pesanan_bahan WHERE pesanan_id = ?");
    $stmt->execute([$pesanan_id]);
    $bahan_dicadangkan = $stmt->fetchAll();

    foreach ($bahan_dicadangkan as $pb) {
        // Lepas cadangan, stok tersedia balik seperti semula
        $stmt2 = $pdo->prepare("UPDATE bahan_baku SET stok_dicadangkan = stok_dicadangkan - ? WHERE id = ?");
        $stmt2->execute([$pb['jumlah_dicadangkan'], $pb['bahan_baku_id']]);

        $stmt3 = $pdo->prepare("
            INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
            VALUES (?, 'dilepas', ?, ?)
        ");
        $stmt3->execute([$pb['bahan_baku_id'], $pb['jumlah_dicadangkan'], "Dibatalkan dari pesanan #{$pesanan_id}"]);
    }

    $stmt = $pdo->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?");
    $stmt->execute([$pesanan_id]);

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pesanan dibatalkan, stok cadangan sudah dilepas.'];
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal membatalkan: ' . $e->getMessage()];
}

header('Location: ../pages/pesanan.php');
exit;