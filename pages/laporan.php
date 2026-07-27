<?php
// pages/laporan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

function getInventoryValueAtDate($date, $pdo) {
    // 1. Cek apakah ada snapshot di akhir bulan sebelumnya
    $target_month = date('Y-m', strtotime($date));
    $prev_month = date('Y-m', strtotime("$target_month -1 month"));
    
    $stmt = $pdo->prepare("SELECT nilai_persediaan FROM snapshot_persediaan WHERE bulan = ?");
    $stmt->execute([$prev_month]);
    $snap = $stmt->fetchColumn();
    if ($snap !== false) {
        return (float)$snap;
    }
    
    // 2. Jika tidak ada snapshot, lakukan backtrack dari stok fisik saat ini
    $bahan_list = $pdo->query("SELECT id, stok_fisik, harga_beli FROM bahan_baku")->fetchAll();
    $current_stok = [];
    foreach ($bahan_list as $b) {
        $current_stok[$b['id']] = (float)$b['stok_fisik'];
    }
    
    $stmt_r = $pdo->prepare("
        SELECT bahan_baku_id, jenis, jumlah 
        FROM riwayat_stok 
        WHERE created_at > ? AND jenis IN ('masuk', 'keluar')
    ");
    $stmt_r->execute([$date . ' 00:00:00']);
    foreach ($stmt_r->fetchAll() as $r) {
        $bid = $r['bahan_baku_id'];
        if (!isset($current_stok[$bid])) continue;
        if ($r['jenis'] === 'masuk') {
            $current_stok[$bid] -= (float)$r['jumlah'];
        } elseif ($r['jenis'] === 'keluar') {
            $current_stok[$bid] += (float)$r['jumlah'];
        }
    }
    
    $val_at_date = 0;
    foreach ($bahan_list as $b) {
        $st = isset($current_stok[$b['id']]) ? max(0, $current_stok[$b['id']]) : 0;
        $val_at_date += $st * $b['harga_beli'];
    }
    return $val_at_date;
}

function getInventoryValueEnd($date, $pdo) {
    // Jika bulan ini sudah ditutup buku, kembalikan nilai snapshot bulan ini
    $target_month = date('Y-m', strtotime($date));
    $stmt = $pdo->prepare("SELECT nilai_persediaan FROM snapshot_persediaan WHERE bulan = ?");
    $stmt->execute([$target_month]);
    $snap = $stmt->fetchColumn();
    if ($snap !== false) {
        return (float)$snap;
    }
    
    $end_day = $date . ' 23:59:59';
    if ($end_day >= date('Y-m-d H:i:s')) {
        $bahan_list = $pdo->query("SELECT stok_fisik, harga_beli FROM bahan_baku")->fetchAll();
        $current_val = 0;
        foreach ($bahan_list as $b) {
            $current_val += $b['stok_fisik'] * $b['harga_beli'];
        }
        return $current_val;
    }
    
    // Backtrack ke esok harinya pukul 00:00:00
    return getInventoryValueAtDate(date('Y-m-d', strtotime("$date +1 day")), $pdo);
}

$page_title = 'Laporan';

$tab = $_GET['tab'] ?? 'ringkasan';
$mode = $_GET['mode'] ?? 'harian';
$tanggal   = $_GET['tanggal'] ?? date('Y-m-d');
$bulan     = $_GET['bulan'] ?? date('Y-m');
$tahun     = $_GET['tahun'] ?? date('Y');
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

if ($mode === 'bulanan') {
    $awal  = $bulan . '-01';
    $akhir = date('Y-m-t', strtotime($awal));
} elseif ($mode === 'tahunan') {
    $awal  = $tahun . '-01-01';
    $akhir = $tahun . '-12-31';
} elseif ($mode === 'rentang') {
    $awal  = $tgl_awal;
    $akhir = $tgl_akhir;
} else {
    $awal  = $tanggal;
    $akhir = $tanggal;
}

// ===== 1. RINGKASAN OMZET & PROFIT =====
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total_harga), 0) AS omzet,
        COALESCE(SUM(total_modal), 0) AS modal,
        COALESCE(SUM(profit), 0) AS profit,
        COUNT(*) AS jumlah_transaksi
    FROM penjualan
    WHERE DATE(tanggal) BETWEEN ? AND ?
");
$stmt->execute([$awal, $akhir]);
$ringkasan = $stmt->fetch();

// ===== 2. PRODUK TERLARIS =====
$stmt = $pdo->prepare("
    SELECT p.nama, SUM(dp.jumlah) AS total_terjual
    FROM detail_penjualan dp
    JOIN penjualan pj ON pj.id = dp.penjualan_id
    JOIN produk p ON p.id = dp.produk_id
    WHERE DATE(pj.tanggal) BETWEEN ? AND ?
    GROUP BY dp.produk_id
    ORDER BY total_terjual DESC
    LIMIT 10
");
$stmt->execute([$awal, $akhir]);
$produk_terlaris = $stmt->fetchAll();

// ===== 3. BAHAN PALING LAKU =====
$stmt = $pdo->prepare("
    SELECT b.nama, b.satuan_dasar, SUM(dp.jumlah) AS total_terpakai
    FROM detail_penjualan dp
    JOIN penjualan pj ON pj.id = dp.penjualan_id
    JOIN bahan_baku b ON b.id = dp.bahan_baku_id
    WHERE DATE(pj.tanggal) BETWEEN ? AND ?
    GROUP BY dp.bahan_baku_id
    ORDER BY total_terpakai DESC
    LIMIT 10
");
$stmt->execute([$awal, $akhir]);
$bahan_terlaku = $stmt->fetchAll();

// ===== 4. RIWAYAT TRANSAKSI (list detail) =====
$stmt = $pdo->prepare("
    SELECT pj.*, p.nama_pemesan,
           (SELECT COALESCE(SUM(biaya_operasional * qty), 0) 
            FROM detail_penjualan_item 
            WHERE penjualan_id = pj.id) as biaya_ops,
           (SELECT COALESCE(SUM(biaya_jasa * qty), 0) 
            FROM detail_penjualan_item 
            WHERE penjualan_id = pj.id) as biaya_jasa
    FROM penjualan pj
    LEFT JOIN pesanan p ON p.id = pj.pesanan_id
    WHERE DATE(pj.tanggal) BETWEEN ? AND ?
    ORDER BY pj.tanggal DESC
");
$stmt->execute([$awal, $akhir]);
$riwayat_transaksi = $stmt->fetchAll();

$total_omzet_riwayat = 0;
$total_biaya_ops_riwayat = 0;
$total_biaya_jasa_riwayat = 0;
$total_profit_riwayat = 0;
foreach ($riwayat_transaksi as $t) {
    $total_omzet_riwayat += (float)$t['total_harga'];
    $total_biaya_ops_riwayat += (float)$t['biaya_ops'];
    $total_biaya_jasa_riwayat += (float)$t['biaya_jasa'];
    $total_profit_riwayat += (float)$t['profit'];
}

$nama_bulan_indo = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];

// ===== LABA RUGI CALCULATIONS =====
$persediaan_awal  = 0;
$persediaan_akhir = 0;
$belanja_bahan    = 0;
$hpp              = 0;
$total_jasa_rangkai = 0;
$hpp_gabungan     = 0;
$untung_kotor     = 0;
$biaya_operasional_riil = 0;
$ops_breakdown    = [];
$total_ongkir_belanja = 0;
$untung_bersih    = 0;

$total_buket_laku = 0;
$target_anggaran_ops = 0;
$realisasi_lapangan  = 0;
$evaluasi_selisih    = 0;
$snapshot_info    = null;

// 1. Pengeluaran Riil (Kas Operasional)
$stmt_ops = $pdo->prepare("
    SELECT kategori, COALESCE(SUM(nominal), 0) as total
    FROM kas_operasional 
    WHERE jenis = 'keluar' 
      AND kategori IN ('Biaya Operasional', 'Alat & Perlengkapan', 'Listrik & Air', 'Gaji & Uang Makan')
      AND DATE(tanggal) BETWEEN ? AND ?
    GROUP BY kategori
");
$stmt_ops->execute([$awal, $akhir]);
foreach ($stmt_ops->fetchAll() as $row) {
    $ops_breakdown[$row['kategori']] = (float)$row['total'];
    $biaya_operasional_riil += (float)$row['total'];
}

// 2. Tambahkan ongkos kirim belanja bahan baku ke pengeluaran operasional
$stmt_ongkir = $pdo->prepare("
    SELECT COALESCE(SUM(biaya_ongkir), 0)
    FROM riwayat_stok
    WHERE jenis = 'masuk' AND status_po = 'lunas' AND DATE(created_at) BETWEEN ? AND ?
");
$stmt_ongkir->execute([$awal, $akhir]);
$total_ongkir_belanja = (float)$stmt_ongkir->fetchColumn();
$biaya_operasional_riil += $total_ongkir_belanja;

if ($tab === 'labarugi') {
    // 1. Cek snapshot bulan ini
    $target_month = date('Y-m', strtotime($awal));
    $stmt_snap = $pdo->prepare("SELECT * FROM snapshot_persediaan WHERE bulan = ?");
    $stmt_snap->execute([$target_month]);
    $snapshot_info = $stmt_snap->fetch();

    // 2. Persediaan awal & akhir
    $persediaan_awal  = getInventoryValueAtDate($awal, $pdo);
    $persediaan_akhir = getInventoryValueEnd($akhir, $pdo);

    // 3. Belanja bahan baku
    $stmt_belanja = $pdo->prepare("
        SELECT COALESCE(SUM(total_biaya - biaya_ongkir), 0) 
        FROM riwayat_stok 
        WHERE jenis = 'masuk' AND status_po = 'lunas' AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt_belanja->execute([$awal, $akhir]);
    $belanja_bahan = (float)$stmt_belanja->fetchColumn();

    // 4. Hitung HPP & Untung Kotor
    $hpp = $persediaan_awal + $belanja_bahan - $persediaan_akhir;
    $hpp = max(0, $hpp);
    
    // 4b. Hitung Jasa Rangkai Terakumulasi
    $stmt_jasa = $pdo->prepare("
        SELECT COALESCE(SUM(biaya_jasa * qty), 0)
        FROM detail_penjualan_item dpi
        JOIN penjualan pj ON pj.id = dpi.penjualan_id
        WHERE DATE(pj.tanggal) BETWEEN ? AND ?
    ");
    $stmt_jasa->execute([$awal, $akhir]);
    $total_jasa_rangkai = (float)$stmt_jasa->fetchColumn();

    $hpp_gabungan = $hpp + $total_jasa_rangkai;
    $untung_kotor = $ringkasan['omzet'] - $hpp_gabungan;

    // 6. Untung Bersih
    $untung_bersih = $untung_kotor - $biaya_operasional_riil;

    // 7. Perbandingan Estimasi vs Realisasi
    $stmt_qty = $pdo->prepare("
        SELECT COALESCE(SUM(qty), 0)
        FROM detail_penjualan_item dpi
        JOIN penjualan pj ON pj.id = dpi.penjualan_id
        WHERE DATE(pj.tanggal) BETWEEN ? AND ?
    ");
    $stmt_qty->execute([$awal, $akhir]);
    $total_buket_laku = (int)$stmt_qty->fetchColumn();

    $stmt_target_ops = $pdo->prepare("
        SELECT COALESCE(SUM(biaya_operasional * qty), 0)
        FROM detail_penjualan_item dpi
        JOIN penjualan pj ON pj.id = dpi.penjualan_id
        WHERE DATE(pj.tanggal) BETWEEN ? AND ?
    ");
    $stmt_target_ops->execute([$awal, $akhir]);
    $target_anggaran_ops = (float)$stmt_target_ops->fetchColumn();

    $realisasi_lapangan = (float)($ops_breakdown['Biaya Operasional'] ?? 0) 
                        + (float)($ops_breakdown['Alat & Perlengkapan'] ?? 0) 
                        + (float)($ops_breakdown['Listrik & Air'] ?? 0)
                        + $total_ongkir_belanja;

    $evaluasi_selisih = $target_anggaran_ops - $realisasi_lapangan;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <!-- ===== FILTER ===== -->
            <div class="panel" style="margin-bottom:20px;">
                <div class="filter-form">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="?mode=harian&tanggal=<?= $tanggal ?>" class="btn <?= $mode === 'harian' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Harian</a>
                        <a href="?mode=bulanan&bulan=<?= $bulan ?>" class="btn <?= $mode === 'bulanan' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Bulanan</a>
                        <a href="?mode=tahunan&tahun=<?= $tahun ?>" class="btn <?= $mode === 'tahunan' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tahunan</a>
                        <a href="?mode=rentang&tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>" class="btn <?= $mode === 'rentang' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Rentang Tanggal</a>
                    </div>

                    <?php if ($mode === 'harian'): ?>
                        <form method="GET" style="display:contents;">
                            <input type="hidden" name="mode" value="harian">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Pilih Tanggal</label>
                                <input type="date" name="tanggal" value="<?= $tanggal ?>" onchange="this.form.submit()">
                            </div>
                        </form>
                    <?php elseif ($mode === 'bulanan'): ?>
                        <form method="GET" style="display:contents;">
                            <input type="hidden" name="mode" value="bulanan">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Pilih Bulan</label>
                                <input type="month" name="bulan" value="<?= $bulan ?>" onchange="this.form.submit()">
                            </div>
                        </form>
                    <?php elseif ($mode === 'tahunan'): ?>
                        <form method="GET" style="display:contents;">
                            <input type="hidden" name="mode" value="tahunan">
                            <div class="form-group" style="margin-bottom:0; width:120px;">
                                <label>Pilih Tahun</label>
                                <select name="tahun" onchange="this.form.submit()">
                                    <?php 
                                    $current_year = (int)date('Y');
                                    for ($y = $current_year; $y >= $current_year - 5; $y--): 
                                    ?>
                                        <option value="<?= $y ?>" <?= (int)$tahun === $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </form>
                    <?php elseif ($mode === 'rentang'): ?>
                        <form method="GET" style="display:contents;">
                            <input type="hidden" name="mode" value="rentang">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Tanggal Awal</label>
                                <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Tanggal Akhir</label>
                                <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <p style="color:var(--color-text-muted); font-size:13px; margin-top:-10px;">
                Menampilkan data:
                <strong>
                    <?php if ($mode === 'harian'): ?>
                        <?= date('d M Y', strtotime($tanggal)) ?>
                    <?php elseif ($mode === 'bulanan'): ?>
                        <?= $nama_bulan_indo[(int) date('n', strtotime($awal))] . ' ' . date('Y', strtotime($awal)) ?>
                    <?php elseif ($mode === 'tahunan'): ?>
                        Tahun <?= date('Y', strtotime($awal)) ?>
                    <?php elseif ($mode === 'rentang'): ?>
                        Rentang <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?>
                    <?php endif; ?>
                </strong>
            </p>

            <!-- ===== TAB SWITCHER ===== -->
            <div style="display:flex; gap:12px; margin-bottom:20px; border-bottom:2px solid var(--color-border); padding-bottom:8px; flex-wrap:wrap;">
                <a href="?tab=ringkasan&mode=<?= $mode ?>&tanggal=<?= urlencode($tanggal) ?>&bulan=<?= urlencode($bulan) ?>&tahun=<?= urlencode($tahun) ?>&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>" 
                   style="font-size:16px; font-weight:700; color:<?= $tab === 'ringkasan' ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>; text-decoration:none; padding:8px 16px; border-bottom: <?= $tab === 'ringkasan' ? '3px solid var(--color-primary)' : 'none' ?>; margin-bottom:-10px; transition:all 0.15s ease;">
                    Ringkasan Penjualan
                </a>
                <a href="?tab=labarugi&mode=<?= $mode ?>&tanggal=<?= urlencode($tanggal) ?>&bulan=<?= urlencode($bulan) ?>&tahun=<?= urlencode($tahun) ?>&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>" 
                   style="font-size:16px; font-weight:700; color:<?= $tab === 'labarugi' ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>; text-decoration:none; padding:8px 16px; border-bottom: <?= $tab === 'labarugi' ? '3px solid var(--color-primary)' : 'none' ?>; margin-bottom:-10px; transition:all 0.15s ease;">
                    Laporan Laba Rugi
                </a>
            </div>

            <?php if ($tab === 'ringkasan'): ?>
                <!-- ===== RINGKASAN ===== -->
                <div class="card-grid">
                    <div class="card">
                        <div class="card-label">Omzet</div>
                        <div class="card-value">Rp<?= number_format($ringkasan['omzet'], 0, ',', '.') ?></div>
                    </div>
                    <div class="card">
                        <div class="card-label">Modal</div>
                        <div class="card-value">Rp<?= number_format($ringkasan['modal'], 0, ',', '.') ?></div>
                    </div>
                    <div class="card">
                        <div class="card-label">Profit</div>
                        <div class="card-value">Rp<?= number_format($ringkasan['profit'], 0, ',', '.') ?></div>
                    </div>
                    <div class="card">
                        <div class="card-label">Jumlah Transaksi</div>
                        <div class="card-value"><?= $ringkasan['jumlah_transaksi'] ?></div>
                    </div>
                </div>

                <div class="panel-grid" style="margin-bottom:24px;">
                    <!-- ===== PRODUK TERLARIS ===== -->
                    <div class="panel">
                        <h3>Produk Terlaris</h3>
                        <?php if (empty($produk_terlaris)): ?>
                            <p style="color:var(--color-text-muted); font-size:13px;">Belum ada data penjualan produk di periode ini.</p>
                        <?php else: ?>
                            <table>
                                <thead><tr><th>Produk</th><th>Terjual</th></tr></thead>
                                <tbody>
                                    <?php foreach ($produk_terlaris as $i => $p): ?>
                                    <tr>
                                        <td><?= $i + 1 ?>. <?= htmlspecialchars($p['nama']) ?></td>
                                        <td><span class="badge badge-diambil"><?= $p['total_terjual'] ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- ===== BAHAN PALING LAKU ===== -->
                    <div class="panel">
                        <h3>Bahan Paling Laku</h3>
                        <?php if (empty($bahan_terlaku)): ?>
                            <p style="color:var(--color-text-muted); font-size:13px;">Belum ada data pemakaian bahan di periode ini.</p>
                        <?php else: ?>
                            <table>
                                <thead><tr><th>Bahan</th><th>Terpakai</th></tr></thead>
                                <tbody>
                                    <?php foreach ($bahan_terlaku as $i => $b): ?>
                                    <tr>
                                        <td><?= $i + 1 ?>. <?= htmlspecialchars($b['nama']) ?></td>
                                        <td><span class="badge badge-menunggu"><?= (float)$b['total_terpakai'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== RIWAYAT TRANSAKSI ===== -->
                <div class="panel">
                    <h3>Riwayat Transaksi</h3>
                    <?php if (empty($riwayat_transaksi)): ?>
                        <p style="color:var(--color-text-muted); font-size:13px;">Tidak ada transaksi di periode ini.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px;">
                            <table style="width: 100%; min-width: 800px; font-size: 13px;">
                                <thead>
                                    <tr>
                                        <th style="padding: 10px 8px;">Waktu</th>
                                        <th style="padding: 10px 8px;">Sumber</th>
                                        <th style="padding: 10px 8px;">Metode</th>
                                        <th style="padding: 10px 8px;">Pembeli</th>
                                        <th style="padding: 10px 8px;">Total</th>
                                        <th style="padding: 10px 8px;">Biaya Ops</th>
                                        <th style="padding: 10px 8px;">Biaya Jasa</th>
                                        <th style="padding: 10px 8px;">Profit</th>
                                        <th style="padding: 10px 8px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat_transaksi as $t): ?>
                                    <tr>
                                        <td style="padding: 8px;"><?= date('d M Y, H:i', strtotime($t['tanggal'])) ?></td>
                                        <td style="padding: 8px;">
                                            <?= $t['sumber'] === 'pesanan'
                                                ? '<span class="badge badge-diambil">Pesanan</span>'
                                                : '<span class="badge badge-menunggu">Walk-in</span>' ?>
                                        </td>
                                        <td style="padding: 8px;"><span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700;"><?= htmlspecialchars(($t['metode_pembayaran'] ?? 'cash') === 'qris' ? 'DANA' : ($t['metode_pembayaran'] ?? 'cash')) ?></span></td>
                                        <td style="padding: 8px;"><?= htmlspecialchars($t['nama_pemesan'] ?? $t['nama_pembeli'] ?? '-') ?></td>
                                        <td style="padding: 8px;">Rp<?= number_format($t['total_harga'], 0, ',', '.') ?></td>
                                        <td style="padding: 8px;">Rp<?= number_format($t['biaya_ops'], 0, ',', '.') ?></td>
                                        <td style="padding: 8px;">Rp<?= number_format($t['biaya_jasa'], 0, ',', '.') ?></td>
                                        <td style="padding: 8px;">Rp<?= number_format($t['profit'], 0, ',', '.') ?></td>
                                        <td style="padding: 8px;"><a href="cetak_struk.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="margin: 0; padding: 4px 8px;">Struk</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight:800; border-top:2px solid var(--color-border); background:#FAF5F2;">
                                        <td colspan="4" style="text-align:right; padding:10px; font-size:13px;">Total Halaman:</td>
                                        <td style="padding:10px; font-size:13px;">Rp<?= number_format($total_omzet_riwayat, 0, ',', '.') ?></td>
                                        <td style="padding:10px; font-size:13px;">Rp<?= number_format($total_biaya_ops_riwayat, 0, ',', '.') ?></td>
                                        <td style="padding:10px; font-size:13px;">Rp<?= number_format($total_biaya_jasa_riwayat, 0, ',', '.') ?></td>
                                        <td style="padding:10px; font-size:13px; color:var(--color-primary);">Rp<?= number_format($total_profit_riwayat, 0, ',', '.') ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- ===== LABA RUGI ACCOUNTING BREAKDOWN ===== -->
                <div class="panel">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                        <h3 style="margin:0;">Laporan Laba Rugi</h3>
                        
                        <?php if ($mode === 'bulanan'): ?>
                            <form action="../actions/tutup_buku.php" method="POST" style="margin:0;">
                                <input type="hidden" name="bulan" value="<?= htmlspecialchars($bulan) ?>">
                                <?php if ($snapshot_info): ?>
                                    <button type="submit" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;" title="Klik untuk hitung ulang & kunci kembali">
                                        🔒 Tutup Buku Bulan Ini (Terkunci: Rp<?= number_format($snapshot_info['nilai_persediaan'], 0, ',', '.') ?>)
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary btn-sm" style="cursor:pointer;">
                                        🔑 Tutup Buku Bulan Ini
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div style="max-width:700px; margin:0 auto; padding:24px; background:#FAF5F2; border-radius:16px; border:1.5px solid #EADCD9; box-shadow: 0 4px 15px rgba(164, 59, 34, 0.05);">
                        <div style="text-align:center; margin-bottom:20px;">
                            <h2 style="margin:0; font-size:18px; color:var(--color-primary); text-transform:uppercase; font-weight:800; letter-spacing:0.5px;">Laporan Keuntungan Toko (Laba Rugi)</h2>
                            <p style="margin:4px 0 0 0; font-size:12px; color:var(--color-text-muted); font-weight:600;">
                                Periode: 
                                <?php if ($mode === 'harian'): ?>
                                    <?= date('d M Y', strtotime($tanggal)) ?>
                                <?php elseif ($mode === 'bulanan'): ?>
                                    <?= $nama_bulan_indo[(int) date('n', strtotime($awal))] . ' ' . date('Y', strtotime($awal)) ?>
                                <?php elseif ($mode === 'tahunan'): ?>
                                    Tahun <?= date('Y', strtotime($awal)) ?>
                                <?php elseif ($mode === 'rentang'): ?>
                                    <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <table style="width:100%; font-family:'Plus Jakarta Sans', sans-serif; font-size:13px; border-collapse:collapse; line-height:1.6;">
                            <!-- 1. TOTAL PENJUALAN (OMZET) -->
                            <tr style="font-weight:700; font-size:14px; color:var(--color-text-dark); border-bottom:1px solid #EADCD9;">
                                <td style="padding:8px 0;" colspan="2">1. TOTAL PENJUALAN (OMZET)</td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(+) Semua Uang Masuk dari Jualan Buket / Penjualan</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($ringkasan['omzet'], 0, ',', '.') ?></td>
                            </tr>
                            <tr style="font-weight:700; background:#FAF0EC;">
                                <td style="padding:6px 0 6px 16px; border-top:1px dashed #EADCD9; border-bottom:1px dashed #EADCD9;">TOTAL PENDAPATAN KOTOR</td>
                                <td style="text-align:right; padding:6px 0; border-top:1px dashed #EADCD9; border-bottom:1px dashed #EADCD9; color:var(--color-primary);">Rp<?= number_format($ringkasan['omzet'], 0, ',', '.') ?></td>
                            </tr>
                            <tr style="height:12px;"><td colspan="2"></td></tr>

                            <!-- 2. MODAL BAHAN BAKU & JASA (HPP) -->
                            <tr style="font-weight:700; font-size:14px; color:var(--color-text-dark); border-bottom:1px solid #EADCD9;">
                                <td style="padding:8px 0;" colspan="2">2. MODAL BAHAN BAKU & JASA (HPP)</td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(+) Persediaan Awal Bahan Baku (Sisa Bulan Lalu)</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($persediaan_awal, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(+) Belanja Bahan Baku Baru (Periode Ini)</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($belanja_bahan, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(-) Persediaan Akhir Bahan Baku (Sisa di Gudang)</td>
                                <td style="text-align:right; padding:6px 0; color:#A43B22;">(Rp<?= number_format($persediaan_akhir, 0, ',', '.') ?>)</td>
                            </tr>
                            <tr style="font-weight:700; color:var(--color-text-dark);">
                                <td style="padding:6px 0 6px 16px; border-top:1px dashed #EADCD9;">Sub-Total Modal Fisik Bahan Baku</td>
                                <td style="text-align:right; padding:6px 0; border-top:1px dashed #EADCD9;">Rp<?= number_format($hpp, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(+) Total Jasa Rangkai Terakumulasi (Jasa Buket)</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($total_jasa_rangkai, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="font-weight:700; background:#FAF0EC;">
                                <td style="padding:6px 0 6px 16px; border-top:1px dashed #EADCD9; border-bottom:1px dashed #EADCD9;">TOTAL MODAL PRODUK (HPP GABUNGAN)</td>
                                <td style="text-align:right; padding:6px 0; border-top:1px dashed #EADCD9; border-bottom:1px dashed #EADCD9; color:#A43B22;">Rp<?= number_format($hpp_gabungan, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="height:8px;"><td colspan="2"></td></tr>
                            <tr style="font-weight:800; font-size:14px; background:#FAF0EC;">
                                <td style="padding:8px 0 8px 16px; border-bottom:1px dashed #EADCD9;">UNTUNG KOTOR (PENDAPATAN − TOTAL MODAL PRODUK)</td>
                                <td style="text-align:right; padding:8px 0; border-bottom:1px dashed #EADCD9; color:var(--color-primary);">Rp<?= number_format($untung_kotor, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="height:12px;"><td colspan="2"></td></tr>

                            <!-- 3. BIAYA OPERASIONAL RIIL (DARI KAS HARIAN) -->
                            <tr style="font-weight:700; font-size:14px; color:var(--color-text-dark); border-bottom:1px solid #EADCD9;">
                                <td style="padding:8px 0;" colspan="2">3. BIAYA OPERASIONAL RIIL (DARI KAS HARIAN)</td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(-) Bayar Listrik & Air Toko</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($ops_breakdown['Listrik & Air'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(-) Ongkir Cargo Bunga Belanja Bahan</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($total_ongkir_belanja, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(-) Beli Alat Toko & Perlengkapan (Lem/Pita/Lainnya)</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($ops_breakdown['Alat & Perlengkapan'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(-) Gaji Karyawan & Florist</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($ops_breakdown['Gaji & Uang Makan'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="color:var(--color-text-muted); font-weight:600;">
                                <td style="padding:6px 0 6px 16px;">(-) Pengeluaran Operasional Harian</td>
                                <td style="text-align:right; padding:6px 0;">Rp<?= number_format($ops_breakdown['Biaya Operasional'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="font-weight:700; background:#FAF0EC;">
                                <td style="padding:6px 0 6px 16px; border-top:1px dashed #EADCD9; border-bottom:1px dashed #EADCD9;">TOTAL SELURUH PENGELUARAN OPERASIONAL</td>
                                <td style="text-align:right; padding:6px 0; border-top:1px dashed #EADCD9; border-bottom:1px dashed #EADCD9; color:#A43B22;">Rp<?= number_format($biaya_operasional_riil, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="height:12px;"><td colspan="2"></td></tr>

                            <!-- UNTUNG BERSIH (UANG MURNI JADI MILIK TOKO) -->
                            <tr style="font-weight:800; font-size:14px; background:#FAF0EC; border:1px solid #EADCD9;">
                                <td style="padding:10px 0 10px 16px;">UNTUNG BERSIH (UANG MURNI JADI MILIK TOKO)</td>
                                <td style="text-align:right; padding:10px 0;">
                                    <?php if ($untung_bersih >= 0): ?>
                                        <span class="badge badge-diambil" style="font-size:12px; padding:6px 12px; font-weight:700;">Rp<?= number_format($untung_bersih, 0, ',', '.') ?> [UNTUNG]</span>
                                    <?php else: ?>
                                        <span class="badge badge-dibatalkan" style="font-size:12px; padding:6px 12px; font-weight:700;">(Rp<?= number_format(abs($untung_bersih), 0, ',', '.') ?>) [RUGI]</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                        <!-- KOTAK EVALUASI BUDGET OPERASIONAL -->
                        <div style="margin-top:24px; padding:18px; background:#ffffff; border-radius:12px; border:2px dashed #EADCD9; font-family:'Plus Jakarta Sans', sans-serif;">
                            <h4 style="margin:0 0 12px 0; font-size:12px; color:var(--color-primary); font-weight:800; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #F3EAE8; padding-bottom:6px;">
                                📊 KOTAK EVALUASI BUDGET OPERASIONAL (ALOKASI BIAYA OPS DI TIAP PRODUK BUKET)
                            </h4>
                            <ul style="margin:0; padding-left:16px; font-size:12px; color:var(--color-text-dark); line-height:1.8; list-style-type:square;">
                                <li>
                                    Total Buket yang Laku Periode Ini : 
                                    <strong><?= $total_buket_laku ?> Pcs</strong> (Hasil penjualan langsung & pesanan selesai)
                                </li>
                                <li>
                                    Target Anggaran Toko Terkumpul : 
                                    <strong>Rp<?= number_format($target_anggaran_ops, 0, ',', '.') ?></strong> (Akumulasi alokasi biaya ops tiap produk yang terjual)
                                </li>
                                <li>
                                    Pengeluaran Lapangan Aslinya : 
                                    <strong>Rp<?= number_format($realisasi_lapangan, 0, ',', '.') ?></strong> (Total biaya listrik, bensin, lem, alat toko, & ongkir belanja)
                                </li>
                                <li style="margin-top:8px; list-style:none; padding-left:0;">
                                    <?php if ($evaluasi_selisih >= 0): ?>
                                        <span class="badge badge-diambil" style="font-size:11px; padding:4px 8px; font-weight:700;">
                                            🟢 STATUS: HEMAT / SURPLUS +Rp<?= number_format($evaluasi_selisih, 0, ',', '.') ?>
                                        </span>
                                        <span style="font-size:11px; color:var(--color-text-muted); margin-left:6px; font-weight:600;">
                                            -> Pengeluaran operasional & alat riil lebih hemat dari perkiraan!
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-dibatalkan" style="font-size:11px; padding:4px 8px; font-weight:700;">
                                            🔴 STATUS: BOROS / MINUS -Rp<?= number_format(abs($evaluasi_selisih), 0, ',', '.') ?>
                                        </span>
                                        <span style="font-size:11px; color:var(--color-text-muted); margin-left:6px; font-weight:600;">
                                            -> Pengeluaran operasional & alat riil lebih besar dari perkiraan!
                                        </span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php include __DIR__ . '/../includes/footer.php'; ?>