<?php
// actions/simpan_konversi.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$bahan_baku_id     = $_POST['bahan_baku_id'] ?? null;
$nama_satuan_beli  = trim($_POST['nama_satuan_beli'] ?? '');
$isi_per_satuan    = $_POST['isi_per_satuan'] ?? 0;

if ($bahan_baku_id && $nama_satuan_beli !== '' && $isi_per_satuan > 0) {
    $stmt = $pdo->prepare("
        INSERT INTO konversi_satuan (bahan_baku_id, nama_satuan_beli, isi_per_satuan)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$bahan_baku_id, $nama_satuan_beli, $isi_per_satuan]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Satuan konversi ditambahkan.'];
}

header("Location: ../pages/tambah_stok.php?bahan_id={$bahan_baku_id}");
exit;