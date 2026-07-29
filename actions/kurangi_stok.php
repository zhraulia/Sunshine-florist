<?php
// actions/kurangi_stok.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$bahan_baku_id  = $_POST['bahan_baku_id'] ?? null;
$jumlah         = (float) ($_POST['jumlah'] ?? 0);
$satuan_pilihan = $_POST['satuan_pilihan'] ?? 'dasar';
$keterangan     = trim($_POST['keterangan'] ?? '');

if (!$bahan_baku_id || $jumlah <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Jumlah stok yang dikurangi tidak valid.'];
    header("Location: ../pages/kurangi_stok.php?bahan_id={$bahan_baku_id}");
    exit;
}

$jumlah_dasar = $jumlah;

// Hitung berdasarkan satuan konversi pilihan
if (str_starts_with($satuan_pilihan, 'master:')) {
    $master_id = (int) str_replace('master:', '', $satuan_pilihan);

    $stmt = $pdo->prepare("SELECT isi_per_satuan FROM konversi_master WHERE id = ?");
    $stmt->execute([$master_id]);
    $master = $stmt->fetch();

    if ($master && (float)$master['isi_per_satuan'] > 0) {
        $jumlah_dasar = $jumlah * (float)$master['isi_per_satuan'];
    }
} elseif (str_starts_with($satuan_pilihan, 'konversi:')) {
    $konversi_id = (int) str_replace('konversi:', '', $satuan_pilihan);

    $stmt = $pdo->prepare("SELECT isi_per_satuan FROM konversi_satuan WHERE id = ? AND bahan_baku_id = ?");
    $stmt->execute([$konversi_id, $bahan_baku_id]);
    $konversi = $stmt->fetch();

    if ($konversi && (float)$konversi['isi_per_satuan'] > 0) {
        $jumlah_dasar = $jumlah * (float)$konversi['isi_per_satuan'];
    }
}

$keterangan = 'Pengurangan stok';

$pdo->beginTransaction();
try {
    // Ambil stok saat ini
    $stmt = $pdo->prepare("SELECT nama, stok_fisik, satuan_dasar FROM bahan_baku WHERE id = ?");
    $stmt->execute([$bahan_baku_id]);
    $bahan = $stmt->fetch();

    if (!$bahan) {
        throw new Exception('Bahan tidak ditemukan.');
    }

    $stok_sekarang = (float)($bahan['stok_fisik'] ?? 0);
    
    // Validasi agar stok tidak menjadi negatif
    if ($jumlah_dasar > $stok_sekarang) {
        throw new Exception("Jumlah pengurangan ({$jumlah_dasar} {$bahan['satuan_dasar']}) melebihi stok yang tersedia saat ini (Maksimal: {$stok_sekarang} {$bahan['satuan_dasar']}).");
    }

    // Update stok fisik bahan baku
    $stmt = $pdo->prepare("UPDATE bahan_baku SET stok_fisik = stok_fisik - ? WHERE id = ?");
    $stmt->execute([$jumlah_dasar, $bahan_baku_id]);

    // Catat ke riwayat_stok
    $stmt = $pdo->prepare("
        INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
        VALUES (?, 'keluar', ?, ?)
    ");
    $stmt->execute([$bahan_baku_id, $jumlah_dasar, $keterangan]);

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Stok {$bahan['nama']} berhasil dikurangi sebanyak {$jumlah_dasar} {$bahan['satuan_dasar']}."];
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    header("Location: ../pages/kurangi_stok.php?bahan_id={$bahan_baku_id}");
    exit;
}

header('Location: ../pages/stok_bahan.php');
exit;
