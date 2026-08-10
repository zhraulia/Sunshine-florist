<?php
// pages/stok_bahan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Check and create 'konversi_master' table if not exists, and seed it
try {
    $pdo->query("SELECT 1 FROM konversi_master LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE konversi_master (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_satuan_beli VARCHAR(50) NOT NULL,
            satuan_dasar VARCHAR(50) NOT NULL,
            isi_per_satuan INT NOT NULL
        )
    ");
    // Seed default values
    $stmt_seed = $pdo->prepare("INSERT INTO konversi_master (nama_satuan_beli, satuan_dasar, isi_per_satuan) VALUES (?, ?, ?)");
    $stmt_seed->execute(['lusin', 'tangkai', 12]);
    $stmt_seed->execute(['ikat', 'tangkai', 6]);
    $stmt_seed->execute(['pack', 'lembar', 50]);
}

// Tambah Master Konversi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_master_konversi'])) {
    $master_nama = trim($_POST['master_nama'] ?? '');
    $master_dasar = trim($_POST['master_dasar'] ?? '');
    $master_isi = (int)($_POST['master_isi'] ?? 0);

    if ($master_nama !== '' && $master_dasar !== '' && $master_isi > 0) {
        $stmt = $pdo->prepare("INSERT INTO konversi_master (nama_satuan_beli, satuan_dasar, isi_per_satuan) VALUES (?, ?, ?)");
        $stmt->execute([$master_nama, $master_dasar, $master_isi]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Satuan konversi master berhasil ditambahkan.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Lengkapi data satuan konversi.'];
    }
    header('Location: stok_bahan.php');
    exit;
}

// Hapus Master Konversi
if (isset($_GET['hapus_master'])) {
    $id_master = (int)$_GET['hapus_master'];
    $stmt = $pdo->prepare("DELETE FROM konversi_master WHERE id = ?");
    $stmt->execute([$id_master]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Satuan konversi master berhasil dihapus.'];
    header('Location: stok_bahan.php');
    exit;
}

$master_konversi_list = $pdo->query("SELECT * FROM konversi_master ORDER BY nama_satuan_beli ASC")->fetchAll();

$page_title = 'Stok Bahan';

// Mode edit: kalau ada ?edit=ID, ambil datanya buat isi form
$edit_data = null;
$edit_konversi = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM bahan_baku WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
    
    if ($edit_data) {
        $stmt_c = $pdo->prepare("SELECT * FROM konversi_satuan WHERE bahan_baku_id = ? LIMIT 1");
        $stmt_c->execute([$edit_data['id']]);
        $edit_konversi = $stmt_c->fetch();
    }
}

$display_harga_beli = '0';
if ($edit_data && isset($edit_data['harga_beli'])) {
    $display_harga_beli = $edit_konversi ? ($edit_data['harga_beli'] * $edit_konversi['isi_per_satuan']) : $edit_data['harga_beli'];
}

$search_query = trim($_GET['search'] ?? '');

$params_b = [];
$where_b = "";
if ($search_query !== '') {
    $where_b = " WHERE (nama LIKE ? OR satuan_dasar LIKE ?) ";
    $params_b[] = "%{$search_query}%";
    $params_b[] = "%{$search_query}%";
}

// Ambil semua bahan + hitung stok tersedia
$stmt_b = $pdo->prepare("
    SELECT *, (stok_fisik - stok_dicadangkan) AS stok_tersedia
    FROM bahan_baku
    {$where_b}
    ORDER BY nama ASC
");
$stmt_b->execute($params_b);
$bahan_list = $stmt_b->fetchAll();

// Ambil daftar Belanja PO yang masih pending (dalam pengiriman)
$pending_po_list = [];
try {
    $pending_po_list = $pdo->query("
        SELECT r.*, b.nama AS nama_bahan, b.satuan_dasar
        FROM riwayat_stok r
        JOIN bahan_baku b ON b.id = r.bahan_baku_id
        WHERE r.status_po = 'pending_po'
        ORDER BY r.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $pending_po_list = [];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Bahan - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css?v=1.4">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- ===== DAFTAR BELANJA PO (DALAM PENGIRIMAN) ===== -->
            <?php if (!empty($pending_po_list)): ?>
                <div class="panel" style="margin-bottom:24px; border:2px solid var(--color-warning);">
                    <h3 style="color:#D97706; display:flex; align-items:center; gap:8px;">
                        <span>🚚</span> Daftar Belanja PO (Uang Keluar, Barang Dalam Pengiriman)
                    </h3>
                    <p style="color:var(--color-text-muted); font-size:13px; margin-top:-10px;">
                        Pembelian bahan di bawah ini sudah memotong kas/rekening toko. Klik <strong>"Konfirmasi Barang Diterima"</strong> saat bahan fisik sudah sampai untuk menambahkannya ke stok fisik.
                    </p>
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal PO</th>
                                <th>Nama Bahan</th>
                                <th>Jumlah Beli</th>
                                <th>Metode Bayar</th>
                                <th>Total Biaya (Inc. Ongkir)</th>
                                <th>Keterangan</th>
                                <th style="text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_po_list as $po): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($po['created_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($po['nama_bahan']) ?></strong></td>
                                    <td><?= (float)$po['jumlah'] ?> <?= htmlspecialchars($po['satuan_dasar']) ?></td>
                                    <td><span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700;"><?= htmlspecialchars($po['metode_pembayaran'] === 'qris' ? 'DANA' : $po['metode_pembayaran']) ?></span></td>
                                    <td style="font-weight:700; color:var(--color-danger);">Rp<?= number_format($po['total_biaya'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($po['keterangan'] ?: '-') ?></td>
                                    <td style="text-align:center;">
                                        <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                            <form action="../actions/konfirmasi_po.php" method="POST" onsubmit="return confirm('Konfirmasi bahwa fisik barang ini sudah sampai di toko?')">
                                                <input type="hidden" name="riwayat_id" value="<?= $po['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" style="padding:6px 12px; font-size:12px; font-weight:700; background:#059669; border-color:#059669; white-space:nowrap;">
                                                    ✓ Diterima
                                                </button>
                                            </form>
                                            <form action="../actions/batal_po.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan PO belanja bahan ini? Saldo kas yang sempat terpotong akan otomatis dikembalikan.')">
                                                <input type="hidden" name="riwayat_id" value="<?= $po['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding:6px 12px; font-size:12px; font-weight:700; background:#A43B22; border-color:#A43B22; white-space:nowrap;">
                                                    ✖ Batal
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- ===== FORM TAMBAH / EDIT BAHAN ===== -->
            <div class="panel" style="margin-bottom:24px;">
                <h3><?= $edit_data ? 'Edit Bahan' : '+ Tambah Bahan Baru' ?></h3>
                <form action="../actions/simpan_bahan.php" method="POST">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
                        <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 6px;">
                            <label style="font-weight: 700;">Tipe Satuan</label>
                            <div style="display: flex; gap: 20px; align-items: center; margin-top: 6px;">
                                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                    <input type="radio" name="tipe_satuan" value="dasar" <?= !$edit_konversi ? 'checked' : '' ?> <?= $edit_data ? 'disabled' : '' ?> onclick="toggleTipeSatuan('dasar')">
                                    Satuan Dasar (Isi Manual)
                                </label>
                                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                    <input type="radio" name="tipe_satuan" value="konversi" <?= $edit_konversi ? 'checked' : '' ?> <?= $edit_data ? 'disabled' : '' ?> onclick="toggleTipeSatuan('konversi')">
                                    Gunakan Satuan Konversi Master
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="group-select-master" style="display: none; grid-column: 1 / -1;">
                            <label>Pilih Satuan Konversi Master</label>
                            <select id="select_master_konversi" onchange="applyMasterKonversi()" <?= $edit_data ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Satuan Konversi --</option>
                                <?php foreach ($master_konversi_list as $mk): ?>
                                    <?php 
                                    $is_selected = false;
                                    if ($edit_konversi && 
                                        strtolower($edit_konversi['nama_satuan_beli']) === strtolower($mk['nama_satuan_beli']) && 
                                        (float)$edit_konversi['isi_per_satuan'] === (float)$mk['isi_per_satuan']) {
                                        $is_selected = true;
                                    }
                                    ?>
                                    <option value="<?= $mk['id'] ?>" 
                                            <?= $is_selected ? 'selected' : '' ?>
                                            data-beli="<?= htmlspecialchars($mk['nama_satuan_beli']) ?>"
                                            data-dasar="<?= htmlspecialchars($mk['satuan_dasar']) ?>"
                                            data-isi="<?= $mk['isi_per_satuan'] ?>">
                                        <?= htmlspecialchars($mk['nama_satuan_beli']) ?> (1 <?= htmlspecialchars($mk['nama_satuan_beli']) ?> = <?= $mk['isi_per_satuan'] ?> <?= htmlspecialchars($mk['satuan_dasar']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nama Bahan</label>
                            <input type="text" name="nama" required
                                   value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Satuan Dasar</label>
                            <input type="text" name="satuan_dasar" id="satuan_dasar" placeholder="tangkai / lembar / rol" required
                                   value="<?= htmlspecialchars($edit_data['satuan_dasar'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label id="label_stok_fisik">Stok Fisik Awal</label>
                            <input type="number" name="stok_fisik" id="stok_fisik" required
                                   value="<?= htmlspecialchars(isset($edit_data['stok_fisik']) ? (float)$edit_data['stok_fisik'] : '0') ?>"
                                   <?= $edit_data ? 'readonly title="Gunakan tombol Tambah Stok untuk restock"' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label>Ambang Minimum</label>
                            <input type="number" name="ambang_minimum" required
                                   value="<?= htmlspecialchars(isset($edit_data['ambang_minimum']) ? (float)$edit_data['ambang_minimum'] : '0') ?>">
                        </div>

                        <div class="form-group">
                            <label id="label_harga_beli">Harga Beli (Rp)</label>
                            <input type="number" name="harga_beli" id="harga_beli" required
                                   value="<?= htmlspecialchars(isset($display_harga_beli) ? (float)$display_harga_beli : '0') ?>">
                        </div>

                        <?php if (!$edit_data): ?>
                            <div class="form-group" style="grid-column: 1 / -1; margin-top: 10px; margin-bottom: 5px;">
                                <label style="font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; color:var(--color-primary);">
                                    <input type="checkbox" name="is_saldo_awal" id="is_saldo_awal" value="1" onchange="toggleBahanPaymentFields()">
                                    <span>Daftarkan sebagai Saldo Awal (Stok bawaan toko, tidak memotong kas)</span>
                                </label>
                            </div>

                            <!-- Container untuk field pembayaran yang bisa disembunyikan -->
                            <div id="bahan_payment_fields" style="display:contents;">
                                <div class="form-group">
                                    <label>Metode Pembayaran</label>
                                    <select name="metode_pembayaran" id="metode_pembayaran">
                                        <option value="cash">Cash (Tunai)</option>
                                        <option value="bri">Bank BRI</option>
                                        <option value="seabank">SeaBank</option>
                                        <option value="dana">DANA</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Biaya Ongkir (Rp)</label>
                                    <input type="number" name="biaya_ongkir" id="biaya_ongkir" placeholder="0" value="0">
                                </div>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Status Pembayaran & Barang</label>
                                    <div style="display:flex; gap:20px; align-items:center; height:42px; flex-wrap:wrap;">
                                        <label style="font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:8px;">
                                            <input type="radio" name="status_po" value="lunas" checked>
                                            Lunas (Uang keluar & barang langsung masuk stok)
                                        </label>
                                        <label style="font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:8px;">
                                            <input type="radio" name="status_po" value="pending_po">
                                            Belanja PO (Uang keluar duluan, barang belum sampai)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <input type="hidden" name="konversi_nama" id="konversi_nama" value="<?= htmlspecialchars($edit_konversi['nama_satuan_beli'] ?? '') ?>">
                        <input type="hidden" name="konversi_isi" id="konversi_isi" value="<?= htmlspecialchars(isset($edit_konversi['isi_per_satuan']) ? (float)$edit_konversi['isi_per_satuan'] : '0') ?>">
                    </div>
                    <div id="calc-preview" style="font-size: 13px; color: var(--color-text-muted); margin-top: 12px; margin-bottom: 12px; font-weight: 600;"></div>

                    <button type="submit" class="btn btn-primary">
                        <?= $edit_data ? 'Simpan Perubahan' : '+ Tambah Bahan' ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="stok_bahan.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ===== DAFTAR BAHAN ===== -->
            <div class="panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <h3 style="margin:0;">Daftar Bahan Baku</h3>
                        <a href="riwayat_stok_tampil.php" class="btn btn-secondary btn-sm" style="font-weight:700; height:38px; display:inline-flex; align-items:center; padding:0 14px;">Riwayat Mutasi Stok</a>
                    </div>
                    <form method="GET" style="display:flex; gap:8px; align-items:center; margin:0;">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari nama bahan / satuan..." style="height:38px; padding:0 12px; font-size:13px; width:220px; margin:0;">
                        <button type="submit" class="btn btn-primary btn-sm" style="height:38px; padding:0 16px;">Cari</button>
                        <?php if ($search_query !== ''): ?>
                            <a href="stok_bahan.php" class="btn btn-secondary btn-sm" style="height:38px; display:inline-flex; align-items:center; padding:0 14px;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Stok Fisik</th>
                            <th>Dicadangkan</th>
                            <th>Stok Tersedia</th>
                            <th>Ambang Min.</th>
                            <th>Harga Beli</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bahan_list as $b): ?>
                        <?php $menipis = $b['stok_tersedia'] <= $b['ambang_minimum']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['nama']) ?></strong></td>
                            <td><?= (float)$b['stok_fisik'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?></td>
                            <td><?= (float)$b['stok_dicadangkan'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?></td>
                            <td>
                                <span class="badge <?= $menipis ? 'badge-dibatalkan' : 'badge-diambil' ?>">
                                    <?= (float)$b['stok_tersedia'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?>
                                </span>
                            </td>
                            <td><?= (float)$b['ambang_minimum'] ?></td>
                            <td>Rp<?= number_format($b['harga_beli'], 0, ',', '.') ?></td>
                            <td style="white-space:nowrap;">
                                <a href="tambah_stok.php?bahan_id=<?= $b['id'] ?>" 
                                   class="btn btn-primary btn-sm" 
                                   title="Tambah Stok" 
                                   style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; margin-right:4px; vertical-align:middle;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                </a>
                                <a href="kurangi_stok.php?bahan_id=<?= $b['id'] ?>" 
                                   class="btn btn-danger btn-sm" 
                                   title="Kurangi Stok" 
                                   style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; margin-right:4px; vertical-align:middle; background:#A43B22; border-color:#A43B22;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                </a>
                                <a href="stok_bahan.php?edit=<?= $b['id'] ?>" 
                                   class="btn btn-secondary btn-sm" 
                                   title="Edit Bahan" 
                                   style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; margin-right:4px; vertical-align:middle;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>
                                <a href="../actions/hapus_bahan.php?id=<?= $b['id'] ?>"
                                   class="btn btn-danger btn-sm"
                                   title="Hapus Bahan"
                                   data-confirm="Hapus bahan '<?= htmlspecialchars($b['nama']) ?>'? Data resep yang memakai bahan ini juga akan terhapus."
                                   style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; vertical-align:middle;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($bahan_list)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--color-text-muted);">Belum ada data bahan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ===== KELOLA SATUAN KONVERSI MASTER ===== -->
            <div class="panel" style="max-width:540px; margin-top:24px;">
                <h3>Satuan Konversi</h3>
                <p style="color:var(--color-text-muted); font-size:13px; margin-bottom: 16px;">
                    Kalau kamu beli bahan ini per ikat/pack, tambahkan konversinya di sini biar bisa dipilih saat restock.
                </p>

                <table style="margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th>Satuan</th>
                            <th>Isi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($master_konversi_list as $mk): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($mk['nama_satuan_beli']) ?></strong></td>
                            <td>= <?= (int)$mk['isi_per_satuan'] ?> <?= htmlspecialchars($mk['satuan_dasar']) ?></td>
                            <td>
                                <a href="?hapus_master=<?= $mk['id'] ?>"
                                   class="btn btn-danger btn-sm"
                                   data-confirm="Hapus satuan konversi master ini?"
                                   style="padding: 4px 10px; font-size: 12px; border-radius: 8px;">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($master_konversi_list)): ?>
                        <tr><td colspan="3" style="text-align:center; color:var(--color-text-muted);">Belum ada satuan konversi master.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <form action="" method="POST" style="border-top:1px solid #EADCD9; padding-top:16px;">
                    <input type="hidden" name="tambah_master_konversi" value="1">
                    <div style="display:grid; grid-template-columns: 1fr 1fr 100px; gap:12px; align-items:end; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Nama Satuan</label>
                            <input type="text" name="master_nama" placeholder="misal: ikat" required style="height:36px; border-radius:8px;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Satuan Dasar</label>
                            <input type="text" name="master_dasar" placeholder="misal: tangkai" required style="height:36px; border-radius:8px;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Isi</label>
                            <input type="number" name="master_isi" placeholder="12" required style="height:36px; border-radius:8px;">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 14px; font-size: 13px; border-radius: 8px; font-weight:700;">Tambah</button>
                </form>
            </div>

<script>
function toggleTipeSatuan(tipe) {
    const groupSelectMaster = document.getElementById('group-select-master');
    const satuanDasarInput = document.getElementById('satuan_dasar');
    const konversiNamaInput = document.getElementById('konversi_nama');
    const konversiIsiInput = document.getElementById('konversi_isi');

    if (tipe === 'konversi') {
        groupSelectMaster.style.display = 'block';
        satuanDasarInput.readOnly = true;
        satuanDasarInput.style.backgroundColor = '#F3F4F6';
        applyMasterKonversi();
    } else {
        groupSelectMaster.style.display = 'none';
        satuanDasarInput.readOnly = false;
        satuanDasarInput.style.backgroundColor = '#ffffff';
        
        // Reset values
        satuanDasarInput.value = '';
        konversiNamaInput.value = '';
        konversiIsiInput.value = '0';
        document.getElementById('select_master_konversi').value = '';
        updateCalcPreview();
    }
}

function applyMasterKonversi() {
    const select = document.getElementById('select_master_konversi');
    const selectedOption = select.options[select.selectedIndex];
    
    const satuanDasarInput = document.getElementById('satuan_dasar');
    const konversiNamaInput = document.getElementById('konversi_nama');
    const konversiIsiInput = document.getElementById('konversi_isi');

    if (selectedOption && selectedOption.value !== '') {
        satuanDasarInput.value = selectedOption.getAttribute('data-dasar');
        konversiNamaInput.value = selectedOption.getAttribute('data-beli');
        konversiIsiInput.value = selectedOption.getAttribute('data-isi');
        satuanDasarInput.readOnly = true;
        satuanDasarInput.style.backgroundColor = '#F3F4F6';
    } else {
        const isEditMode = document.getElementById('stok_fisik').hasAttribute('readonly');
        if (!isEditMode) {
            satuanDasarInput.value = '';
            konversiNamaInput.value = '';
            konversiIsiInput.value = '0';
        }
    }
    updateCalcPreview();
}

function updateCalcPreview() {
    const stokFisik = parseFloat(document.getElementById('stok_fisik').value) || 0;
    const hargaBeli = parseFloat(document.getElementById('harga_beli').value) || 0;
    const konversiIsi = parseFloat(document.getElementById('konversi_isi').value) || 0;
    const satuanDasar = document.getElementById('satuan_dasar').value || 'satuan';
    const konversiNama = document.getElementById('konversi_nama').value || '';
    const previewDiv = document.getElementById('calc-preview');
    const isEditMode = document.getElementById('stok_fisik').hasAttribute('readonly');

    // Update dynamic labels
    const labelStok = document.getElementById('label_stok_fisik');
    const labelHarga = document.getElementById('label_harga_beli');

    if (konversiNama && konversiNama.trim() !== '') {
        if (labelStok) labelStok.innerHTML = `Stok Fisik Awal (dalam <strong>${konversiNama}</strong>)`;
        if (labelHarga) labelHarga.innerHTML = `Harga Beli Satuan (per <strong>${konversiNama}</strong>)`;
    } else {
        if (labelStok) labelStok.innerHTML = satuanDasar !== 'satuan' ? `Stok Fisik Awal (dalam <strong>${satuanDasar}</strong>)` : 'Stok Fisik Awal';
        if (labelHarga) labelHarga.innerHTML = 'Harga Beli Satuan (Rp)';
    }

    let message = '';

    if (konversiIsi > 0) {
        if (!isEditMode && stokFisik > 0) {
            const stokDasar = Math.round(stokFisik * konversiIsi);
            message += `✓ Stok awal dikonversi: <strong>${stokDasar} ${satuanDasar}</strong> (dari ${stokFisik} ${konversiNama}).<br>`;
        }
        if (hargaBeli > 0) {
            const hargaDasar = Math.round(hargaBeli / konversiIsi);
            message += `✓ Harga per <strong>${satuanDasar}</strong> dikonversi: <strong>Rp${hargaDasar.toLocaleString('id-ID')}</strong> (dari Rp${hargaBeli.toLocaleString('id-ID')} per ${konversiNama} isi ${konversiIsi}).`;
        }
    }

    previewDiv.innerHTML = message;
}

document.getElementById('stok_fisik').addEventListener('input', updateCalcPreview);
document.getElementById('harga_beli').addEventListener('input', updateCalcPreview);
document.getElementById('satuan_dasar').addEventListener('input', updateCalcPreview);

// Run initial configurations and toggles on load
document.addEventListener('DOMContentLoaded', () => {
    const checkedRadio = document.querySelector('input[name="tipe_satuan"]:checked');
    if (checkedRadio) {
        if (checkedRadio.value === 'konversi') {
            document.getElementById('group-select-master').style.display = 'block';
            document.getElementById('satuan_dasar').readOnly = true;
            document.getElementById('satuan_dasar').style.backgroundColor = '#F3F4F6';
        } else {
            document.getElementById('group-select-master').style.display = 'none';
            document.getElementById('satuan_dasar').readOnly = false;
            document.getElementById('satuan_dasar').style.backgroundColor = '#ffffff';
        }
    }
    updateCalcPreview();
    if (document.getElementById('is_saldo_awal')) {
        toggleBahanPaymentFields();
    }
});

function toggleBahanPaymentFields() {
    const isSaldoAwal = document.getElementById('is_saldo_awal') && document.getElementById('is_saldo_awal').checked;
    const paymentFields = document.getElementById('bahan_payment_fields');
    if (paymentFields) {
        if (isSaldoAwal) {
            paymentFields.style.display = 'none';
        } else {
            paymentFields.style.display = 'contents';
        }
    }
}
</script>

            <?php include __DIR__ . '/../includes/footer.php'; ?>