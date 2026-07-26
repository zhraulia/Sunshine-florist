<?php
// actions/simpan_kategori.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$nama = trim($_POST['nama'] ?? '');

if ($nama !== '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO kategori_produk (nama) VALUES (?)");
        $stmt->execute([$nama]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Kategori '{$nama}' ditambahkan."];
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Kategori dengan nama itu sudah ada.'];
    }
}

header('Location: ../pages/produk_resep.php');
exit;