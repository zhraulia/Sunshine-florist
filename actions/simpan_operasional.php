<?php
// actions/simpan_operasional.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/operasional.php');
    exit;
}

$tanggal = $_POST['tanggal'] ?? date('Y-m-d');
$jenis   = $_POST['jenis'] ?? 'keluar';
$kategori = trim($_POST['kategori'] ?? 'Biaya Operasional');
$metode   = $_POST['metode_pembayaran'] ?? 'cash';
$nominal  = (float) ($_POST['nominal'] ?? 0);
$keterangan = trim($_POST['keterangan'] ?? '');

if ($nominal <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Nominal transaksi harus lebih besar dari 0.'];
    header('Location: ../pages/operasional.php');
    exit;
}

// Upload Nota (jika ada)
$foto_nota = null;
if (!empty($_FILES['foto_nota']['name'])) {
    $target_dir = __DIR__ . '/../uploads/nota/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['foto_nota']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    
    if (in_array($file_ext, $allowed_exts)) {
        $new_filename = 'nota_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['foto_nota']['tmp_name'], $target_file)) {
            $foto_nota = 'uploads/nota/' . $new_filename;
        }
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO kas_operasional (tanggal, jenis, kategori, metode_pembayaran, nominal, keterangan, foto_nota)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tanggal, $jenis, $kategori, $metode, $nominal, $keterangan, $foto_nota]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pencatatan kas operasional berhasil disimpan.'];
} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()];
}

header('Location: ../pages/operasional.php');
exit;
