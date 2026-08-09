<?php
// actions/simpan_jual_bahan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/jual_bahan.php');
    exit;
}

$items = $_POST['items'] ?? [];
$nama_pembeli = trim($_POST['nama_pembeli'] ?? '');
$metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
$catatan = trim($_POST['catatan'] ?? '');

if (empty($items)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Harus memilih minimal 1 bahan.'];
    header('Location: ../pages/jual_bahan.php');
    exit;
}

$pdo->beginTransaction();

try {
    $total_harga = 0; // Omzet (Total Jual)
    $total_modal = 0; // HPP (Total Modal)

    // Validasi & Hitung Modal
    $bahan_processed = [];
    foreach ($items as $item) {
        $bahan_id = $item['bahan_baku_id'] ?? null;
        $qty = (float)($item['qty'] ?? 0);
        $harga_jual = (float)($item['harga_jual'] ?? 0);

        if (!$bahan_id || $qty <= 0) continue;

        // Ambil stok & modal terbaru
        $stmt_b = $pdo->prepare("SELECT nama, stok_fisik, stok_dicadangkan, harga_beli, satuan_dasar FROM bahan_baku WHERE id = ?");
        $stmt_b->execute([$bahan_id]);
        $bhn = $stmt_b->fetch();

        if (!$bhn) {
            throw new Exception("Bahan dengan ID {$bahan_id} tidak ditemukan.");
        }

        $stok_tersedia = $bhn['stok_fisik'] - $bhn['stok_dicadangkan'];
        if ($qty > $stok_tersedia) {
            throw new Exception("Stok '{$bhn['nama']}' tidak cukup. Tersedia: {$stok_tersedia}, dibutuhkan: {$qty}.");
        }

        $row_modal = $bhn['harga_beli'] * $qty;
        $row_harga = $harga_jual * $qty;

        $total_modal += $row_modal;
        $total_harga += $row_harga;

        $bahan_processed[] = [
            'id' => $bahan_id,
            'nama' => $bhn['nama'],
            'satuan' => $bhn['satuan_dasar'],
            'qty' => $qty,
            'harga_jual' => $harga_jual,
            'modal_per_unit' => $bhn['harga_beli']
        ];
    }

    if (empty($bahan_processed)) {
        throw new Exception("Tidak ada item bahan valid yang diproses.");
    }

    // 1. Insert ke tabel penjualan (sumber = eceran, pesanan_id = NULL)
    $profit = $total_harga - $total_modal;
    $stmt_penjualan = $pdo->prepare("
        INSERT INTO penjualan (pesanan_id, sumber, nama_pembeli, kontak_pembeli, total_harga, total_modal, profit, metode_pembayaran, catatan)
        VALUES (NULL, 'eceran', ?, NULL, ?, ?, ?, ?, ?)
    ");
    $stmt_penjualan->execute([
        $nama_pembeli !== '' ? $nama_pembeli : null,
        $total_harga,
        $total_modal,
        $profit,
        $metode_pembayaran,
        $catatan !== '' ? $catatan : null
    ]);
    $penjualan_id = $pdo->lastInsertId();

    // 2. Potong stok fisik, log ke riwayat_stok, & catat rincian item
    $stmt_potong = $pdo->prepare("UPDATE bahan_baku SET stok_fisik = stok_fisik - ? WHERE id = ?");
    
    $stmt_riwayat = $pdo->prepare("
        INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
        VALUES (?, 'keluar', ?, ?)
    ");

    $stmt_dp_bahan = $pdo->prepare("
        INSERT INTO detail_penjualan (penjualan_id, bahan_baku_id, jumlah)
        VALUES (?, ?, ?)
    ");

    $stmt_dp_item = $pdo->prepare("
        INSERT INTO detail_penjualan_item (penjualan_id, produk_id, nama_item, harga, qty, biaya_operasional, biaya_jasa)
        VALUES (?, NULL, ?, ?, ?, 0, 0)
    ");

    foreach ($bahan_processed as $bp) {
        // Potong stok
        $stmt_potong->execute([$bp['qty'], $bp['id']]);

        // Log riwayat stok keluar
        $keterangan_log = "Penjualan eceran: " . $bp['nama'] . " (" . $bp['qty'] . " " . $bp['satuan'] . ")";
        if ($nama_pembeli !== '') {
            $keterangan_log .= " - Pembeli: " . $nama_pembeli;
        }
        $stmt_riwayat->execute([$bp['id'], $bp['qty'], $keterangan_log]);

        // Catat detail_penjualan (bahan) untuk statistik laku
        $stmt_dp_bahan->execute([$penjualan_id, $bp['id'], $bp['qty']]);

        // Catat detail_penjualan_item untuk pembukuan itemized
        $nama_item_sold = $bp['nama'] . " (Eceran)";
        $stmt_dp_item->execute([$penjualan_id, $nama_item_sold, $bp['harga_jual'], $bp['qty']]);
    }

    $pdo->commit();
    $_SESSION['flash'] = [
        'type' => 'success', 
        'message' => 'Transaksi penjualan bahan eceran berhasil disimpan. Total: Rp' . number_format($total_harga, 0, ',', '.')
    ];
    header('Location: ../pages/jual_bahan.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()];
    header('Location: ../pages/jual_bahan.php');
    exit;
}
