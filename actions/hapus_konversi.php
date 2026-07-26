<?php
// actions/hapus_konversi.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id        = $_GET['id'] ?? null;
$bahan_id  = $_GET['bahan_id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM konversi_satuan WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: ../pages/tambah_stok.php?bahan_id={$bahan_id}");
exit;