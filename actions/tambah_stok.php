<?php
// actions/tambah_stok.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$bahan_baku_id     = $_POST['bahan_baku_id'] ?? null;
$jumlah            = (float) ($_POST['jumlah'] ?? 0);
$satuan_pilihan    = $_POST['satuan_pilihan'] ?? 'dasar';
$harga_total       = (float) ($_POST['harga_total'] ?? 0);
$biaya_ongkir      = (float) ($_POST['biaya_ongkir'] ?? 0);
$metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
$status_po         = $_POST['status_po'] ?? 'lunas';
$keterangan        = trim($_POST['keterangan'] ?? '');

if (!$bahan_baku_id || $jumlah <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Jumlah stok tidak valid.'];
    header("Location: ../pages/tambah_stok.php?bahan_id={$bahan_baku_id}");
    exit;
}

$jumlah_dasar = $jumlah;

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

$total_biaya = $harga_total + $biaya_ongkir;

$pdo->beginTransaction();
try {
    // Jika LUNAS: Tambah stok fisik sekarang
    if ($status_po === 'lunas') {
        $stmt = $pdo->prepare("UPDATE bahan_baku SET stok_fisik = stok_fisik + ? WHERE id = ?");
        $stmt->execute([$jumlah_dasar, $bahan_baku_id]);
    }

    // Update harga beli dengan metode rata-rata tertimbang (Weighted Average Cost)
    if ($harga_total > 0 && $jumlah_dasar > 0) {
        $stmt_cur = $pdo->prepare("SELECT stok_fisik, harga_beli FROM bahan_baku WHERE id = ?");
        $stmt_cur->execute([$bahan_baku_id]);
        $bhn = $stmt_cur->fetch();
        
        $current_stok = (float)($bhn['stok_fisik'] ?? 0);
        $current_price = (float)($bhn['harga_beli'] ?? 0);
        
        $modal_lama = $current_stok * $current_price;
        $modal_baru = $harga_total; // Nilai murni belanja di luar ongkir
        
        $total_stok_baru = $current_stok + $jumlah_dasar;
        if ($total_stok_baru > 0) {
            $harga_rata_rata = ($modal_lama + $modal_baru) / $total_stok_baru;
            $stmt_up = $pdo->prepare("UPDATE bahan_baku SET harga_beli = ? WHERE id = ?");
            $stmt_up->execute([$harga_rata_rata, $bahan_baku_id]);
        }
    }

    // Catat ke riwayat_stok dengan rincian biaya, ongkir, metode, dan status PO
    $stmt = $pdo->prepare("
        INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, metode_pembayaran, biaya_ongkir, total_biaya, status_po, keterangan)
        VALUES (?, 'masuk', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$bahan_baku_id, $jumlah_dasar, $metode_pembayaran, $biaya_ongkir, $total_biaya, $status_po, $keterangan]);

    $pdo->commit();

    if ($status_po === 'pending_po') {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Belanja PO berhasil dicatat (Uang keluar: Rp" . number_format($total_biaya, 0, ',', '.') . "). Barang akan masuk stok fisik setelah dikonfirmasi diterima."];
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Stok berhasil ditambahkan (+{$jumlah_dasar}). Total biaya: Rp" . number_format($total_biaya, 0, ',', '.')];
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal menambah stok: ' . $e->getMessage()];
}

header('Location: ../pages/stok_bahan.php');
exit;