<?php
// actions/hapus_foto_pesanan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $foto_id = $_POST['foto_id'] ?? null;
    $pesanan_id = $_POST['pesanan_id'] ?? null;

    if ($foto_id && $pesanan_id) {
        $stmt = $pdo->prepare("SELECT * FROM pesanan_foto WHERE id = ? AND pesanan_id = ?");
        $stmt->execute([$foto_id, $pesanan_id]);
        $foto = $stmt->fetch();

        if ($foto) {
            $file_path = __DIR__ . '/../' . $foto['foto_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            $stmt_del = $pdo->prepare("DELETE FROM pesanan_foto WHERE id = ?");
            $stmt_del->execute([$foto_id]);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Foto custom berhasil dihapus.'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Foto tidak ditemukan.'
            ];
        }
    }
}

header("Location: ../pages/pesanan_detail.php?id=" . $pesanan_id);
exit;
