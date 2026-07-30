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
    <link rel="stylesheet" href="../assets/css/style_v2.css">
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

            <!-- ===== UPDATE SISTEM & DATABASE ===== -->
            <div class="panel" style="max-width:600px; margin-top:20px; border:1px solid #EADCD9;">
                <h3>Update Sistem & Database</h3>
                <p style="color:var(--color-text-muted); font-size:13px; margin-bottom:14px; line-height:1.5;">
                    Gunakan tombol di bawah ini untuk menyelaraskan struktur tabel database dan memproses migrasi data setelah melakukan deploy atau mengunggah pembaruan sistem baru.
                </p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="run_migration.php" class="btn btn-secondary" style="font-weight:700;">Jalankan Sinkronisasi Database</a>
                    <a href="reset_db.php" class="btn btn-danger" style="background:#A43B22; border-color:#A43B22; font-weight:700;">Kosongkan Semua Data Uji Coba</a>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>