<?php
// actions/hapus_operasional.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/operasional.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        // Hapus foto nota jika ada
        $stmt_img = $pdo->prepare("SELECT foto_nota FROM kas_operasional WHERE id = ?");
        $stmt_img->execute([$id]);
        $img = $stmt_img->fetchColumn();
        
        if ($img && file_exists(__DIR__ . '/../' . $img)) {
            unlink(__DIR__ . '/../' . $img);
        }

        // Hapus record
        $stmt = $pdo->prepare("DELETE FROM kas_operasional WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Catatan kas operasional berhasil dihapus.'];
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()];
    }
}

header('Location: ../pages/operasional.php');
exit;
