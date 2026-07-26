<?php
// actions/simpan_resep.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$produk_id          = $_POST['produk_id'] ?? null;
$bahan_baku_id      = $_POST['bahan_baku_id'] ?? null;
$jumlah_dibutuhkan  = $_POST['jumlah_dibutuhkan'] ?? 0;

if ($produk_id && $bahan_baku_id && $jumlah_dibutuhkan > 0) {
    // Cek supaya gak duplikat bahan yang sama di resep yang sama
    $stmt = $pdo->prepare("SELECT id FROM resep_produk WHERE produk_id = ? AND bahan_baku_id = ?");
    $stmt->execute([$produk_id, $bahan_baku_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE resep_produk SET jumlah_dibutuhkan = ? WHERE id = ?");
        $stmt->execute([$jumlah_dibutuhkan, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO resep_produk (produk_id, bahan_baku_id, jumlah_dibutuhkan)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$produk_id, $bahan_baku_id, $jumlah_dibutuhkan]);
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Resep berhasil disimpan.'];
}

header("Location: ../pages/produk_resep.php?resep={$produk_id}");
exit;