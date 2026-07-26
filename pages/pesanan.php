<?php
// pages/pesanan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Check if 'metode_pengiriman' column exists in 'pesanan' table, if not, add it
try {
    $pdo->query("SELECT metode_pengiriman FROM pesanan LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE pesanan ADD COLUMN metode_pengiriman VARCHAR(50) DEFAULT 'jemput'");
}

$page_title = 'Pesanan';
$status_filter = $_GET['status'] ?? 'menunggu';
$allowed_status = ['menunggu', 'diambil', 'dibatalkan'];
if (!in_array($status_filter, $allowed_status)) $status_filter = 'menunggu';

$sort_filter = $_GET['sort'] ?? ($status_filter === 'diambil' ? 'jemput_desc' : 'jemput_asc');
$search_query = trim($_GET['search'] ?? '');

$order_sql = "tanggal_jemput ASC, jam_jemput ASC";
if ($sort_filter === 'jemput_desc') {
    $order_sql = "tanggal_jemput DESC, jam_jemput DESC";
} elseif ($sort_filter === 'terbaru') {
    $order_sql = "id DESC";
} elseif ($sort_filter === 'terlama') {
    $order_sql = "id ASC";
} elseif ($sort_filter === 'nama_asc') {
    $order_sql = "nama_pemesan ASC";
} elseif ($sort_filter === 'nama_desc') {
    $order_sql = "nama_pemesan DESC";
} elseif ($sort_filter === 'total_desc') {
    $order_sql = "total_harga DESC";
}

$params = [$status_filter];
$where_search = "";
if ($search_query !== '') {
    $where_search = " AND (nama_pemesan LIKE ? OR kontak LIKE ?) ";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
}

$stmt = $pdo->prepare("
    SELECT * FROM pesanan
    WHERE status = ? {$where_search}
    ORDER BY {$order_sql}
");
$stmt->execute($params);
$pesanan_list = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan - Sunshine Florist</title>
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

            <!-- ===== FILTER BAR & TOMBOL AKSI PESANAN ===== -->
            <div class="panel" style="margin-bottom:20px; padding:20px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a href="?status=menunggu&sort=<?= urlencode($sort_filter) ?>&search=<?= urlencode($search_query) ?>" class="btn <?= $status_filter === 'menunggu' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Menunggu</a>
                        <a href="?status=diambil&sort=<?= urlencode($sort_filter) ?>&search=<?= urlencode($search_query) ?>" class="btn <?= $status_filter === 'diambil' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Sudah Diambil</a>
                        <a href="?status=dibatalkan&sort=<?= urlencode($sort_filter) ?>&search=<?= urlencode($search_query) ?>" class="btn <?= $status_filter === 'dibatalkan' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Dibatalkan</a>
                    </div>

                    <a href="pesanan_tambah.php" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px; height:38px;">+ Pesanan Baru</a>
                </div>

                <form method="GET" style="display:flex; gap:12px; align-items:center; margin-top:16px; padding-top:16px; border-top:1px dashed var(--color-border); flex-wrap:wrap;">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                    
                    <div style="flex:1; min-width:200px;">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari nama pemesan atau nomor kontak..." style="height:40px; margin:0;">
                    </div>

                    <div style="width:240px; min-width:180px;">
                        <select name="sort" onchange="this.form.submit()" style="height:40px; margin:0;">
                            <option value="jemput_desc" <?= $sort_filter === 'jemput_desc' ? 'selected' : '' ?>>Tanggal (Terbaru -> Terlama)</option>
                            <option value="jemput_asc" <?= $sort_filter === 'jemput_asc' ? 'selected' : '' ?>>Tanggal (Terlama -> Terbaru)</option>
                            <option value="terbaru" <?= $sort_filter === 'terbaru' ? 'selected' : '' ?>>Baru Di-input</option>
                            <option value="terlama" <?= $sort_filter === 'terlama' ? 'selected' : '' ?>>Awal Di-input</option>
                            <option value="nama_asc" <?= $sort_filter === 'nama_asc' ? 'selected' : '' ?>>Nama Pemesan (A - Z)</option>
                            <option value="nama_desc" <?= $sort_filter === 'nama_desc' ? 'selected' : '' ?>>Nama Pemesan (Z - A)</option>
                            <option value="total_desc" <?= $sort_filter === 'total_desc' ? 'selected' : '' ?>>Total Harga Terbesar</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm" style="height:40px; padding:0 20px;">Cari</button>

                    <?php if ($search_query !== '' || isset($_GET['sort'])): ?>
                        <a href="pesanan.php?status=<?= $status_filter ?>" class="btn btn-secondary btn-sm" style="height:40px; display:inline-flex; align-items:center; padding:0 16px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>Pemesan</th>
                            <th>Kontak</th>
                            <th>Jadwal Jemput</th>
                            <th>Metode</th>
                            <th>Total</th>
                            <th>DP</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan_list as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['nama_pemesan']) ?></strong></td>
                            <td><?= htmlspecialchars($p['kontak'] ?: '-') ?></td>
                            <td>
                                <?= date('d M Y', strtotime($p['tanggal_jemput'])) ?>,
                                <?= date('H:i', strtotime($p['jam_jemput'])) ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size:11px; padding:4px 8px; font-weight:700;"><?= $p['metode_pengiriman'] === 'delivery' ? 'Delivery' : 'Jemput Sendiri' ?></span>
                                <span class="badge badge-secondary" style="font-size:11px; padding:4px 8px; font-weight:700; text-transform:uppercase; margin-top:2px; display:inline-block;"><?= htmlspecialchars(($p['metode_pembayaran'] ?? 'cash') === 'qris' ? 'DANA' : ($p['metode_pembayaran'] ?? 'cash')) ?></span>
                            </td>
                            <td>Rp<?= number_format($p['total_harga'], 0, ',', '.') ?></td>
                            <td><?= $p['dp'] > 0 ? 'Rp' . number_format($p['dp'], 0, ',', '.') : '-' ?></td>
                            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td style="white-space:nowrap; display:inline-flex; gap:4px; align-items:center;">
                                <a href="pesanan_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Detail" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php if ($p['status'] === 'menunggu'): ?>
                                    <a href="pesanan_edit.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" title="Edit" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; background:var(--color-primary); border-color:var(--color-primary);">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    </a>
                                    <a href="javascript:void(0)"
                                       onclick="bukaPelunasan(<?= $p['id'] ?>, <?= (float)$p['total_harga'] ?>, <?= (float)$p['dp'] ?>, '<?= htmlspecialchars($p['ongkir_tipe'] ?? 'kurir', ENT_QUOTES) ?>', <?= (float)($p['ongkir_nominal'] ?? 0) ?>, '<?= htmlspecialchars($p['ongkir_metode_kurir'] ?? 'cash', ENT_QUOTES) ?>')"
                                       class="btn btn-success btn-sm"
                                       title="Konfirmasi Diambil"
                                       style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; background:var(--color-success); border-color:var(--color-success); color:#fff;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    </a>
                                    <a href="../actions/batalkan_pesanan.php?id=<?= $p['id'] ?>"
                                       class="btn btn-danger btn-sm"
                                       title="Batalkan Pesanan"
                                       data-confirm="Batalkan pesanan ini? Stok yang dicadangkan akan dilepas kembali."
                                       style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    </a>
                                <?php elseif ($p['status'] === 'diambil'): ?>
                                    <a href="cetak_struk.php?id=<?= $p['id'] ?>&via=pesanan" target="_blank" class="btn btn-primary btn-sm" title="Cetak Struk" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pesanan_list)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--color-text-muted);">Tidak ada pesanan dengan status ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal Konfirmasi Pelunasan -->
            <div id="modal-pelunasan" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
                <div class="panel" style="background:#fff; width:100%; max-width:400px; padding:24px; border-radius:16px; margin:auto; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                    <h3 style="margin-top:0;">Pelunasan Pesanan</h3>
                    <p style="font-size:13px; color:var(--color-text-muted); margin-bottom:16px; line-height:1.4;">Silakan pilih metode pembayaran untuk sisa pelunasan pesanan ini.</p>
                    
                    <form action="../actions/konfirmasi_jemput.php" method="POST">
                        <input type="hidden" name="id" id="pelunasan-pesanan-id">
                        
                        <div class="form-group">
                            <label>Sisa Pembayaran Produk</label>
                            <div style="font-size:22px; font-weight:800; color:var(--color-accent);" id="pelunasan-sisa-text">Rp0</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Metode Pembayaran Pelunasan</label>
                            <select name="metode_pembayaran_pelunasan" required style="width:100%;">
                                <option value="cash">Cash (Tunai)</option>
                                <option value="bri">Bank BRI</option>
                                <option value="seabank">SeaBank</option>
                                <option value="dana">DANA</option>
                            </select>
                        </div>

                        <div id="modal-ongkir-fields" style="display:none; border-top: 1px dashed #ccc; margin-top:12px; padding-top:12px;">
                            <div class="form-group">
                                <label>Nominal Ongkir Delivery (Bisa Disesuaikan)</label>
                                <input type="number" name="ongkir_nominal" id="pelunasan-ongkir-nominal" value="0" min="0" oninput="hitungTotalPelunasan()" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Metode Bayar Ongkir ke Kurir</label>
                                <select name="ongkir_metode_kurir" id="pelunasan-ongkir-metode" style="width:100%;">
                                    <option value="cash">Cash (Tunai)</option>
                                    <option value="bri">Bank BRI</option>
                                    <option value="seabank">SeaBank</option>
                                    <option value="dana">DANA</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-top:10px;">
                                <label>Total Uang Diterima dari Customer</label>
                                <div style="font-size:22px; font-weight:800; color:var(--color-primary);" id="pelunasan-total-diterima">Rp0</div>
                            </div>
                        </div>
                        
                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="closePelunasanModal()">Batal</button>
                            <button type="submit" class="btn btn-primary btn-sm">Konfirmasi Diambil</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            let currentSisa = 0;
            let currentTipe = 'kurir';

            function bukaPelunasan(id, total, dp, ongkir_tipe, ongkir_nominal, ongkir_metode_kurir) {
                const sisa = total - dp;
                currentSisa = sisa;
                currentTipe = ongkir_tipe;

                if (sisa <= 0 && ongkir_tipe !== 'toko') {
                    if (confirm("Konfirmasi pesanan ini sudah diambil? Stok akan terpotong permanen.")) {
                        window.location.href = "../actions/konfirmasi_jemput.php?id=" + id;
                    }
                    return;
                }

                document.getElementById('pelunasan-pesanan-id').value = id;
                document.getElementById('pelunasan-sisa-text').textContent = "Rp" + sisa.toLocaleString('id-ID');
                
                const ongkirFields = document.getElementById('modal-ongkir-fields');
                if (ongkir_tipe === 'toko') {
                    ongkirFields.style.display = 'block';
                    document.getElementById('pelunasan-ongkir-nominal').value = Math.round(parseFloat(ongkir_nominal || 0));
                    document.getElementById('pelunasan-ongkir-metode').value = ongkir_metode_kurir || 'cash';
                    hitungTotalPelunasan();
                } else {
                    ongkirFields.style.display = 'none';
                }

                document.getElementById('modal-pelunasan').style.display = 'flex';
            }

            function hitungTotalPelunasan() {
                const ongkir = parseFloat(document.getElementById('pelunasan-ongkir-nominal').value) || 0;
                const total = currentSisa + ongkir;
                document.getElementById('pelunasan-total-diterima').textContent = "Rp" + total.toLocaleString('id-ID');
            }

            function closePelunasanModal() {
                document.getElementById('modal-pelunasan').style.display = 'none';
            }
            </script>

            <?php include __DIR__ . '/../includes/footer.php'; ?>