<?php
// actions/update_pesanan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Check if 'metode_pengiriman' column exists in 'pesanan' table, if not, add it
try {
    $pdo->query("SELECT metode_pengiriman FROM pesanan LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE pesanan ADD COLUMN metode_pengiriman VARCHAR(50) DEFAULT 'jemput'");
}

$id                 = $_POST['id'] ?? null;
$nama_pemesan       = trim($_POST['nama_pemesan'] ?? '');
$kontak             = trim($_POST['kontak'] ?? '');
$tanggal_jemput     = $_POST['tanggal_jemput'] ?? '';
$jam_jemput         = $_POST['jam_jemput'] ?? '';
$metode_pengiriman  = trim($_POST['metode_pengiriman'] ?? 'jemput');
$catatan            = trim($_POST['catatan'] ?? '');
$dp                 = (float) ($_POST['dp'] ?? 0);
$items              = $_POST['items'] ?? [];
$bahan              = $_POST['bahan'] ?? [];

if (!$id || $nama_pemesan === '' || $tanggal_jemput === '' || $jam_jemput === '' || empty($items)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Data pesanan tidak lengkap.'];
    header('Location: ../pages/pesanan_edit.php?id=' . $id);
    exit;
}

// Hitung total harga dari item
$total_harga = 0;
foreach ($items as $item) {
    $total_harga += ((float) $item['qty']) * ((float) $item['harga']);
}

if ($dp > $total_harga) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Uang muka (DP) tidak boleh lebih besar dari total harga pesanan (Rp' . number_format($total_harga, 0, ',', '.') . ').'];
    header('Location: ../pages/pesanan_edit.php?id=' . $id);
    exit;
}

$pdo->beginTransaction();
try {
    // 1. Lock pesanan dan pastikan status masih 'menunggu'
    $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $pesanan = $stmt->fetch();

    if (!$pesanan) {
        throw new Exception("Pesanan tidak ditemukan.");
    }
    if ($pesanan['status'] !== 'menunggu') {
        throw new Exception("Hanya pesanan berstatus 'Menunggu' yang dapat diubah.");
    }

    // 2. Lepas dan bersihkan cadangan bahan baku lama
    $stmt = $pdo->prepare("SELECT * FROM pesanan_bahan WHERE pesanan_id = ?");
    $stmt->execute([$id]);
    $old_bahan = $stmt->fetchAll();

    $stmt_restore = $pdo->prepare("UPDATE bahan_baku SET stok_dicadangkan = stok_dicadangkan - ? WHERE id = ?");
    $stmt_log_restore = $pdo->prepare("
        INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, keterangan)
        VALUES (?, 'dilepas', ?, ?)
    ");

    foreach ($old_bahan as $ob) {
        $stmt_restore->execute([$ob['jumlah_dicadangkan'], $ob['bahan_baku_id']]);
        $stmt_log_restore->execute([
            $ob['bahan_baku_id'], 
            $ob['jumlah_dicadangkan'], 
            "Dilepas sementara karena edit pesanan #{$id}"
        ]);
    }

    // Hapus dari pesanan_bahan lama
    $pdo->prepare("DELETE FROM pesanan_bahan WHERE pesanan_id = ?")->execute([$id]);

    // Hapus detail_pesanan lama
    $pdo->prepare("DELETE FROM detail_pesanan WHERE pesanan_id = ?")->execute([$id]);


    // 3. Validasi stok untuk bahan baku baru
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

    // 4. Update pesanan
    $stmt = $pdo->prepare("
        UPDATE pesanan 
        SET nama_pemesan = ?, kontak = ?, tanggal_jemput = ?, jam_jemput = ?, catatan = ?, dp = ?, total_harga = ?, metode_pengiriman = ?, metode_pembayaran = ?, ongkir_tipe = ?, ongkir_nominal = ?, ongkir_metode_kurir = ?
        WHERE id = ?
    ");
    $stmt->execute([$nama_pemesan, $kontak, $tanggal_jemput, $jam_jemput, $catatan, $dp, $total_harga, $metode_pengiriman, $metode_pembayaran, $ongkir_tipe, $ongkir_nominal, $ongkir_metode_kurir, $id]);

    // 5. Insert detail_pesanan baru
    $stmt = $pdo->prepare("
        INSERT INTO detail_pesanan (pesanan_id, produk_id, nama_item, harga, qty, biaya_operasional, biaya_jasa)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $produk_id = (!empty($item['produk_id']) && $item['produk_id'] !== 'null') ? $item['produk_id'] : null;
        $biaya_operasional = (float) ($item['biaya_operasional'] ?? 0);
        $biaya_jasa = (float) ($item['biaya_jasa'] ?? 0);
        $stmt->execute([$id, $produk_id, $item['nama_item'], $item['harga'], $item['qty'], $biaya_operasional, $biaya_jasa]);
    }

    // 6. Insert pesanan_bahan baru + update stok_dicadangkan + riwayat_stok
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

        $stmt_pb->execute([$id, $b['bahan_baku_id'], $b['jumlah']]);
        $stmt_update->execute([$b['jumlah'], $b['bahan_baku_id']]);
        $stmt_riwayat->execute([$b['bahan_baku_id'], $b['jumlah'], "Dicadangkan (edited) untuk pesanan #{$id} ({$nama_pemesan})"]);
    }

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pesanan berhasil diperbarui dan penyesuaian stok selesai dilakukan.'];
    header('Location: ../pages/pesanan.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal memperbarui pesanan: ' . $e->getMessage()];
    header('Location: ../pages/pesanan_edit.php?id=' . $id);
    exit;
}
