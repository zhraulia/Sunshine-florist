<?php
// pages/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Selamat Datang!';
$today = date('Y-m-d');

// 1. Pendapatan hari ini
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) AS total FROM penjualan WHERE DATE(tanggal) = ?");
$stmt->execute([$today]);
$pendapatan_hari_ini = $stmt->fetch()['total'];

// 2. Produk terlaris (bulan ini, berdasarkan qty terjual)
$stmt = $pdo->prepare("
    SELECT p.nama, SUM(dp.jumlah) AS total_qty
    FROM detail_penjualan dp
    JOIN penjualan pj ON pj.id = dp.penjualan_id
    JOIN produk p ON p.id = dp.produk_id
    WHERE MONTH(pj.tanggal) = MONTH(?) AND YEAR(pj.tanggal) = YEAR(?)
    GROUP BY dp.produk_id
    ORDER BY total_qty DESC
    LIMIT 1
");
$stmt->execute([$today, $today]);
$produk_terlaris = $stmt->fetch();

// 3. Stok menipis (stok tersedia <= ambang minimum)
$stmt = $pdo->query("
    SELECT COUNT(*) AS jumlah
    FROM bahan_baku
    WHERE (stok_fisik - stok_dicadangkan) <= ambang_minimum
");
$stok_menipis_count = $stmt->fetch()['jumlah'];

// 4. Menunggu jemput
$stmt = $pdo->query("SELECT COUNT(*) AS jumlah FROM pesanan WHERE status = 'menunggu'");
$menunggu_jemput_count = $stmt->fetch()['jumlah'];

// 5. Produk terjual hari ini (total qty produk/item yang terjual hari ini)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(dpi.qty), 0) AS total_qty
    FROM detail_penjualan_item dpi
    JOIN penjualan pj ON pj.id = dpi.penjualan_id
    WHERE DATE(pj.tanggal) = ?
");
$stmt->execute([$today]);
$produk_terjual_count = (int)$stmt->fetch()['total_qty'];

// 6. Panel: Pesanan hari ini / besok yang belum diambil
$stmt = $pdo->prepare("
    SELECT id, nama_pemesan, tanggal_jemput, jam_jemput, status
    FROM pesanan
    WHERE status = 'menunggu' AND tanggal_jemput <= DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY tanggal_jemput ASC, jam_jemput ASC
    LIMIT 8
");
$stmt->execute([$today]);
$pesanan_terdekat = $stmt->fetchAll();

// 7. Panel: Bahan perlu direstock
$stmt = $pdo->query("
    SELECT nama, satuan_dasar, (stok_fisik - stok_dicadangkan) AS stok_tersedia, ambang_minimum
    FROM bahan_baku
    WHERE (stok_fisik - stok_dicadangkan) <= ambang_minimum
    ORDER BY stok_tersedia ASC
    LIMIT 8
");
$bahan_restock = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="card-row card-row-1">
                <div class="card">
                    <div class="card-label">PENDAPATAN HARI INI</div>
                    <div class="card-value">Rp<?= number_format($pendapatan_hari_ini, 0, ',', '.') ?></div>
                </div>

                <div class="card">
                    <div class="card-label">PRODUK TERLARIS</div>
                    <div class="card-value" style="font-size:16px;">
                        <?= $produk_terlaris ? htmlspecialchars($produk_terlaris['nama']) : 'Belum ada data' ?>
                    </div>
                </div>

                <div class="card <?= $stok_menipis_count > 0 ? 'alert-card' : '' ?>">
                    <div class="card-label">STOK MENIPIS</div>
                    <div class="card-value"><?= $stok_menipis_count ?> Bahan</div>
                </div>
            </div>

            <div class="card-row card-row-2">
                <div class="card <?= $menunggu_jemput_count > 0 ? 'warning-card' : '' ?>">
                    <div class="card-label">MENUNGGU JEMPUT</div>
                    <div class="card-value"><?= $menunggu_jemput_count ?> Pesanan</div>
                </div>

                <div class="card">
                    <div class="card-label">PRODUK TERJUAL</div>
                    <div class="card-value"><?= $produk_terjual_count ?> Produk</div>
                </div>
            </div>

            <div class="panel-grid">
                <div class="panel">
                    <h3>Pesanan hari ini</h3>
                    <?php if (empty($pesanan_terdekat)): ?>
                        <p style="color:var(--color-text-muted); font-size:13px;">Tidak ada pesanan yang mendekati jadwal jemput.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr><th>Pemesan</th><th>Jemput</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pesanan_terdekat as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nama_pemesan']) ?></td>
                                    <td>
                                        <?= date('d M', strtotime($p['tanggal_jemput'])) ?>,
                                        <?= date('H:i', strtotime($p['jam_jemput'])) ?>
                                    </td>
                                    <td><span class="badge badge-menunggu">Menunggu</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <div style="margin-top:14px;">
                        <a href="pesanan.php" class="btn btn-secondary btn-sm">Lihat Semua Pesanan →</a>
                    </div>
                </div>

                <div class="panel">
                    <h3>Bahan perlu di restock</h3>
                    <?php if (empty($bahan_restock)): ?>
                        <p style="color:var(--color-text-muted); font-size:13px;">Semua stok bahan masih aman.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr><th>Bahan</th><th>Stok Tersedia</th><th>Batas Min.</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bahan_restock as $b): ?>
                                <tr>
                                     <td><?= htmlspecialchars($b['nama']) ?></td>
                                     <td><span class="badge badge-dibatalkan"><?= (float)$b['stok_tersedia'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?></span></td>
                                     <td><?= (float)$b['ambang_minimum'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?></td>
                                 </tr>
                                 <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <div style="margin-top:14px;">
                        <a href="stok_bahan.php" class="btn btn-secondary btn-sm">Kelola Stok Bahan →</a>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>