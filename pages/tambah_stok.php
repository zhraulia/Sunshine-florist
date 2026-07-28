<?php
// pages/tambah_stok.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Tambah Stok';
$bahan_id = $_GET['bahan_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM bahan_baku WHERE id = ?");
$stmt->execute([$bahan_id]);
$bahan = $stmt->fetch();

if (!$bahan) {
    die('Bahan tidak ditemukan.');
}

// Tambah Master Konversi dari Halaman Restock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_master_konversi'])) {
    $master_nama = trim($_POST['master_nama'] ?? '');
    $master_dasar = trim($_POST['master_dasar'] ?? '');
    $master_isi = (float)($_POST['master_isi'] ?? 0);

    if ($master_nama !== '' && $master_dasar !== '' && $master_isi > 0) {
        $stmt = $pdo->prepare("INSERT INTO konversi_master (nama_satuan_beli, satuan_dasar, isi_per_satuan) VALUES (?, ?, ?)");
        $stmt->execute([$master_nama, $master_dasar, $master_isi]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Satuan konversi master berhasil ditambahkan.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Lengkapi data satuan konversi.'];
    }
    header("Location: tambah_stok.php?bahan_id={$bahan_id}");
    exit;
}

// Hapus Master Konversi dari Halaman Restock
if (isset($_GET['hapus_master'])) {
    $id_master = (int)$_GET['hapus_master'];
    $stmt = $pdo->prepare("DELETE FROM konversi_master WHERE id = ?");
    $stmt->execute([$id_master]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Satuan konversi master berhasil dihapus.'];
    header("Location: tambah_stok.php?bahan_id={$bahan_id}");
    exit;
}

// Ambil daftar konversi master global
$stmt_master = $pdo->query("SELECT * FROM konversi_master ORDER BY nama_satuan_beli ASC");
$master_konversi_list = $stmt_master ? $stmt_master->fetchAll() : [];

// Ambil daftar konversi khusus bahan ini jika ada (legacy support)
$stmt = $pdo->prepare("SELECT * FROM konversi_satuan WHERE bahan_baku_id = ?");
$stmt->execute([$bahan_id]);
$konversi_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Stok - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="panel" style="max-width:480px;">
                <h3>Tambah Stok: <?= htmlspecialchars($bahan['nama']) ?></h3>
                <p style="color:var(--color-text-muted); font-size:13px;">
                    Stok fisik saat ini: <strong><?= (float)$bahan['stok_fisik'] ?> <?= htmlspecialchars($bahan['satuan_dasar']) ?></strong>
                </p>

                <form action="../actions/tambah_stok.php" method="POST">
                    <input type="hidden" name="bahan_baku_id" value="<?= $bahan['id'] ?>">

                    <div class="form-group">
                        <label id="label_jumlah_beli">Jumlah Beli</label>
                        <input type="number" step="0.01" name="jumlah" id="jumlah_input" required style="max-width:100%;">
                    </div>

                    <div class="form-group">
                        <label>Satuan Pembelian</label>
                        <select name="satuan_pilihan" id="satuan_pilihan" onchange="updateRestockLabels()" required>
                            <option value="dasar" data-nama="<?= htmlspecialchars($bahan['satuan_dasar']) ?>" data-isi="1">
                                <?= htmlspecialchars($bahan['satuan_dasar']) ?> (Satuan Dasar)
                            </option>
                            <?php foreach ($master_konversi_list as $mk): ?>
                                <option value="master:<?= $mk['id'] ?>"
                                        data-nama="<?= htmlspecialchars($mk['nama_satuan_beli']) ?>"
                                        data-isi="<?= $mk['isi_per_satuan'] ?>">
                                    <?= htmlspecialchars($mk['nama_satuan_beli']) ?> 
                                    (1 <?= htmlspecialchars($mk['nama_satuan_beli']) ?> = <?= (float)$mk['isi_per_satuan'] ?> <?= htmlspecialchars($mk['satuan_dasar']) ?>)
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

                    <div class="form-group">
                        <label id="label_harga_total">Harga Total Bahan (Rp)</label>
                        <input type="number" name="harga_total" placeholder="0" required style="max-width:100%;">
                    </div>

                    <div class="form-group">
                        <label>Biaya Ongkir (Rp)</label>
                        <input type="number" name="biaya_ongkir" placeholder="0" value="0" style="max-width:100%;">
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
                        <label>Status Pembayaran & Barang</label>
                        <div style="display:flex; flex-direction:column; gap:8px; margin-top:6px;">
                            <label style="font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:8px;">
                                <input type="radio" name="status_po" value="lunas" checked>
                                <span><strong>Lunas</strong> (Uang keluar & barang langsung masuk stok)</span>
                            </label>
                            <label style="font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:8px;">
                                <input type="radio" name="status_po" value="pending_po">
                                <span><strong>Belanja PO</strong> (Uang keluar duluan, barang belum sampai)</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Keterangan (opsional)</label>
                        <input type="text" name="keterangan" placeholder="misal: Supplier A / No. Resi">
                    </div>

                    <button type="submit" class="btn btn-primary">+ Simpan Stok Masuk</button>
                    <a href="stok_bahan.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>

            <!-- ===== KELOLA SATUAN KONVERSI MASTER ===== -->
            <div class="panel" style="max-width:480px; margin-top:20px;">
                <h3>Satuan Konversi Master</h3>
                <p style="color:var(--color-text-muted); font-size:13px;">
                    Daftar satuan konversi master global. Tambahkan di sini agar bisa dipilih langsung saat restock bahan.
                </p>

                <table style="margin-bottom:16px;">
                    <thead><tr><th>Satuan</th><th>Isi</th><th style="width:60px; text-align:center;">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($master_konversi_list as $mk): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($mk['nama_satuan_beli']) ?></strong></td>
                            <td>= <?= (float)$mk['isi_per_satuan'] ?> <?= htmlspecialchars($mk['satuan_dasar']) ?></td>
                            <td style="text-align:center;">
                                <a href="tambah_stok.php?bahan_id=<?= $bahan['id'] ?>&hapus_master=<?= $mk['id'] ?>"
                                   class="btn btn-danger btn-sm" onclick="return confirm('Hapus satuan konversi master ini?')">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($master_konversi_list)): ?>
                        <tr><td colspan="3" style="color:var(--color-text-muted);">Belum ada satuan konversi master.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <form action="tambah_stok.php?bahan_id=<?= $bahan['id'] ?>" method="POST" style="display:flex; gap:10px; align-items:end;">
                    <input type="hidden" name="tambah_master_konversi" value="1">
                    <div class="form-group" style="flex:1; margin-bottom:0;">
                        <label>Nama Satuan</label>
                        <input type="text" name="master_nama" placeholder="ikat / pack" required>
                    </div>
                    <div class="form-group" style="flex:1; margin-bottom:0;">
                        <label>Satuan Dasar</label>
                        <input type="text" name="master_dasar" value="<?= htmlspecialchars($bahan['satuan_dasar']) ?>" readonly style="background:#f3f4f6;" required>
                    </div>
                    <div class="form-group" style="flex:1; margin-bottom:0;">
                        <label>Isi</label>
                        <input type="number" step="0.01" name="master_isi" placeholder="6" required>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm" style="height:42px;">Tambah</button>
                </form>
            </div>

            <script>
            function updateRestockLabels() {
                const select = document.getElementById('satuan_pilihan');
                const selectedOpt = select.options[select.selectedIndex];
                const unitName = selectedOpt.getAttribute('data-nama');
                
                const labelJumlah = document.getElementById('label_jumlah_beli');
                const labelHarga = document.getElementById('label_harga_total');
                
                if (labelJumlah) {
                    labelJumlah.innerHTML = `Jumlah Beli (dalam <strong>${unitName}</strong>)`;
                }
                if (labelHarga) {
                    labelHarga.innerHTML = `Harga Total Bahan (per <strong>${unitName}</strong> / Total)`;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize label on load
                updateRestockLabels();
            });
            </script>

            <?php include __DIR__ . '/../includes/footer.php'; ?>