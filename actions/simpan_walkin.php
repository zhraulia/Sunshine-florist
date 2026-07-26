<?php
// actions/simpan_walkin.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$nama_pembeli = trim($_POST['nama_pembeli'] ?? '');
$kontak       = trim($_POST['kontak'] ?? '');
$items        = $_POST['items'] ?? [];
$bahan        = $_POST['bahan'] ?? [];

if (empty($items)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Minimal harus ada 1 item terjual.'];
    header('Location: ../pages/jual_langsung.php');
    exit;
}

$total_harga = 0;
foreach ($items as $item) {
    $total_harga += ((float) $item['qty']) * ((float) $item['harga']);
}

$pdo->beginTransaction();
try {
    // 1. Validasi stok fisik cukup (walk-in gak ada tahap cadangan, jadi cek langsung ke stok tersedia)
    $total_modal = 0;
    $harga_beli_map = [];

    foreach ($bahan as $b) {
        if (empty($b['bahan_baku_id']) || (float) $b['jumlah'] <= 0) continue;

        $stmt = $pdo->prepare("SELECT nama, stok_fisik, stok_dicadangkan, harga_beli FROM bahan_baku WHERE id = ? FOR UPDATE");
        $stmt->execute([$b['bahan_baku_id']]);
        $bhn = $stmt->fetch();

        if (!$bhn) {
            throw new Exception("Bahan tidak ditemukan.");
        }

        $stok_tersedia = $bhn['stok_fisik'] - $bhn['stok_dicadangkan'];
        if ($stok_tersedia < (float) $b['jumlah']) {
            throw new Exception("Stok '{$bhn['nama']}' tidak cukup. Tersedia: {$stok_tersedia}, dibutuhkan: {$b['jumlah']}.");
        }

        $total_modal += $bhn['harga_beli'] * (float) $b['jumlah'];
    }

    // 2. Potong stok fisik langsung (permanen, tanpa lewat tahap dicadangkan)
    $stmt_update = $pdo->prepare("UPDATE bahan_baku SET stok_fisik = stok_fisik - ? WHERE id = ?");
    $stmt_riwayat = $pdo->prepare("
        INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
        VALUES (?, 'keluar', ?, ?)
    ");

    foreach ($bahan as $b) {
        if (empty($b['bahan_baku_id']) || (float) $b['jumlah'] <= 0) continue;
        $stmt_update->execute([$b['jumlah'], $b['bahan_baku_id']]);
        $stmt_riwayat->execute([$b['bahan_baku_id'], $b['jumlah'], "Terpakai untuk transaksi walk-in" . ($nama_pembeli ? " ({$nama_pembeli})" : '')]);
    }

   // 2b. Tambahkan biaya operasional & jasa ke modal — diambil langsung dari input form (berlaku juga untuk item custom)
foreach ($items as $item) {
    $biaya_operasional = (float) ($item['biaya_operasional'] ?? 0);
    $biaya_jasa = (float) ($item['biaya_jasa'] ?? 0);
    $total_modal += ($biaya_operasional + $biaya_jasa) * (float) $item['qty'];
}

    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';

    // 3. Insert penjualan (sumber = walkin, pesanan_id = NULL)
    $profit = $total_harga - $total_modal;
    $stmt = $pdo->prepare("
        INSERT INTO penjualan (pesanan_id, sumber, nama_pembeli, kontak_pembeli, total_harga, total_modal, profit, metode_pembayaran)
        VALUES (NULL, 'walkin', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nama_pembeli !== '' ? $nama_pembeli : null,
        $kontak !== '' ? $kontak : null,
        $total_harga,
        $total_modal,
        $profit,
        $metode_pembayaran
    ]);
    $penjualan_id = $pdo->lastInsertId();

    // 4. Insert detail_penjualan (bahan) — basis laporan bahan paling laku
    $stmt_dp_bahan = $pdo->prepare("
        INSERT INTO detail_penjualan (penjualan_id, bahan_baku_id, jumlah)
        VALUES (?, ?, ?)
    ");
    foreach ($bahan as $b) {
        if (empty($b['bahan_baku_id']) || (float) $b['jumlah'] <= 0) continue;
        $stmt_dp_bahan->execute([$penjualan_id, $b['bahan_baku_id'], $b['jumlah']]);
    }

    // 5. Insert detail_penjualan (produk) — basis laporan produk terlaris
    $stmt_dp_produk = $pdo->prepare("
        INSERT INTO detail_penjualan (penjualan_id, produk_id, jumlah)
        VALUES (?, ?, ?)
    ");
    // 6. Insert detail_penjualan_item — basis struk
    $stmt_item = $pdo->prepare("
        INSERT INTO detail_penjualan_item (penjualan_id, produk_id, nama_item, harga, qty, biaya_operasional, biaya_jasa)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $produk_id = !empty($item['produk_id']) ? $item['produk_id'] : null;
        $biaya_op = (float)($item['biaya_operasional'] ?? 0);
        $biaya_js = (float)($item['biaya_jasa'] ?? 0);
        $stmt_item->execute([$penjualan_id, $produk_id, $item['nama_item'], $item['harga'], $item['qty'], $biaya_op, $biaya_js]);

        if ($produk_id) {
            $stmt_dp_produk->execute([$penjualan_id, $produk_id, $item['qty']]);
        }
    }

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Transaksi walk-in berhasil disimpan.'];
    header("Location: ../pages/cetak_struk.php?id={$penjualan_id}&via=walkin");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()];
    header('Location: ../pages/jual_langsung.php');
    exit;
}