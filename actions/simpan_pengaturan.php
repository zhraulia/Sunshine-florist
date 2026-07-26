<?php
// actions/simpan_pengaturan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$nama_toko     = trim($_POST['nama_toko'] ?? '');
$tagline       = trim($_POST['tagline'] ?? '');
$warna_primary = $_POST['warna_primary'] ?? '#E8B4B8';
$warna_aksen   = $_POST['warna_aksen'] ?? '#B76E79';

if ($nama_toko === '') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Nama toko wajib diisi.'];
    header('Location: ../pages/pengaturan.php');
    exit;
}

// Handle upload logo (opsional)
$logo_url = null;
if (!empty($_FILES['logo']['name'])) {
    $upload_dir = __DIR__ . '/../assets/images/';
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowed)) {
        $filename = 'logo-sunshine.' . $ext;   // nama file tetap, jadi otomatis kepakai di semua tempat yang refer ke path ini
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            $logo_url = 'assets/images/' . $filename;
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Format logo tidak didukung (hanya jpg, jpeg, png, webp).'];
        header('Location: ../pages/pengaturan.php');
        exit;
    }
}

if ($logo_url) {
    $stmt = $pdo->prepare("
        UPDATE pengaturan SET nama_toko=?, tagline=?, logo_url=?, warna_primary=?, warna_aksen=?
        WHERE id = 1
    ");
    $stmt->execute([$nama_toko, $tagline, $logo_url, $warna_primary, $warna_aksen]);
} else {
    $stmt = $pdo->prepare("
        UPDATE pengaturan SET nama_toko=?, tagline=?, warna_primary=?, warna_aksen=?
        WHERE id = 1
    ");
    $stmt->execute([$nama_toko, $tagline, $warna_primary, $warna_aksen]);
}

// Sync juga ke session biar header langsung update tanpa perlu login ulang
$_SESSION['nama_toko'] = $nama_toko;

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Pengaturan berhasil disimpan.'];
header('Location: ../pages/pengaturan.php');
exit;