<?php
// actions/simpan_bahan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id             = $_POST['id'] ?? null;
$nama           = trim($_POST['nama'] ?? '');
$satuan_dasar   = trim($_POST['satuan_dasar'] ?? '');
$stok_fisik     = $_POST['stok_fisik'] ?? 0;
$ambang_minimum = $_POST['ambang_minimum'] ?? 0;
$harga_beli     = $_POST['harga_beli'] ?? 0;

$konversi_nama  = trim($_POST['konversi_nama'] ?? '');
$konversi_isi   = (float) ($_POST['konversi_isi'] ?? 0);

if ($nama === '' || $satuan_dasar === '') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Nama dan satuan wajib diisi.'];
    header('Location: ../pages/stok_bahan.php');
    exit;
}

if ($id) {
    // Update — stok_fisik TIDAK diupdate dari sini (harus lewat tambah_stok.php)
    $stmt = $pdo->prepare("
        UPDATE bahan_baku
        SET nama = ?, satuan_dasar = ?, ambang_minimum = ?, harga_beli = ?
        WHERE id = ?
    ");
    $stmt->execute([$nama, $satuan_dasar, $ambang_minimum, $harga_beli, $id]);

    // Update konversi_satuan
    // Cek dulu apakah bahan ini sudah punya konversi
    $stmt_check = $pdo->prepare("SELECT id FROM konversi_satuan WHERE bahan_baku_id = ? LIMIT 1");
    $stmt_check->execute([$id]);
    $existing_conv = $stmt_check->fetchColumn();

    if ($existing_conv) {
        if ($konversi_nama !== '' && $konversi_isi > 0) {
            // Update existing conversion
            $stmt_u = $pdo->prepare("UPDATE konversi_satuan SET nama_satuan_beli = ?, isi_per_satuan = ? WHERE id = ?");
            $stmt_u->execute([$konversi_nama, $konversi_isi, $existing_conv]);
        } else {
            // Deleted / cleared
            $pdo->prepare("DELETE FROM konversi_satuan WHERE id = ?")->execute([$existing_conv]);
        }
    } else {
        if ($konversi_nama !== '' && $konversi_isi > 0) {
            // Create new conversion
            $stmt_i = $pdo->prepare("INSERT INTO konversi_satuan (bahan_baku_id, nama_satuan_beli, isi_per_satuan) VALUES (?, ?, ?)");
            $stmt_i->execute([$id, $konversi_nama, $konversi_isi]);
        }
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Bahan berhasil diperbarui.'];
} else {
    $is_saldo_awal = isset($_POST['is_saldo_awal']);

    $stok_fisik_dasar = $konversi_isi > 0 ? ($stok_fisik * $konversi_isi) : (float)$stok_fisik;
    $harga_beli_dasar = $konversi_isi > 0 ? ($harga_beli / $konversi_isi) : (float)$harga_beli;

    if ($is_saldo_awal) {
        $stok_awal_db = $stok_fisik_dasar;
    } else {
        $status_po = $_POST['status_po'] ?? 'lunas';
        $stok_awal_db = ($status_po === 'pending_po') ? 0 : $stok_fisik_dasar;
    }

    $stmt = $pdo->prepare("
        INSERT INTO bahan_baku (nama, satuan_dasar, stok_fisik, ambang_minimum, harga_beli)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nama, $satuan_dasar, $stok_awal_db, $ambang_minimum, $harga_beli_dasar]);
    $bahan_baku_id = $pdo->lastInsertId();

    if ($konversi_nama !== '' && $konversi_isi > 0) {
        $stmt_i = $pdo->prepare("INSERT INTO konversi_satuan (bahan_baku_id, nama_satuan_beli, isi_per_satuan) VALUES (?, ?, ?)");
        $stmt_i->execute([$bahan_baku_id, $konversi_nama, $konversi_isi]);
    }

    if ($is_saldo_awal) {
        // Catat sebagai Saldo Awal (Tidak memotong Kas)
        if ($stok_fisik_dasar > 0) {
            $stmt_r = $pdo->prepare("
                INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, metode_pembayaran, biaya_ongkir, total_biaya, status_po, keterangan)
                VALUES (?, 'masuk', ?, NULL, 0, 0, 'saldo_awal', 'Saldo Awal Toko')
            ");
            $stmt_r->execute([$bahan_baku_id, $stok_fisik_dasar]);
        }
    } else {
        // Catat sebagai Pembelian Biasa (Memotong Kas)
        $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
        $biaya_ongkir       = (float)($_POST['biaya_ongkir'] ?? 0);
        $status_po          = $_POST['status_po'] ?? 'lunas';
        
        if ($stok_fisik > 0) {
            $total_biaya = ($stok_fisik_dasar * $harga_beli_dasar) + $biaya_ongkir;
        } else {
            $total_biaya = 0;
        }

        if ($stok_fisik_dasar > 0 || $biaya_ongkir > 0) {
            $ket = ($status_po === 'pending_po') 
                ? "Belanja PO stok awal bahan baru" 
                : "Stok awal pembuatan bahan baru";

            $stmt_r = $pdo->prepare("
                INSERT INTO riwayat_stok (bahan_baku_id, jenis, jumlah, metode_pembayaran, biaya_ongkir, total_biaya, status_po, keterangan)
                VALUES (?, 'masuk', ?, ?, ?, ?, ?, ?)
            ");
            $stmt_r->execute([
                $bahan_baku_id,
                $stok_fisik_dasar,
                $metode_pembayaran,
                $biaya_ongkir,
                $total_biaya,
                $status_po,
                $ket
            ]);
        }
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Bahan baru berhasil ditambahkan.'];
}

header('Location: ../pages/stok_bahan.php');
exit;