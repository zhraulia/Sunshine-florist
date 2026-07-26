<?php
// actions/simpan_produk.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id                 = $_POST['id'] ?? null;
$nama               = trim($_POST['nama'] ?? '');
$kategori_id        = $_POST['kategori_id'] ?? null;
$harga_jual         = $_POST['harga_jual'] ?? 0;
$biaya_operasional  = $_POST['biaya_operasional'] ?? 0;
$biaya_jasa         = $_POST['biaya_jasa'] ?? 0;
$is_custom          = 0;

if ($nama === '') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Nama produk wajib diisi.'];
    header('Location: ../pages/produk_resep.php');
    exit;
}

// Handle upload foto (opsional)
$foto_url = null;
if (!empty($_FILES['foto']['name'])) {
    $upload_dir = __DIR__ . '/../uploads/produk/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowed)) {
        $filename = 'produk_' . time() . '_' . uniqid() . '.' . $ext;
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            $foto_url = 'uploads/produk/' . $filename;
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Format foto tidak didukung (hanya jpg, jpeg, png, webp).'];
        header('Location: ../pages/produk_resep.php');
        exit;
    }
}

if ($id) {
    if ($foto_url) {
        $stmt = $pdo->prepare("
            UPDATE produk SET nama=?, kategori_id=?, harga_jual=?, biaya_operasional=?, biaya_jasa=?, is_custom=?, foto_url=?
            WHERE id=?
        ");
        $stmt->execute([$nama, $kategori_id, $harga_jual, $biaya_operasional, $biaya_jasa, $is_custom, $foto_url, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE produk SET nama=?, kategori_id=?, harga_jual=?, biaya_operasional=?, biaya_jasa=?, is_custom=?
            WHERE id=?
        ");
        $stmt->execute([$nama, $kategori_id, $harga_jual, $biaya_operasional, $biaya_jasa, $is_custom, $id]);
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Produk berhasil diperbarui.'];
} else {
    $stmt = $pdo->prepare("
        INSERT INTO produk (nama, kategori_id, harga_jual, biaya_operasional, biaya_jasa, is_custom, foto_url)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nama, $kategori_id, $harga_jual, $biaya_operasional, $biaya_jasa, $is_custom, $foto_url]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Produk baru berhasil ditambahkan.'];
}

header('Location: ../pages/produk_resep.php');
exit;