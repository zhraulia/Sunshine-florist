<?php
// actions/hapus_resep.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id        = $_GET['id'] ?? null;
$produk_id = $_GET['produk_id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM resep_produk WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: ../pages/produk_resep.php?resep={$produk_id}");
exit;