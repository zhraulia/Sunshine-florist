<?php
// pages/riwayat_stok_tampil.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Riwayat Mutasi Stok';

// Ambil list bahan baku untuk filter dropdown
$bahan_dropdown = $pdo->query("SELECT id, nama FROM bahan_baku ORDER BY nama ASC")->fetchAll();

// Ambil parameter filter
$filter_bahan = $_GET['bahan_id'] ?? '';
$filter_aktivitas = $_GET['aktivitas'] ?? '';
$filter_tgl_awal = $_GET['tgl_awal'] ?? '';
$filter_tgl_akhir = $_GET['tgl_akhir'] ?? '';

// Query riwayat stok dari awal (ASC) untuk menghitung sisa stok berjalan secara akurat (Kecuali PO yang masih pending)
$query = "
    SELECT r.*, b.nama AS nama_bahan, b.satuan_dasar
    FROM riwayat_stok r
    JOIN bahan_baku b ON b.id = r.bahan_baku_id
    WHERE r.status_po != 'pending_po'
    ORDER BY r.id ASC
";
$stmt = $pdo->query($query);
$all_records = $stmt->fetchAll();

// Hitung sisa stok berjalan (running balance) per bahan baku
$balances = [];
$processed_rows = [];
foreach ($all_records as $row) {
    $bid = $row['bahan_baku_id'];
    if (!isset($balances[$bid])) {
        $balances[$bid] = 0;
    }

    $qty = (float)$row['jumlah'];
    if ($row['jenis'] === 'masuk') {
        $balances[$bid] += $qty;
        $row['display_jumlah'] = '+' . $qty;
    } else { // keluar
        $balances[$bid] -= $qty;
        $row['display_jumlah'] = '-' . $qty;
    }

    $row['sisa_stok_after'] = $balances[$bid];

    // Tentukan Kategori Alasan yang Bersih & Mudah Dibaca
    $keterangan = $row['keterangan'] ?? '';
    $row['alasan_kategori'] = 'Lain-lain';

    if ($row['status_po'] === 'saldo_awal') {
        $row['alasan_kategori'] = 'Saldo Awal';
    } elseif ($row['jenis'] === 'masuk' && !empty($row['status_po'])) {
        $row['alasan_kategori'] = 'Pembelian Supplier';
    } elseif (stripos($keterangan, 'Terpakai untuk pesanan') !== false) {
        $row['alasan_kategori'] = 'Produksi Pesanan';
    } elseif (stripos($keterangan, 'Terpakai untuk transaksi walk-in') !== false) {
        $row['alasan_kategori'] = 'Produksi Walk-in';
    } elseif (stripos($keterangan, 'Penjualan eceran') !== false) {
        $row['alasan_kategori'] = 'Penjualan Eceran';
    } elseif (stripos($keterangan, 'Pengurangan stok') !== false || stripos($keterangan, 'Penyesuaian stok fisik (pengurangan)') !== false || stripos($keterangan, 'rusak') !== false) {
        $row['alasan_kategori'] = 'Rusak / Layu (Wastage)';
    } elseif (stripos($keterangan, 'Koreksi') !== false) {
        $row['alasan_kategori'] = 'Koreksi Input';
    } else {
        $row['alasan_kategori'] = $keterangan ?: 'Mutasi Stok';
    }

    // Filter baris
    $keep = true;
    if ($filter_bahan !== '' && $row['bahan_baku_id'] != $filter_bahan) {
        $keep = false;
    }
    if ($filter_aktivitas !== '') {
        if ($filter_aktivitas === 'masuk' && $row['jenis'] !== 'masuk') $keep = false;
        if ($filter_aktivitas === 'keluar' && $row['jenis'] !== 'keluar') $keep = false;
    }

    if ($filter_tgl_awal !== '') {
        $row_date = date('Y-m-d', strtotime($row['created_at']));
        if ($row_date < $filter_tgl_awal) $keep = false;
    }
    if ($filter_tgl_akhir !== '') {
        $row_date = date('Y-m-d', strtotime($row['created_at']));
        if ($row_date > $filter_tgl_akhir) $keep = false;
    }

    if ($keep) {
        $processed_rows[] = $row;
    }
}

// Balikkan urutan agar mutasi terbaru tampil di paling atas
$display_rows = array_reverse($processed_rows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Mutasi Stok - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css?v=1.4">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <div>
                    <h2 style="margin:0;">Riwayat Mutasi Stok</h2>
                    <p style="margin:4px 0 0 0; color:var(--color-text-muted); font-size:13px;">
                        Tampilan ringkas seluruh aktivitas masuk, keluar, dan penyesuaian stok bahan baku beserta sisa stok berjalan.
                    </p>
                </div>
                <a href="stok_bahan.php" class="btn btn-secondary" style="font-weight:700;">← Kembali ke Stok Bahan</a>
            </div>

            <!-- ===== PANEL FILTER ===== -->
            <div class="panel" style="margin-bottom:24px;">
                <h3>Filter Riwayat</h3>
                <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:14px; align-items:flex-end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Bahan Baku</label>
                        <select name="bahan_id">
                            <option value="">-- Semua Bahan --</option>
                            <?php foreach ($bahan_dropdown as $bd): ?>
                                <option value="<?= $bd['id'] ?>" <?= $filter_bahan == $bd['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bd['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Aktivitas</label>
                        <select name="aktivitas">
                            <option value="">-- Semua Aktivitas --</option>
                            <option value="masuk" <?= $filter_aktivitas === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                            <option value="keluar" <?= $filter_aktivitas === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Tanggal Awal</label>
                        <input type="date" name="tgl_awal" value="<?= htmlspecialchars($filter_tgl_awal) ?>" style="height:38px; margin:0;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($filter_tgl_akhir) ?>" style="height:38px; margin:0;">
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="submit" class="btn btn-primary" style="flex:1; height:38px;">Terapkan</button>
                        <?php if ($filter_bahan !== '' || $filter_aktivitas !== '' || $filter_tgl_awal !== '' || $filter_tgl_akhir !== ''): ?>
                            <a href="riwayat_stok_tampil.php" class="btn btn-secondary" style="height:38px; display:inline-flex; align-items:center; justify-content:center; padding:0 14px;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- ===== TABEL RIWAYAT STOK ===== -->
            <div class="panel">
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; min-width:800px; font-size:13px; line-height:1.6;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--color-border);">
                                <th style="padding:10px 8px; width:150px;">Tanggal & Waktu</th>
                                <th style="padding:10px 8px;">Nama Bahan</th>
                                <th style="padding:10px 8px; width:120px;">Aktivitas</th>
                                <th style="padding:10px 8px;">Alasan</th>
                                <th style="padding:10px 8px; width:120px; text-align:right;">Jumlah</th>
                                <th style="padding:10px 8px; width:140px; text-align:right;">Sisa Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($display_rows)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:20px; color:var(--color-text-muted);">Tidak ada riwayat mutasi stok ditemukan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($display_rows as $row): ?>
                                    <tr style="border-bottom:1px solid var(--color-border); font-family:'Plus Jakarta Sans', sans-serif;">
                                        <td style="padding:10px 8px; color:var(--color-text-muted);">
                                            <?= date('d/m - H:i', strtotime($row['created_at'])) ?>
                                        </td>
                                        <td style="padding:10px 8px; font-weight:700; color:var(--color-text-dark);">
                                            <?= htmlspecialchars($row['nama_bahan']) ?>
                                        </td>
                                        <td style="padding:10px 8px;">
                                            <span style="display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                                                <?php
                                                $color = '#10B981'; // Green (default masuk)
                                                if ($row['jenis'] === 'keluar') {
                                                    if ($row['alasan_kategori'] === 'Rusak / Layu (Wastage)') {
                                                        $color = '#F59E0B'; // Yellow
                                                    } else {
                                                        $color = '#EF4444'; // Red
                                                    }
                                                }
                                                ?>
                                                <span style="width:8px; height:8px; border-radius:50%; background-color:<?= $color ?>; display:inline-block;"></span>
                                                <?= ucfirst($row['jenis']) ?>
                                            </span>
                                        </td>
                                        <td style="padding:10px 8px; color:var(--color-text-dark);">
                                            <?= htmlspecialchars($row['alasan_kategori']) ?>
                                        </td>
                                        <td style="padding:10px 8px; text-align:right; font-weight:700; color:<?= $row['jenis'] === 'masuk' ? '#10B981' : '#EF4444' ?>;">
                                            <?= $row['display_jumlah'] ?> <?= htmlspecialchars($row['satuan_dasar']) ?>
                                        </td>
                                        <td style="padding:10px 8px; text-align:right; font-weight:600; color:var(--color-text-muted);">
                                            <?= $row['sisa_stok_after'] ?> <?= htmlspecialchars($row['satuan_dasar']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </main>
    </div>
</body>
</html>
