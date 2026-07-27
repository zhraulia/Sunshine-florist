<?php
// pages/produk_resep.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Produk & Resep';

$edit_data = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
}

$search_query = $_GET['search'] ?? '';

if ($search_query !== '') {
    $stmt = $pdo->prepare("
        SELECT p.*, k.nama AS nama_kategori,
               COALESCE(SUM(rp.jumlah_dibutuhkan * b.harga_beli), 0) AS total_modal_bahan
        FROM produk p
        LEFT JOIN kategori_produk k ON k.id = p.kategori_id
        LEFT JOIN resep_produk rp ON rp.produk_id = p.id
        LEFT JOIN bahan_baku b ON b.id = rp.bahan_baku_id
        WHERE p.nama LIKE ? OR k.nama LIKE ?
        GROUP BY p.id
        ORDER BY p.nama ASC
    ");
    $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
    $produk_list = $stmt->fetchAll();
} else {
    $produk_list = $pdo->query("
        SELECT p.*, k.nama AS nama_kategori,
               COALESCE(SUM(rp.jumlah_dibutuhkan * b.harga_beli), 0) AS total_modal_bahan
        FROM produk p
        LEFT JOIN kategori_produk k ON k.id = p.kategori_id
        LEFT JOIN resep_produk rp ON rp.produk_id = p.id
        LEFT JOIN bahan_baku b ON b.id = rp.bahan_baku_id
        GROUP BY p.id
        ORDER BY p.nama ASC
    ")->fetchAll();
}
$kategori_list = $pdo->query("SELECT * FROM kategori_produk ORDER BY nama ASC")->fetchAll();
$bahan_semua = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Kalau lagi lihat resep produk tertentu
$lihat_resep_id = $_GET['resep'] ?? null;
$resep_items = [];
$produk_resep = null;
if ($lihat_resep_id) {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
    $stmt->execute([$lihat_resep_id]);
    $produk_resep = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT rp.*, b.nama AS nama_bahan, b.satuan_dasar, b.harga_beli AS harga_satuan
        FROM resep_produk rp
        JOIN bahan_baku b ON b.id = rp.bahan_baku_id
        WHERE rp.produk_id = ?
    ");
    $stmt->execute([$lihat_resep_id]);
    $resep_items = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk & Resep - Sunshine Florist</title>
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

            <?php if ($produk_resep): ?>
                <!-- ================= HALAMAN KELOLA RESEP ================= -->
                <div class="panel" style="margin-bottom:24px;">
                    <h3>Resep: <?= htmlspecialchars($produk_resep['nama']) ?></h3>

                    <?php $total_modal_bahan = 0; ?>
                    <table style="margin-bottom:16px;">
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th style="text-align:right;">Harga Satuan</th>
                                <th style="text-align:center;">Jumlah Dibutuhkan</th>
                                <th style="text-align:right;">Sub-Total Modal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resep_items as $r): 
                                $cost_per_unit = (float)$r['harga_satuan'];
                                $subtotal_cost = (float)$r['jumlah_dibutuhkan'] * $cost_per_unit;
                                $total_modal_bahan += $subtotal_cost;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['nama_bahan']) ?></strong></td>
                                <td style="text-align:right; color:var(--color-text-muted);">Rp<?= number_format($cost_per_unit, 0, ',', '.') ?> / <?= htmlspecialchars($r['satuan_dasar']) ?></td>
                                <td style="text-align:center;"><?= (float)$r['jumlah_dibutuhkan'] ?> <?= htmlspecialchars($r['satuan_dasar']) ?></td>
                                <td style="text-align:right; font-weight:600; color:var(--color-text-dark);">Rp<?= number_format($subtotal_cost, 0, ',', '.') ?></td>
                                <td>
                                    <a href="../actions/hapus_resep.php?id=<?= $r['id'] ?>&produk_id=<?= $lihat_resep_id ?>"
                                       class="btn btn-danger btn-sm" data-confirm="Hapus bahan ini dari resep?">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($resep_items)): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--color-text-muted);">Belum ada bahan di resep ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- ===== BREAKDOWN MODAL & PROFIT ===== -->
                    <div style="background:#FAF0EC; border-radius:12px; padding:16px; margin-bottom:20px; border:1px solid #EADCD9;">
                        <h4 style="margin-top:0; margin-bottom:12px; color:var(--color-primary);">Breakdown Modal & Estimasi Profit</h4>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                            <div>
                                <div style="font-size:12px; color:var(--color-text-muted);">Total Modal Bahan Baku (Resep)</div>
                                <div style="font-size:18px; font-weight:700; color:var(--color-text-dark);">Rp<?= number_format($total_modal_bahan, 0, ',', '.') ?></div>
                            </div>
                            <div>
                                <div style="font-size:12px; color:var(--color-text-muted);">Biaya Operasional Produk</div>
                                <div style="font-size:18px; font-weight:700; color:var(--color-text-muted);">Rp<?= number_format($produk_resep['biaya_operasional'], 0, ',', '.') ?></div>
                            </div>
                            <div>
                                <div style="font-size:12px; color:var(--color-text-muted);">Biaya Jasa Rangkai (Upah)</div>
                                <div style="font-size:18px; font-weight:700; color:var(--color-text-muted);">Rp<?= number_format($produk_resep['biaya_jasa'], 0, ',', '.') ?></div>
                            </div>
                            <?php 
                            $total_hpp_produk = $total_modal_bahan + (float)$produk_resep['biaya_operasional'] + (float)$produk_resep['biaya_jasa'];
                            $harga_jual = (float)$produk_resep['harga_jual'];
                            $estimasi_profit = $harga_jual - $total_hpp_produk;
                            ?>
                            <div>
                                <div style="font-size:12px; color:var(--color-primary); font-weight:700;">TOTAL HPP PRODUK (MODAL)</div>
                                <div style="font-size:18px; font-weight:800; color:var(--color-primary);">Rp<?= number_format($total_hpp_produk, 0, ',', '.') ?></div>
                            </div>
                            <div>
                                <div style="font-size:12px; color:var(--color-text-muted);">Harga Jual Produk</div>
                                <div style="font-size:18px; font-weight:700; color:var(--color-text-dark);">Rp<?= number_format($harga_jual, 0, ',', '.') ?></div>
                            </div>
                            <div>
                                <div style="font-size:12px; color:var(--color-success); font-weight:700;">ESTIMASI UNTUNG KOTOR</div>
                                <div style="font-size:18px; font-weight:800; color:var(--color-success);">Rp<?= number_format($estimasi_profit, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>

                    <form action="../actions/simpan_resep.php" method="POST" style="display:flex; gap:10px; align-items:end;">
                        <input type="hidden" name="produk_id" value="<?= $lihat_resep_id ?>">
                        <div class="form-group" style="flex:1; margin-bottom:0;">
                            <label>Bahan</label>
                            <select name="bahan_baku_id" class="ts-select" required>
                                <option value="">-- Pilih bahan --</option>
                                <?php foreach ($bahan_semua as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama']) ?> (<?= htmlspecialchars($b['satuan_dasar']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="width:140px; margin-bottom:0;">
                            <label>Jumlah</label>
                            <input type="number" step="0.01" name="jumlah_dibutuhkan" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">+ Tambah</button>
                    </form>
                </div>

                <a href="produk_resep.php" class="btn btn-secondary">← Kembali ke Daftar Produk</a>

            <?php else: ?>
                <!-- ================= FORM TAMBAH/EDIT PRODUK ================= -->
                <div class="panel" style="margin-bottom:24px;">
                    <h3><?= $edit_data ? 'Edit Produk' : '+ Tambah Produk Baru' ?></h3>
                    <form action="../actions/simpan_produk.php" method="POST" enctype="multipart/form-data">
                        <?php if ($edit_data): ?>
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                        <?php endif; ?>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
                            <div class="form-group">
                                <label>Nama Produk</label>
                                <input type="text" name="nama" required
                                       value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
                            </div>

                           <div class="form-group">
                                <label>Kategori</label>
                                <select name="kategori_id" required>
                                    <option value="">-- Pilih kategori --</option>
                                    <?php foreach ($kategori_list as $k): ?>
                                        <option value="<?= $k['id'] ?>" <?= (isset($edit_data['kategori_id']) && $edit_data['kategori_id'] == $k['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($k['nama']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Harga Jual (Rp)</label>
                                <input type="number" step="0.01" name="harga_jual" required
                                       value="<?= htmlspecialchars($edit_data['harga_jual'] ?? '0') ?>">
                            </div>

                            <div class="form-group">
                                <label>Biaya Operasional / Produk (Rp)</label>
                                <input type="number" step="0.01" name="biaya_operasional"
                                    value="<?= htmlspecialchars($edit_data['biaya_operasional'] ?? '0') ?>">
                                <small style="color:var(--color-text-muted);">listrik, dll </small>
                            </div>

                            <div class="form-group">
                                <label>Biaya Jasa Pembuatan (Rp)</label>
                                <input type="number" step="0.01" name="biaya_jasa"
                                    value="<?= htmlspecialchars($edit_data['biaya_jasa'] ?? '0') ?>">
                                <small style="color:var(--color-text-muted);">Upah rangkai </small>
                            </div>

                            <div class="form-group">
                                <label>Foto Produk</label>
                                <input type="file" name="foto" accept="image/*">
                                <?php if (!empty($edit_data['foto_url'])): ?>
                                    <small style="color:var(--color-text-muted);">Foto saat ini akan diganti kalau upload baru</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?= $edit_data ? 'Simpan Perubahan' : '+ Tambah Produk' ?>
                        </button>
                        <?php if ($edit_data): ?>
                            <a href="produk_resep.php" class="btn btn-secondary">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- ===== KELOLA KATEGORI ===== -->
                <div class="panel" style="margin-bottom:24px;">
                    <h3>Kategori Produk</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
                        <?php foreach ($kategori_list as $k): ?>
                            <span class="badge badge-diambil" style="display:flex; align-items:center; gap:6px;">
                                <?= htmlspecialchars($k['nama']) ?>
                                <a href="../actions/hapus_kategori.php?id=<?= $k['id'] ?>"
                                style="color:inherit; text-decoration:none;"
                                data-confirm="Hapus kategori '<?= htmlspecialchars($k['nama']) ?>'? Produk yang pakai kategori ini akan jadi 'tanpa kategori'.">✖</a>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <form action="../actions/simpan_kategori.php" method="POST" style="display:flex; gap:10px;">
                        <input type="text" name="nama" placeholder="Nama kategori baru" required style="flex:1; padding:10px 12px; border:1px solid #E0D5D6; border-radius:8px;">
                        <button type="submit" class="btn btn-secondary btn-sm">+ Tambah Kategori</button>
                    </form>
                </div>
                <!-- ================= DAFTAR PRODUK ================= -->
                <div class="panel">
                    <h3>Daftar Produk</h3>
                    
                    <form method="GET" style="display:flex; gap:10px; align-items:center; margin-bottom:20px; width:100%; max-width:500px;">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari nama produk atau kategori..." style="flex:1; padding:10px 12px; border:1px solid #E0D5D6; border-radius:8px; height:42px; box-sizing:border-box;">
                        <button type="submit" class="btn btn-primary" style="height:42px; padding:0 16px; margin:0; white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; font-weight:700;">Cari</button>
                        <?php if ($search_query !== ''): ?>
                            <a href="produk_resep.php" class="btn btn-secondary" style="height:42px; padding:0 16px; margin:0; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; text-decoration:none;">Reset</a>
                        <?php endif; ?>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Harga Jual</th>
                                <th>Estimasi Modal (HPP)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produk_list as $p): ?>
                            <tr>
                                <td>
                                    <?php if ($p['foto_url']): ?>
                                        <img src="../<?= htmlspecialchars($p['foto_url']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                                    <?php else: ?>
                                        <div style="width:48px;height:48px;background:#F0E4E5;border-radius:8px;"></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>
                                <td><?= htmlspecialchars($p['nama_kategori'] ?? '-') ?></td>
                                <td><strong>Rp<?= number_format($p['harga_jual'], 0, ',', '.') ?></strong></td>
                                <td style="font-size:12px; color:var(--color-text-muted); line-height:1.4;">
                                    Bahan: Rp<?= number_format($p['total_modal_bahan'], 0, ',', '.') ?><br>
                                    Ops: Rp<?= number_format($p['biaya_operasional'], 0, ',', '.') ?><br>
                                    Jasa: Rp<?= number_format($p['biaya_jasa'], 0, ',', '.') ?><br>
                                    <?php 
                                    $total_hpp = (float)$p['total_modal_bahan'] + (float)$p['biaya_operasional'] + (float)$p['biaya_jasa'];
                                    $profit = (float)$p['harga_jual'] - $total_hpp;
                                    ?>
                                    <strong style="color:var(--color-primary);">Total HPP: Rp<?= number_format($total_hpp, 0, ',', '.') ?></strong><br>
                                    <span class="badge badge-success" style="font-size:10px; padding:2px 6px; display:inline-block; margin-top:4px; font-weight:700;">Untung: Rp<?= number_format($profit, 0, ',', '.') ?></span>
                                </td>                                
                                <td style="white-space:nowrap;">
                                    <a href="produk_resep.php?resep=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Resep</a>
                                    <a href="produk_resep.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <a href="../actions/hapus_produk.php?id=<?= $p['id'] ?>"
                                       class="btn btn-danger btn-sm"
                                       data-confirm="Hapus produk '<?= htmlspecialchars($p['nama']) ?>'?">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($produk_list)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; color:var(--color-text-muted);">
                                    <?= $search_query !== '' ? 'Tidak ada produk yang cocok dengan pencarian.' : 'Belum ada produk.' ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php include __DIR__ . '/../includes/footer.php'; ?>