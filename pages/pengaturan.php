<?php
// pages/pengaturan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Pengaturan';

$pengaturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- ===== PROFIL TOKO ===== -->
            <div class="panel" style="margin-bottom:20px; max-width:600px;">
                <h3>Profil Toko</h3>
                <form action="../actions/simpan_pengaturan.php" method="POST" enctype="multipart/form-data">

                    <div class="form-group">
                        <label>Nama Toko</label>
                        <input type="text" name="nama_toko" required
                               value="<?= htmlspecialchars($pengaturan['nama_toko']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Tagline</label>
                        <input type="text" name="tagline" placeholder='"Your moments, our blooms"'
                               value="<?= htmlspecialchars($pengaturan['tagline'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Logo Toko</label><br>
                        <?php if (!empty($pengaturan['logo_url'])): ?>
                            <img src="../<?= htmlspecialchars($pengaturan['logo_url']) ?>" alt="Logo saat ini"
                                 style="width:60px; height:60px; object-fit:cover; border-radius:50%; margin-bottom:8px; display:block;">
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*">
                        <small style="color:var(--color-text-muted);">Dipakai sebagai favicon & logo di sidebar. Kosongkan kalau tidak ingin ganti.</small>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
                        <div class="form-group">
                            <label>Warna Utama (Sidebar)</label>
                            <input type="color" name="warna_primary" value="<?= htmlspecialchars($pengaturan['warna_primary']) ?>" style="height:42px; padding:4px;">
                        </div>
                        <div class="form-group">
                            <label>Warna Aksen (Tombol/Highlight)</label>
                            <input type="color" name="warna_aksen" value="<?= htmlspecialchars($pengaturan['warna_aksen']) ?>" style="height:42px; padding:4px;">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </form>
            </div>

            <!-- ===== GANTI PASSWORD ===== -->
            <div class="panel" style="max-width:600px;">
                <h3>Ganti Password</h3>
                <form action="../actions/ganti_password.php" method="POST">
                    <div class="form-group">
                        <label>Password Lama</label>
                        <input type="password" name="password_lama" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password_baru" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="konfirmasi_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary">Ganti Password</button>
                </form>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>