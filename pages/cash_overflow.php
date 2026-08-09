<?php
// pages/cash_overflow.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Cash Overflow';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Filter Tanggal & Metode
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filter_metode = $_GET['metode'] ?? '';

// ===== 1. KUMPULKAN SEMUA SUMBER TRANSAKSI KEUANGAN =====
$mutasi_all = [];

// A. Streams dari Kas Operasional
$stmt_op = $pdo->prepare("SELECT * FROM kas_operasional WHERE tanggal BETWEEN ? AND ?");
$stmt_op->execute([$tgl_awal, $tgl_akhir]);
foreach ($stmt_op->fetchAll() as $op) {
    $mutasi_all[] = [
        'timestamp' => strtotime($op['tanggal'] . ' ' . date('H:i:s', strtotime($op['created_at']))),
        'tanggal' => $op['tanggal'],
        'created_at' => $op['created_at'],
        'jenis' => $op['jenis'], // 'masuk' / 'keluar'
        'kategori' => $op['kategori'],
        'metode' => !empty($op['metode_pembayaran']) ? $op['metode_pembayaran'] : 'cash',
        'nominal' => (float)$op['nominal'],
        'keterangan' => $op['keterangan'] ?: '-',
        'nota' => $op['foto_nota'],
        'sumber' => 'Kas Operasional'
    ];
}

// B1. Streams dari DP Pesanan yang Masih Pending (Menunggu)
$stmt_pending_dp = $pdo->prepare("
    SELECT id, nama_pemesan, dp, metode_pembayaran, created_at
    FROM pesanan
    WHERE status = 'menunggu' AND dp > 0 AND DATE(created_at) BETWEEN ? AND ?
");
$stmt_pending_dp->execute([$tgl_awal, $tgl_akhir]);
foreach ($stmt_pending_dp->fetchAll() as $p) {
    $mutasi_all[] = [
        'timestamp' => strtotime($p['created_at']),
        'tanggal' => date('Y-m-d', strtotime($p['created_at'])),
        'created_at' => $p['created_at'],
        'jenis' => 'masuk',
        'kategori' => 'Uang Muka (DP)',
        'metode' => !empty($p['metode_pembayaran']) ? $p['metode_pembayaran'] : 'cash',
        'nominal' => (float)$p['dp'],
        'keterangan' => "DP Pesanan #" . $p['id'] . " (" . ($p['nama_pemesan'] ?: 'Pelanggan') . ")",
        'nota' => null,
        'sumber' => 'Penjualan'
    ];
}

// B2. Streams dari Penjualan / Pesanan Selesai (Walk-in & Completed Sales)
$stmt_pj = $pdo->prepare("
    SELECT pj.*, p.nama_pemesan, p.metode_pembayaran AS pesanan_metode, p.dp AS pesanan_dp, p.created_at AS pesanan_tgl_buat
    FROM penjualan pj
    LEFT JOIN pesanan p ON p.id = pj.pesanan_id
    WHERE (DATE(pj.tanggal) BETWEEN ? AND ?) OR (pj.pesanan_id IS NOT NULL AND DATE(p.created_at) BETWEEN ? AND ?)
");
$stmt_pj->execute([$tgl_awal, $tgl_akhir, $tgl_awal, $tgl_akhir]);
foreach ($stmt_pj->fetchAll() as $pj) {
    if ($pj['pesanan_id']) {
        $dp = (float)$pj['pesanan_dp'];
        $total = (float)$pj['total_harga'];
        $sisa = $total - $dp;
        $nama = $pj['nama_pemesan'] ?: 'Pelanggan';

        // 1. DP Entry (pada tanggal pembuatan pesanan)
        if ($dp > 0) {
            $tgl_dp = date('Y-m-d', strtotime($pj['pesanan_tgl_buat']));
            if ($tgl_dp >= $tgl_awal && $tgl_dp <= $tgl_akhir) {
                $mutasi_all[] = [
                    'timestamp' => strtotime($pj['pesanan_tgl_buat']),
                    'tanggal' => $tgl_dp,
                    'created_at' => $pj['pesanan_tgl_buat'],
                    'jenis' => 'masuk',
                    'kategori' => 'Uang Muka (DP)',
                    'metode' => !empty($pj['pesanan_metode']) ? $pj['pesanan_metode'] : 'cash',
                    'nominal' => $dp,
                    'keterangan' => "DP Pesanan #" . $pj['pesanan_id'] . " (" . $nama . ")",
                    'nota' => null,
                    'sumber' => 'Penjualan'
                ];
            }
        }

        // 2. Pelunasan/Sisa Entry (pada tanggal pesanan diambil)
        $sisa_nominal = $sisa;
        $ket_tambahan = "";
        if (($pj['ongkir_tipe'] ?? 'kurir') === 'toko' && (float)($pj['ongkir_nominal'] ?? 0) > 0) {
            $sisa_nominal += (float)$pj['ongkir_nominal'];
            $ket_tambahan = " [Inc. Ongkir Delivery Rp" . number_format($pj['ongkir_nominal'], 0, ',', '.') . "]";
        }

        if ($sisa_nominal > 0) {
            $tgl_pj = date('Y-m-d', strtotime($pj['tanggal']));
            if ($tgl_pj >= $tgl_awal && $tgl_pj <= $tgl_akhir) {
                $mutasi_all[] = [
                    'timestamp' => strtotime($pj['tanggal']),
                    'tanggal' => $tgl_pj,
                    'created_at' => $pj['tanggal'],
                    'jenis' => 'masuk',
                    'kategori' => $dp > 0 ? 'Pelunasan Pesanan' : 'Pesanan Selesai',
                    'metode' => !empty($pj['metode_pembayaran']) ? $pj['metode_pembayaran'] : 'cash',
                    'nominal' => $sisa_nominal,
                    'keterangan' => "Pelunasan Pesanan #" . $pj['pesanan_id'] . " (" . $nama . ")" . $ket_tambahan,
                    'nota' => null,
                    'sumber' => 'Penjualan'
                ];
            }
        }

        // 3. Penerusan Ongkir ke Kurir (Kas Keluar)
        if (($pj['ongkir_tipe'] ?? 'kurir') === 'toko' && (float)($pj['ongkir_nominal'] ?? 0) > 0) {
            $tgl_pj = date('Y-m-d', strtotime($pj['tanggal']));
            if ($tgl_pj >= $tgl_awal && $tgl_pj <= $tgl_akhir) {
                $mutasi_all[] = [
                    'timestamp' => strtotime($pj['tanggal']) + 1,
                    'tanggal' => $tgl_pj,
                    'created_at' => $pj['tanggal'],
                    'jenis' => 'keluar',
                    'kategori' => 'Ongkir Delivery Kurir',
                    'metode' => !empty($pj['ongkir_metode_kurir']) ? $pj['ongkir_metode_kurir'] : 'cash',
                    'nominal' => (float)$pj['ongkir_nominal'],
                    'keterangan' => "Penerusan Ongkir Delivery Pesanan #" . $pj['pesanan_id'] . " ke Kurir",
                    'nota' => null,
                    'sumber' => 'Penjualan'
                ];
            }
        }
    } else {
        // Walk-in atau Eceran
        $tgl_pj = date('Y-m-d', strtotime($pj['tanggal']));
        if ($tgl_pj >= $tgl_awal && $tgl_pj <= $tgl_akhir) {
            $is_eceran = ($pj['sumber'] ?? '') === 'eceran';
            $mutasi_all[] = [
                'timestamp' => strtotime($pj['tanggal']),
                'tanggal' => $tgl_pj,
                'created_at' => $pj['tanggal'],
                'jenis' => 'masuk',
                'kategori' => $is_eceran ? 'Penjualan Eceran' : 'Penjualan Walk-in',
                'metode' => !empty($pj['metode_pembayaran']) ? $pj['metode_pembayaran'] : 'cash',
                'nominal' => (float)$pj['total_harga'],
                'keterangan' => $is_eceran 
                    ? "Penjualan Bahan Eceran (" . ($pj['nama_pembeli'] ?: 'Umum') . ")"
                    : "Penjualan Walk-in (" . ($pj['nama_pembeli'] ?: 'Umum') . ")",
                'nota' => null,
                'sumber' => 'Penjualan'
            ];
        }
    }
}

// C. Streams dari Pembelian Stok Bahan Baku & Belanja PO
$stmt_st = $pdo->prepare("
    SELECT r.*, b.nama AS nama_bahan
    FROM riwayat_stok r
    JOIN bahan_baku b ON b.id = r.bahan_baku_id
    WHERE r.jenis = 'masuk' AND DATE(r.created_at) BETWEEN ? AND ?
");
$stmt_st->execute([$tgl_awal, $tgl_akhir]);
foreach ($stmt_st->fetchAll() as $st) {
    $nom = (float)($st['total_biaya'] ?? 0);
    if ($nom <= 0) continue; // Biarkan stok tanpa nilai biaya bernilai 0 tidak mengotori kas

    $status_po = $st['status_po'] ?? 'lunas';
    $biaya_ongkir = (float)($st['biaya_ongkir'] ?? 0);
    $metode_stok = !empty($st['metode_pembayaran']) ? $st['metode_pembayaran'] : 'cash';

    $kat = ($status_po === 'pending_po') ? 'Belanja PO Bahan' : 'Restock Bahan Baku';
    $ket = "Pembelian " . $st['nama_bahan'] . " (" . (float)$st['jumlah'] . " unit)";
    if ($biaya_ongkir > 0) {
        $ket .= " [Inc. Ongkir Rp" . number_format($biaya_ongkir, 0, ',', '.') . "]";
    }
    if (!empty($st['keterangan'])) {
        $ket .= " - " . $st['keterangan'];
    }

    $mutasi_all[] = [
        'timestamp' => strtotime($st['created_at']),
        'tanggal' => date('Y-m-d', strtotime($st['created_at'])),
        'created_at' => $st['created_at'],
        'jenis' => 'keluar',
        'kategori' => $kat,
        'metode' => $metode_stok,
        'nominal' => $nom,
        'keterangan' => $ket,
        'nota' => null,
        'sumber' => 'Pembelian Stok'
    ];
}

// Sort Kronologis (ASC) untuk Menghitung Saldo Akhir Running Balance
usort($mutasi_all, function($a, $b) {
    return $a['timestamp'] <=> $b['timestamp'];
});

// Hitung Running Balance untuk masing-masing Metode Pembayaran & Total Overall
$saldo_running = ['cash' => 0, 'bri' => 0, 'seabank' => 0, 'dana' => 0, 'total' => 0];

foreach ($mutasi_all as $idx => &$m) {
    $metode = strtolower($m['metode']);
    if (!isset($saldo_running[$metode])) {
        $saldo_running[$metode] = 0;
    }

    if ($m['jenis'] === 'masuk') {
        $saldo_running[$metode] += $m['nominal'];
        $saldo_running['total'] += $m['nominal'];
    } else {
        $saldo_running[$metode] -= $m['nominal'];
        $saldo_running['total'] -= $m['nominal'];
    }

    $m['saldo_akun'] = $saldo_running[$metode];
    $m['saldo_total'] = $saldo_running['total'];
}
unset($m);

// Hitung Ringkasan Kartu Saldo Berdasarkan Filter Metode (Jika dipilih)
$card_balances = [
    'cash' => $saldo_running['cash'],
    'bri' => $saldo_running['bri'],
    'seabank' => $saldo_running['seabank'],
    'dana' => $saldo_running['dana'],
    'total' => $saldo_running['total']
];

// Reverser array agar urutan tampil di tabel dari terbaru ke terlama (DESC)
$mutasi_display = array_reverse($mutasi_all);

// Filter tampilan berdasarkan metode jika ada
if (!empty($filter_metode)) {
    $mutasi_display = array_filter($mutasi_display, function($row) use ($filter_metode) {
        return strtolower($row['metode']) === strtolower($filter_metode);
    });
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Overflow - Sunshine Florist</title>
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
                Laporan jurnal mutasi kas terintegrasi (pesanan, restock bahan, & kas harian operasional) beserta saldo akhir rekening.
            </p>

            <!-- ===== 4+1 KARTU SALDO METODE PEMBAYARAN ===== -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:16px; margin-bottom:24px;">
                <div class="card" style="padding:18px 16px;">
                    <div class="card-label">Kas Tunai (Cash)</div>
                    <div class="card-value" style="font-size:20px; color:<?= $card_balances['cash'] >= 0 ? '#3E3533' : 'var(--color-danger)' ?>;">
                        Rp<?= number_format($card_balances['cash'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="card" style="padding:18px 16px;">
                    <div class="card-label">Bank BRI</div>
                    <div class="card-value" style="font-size:20px; color:<?= $card_balances['bri'] >= 0 ? '#3E3533' : 'var(--color-danger)' ?>;">
                        Rp<?= number_format($card_balances['bri'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="card" style="padding:18px 16px;">
                    <div class="card-label">SeaBank</div>
                    <div class="card-value" style="font-size:20px; color:<?= $card_balances['seabank'] >= 0 ? '#3E3533' : 'var(--color-danger)' ?>;">
                        Rp<?= number_format($card_balances['seabank'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="card" style="padding:18px 16px;">
                    <div class="card-label">DANA</div>
                    <div class="card-value" style="font-size:20px; color:<?= $card_balances['dana'] >= 0 ? '#3E3533' : 'var(--color-danger)' ?>;">
                        Rp<?= number_format($card_balances['dana'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="card" style="padding:18px 16px; border-color:var(--color-accent);">
                    <div class="card-label" style="color:var(--color-accent);">Total Saldo Kas</div>
                    <div class="card-value" style="font-size:20px; color:var(--color-accent);">
                        Rp<?= number_format($card_balances['total'], 0, ',', '.') ?>
                    </div>
                </div>
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
                        <label>Filter Metode Pembayaran</label>
                        <select name="metode">
                            <option value="">-- Semua Akun Kas --</option>
                            <option value="cash" <?= $filter_metode === 'cash' ? 'selected' : '' ?>>Cash (Tunai)</option>
                            <option value="bri" <?= $filter_metode === 'bri' ? 'selected' : '' ?>>Bank BRI</option>
                            <option value="seabank" <?= $filter_metode === 'seabank' ? 'selected' : '' ?>>SeaBank</option>
                            <option value="dana" <?= $filter_metode === 'dana' ? 'selected' : '' ?>>DANA</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height:42px; margin-bottom:0;">Tampilkan Mutasi</button>
                    <a href="cash_overflow.php" class="btn btn-secondary btn-sm" style="height:42px; margin-bottom:0; display:inline-flex; align-items:center;">Reset Filter</a>
                </form>
            </div>

            <!-- ===== TABEL MUTASI KAS UNIFIED ===== -->
            <div class="panel">
                <h3>Riwayat Mutasi Kas (Ledger)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Waktu & Tanggal</th>
                            <th>Jenis</th>
                            <th>Kategori / Sumber</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Saldo Akhir Akun</th>
                            <th>Keterangan / Rincian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mutasi_display)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; color:var(--color-text-muted); padding:24px;">
                                    Belum ada aktivitas mutasi kas pada periode dan filter ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mutasi_display as $row): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700;"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                        <div style="font-size:11px; color:var(--color-text-muted);"><?= date('H:i', strtotime($row['created_at'])) ?> WIB</div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:<?= $row['jenis'] === 'masuk' ? '#DEF7EC' : '#FEE2E2' ?>; color:<?= $row['jenis'] === 'masuk' ? '#03543F' : '#DC2626' ?>; font-weight:700;">
                                            <?= $row['jenis'] === 'masuk' ? 'Kas Masuk (+)' : 'Kas Keluar (-)' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['kategori']) ?></strong></td>
                                    <td><span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700;"><?= htmlspecialchars($row['metode']) ?></span></td>
                                    <td style="font-weight:700; color:<?= $row['jenis'] === 'masuk' ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                                        <?= $row['jenis'] === 'masuk' ? '+' : '-' ?>Rp<?= number_format($row['nominal'], 0, ',', '.') ?>
                                    </td>
                                    <td style="font-weight:700; color:#3E3533;">
                                        Rp<?= number_format($row['saldo_akun'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['keterangan']) ?>
                                        <?php if (!empty($row['nota'])): ?>
                                            <a href="../<?= htmlspecialchars($row['nota']) ?>" target="_blank" style="margin-left:6px; font-size:11px; color:var(--color-accent);">[Nota]</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
