<?php
// pages/pesanan_detail.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Check if 'metode_pengiriman' column exists in 'pesanan' table, if not, add it
try {
    $pdo->query("SELECT metode_pengiriman FROM pesanan LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE pesanan ADD COLUMN metode_pengiriman VARCHAR(50) DEFAULT 'jemput'");
}

$page_title = 'Detail Pesanan';
$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
$stmt->execute([$id]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    die('Pesanan tidak ditemukan.');
}

$stmt = $pdo->prepare("SELECT * FROM detail_pesanan WHERE pesanan_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT pb.*, b.nama AS nama_bahan, b.satuan_dasar
    FROM pesanan_bahan pb
    JOIN bahan_baku b ON b.id = pb.bahan_baku_id
    WHERE pb.pesanan_id = ?
");
$stmt->execute([$id]);
$bahan_dicadangkan = $stmt->fetchAll();

$sisa_bayar = max(0, $pesanan['total_harga'] - $pesanan['dp']);

// Check if 'foto_url' column exists in 'pesanan' table, if not, add it
try {
    $pdo->query("SELECT foto_url FROM pesanan LIMIT 1");
} catch (PDOException $e) {
    // Column doesn't exist, alter table to add it
    $pdo->exec("ALTER TABLE pesanan ADD COLUMN foto_url VARCHAR(255) DEFAULT NULL");
}

// Handling custom photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_custom'])) {
    $file = $_FILES['foto_custom'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            // Create uploads dir if it doesn't exist
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate filename
            $filename = 'custom_order_' . $id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $db_path = 'uploads/' . $filename;
                // Update database: check if pesanan_foto table exists, else fallback to pesanan.foto_url
                try {
                    $stmt = $pdo->prepare("INSERT INTO pesanan_foto (pesanan_id, foto_url) VALUES (?, ?)");
                    $stmt->execute([$id, $db_path]);
                } catch (Exception $e) {
                    $stmt = $pdo->prepare("UPDATE pesanan SET foto_url = ? WHERE id = ?");
                    $stmt->execute([$db_path, $id]);
                }
                
                // Refresh data
                header("Location: pesanan_detail.php?id=" . $id);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="panel" style="margin-bottom:20px; display:flex; gap:30px; justify-content:space-between; align-items:stretch;">
                <div style="flex:1;">
                    <h3>Info Pesanan</h3>
                    <table style="width:100%;">
                        <tr><td style="width:180px;">Pemesan</td><td><strong><?= htmlspecialchars($pesanan['nama_pemesan']) ?></strong></td></tr>
                        <tr><td>Kontak</td><td><?= htmlspecialchars($pesanan['kontak'] ?: '-') ?></td></tr>
                        <tr><td>Jadwal Jemput</td><td><?= date('d M Y', strtotime($pesanan['tanggal_jemput'])) ?>, <?= date('H:i', strtotime($pesanan['jam_jemput'])) ?></td></tr>
                        <tr><td>Metode Pengiriman</td><td><span class="badge badge-secondary" style="font-weight:700;"><?= $pesanan['metode_pengiriman'] === 'delivery' ? 'Delivery' : 'Jemput Sendiri' ?></span></td></tr>
                        
                        <?php if ($pesanan['metode_pengiriman'] === 'delivery'): ?>
                            <tr>
                                <td>Alur Ongkir Delivery</td>
                                <td>
                                    <strong><?= $pesanan['ongkir_tipe'] === 'toko' ? 'Bayar ke Toko Dulu' : 'Bayar Langsung ke Kurir' ?></strong>
                                </td>
                            </tr>
                            <?php if ($pesanan['ongkir_tipe'] === 'toko'): ?>
                                <tr>
                                    <td>Nominal Ongkir</td>
                                    <td>Rp<?= number_format($pesanan['ongkir_nominal'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Metode Bayar ke Kurir</td>
                                    <td><span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700;"><?= htmlspecialchars($pesanan['ongkir_metode_kurir'] ?? 'cash') ?></span></td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>

                        <tr><td>Metode Pembayaran</td><td><span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700;"><?= htmlspecialchars(($pesanan['metode_pembayaran'] ?? 'cash') === 'qris' ? 'DANA' : ($pesanan['metode_pembayaran'] ?? 'cash')) ?></span></td></tr>
                        <tr><td>Status</td><td><span class="badge badge-<?= $pesanan['status'] ?>"><?= ucfirst($pesanan['status']) ?></span></td></tr>
                        <tr><td>Catatan</td><td><?= htmlspecialchars($pesanan['catatan'] ?: '-') ?></td></tr>
                    </table>
                </div>

                <!-- Foto Produk / Foto Custom -->
                <div style="width:250px; flex-shrink:0; text-align:center; padding:15px; border:2px dashed var(--color-border); border-radius:16px; background:color-mix(in srgb, var(--color-primary) 6%, #ffffff); display:flex; flex-direction:column; align-items:center; justify-content:flex-start; min-height:180px; gap:16px;">
                    <?php
                    // 1. Cari semua foto dari produk katalog
                    $product_photos = [];
                    foreach ($items as $it) {
                        if ($it['produk_id']) {
                            $stmt_p = $pdo->prepare("SELECT nama, foto_url FROM produk WHERE id = ?");
                            $stmt_p->execute([$it['produk_id']]);
                            $prod = $stmt_p->fetch();
                            if ($prod && $prod['foto_url']) {
                                $product_photos[] = [
                                    'url' => '../' . $prod['foto_url'],
                                    'label' => 'Katalog: ' . $prod['nama']
                                ];
                            }
                        }
                    }

                    // 2. Ambil semua foto custom
                    $custom_photos = [];
                    try {
                        $stmt_f = $pdo->prepare("SELECT id, foto_url FROM pesanan_foto WHERE pesanan_id = ? ORDER BY id ASC");
                        $stmt_f->execute([$id]);
                        foreach ($stmt_f->fetchAll() as $f) {
                            $custom_photos[] = [
                                'id' => $f['id'],
                                'url' => '../' . $f['foto_url'],
                                'label' => 'Foto Custom'
                            ];
                        }
                    } catch (Exception $e) {
                        // Fallback jika belum sinkronisasi database
                        if (!empty($pesanan['foto_url'])) {
                            $custom_photos[] = [
                                'id' => null,
                                'url' => '../' . $pesanan['foto_url'],
                                'label' => 'Foto Custom'
                            ];
                        }
                    }

                    $has_any_photos = (!empty($product_photos) || !empty($custom_photos));
                    
                    if ($has_any_photos):
                    ?>
                        <div style="display:flex; flex-direction:column; gap:16px; width:100%;">
                            <?php foreach ($product_photos as $p): ?>
                                <div style="width:100%; display:flex; flex-direction:column; align-items:center; gap:6px; border-bottom:1px dashed var(--color-border); padding-bottom:12px;">
                                    <img src="<?= htmlspecialchars($p['url']) ?>" alt="Foto Produk" style="width:100%; height:auto; max-height:220px; object-fit:contain; border-radius:12px;">
                                    <span style="font-size:10px; font-weight:700; color:var(--color-text-muted);"><?= htmlspecialchars($p['label']) ?></span>
                                </div>
                            <?php endforeach; ?>

                            <?php foreach ($custom_photos as $f): ?>
                                <div style="width:100%; display:flex; flex-direction:column; align-items:center; gap:6px; border-bottom:1px dashed var(--color-border); padding-bottom:12px;">
                                    <img src="<?= htmlspecialchars($f['url']) ?>" alt="Foto Custom" style="width:100%; height:auto; max-height:220px; object-fit:contain; border-radius:12px;">
                                    <span style="font-size:10px; font-weight:700; color:var(--color-text-muted);"><?= htmlspecialchars($f['label']) ?></span>
                                    
                                    <?php if ($f['id']): ?>
                                        <form action="../actions/hapus_foto_pesanan.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus foto custom ini?')" style="margin-top:4px;">
                                            <input type="hidden" name="foto_id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="pesanan_id" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding:2px 8px; font-size:10px; background:#A43B22; color:#fff; border-color:#A43B22; border-radius:8px; line-height:1.2;">
                                                ✖ Hapus
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; width:100%; height:100%;">
                            <span style="font-size:24px; color:#8E7873; margin-bottom:6px;">📷</span>
                            <div style="font-size:11px; color:var(--color-text-muted); margin-bottom:12px; line-height:1.3;">Belum ada foto produk/custom</div>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data" style="width:100%; margin-top:4px;">
                        <label class="btn btn-primary btn-sm" style="margin-bottom:0; cursor:pointer; font-size:11px; padding:6px 12px; width:100%; box-sizing:border-box; display:inline-block; text-align:center;">
                            + Tambah Foto Custom
                            <input type="file" name="foto_custom" onchange="this.form.submit()" style="display:none;" accept="image/*">
                        </label>
                    </form>
                </div>
            </div>

            <div class="panel" style="margin-bottom:20px;">
                <h3>Item Pesanan</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th><th>Qty</th><th>Harga</th><th>Biaya Ops</th><th>Biaya Jasa</th><th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_ops = 0;
                        $total_jasa = 0;
                        foreach ($items as $it):
                            $total_ops += $it['biaya_operasional'] * $it['qty'];
                            $total_jasa += $it['biaya_jasa'] * $it['qty'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($it['nama_item']) ?></td>
                            <td><?= $it['qty'] ?></td>
                            <td>Rp<?= number_format($it['harga'], 0, ',', '.') ?></td>
                            <td>Rp<?= number_format($it['biaya_operasional'], 0, ',', '.') ?></td>
                            <td>Rp<?= number_format($it['biaya_jasa'], 0, ',', '.') ?></td>
                            <td>Rp<?= number_format($it['harga'] * $it['qty'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:14px; text-align:right;">
                    <div style="color:var(--color-text-muted); font-size:13px;">Total Biaya Operasional: Rp<?= number_format($total_ops, 0, ',', '.') ?></div>
                    <div style="color:var(--color-text-muted); font-size:13px; margin-bottom:8px;">Total Biaya Jasa: Rp<?= number_format($total_jasa, 0, ',', '.') ?></div>
                    <div>Total: <strong>Rp<?= number_format($pesanan['total_harga'], 0, ',', '.') ?></strong></div>
                    <?php if ($pesanan['dp'] > 0): ?>
                        <div>DP: Rp<?= number_format($pesanan['dp'], 0, ',', '.') ?></div>
                        <div>Sisa Bayar: <strong>Rp<?= number_format($sisa_bayar, 0, ',', '.') ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel" style="margin-bottom:20px;">
                <h3>Bahan Dicadangkan</h3>
                <table>
                    <thead><tr><th>Bahan</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        <?php foreach ($bahan_dicadangkan as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['nama_bahan']) ?></td>
                            <td><?= (float)$b['jumlah_dicadangkan'] ?> <?= htmlspecialchars($b['satuan_dasar']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bahan_dicadangkan)): ?>
                        <tr><td colspan="2" style="color:var(--color-text-muted);">Tidak ada bahan tercatat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="pesanan.php" class="btn btn-secondary">← Kembali</a>
            <?php if ($pesanan['status'] === 'menunggu'): ?>
                <a href="javascript:void(0)" onclick="bukaPelunasan(<?= $pesanan['id'] ?>, <?= (float)$pesanan['total_harga'] ?>, <?= (float)$pesanan['dp'] ?>, '<?= htmlspecialchars($pesanan['ongkir_tipe'] ?? 'kurir', ENT_QUOTES) ?>', <?= (float)($pesanan['ongkir_nominal'] ?? 0) ?>, '<?= htmlspecialchars($pesanan['ongkir_metode_kurir'] ?? 'cash', ENT_QUOTES) ?>')" class="btn btn-primary">✓ Konfirmasi Diambil</a>
                <a href="../actions/batalkan_pesanan.php?id=<?= $pesanan['id'] ?>" class="btn btn-danger" data-confirm="Batalkan pesanan ini?">✖ Batalkan</a>
            <?php elseif ($pesanan['status'] === 'diambil'): ?>
                <a href="cetak_struk.php?id=<?= $pesanan['id'] ?>&via=pesanan" target="_blank" class="btn btn-primary">Cetak Struk</a>
            <?php endif; ?>

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