<?php
// actions/proses_login.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Username dan password wajib diisi.';
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, username, password_hash, nama_toko FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Username atau password salah.';
    header('Location: ../login.php');
    exit;
}

// Login sukses
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['nama_toko'] = $user['nama_toko'];

header('Location: ../pages/dashboard.php');
exit;