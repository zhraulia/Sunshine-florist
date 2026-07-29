<?php
// pages/kurangi_stok.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Kurangi Stok';
$bahan_id = $_GET['bahan_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM bahan_baku WHERE id = ?");
$stmt->execute([$bahan_id]);
$bahan = $stmt->fetch();

if (!$bahan) {
    die('Bahan tidak ditemukan.');
}

// Ambil daftar konversi master global
$stmt_master = $pdo->query("SELECT * FROM konversi_master ORDER BY nama_satuan_beli ASC");
$master_konversi_list = $stmt_master ? $stmt_master->fetchAll() : [];

// Ambil daftar konversi khusus bahan ini if any
$stmt = $pdo->prepare("SELECT * FROM konversi_satuan WHERE bahan_baku_id = ?");
$stmt->execute([$bahan_id]);
$konversi_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurangi Stok - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="panel" style="max-width:480px;">
                <h3>Kurangi Stok: <?= htmlspecialchars($bahan['nama']) ?></h3>
                <p style="color:var(--color-text-muted); font-size:13px; margin-bottom: 20px;">
                    Stok fisik saat ini: <strong><?= (float)$bahan['stok_fisik'] ?> <?= htmlspecialchars($bahan['satuan_dasar']) ?></strong>
                </p>

                <form id="form-kurangi-stok" action="../actions/kurangi_stok.php" method="POST">
                    <input type="hidden" name="bahan_baku_id" value="<?= $bahan['id'] ?>">

                    <div class="form-group">
                        <label id="label_jumlah_kurang">Jumlah yang Dikurangi</label>
                        <input type="number" step="0.01" name="jumlah" id="jumlah_input" required style="max-width:100%;">
                    </div>

                    <div class="form-group">
                        <label>Satuan Pengurangan</label>
                        <select name="satuan_pilihan" id="satuan_pilihan" onchange="updateDeductionLabels()" required>
                            <option value="dasar" data-nama="<?= htmlspecialchars($bahan['satuan_dasar']) ?>" data-isi="1">
                                <?= htmlspecialchars($bahan['satuan_dasar']) ?> (Satuan Dasar)
                            </option>
                            <?php foreach ($master_konversi_list as $mk): ?>
                                <option value="master:<?= $mk['id'] ?>"
                                        data-nama="<?= htmlspecialchars($mk['nama_satuan_beli']) ?>"
                                        data-isi="<?= $mk['isi_per_satuan'] ?>">
                                    <?= htmlspecialchars($mk['nama_satuan_beli']) ?> 
                                    (1 <?= htmlspecialchars($mk['nama_satuan_beli']) ?> = <?= (float)$mk['isi_per_satuan'] ?> <?= htmlspecialchars($bahan['satuan_dasar']) ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php foreach ($konversi_list as $k): ?>
                                <option value="konversi:<?= $k['id'] ?>"
                                        data-nama="<?= htmlspecialchars($k['nama_satuan_beli']) ?>"
                                        data-isi="<?= $k['isi_per_satuan'] ?>">
                                    <?= htmlspecialchars($k['nama_satuan_beli']) ?>
                                    (1 <?= htmlspecialchars($k['nama_satuan_beli']) ?> = <?= (float)$k['isi_per_satuan'] ?> <?= htmlspecialchars($bahan['satuan_dasar']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-danger" style="background:#A43B22; border-color:#A43B22;">- Simpan Pengurangan Stok</button>
                    <a href="stok_bahan.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>

            <script>
            function updateDeductionLabels() {
                const select = document.getElementById('satuan_pilihan');
                const selectedOpt = select.options[select.selectedIndex];
                const unitName = selectedOpt.getAttribute('data-nama');
                
                const labelJumlah = document.getElementById('label_jumlah_kurang');
                if (labelJumlah) {
                    labelJumlah.innerHTML = `Jumlah yang Dikurangi (dalam <strong>${unitName}</strong>)`;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize label on load
                updateDeductionLabels();

                // Confirm on submit
                const form = document.getElementById('form-kurangi-stok');
                form.addEventListener('submit', function(e) {
                    const select = document.getElementById('satuan_pilihan');
                    const selectedOpt = select.options[select.selectedIndex];
                    const unitName = selectedOpt.getAttribute('data-nama');
                    const qty = document.getElementById('jumlah_input').value;

                    const msg = `Apakah Anda yakin ingin mengurangi stok sebanyak ${qty} ${unitName}?`;
                    if (!confirm(msg)) {
                        e.preventDefault();
                    }
                });
            });
            </script>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
