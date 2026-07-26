<?php
// actions/simpan_pesanan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Check if 'metode_pengiriman' column exists in 'pesanan' table, if not, add it
try {
    $pdo->query("SELECT metode_pengiriman FROM pesanan LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE pesanan ADD COLUMN metode_pengiriman VARCHAR(50) DEFAULT 'jemput'");
}

$nama_pemesan       = trim($_POST['nama_pemesan'] ?? '');
$kontak             = trim($_POST['kontak'] ?? '');
$tanggal_jemput     = $_POST['tanggal_jemput'] ?? '';
$jam_jemput         = $_POST['jam_jemput'] ?? '';
$metode_pengiriman  = trim($_POST['metode_pengiriman'] ?? 'jemput');
$catatan            = trim($_POST['catatan'] ?? '');
$dp                 = (float) ($_POST['dp'] ?? 0);
$items              = $_POST['items'] ?? [];
$bahan              = $_POST['bahan'] ?? [];

if ($nama_pemesan === '' || $tanggal_jemput === '' || $jam_jemput === '' || empty($items)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Data pesanan tidak lengkap.'];
    header('Location: ../pages/pesanan_tambah.php');
    exit;
}

// Hitung total harga dari item
$total_harga = 0;
foreach ($items as $item) {
    $total_harga += ((float) $item['qty']) * ((float) $item['harga']);
}

if ($dp > $total_harga) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Uang muka (DP) tidak boleh lebih besar dari total harga pesanan (Rp' . number_format($total_harga, 0, ',', '.') . ').'];
    header('Location: ../pages/pesanan_tambah.php');
    exit;
}

$pdo->beginTransaction();
try {
    // 1. Validasi stok tersedia CUKUP untuk semua bahan yang mau dicadangkan
    foreach ($bahan as $b) {
        if (empty($b['bahan_baku_id']) || (float) $b['jumlah'] <= 0) continue;

        $stmt = $pdo->prepare("SELECT nama, stok_fisik, stok_dicadangkan FROM bahan_baku WHERE id = ? FOR UPDATE");
        $stmt->execute([$b['bahan_baku_id']]);
        $bhn = $stmt->fetch();

        if (!$bhn) {
            throw new Exception("Bahan tidak ditemukan.");
        }

        $stok_tersedia = $bhn['stok_fisik'] - $bhn['stok_dicadangkan'];
        if ($stok_tersedia < (float) $b['jumlah']) {
            throw new Exception("Stok '{$bhn['nama']}' tidak cukup. Tersedia: {$stok_tersedia}, dibutuhkan: {$b['jumlah']}.");
        }
    }

    // 2. Insert pesanan
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
    $ongkir_tipe = trim($_POST['ongkir_tipe'] ?? 'kurir');
    $ongkir_nominal = 0.0;
    $ongkir_metode_kurir = null;
    
    if ($metode_pengiriman === 'delivery') {
        if ($ongkir_tipe === 'toko') {
            $ongkir_nominal = (float)($_POST['ongkir_nominal'] ?? 0);
            $ongkir_metode_kurir = $_POST['ongkir_metode_kurir'] ?? 'cash';
        }
    } else {
        $ongkir_tipe = 'kurir';
    }

    $stmt = $pdo->prepare("
        INSERT INTO pesanan (nama_pemesan, kontak, tanggal_jemput, jam_jemput, catatan, dp, total_harga, metode_pengiriman, metode_pembayaran, ongkir_tipe, ongkir_nominal, ongkir_metode_kurir)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nama_pemesan, $kontak, $tanggal_jemput, $jam_jemput, $catatan, $dp, $total_harga, $metode_pengiriman, $metode_pembayaran, $ongkir_tipe, $ongkir_nominal, $ongkir_metode_kurir]);
    $pesanan_id = $pdo->lastInsertId();

    // 3. Insert detail_pesanan (item)
   // 3. Insert detail_pesanan (item) — termasuk biaya operasional & jasa per item
$stmt = $pdo->prepare("
    INSERT INTO detail_pesanan (pesanan_id, produk_id, nama_item, harga, qty, biaya_operasional, biaya_jasa)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
foreach ($items as $item) {
    $produk_id = (!empty($item['produk_id']) && $item['produk_id'] !== 'null') ? $item['produk_id'] : null;
    $biaya_operasional = (float) ($item['biaya_operasional'] ?? 0);
    $biaya_jasa = (float) ($item['biaya_jasa'] ?? 0);
    $stmt->execute([$pesanan_id, $produk_id, $item['nama_item'], $item['harga'], $item['qty'], $biaya_operasional, $biaya_jasa]);
}

    // 4. Insert pesanan_bahan + update stok_dicadangkan + riwayat_stok
    $stmt_pb = $pdo->prepare("
        INSERT INTO pesanan_bahan (pesanan_id, bahan_baku_id, jumlah_dicadangkan)
        VALUES (?, ?, ?)
    ");
    $stmt_update = $pdo->prepare("
        UPDATE bahan_baku SET stok_dicadangkan = stok_dicadangkan + ? WHERE id = ?
    ");
    $stmt_riwayat = $pdo->prepare("
        INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
        VALUES (?, 'dicadangkan', ?, ?)
    ");

    foreach ($bahan as $b) {
        if (empty($b['bahan_baku_id']) || (float) $b['jumlah'] <= 0) continue;

        $stmt_pb->execute([$pesanan_id, $b['bahan_baku_id'], $b['jumlah']]);
        $stmt_update->execute([$b['jumlah'], $b['bahan_baku_id']]);
        $stmt_riwayat->execute([$b['bahan_baku_id'], $b['jumlah'], "Dicadangkan untuk pesanan #{$pesanan_id} ({$nama_pemesan})"]);
    }

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pesanan berhasil disimpan dan stok telah dicadangkan.'];
    header('Location: ../pages/pesanan.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()];
    header('Location: ../pages/pesanan_tambah.php');
    exit;
}