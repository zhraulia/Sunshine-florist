<?php
// login.php
require_once __DIR__ . '/includes/auth.php';

// Kalau sudah login, langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sunshine Florist</title>
    <link rel="icon" href="assets/images/logo-sunshine.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-hero">
        <img src="assets/images/hero-bunga.jpg" alt="Sunshine Florist" class="login-hero-bg">

        <div class="login-hero-content">
            <div class="login-brand">
                <h1 class="login-brand-title">
                    SUNSHINE<br>
                    <span class="login-brand-script">Florist</span>
                </h1>
                <div class="login-brand-tagline-container">
                    <svg viewBox="0 0 50 100" class="floral-sprig sprig-left" width="32" height="64">
                        <path d="M25 90 C25 60 25 35 25 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                        <path d="M25 75 C15 70 10 60 18 55 C22 52 25 62 25 68" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 55 C12 50 8 38 18 32 C22 29 25 42 25 48" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 35 C15 30 12 20 20 16 C23 14 25 22 25 28" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 65 C35 60 40 50 32 45 C28 42 25 52 25 58" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 45 C38 40 42 28 32 22 C28 19 25 32 25 38" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 25 C35 20 38 10 30 6 C27 4 25 12 25 18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <circle cx="25" cy="12" r="3" fill="currentColor"/>
                        <circle cx="15" cy="53" r="2.5" fill="currentColor"/>
                        <circle cx="35" cy="43" r="2.5" fill="currentColor"/>
                        <circle cx="15" cy="31" r="2.5" fill="currentColor"/>
                        <circle cx="33" cy="20" r="2.5" fill="currentColor"/>
                    </svg>
                    <p class="login-brand-tagline">Your moments, our blooms</p>
                    <svg viewBox="0 0 50 100" class="floral-sprig sprig-right" width="32" height="64">
                        <path d="M25 90 C25 60 25 35 25 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                        <path d="M25 75 C15 70 10 60 18 55 C22 52 25 62 25 68" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 55 C12 50 8 38 18 32 C22 29 25 42 25 48" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 35 C15 30 12 20 20 16 C23 14 25 22 25 28" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 65 C35 60 40 50 32 45 C28 42 25 52 25 58" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 45 C38 40 42 28 32 22 C28 19 25 32 25 38" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <path d="M25 25 C35 20 38 10 30 6 C27 4 25 12 25 18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                        <circle cx="25" cy="12" r="3" fill="currentColor"/>
                        <circle cx="15" cy="53" r="2.5" fill="currentColor"/>
                        <circle cx="35" cy="43" r="2.5" fill="currentColor"/>
                        <circle cx="15" cy="31" r="2.5" fill="currentColor"/>
                        <circle cx="33" cy="20" r="2.5" fill="currentColor"/>
                    </svg>
                </div>
            </div>

            <div class="login-box">
                <h2>Login</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="actions/proses_login.php" method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">➔ Masuk</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>