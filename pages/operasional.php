<?php
// pages/operasional.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Kas Operasional';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Filter Tanggal
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filter_metode = $_GET['metode'] ?? '';

$where_clauses = ["tanggal BETWEEN ? AND ?"];
$params = [$tgl_awal, $tgl_akhir];

if (!empty($filter_metode)) {
    $where_clauses[] = "metode_pembayaran = ?";
    $params[] = $filter_metode;
}

$where_sql = implode(" AND ", $where_clauses);

// Query Data Kas Operasional
$stmt = $pdo->prepare("SELECT * FROM kas_operasional WHERE {$where_sql} ORDER BY tanggal DESC, id DESC");
$stmt->execute($params);
$riwayat_kas = $stmt->fetchAll();

// Total Ringkasan Kas Operasional Periode Ini
$stmt_sum = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN nominal ELSE 0 END), 0) AS total_keluar
    FROM kas_operasional 
    WHERE {$where_sql}
");
$stmt_sum->execute($params);
$ringkasan_kas = $stmt_sum->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kas Operasional - Sunshine Florist</title>
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

            <p style="margin-top:-12px; color:var(--color-text-muted); font-size:13px;">
                Pencatatan rincian biaya pengeluaran dan pemasukan harian di luar pesanan (seperti bayar listrik, kebutuhan pribadi, alat, dll).
            </p>

            <!-- ===== FORM INPUT KAS OPERASIONAL ===== -->
            <div class="panel" style="margin-bottom:24px;">
                <h3>+ Input Kas Harian / Operasional</h3>
                <form action="../actions/simpan_operasional.php" method="POST" enctype="multipart/form-data">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
                        
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Jenis Transaksi</label>
                            <div style="display:flex; gap:16px; align-items:center; height:42px;">
                                <label style="font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                    <input type="radio" name="jenis" value="keluar" checked>
                                    Arus Keluar (-)
                                </label>
                                <label style="font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                    <input type="radio" name="jenis" value="masuk">
                                    Arus Masuk (+)
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori" required>
                                <option value="Biaya Operasional">Biaya Operasional</option>
                                <option value="Alat & Perlengkapan">Alat & Perlengkapan</option>
                                <option value="Listrik & Air">Listrik & Air</option>
                                <option value="Gaji & Uang Makan">Gaji & Uang Makan</option>
                                <option value="Kebutuhan Pribadi">Kebutuhan Pribadi</option>
                                <option value="Pemasukan Lain">Pemasukan Lain</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Metode Pembayaran</label>
                            <select name="metode_pembayaran" required>
                                <option value="cash">Cash (Tunai)</option>
                                <option value="bri">Bank BRI</option>
                                <option value="seabank">SeaBank</option>
                                <option value="dana">DANA</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nominal (Rp)</label>
                            <input type="number" name="nominal" placeholder="0" min="1" required style="max-width:100%;">
                        </div>

                        <div class="form-group">
                            <label>Keterangan</label>
                            <input type="text" name="keterangan" placeholder="Contoh: Bayar token listrik toko" required>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Unggah Foto Nota (opsional)</label>
                            <input type="file" name="foto_nota" accept="image/*" style="height:auto; padding:8px 0;">
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:8px;">+ Simpan Transaksi Kas</button>
                </form>
            </div>

            <!-- ===== FILTER BAR ===== -->
            <div class="panel" style="margin-bottom:20px;">
                <form method="GET" class="filter-form">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Tanggal Awal</label>
                        <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Metode Pembayaran</label>
                        <select name="metode">
                            <option value="">-- Semua Metode --</option>
                            <option value="cash" <?= $filter_metode === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="bri" <?= $filter_metode === 'bri' ? 'selected' : '' ?>>BRI</option>
                            <option value="seabank" <?= $filter_metode === 'seabank' ? 'selected' : '' ?>>SeaBank</option>
                            <option value="dana" <?= $filter_metode === 'dana' ? 'selected' : '' ?>>DANA</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height:42px; margin-bottom:0;">Filter</button>
                    <a href="operasional.php" class="btn btn-secondary btn-sm" style="height:42px; margin-bottom:0; display:inline-flex; align-items:center;">Reset</a>
                </form>
            </div>

            <!-- ===== RINGKASAN SUB-PERIODE ===== -->
            <div style="display:flex; gap:16px; margin-bottom:20px;">
                <div class="card" style="flex:1;">
                    <div class="card-label">Total Arus Masuk (+)</div>
                    <div class="card-value" style="color:var(--color-success);">Rp<?= number_format($ringkasan_kas['total_masuk'], 0, ',', '.') ?></div>
                </div>
                <div class="card" style="flex:1;">
                    <div class="card-label">Total Arus Keluar (-)</div>
                    <div class="card-value" style="color:var(--color-danger);">Rp<?= number_format($ringkasan_kas['total_keluar'], 0, ',', '.') ?></div>
                </div>
            </div>

            <!-- ===== TABEL DAFTAR KAS OPERASIONAL ===== -->
            <div class="panel">
                <h3>Riwayat Kas Operasional</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Keterangan</th>
                            <th>Nota</th>
                            <th style="width:60px; text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat_kas)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:var(--color-text-muted); padding:24px;">
                                    Belum ada transaksi kas operasional di periode ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat_kas as $k): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($k['tanggal'])) ?></td>
                                    <td>
                                        <span class="badge" style="background:<?= $k['jenis'] === 'masuk' ? '#DEF7EC' : '#FEE2E2' ?>; color:<?= $k['jenis'] === 'masuk' ? '#03543F' : '#DC2626' ?>; font-weight:700;">
                                            <?= $k['jenis'] === 'masuk' ? 'Arus Masuk (+)' : 'Arus Keluar (-)' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($k['kategori']) ?></strong></td>
                                    <td><span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700;"><?= htmlspecialchars($k['metode_pembayaran'] === 'qris' ? 'DANA' : $k['metode_pembayaran']) ?></span></td>
                                    <td style="font-weight:700; color:<?= $k['jenis'] === 'masuk' ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                        <?= $k['jenis'] === 'masuk' ? '+' : '-' ?>Rp<?= number_format($k['nominal'], 0, ',', '.') ?>
                                    </td>
                                    <td><?= htmlspecialchars($k['keterangan'] ?: '-') ?></td>
                                    <td>
                                        <?php if (!empty($k['foto_nota'])): ?>
                                            <a href="../<?= htmlspecialchars($k['foto_nota']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:2px 8px; font-size:11px;">View Nota</a>
                                        <?php else: ?>
                                            <span style="color:var(--color-text-muted); font-size:12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <form action="../actions/hapus_operasional.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus catatan kas ini?')">
                                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px;" title="Hapus">
                                                <svg viewBox="0 0 24 24" width="14" height="14" stroke="white" stroke-width="2" fill="none"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
