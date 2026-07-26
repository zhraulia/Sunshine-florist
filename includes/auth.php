<?php
// includes/auth.php
// Cek session login & atur durasi session 12 jam (43200 detik) untuk operasional toko

if (session_status() === PHP_SESSION_NONE) {
    // Durasi session 12 jam = 43200 detik
    $session_lifetime = 43200;
    ini_set('session.gc_maxlifetime', $session_lifetime);
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'httponly' => true
    ]);
    session_start();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}