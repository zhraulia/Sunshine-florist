<?php
// actions/ganti_password.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$password_lama        = $_POST['password_lama'] ?? '';
$password_baru         = $_POST['password_baru'] ?? '';
$konfirmasi_password  = $_POST['konfirmasi_password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([current_user_id()]);
$user = $stmt->fetch();

if (!$user || !password_verify($password_lama, $user['password_hash'])) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password lama salah.'];
    header('Location: ../pages/pengaturan.php');
    exit;
}

if ($password_baru !== $konfirmasi_password) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Konfirmasi password baru tidak cocok.'];
    header('Location: ../pages/pengaturan.php');
    exit;
}

if (strlen($password_baru) < 6) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password baru minimal 6 karakter.'];
    header('Location: ../pages/pengaturan.php');
    exit;
}

$new_hash = password_hash($password_baru, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([$new_hash, current_user_id()]);

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Password berhasil diganti.'];
header('Location: ../pages/pengaturan.php');
exit;