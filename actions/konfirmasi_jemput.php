<?php
// actions/konfirmasi_jemput.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$pesanan_id = $_POST['id'] ?? $_GET['id'] ?? null;
$metode_pelunasan = $_POST['metode_pembayaran_pelunasan'] ?? $_GET['metode_pembayaran_pelunasan'] ?? null;

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
    // 1. Ambil bahan yang dicadangkan buat pesanan ini
    $stmt = $pdo->prepare("SELECT * FROM pesanan_bahan WHERE pesanan_id = ?");
    $stmt->execute([$pesanan_id]);
    $bahan_dicadangkan = $stmt->fetchAll();

    $total_modal = 0;

    foreach ($bahan_dicadangkan as $pb) {
        // Ambil harga beli buat hitung modal
        $stmt2 = $pdo->prepare("SELECT harga_beli FROM bahan_baku WHERE id = ?");
        $stmt2->execute([$pb['bahan_baku_id']]);
        $harga_beli = $stmt2->fetchColumn();
        $total_modal += $harga_beli * $pb['jumlah_dicadangkan'];

        // Potong stok fisik permanen + lepas cadangan
        $stmt3 = $pdo->prepare("
            UPDATE bahan_baku
            SET stok_fisik = stok_fisik - ?, stok_dicadangkan = stok_dicadangkan - ?
            WHERE id = ?
        ");
        $stmt3->execute([$pb['jumlah_dicadangkan'], $pb['jumlah_dicadangkan'], $pb['bahan_baku_id']]);

        // Catat riwayat stok keluar
        $stmt4 = $pdo->prepare("
            INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
            VALUES (?, 'keluar', ?, ?)
        ");
        $stmt4->execute([$pb['bahan_baku_id'], $pb['jumlah_dicadangkan'], "Terpakai untuk pesanan #{$pesanan_id} diambil"]);
    }

    // 1b. Ambil item pesanan + tambahkan biaya operasional & jasa (tersimpan per item, termasuk item custom)
        $stmt = $pdo->prepare("SELECT * FROM detail_pesanan WHERE pesanan_id = ?");
        $stmt->execute([$pesanan_id]);
        $items = $stmt->fetchAll();

        foreach ($items as $it) {
            $total_modal += ((float) $it['biaya_operasional'] + (float) $it['biaya_jasa']) * $it['qty'];
        }

    // 1c. Periksa apakah ongkir disesuaikan saat pelunasan (jika tipe 'toko')
    $ongkir_nominal = isset($_POST['ongkir_nominal']) ? (float)$_POST['ongkir_nominal'] : null;
    $ongkir_metode_kurir = isset($_POST['ongkir_metode_kurir']) ? $_POST['ongkir_metode_kurir'] : null;

    if ($ongkir_nominal !== null && $pesanan['ongkir_tipe'] === 'toko') {
        $stmt_up = $pdo->prepare("UPDATE pesanan SET ongkir_nominal = ?, ongkir_metode_kurir = ? WHERE id = ?");
        $stmt_up->execute([$ongkir_nominal, $ongkir_metode_kurir, $pesanan_id]);
        $pesanan['ongkir_nominal'] = $ongkir_nominal;
        $pesanan['ongkir_metode_kurir'] = $ongkir_metode_kurir;
    }

    // 2. Update status pesanan
    $stmt = $pdo->prepare("UPDATE pesanan SET status = 'diambil' WHERE id = ?");
    $stmt->execute([$pesanan_id]);

    // 3. Insert ke penjualan
    $profit = $pesanan['total_harga'] - $total_modal;
    $metode_pembayaran_final = !empty($metode_pelunasan) ? $metode_pelunasan : (!empty($pesanan['metode_pembayaran']) ? $pesanan['metode_pembayaran'] : 'cash');
    $stmt = $pdo->prepare("
        INSERT INTO penjualan (pesanan_id, sumber, total_harga, total_modal, profit, metode_pembayaran, ongkir_tipe, ongkir_nominal, ongkir_metode_kurir)
        VALUES (?, 'pesanan', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $pesanan_id, 
        $pesanan['total_harga'], 
        $total_modal, 
        $profit, 
        $metode_pembayaran_final,
        $pesanan['ongkir_tipe'] ?? 'kurir',
        $pesanan['ongkir_nominal'] ?? 0,
        $pesanan['ongkir_metode_kurir'] ?? null
    ]);
    $penjualan_id = $pdo->lastInsertId();

    // 4. Insert detail_penjualan (basis laporan bahan paling laku)
    $stmt = $pdo->prepare("
        INSERT INTO detail_penjualan (penjualan_id, bahan_baku_id, jumlah)
        VALUES (?, ?, ?)
    ");
    foreach ($bahan_dicadangkan as $pb) {
        $stmt->execute([$penjualan_id, $pb['bahan_baku_id'], $pb['jumlah_dicadangkan']]);
    }

    // 5. Insert detail_penjualan_item (basis struk + laporan produk terlaris)
    $stmt_item = $pdo->prepare("
        INSERT INTO detail_penjualan_item (penjualan_id, produk_id, nama_item, harga, qty, biaya_operasional, biaya_jasa)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($items as $it) {
        $biaya_op = (float)($it['biaya_operasional'] ?? 0);
        $biaya_js = (float)($it['biaya_jasa'] ?? 0);
        $stmt_item->execute([$penjualan_id, $it['produk_id'], $it['nama_item'], $it['harga'], $it['qty'], $biaya_op, $biaya_js]);
    }

    // Juga isi detail_penjualan dengan produk_id (buat query "produk terlaris" di dashboard)
    $stmt_dp = $pdo->prepare("
        INSERT INTO detail_penjualan (penjualan_id, produk_id, jumlah)
        VALUES (?, ?, ?)
    ");
    foreach ($items as $it) {
        if ($it['produk_id']) {
            $stmt_dp->execute([$penjualan_id, $it['produk_id'], $it['qty']]);
        }
    }

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pesanan berhasil dikonfirmasi diambil & tercatat sebagai penjualan.'];
    $redirect = $_SERVER['HTTP_REFERER'] ?? '../pages/pesanan.php';
    header("Location: $redirect");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal konfirmasi: ' . $e->getMessage()];
    header('Location: ../pages/pesanan.php');
    exit;
}