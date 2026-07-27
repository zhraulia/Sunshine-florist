<?php
// pages/pesanan_edit.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Check if 'metode_pengiriman' column exists in 'pesanan' table, if not, add it
try {
    $pdo->query("SELECT metode_pengiriman FROM pesanan LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE pesanan ADD COLUMN metode_pengiriman VARCHAR(50) DEFAULT 'jemput'");
}

$page_title = 'Edit Pesanan';
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: pesanan.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
$stmt->execute([$id]);
$pesanan = $stmt->fetch();

if (!$pesanan || $pesanan['status'] !== 'menunggu') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Pesanan tidak ditemukan atau tidak dapat diedit.'];
    header('Location: pesanan.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM detail_pesanan WHERE pesanan_id = ?");
$stmt->execute([$id]);
$pesanan_items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pesanan_bahan WHERE pesanan_id = ?");
$stmt->execute([$id]);
$pesanan_bahan = $stmt->fetchAll();

$produk_list = $pdo->query("SELECT id, nama, harga_jual, biaya_operasional, biaya_jasa, is_custom FROM produk ORDER BY nama ASC")->fetchAll();
$bahan_list  = $pdo->query("SELECT id, nama, satuan_dasar FROM bahan_baku ORDER BY nama ASC")->fetchAll();

// Ambil resep semua produk sekaligus, dikelompokkan per produk_id
$resep_raw = $pdo->query("SELECT * FROM resep_produk")->fetchAll();
$resep_map = [];
foreach ($resep_raw as $r) {
    $resep_map[$r['produk_id']][] = [
        'bahan_baku_id' => (int) $r['bahan_baku_id'],
        'jumlah' => (float) $r['jumlah_dibutuhkan'],
    ];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesanan - Sunshine Florist</title>
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

            <form action="../actions/update_pesanan.php" method="POST" id="form-pesanan">
                <input type="hidden" name="id" value="<?= htmlspecialchars($pesanan['id']) ?>">

                <!-- ===== INFO PEMESAN ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Info Pemesan</h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
                        <div class="form-group">
                            <label>Nama Pemesan</label>
                            <input type="text" name="nama_pemesan" value="<?= htmlspecialchars($pesanan['nama_pemesan']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Kontak (opsional)</label>
                            <input type="text" name="kontak" value="<?= htmlspecialchars($pesanan['kontak']) ?>" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Jemput</label>
                            <input type="date" name="tanggal_jemput" value="<?= htmlspecialchars($pesanan['tanggal_jemput']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Jam Jemput</label>
                            <input type="time" name="jam_jemput" value="<?= htmlspecialchars($pesanan['jam_jemput']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>DP (opsional)</label>
                            <input type="number" name="dp" value="<?= (float)$pesanan['dp'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Metode Pengiriman</label>
                            <select name="metode_pengiriman" id="metode_pengiriman" onchange="toggleDeliveryFields()" required>
                                <option value="jemput" <?= ($pesanan['metode_pengiriman'] ?? 'jemput') === 'jemput' ? 'selected' : '' ?>>Jemput Sendiri</option>
                                <option value="delivery" <?= ($pesanan['metode_pengiriman'] ?? '') === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                            </select>
                        </div>
                        <div class="form-group" id="group-ongkir-tipe" style="display:none;">
                            <label>Ongkir Delivery</label>
                            <select name="ongkir_tipe" id="ongkir_tipe" onchange="toggleOngkirTipe()" required>
                                <option value="kurir" <?= ($pesanan['ongkir_tipe'] ?? 'kurir') === 'kurir' ? 'selected' : '' ?>>Dibayar langsung ke kurir oleh pembeli</option>
                                <option value="toko" <?= ($pesanan['ongkir_tipe'] ?? '') === 'toko' ? 'selected' : '' ?>>Dibayar ke toko dulu</option>
                            </select>
                        </div>
                        <div class="form-group" id="group-ongkir-nominal" style="display:none;">
                            <label>Nominal Ongkir (Rp)</label>
                            <input type="number" name="ongkir_nominal" id="ongkir_nominal" value="<?= (float)($pesanan['ongkir_nominal'] ?? 0) ?>" min="0">
                        </div>
                        <div class="form-group" id="group-ongkir-metode" style="display:none;">
                            <label>Metode Bayar ke Kurir</label>
                            <select name="ongkir_metode_kurir" id="ongkir_metode_kurir">
                                <option value="cash" <?= ($pesanan['ongkir_metode_kurir'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash (Tunai)</option>
                                <option value="bri" <?= ($pesanan['ongkir_metode_kurir'] ?? '') === 'bri' ? 'selected' : '' ?>>Bank BRI</option>
                                <option value="seabank" <?= ($pesanan['ongkir_metode_kurir'] ?? '') === 'seabank' ? 'selected' : '' ?>>SeaBank</option>
                                <option value="dana" <?= ($pesanan['ongkir_metode_kurir'] ?? '') === 'dana' ? 'selected' : '' ?>>DANA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Metode Pembayaran</label>
                            <select name="metode_pembayaran" required>
                                <option value="cash" <?= ($pesanan['metode_pembayaran'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash (Tunai)</option>
                                <option value="bri" <?= ($pesanan['metode_pembayaran'] ?? '') === 'bri' ? 'selected' : '' ?>>Bank BRI</option>
                                <option value="seabank" <?= ($pesanan['metode_pembayaran'] ?? '') === 'seabank' ? 'selected' : '' ?>>SeaBank</option>
                                <option value="dana" <?= ($pesanan['metode_pembayaran'] ?? '') === 'dana' ? 'selected' : '' ?>>DANA</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="catatan" rows="2"><?= htmlspecialchars($pesanan['catatan']) ?></textarea>
                    </div>
                </div>

                <!-- ===== ITEM PESANAN ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Item Pesanan</h3>
                    <div class="item-grid-header-order" style="font-size:11px; color:var(--color-text-muted); margin-bottom:4px;">
                        <span>Produk</span><span>Nama Item</span><span>Qty</span><span>Harga Jual</span><span>Ops</span><span>Jasa</span><span></span>
                    </div>
                    <div id="items-container"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow()">+ Tambah Item</button>
                    <div style="margin-top:12px; font-weight:700;">
                        Total: Rp<span id="total-harga-display">0</span>
                    </div>
                </div>

                <!-- ===== BAHAN DICADANGKAN ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Bahan yang Dicadangkan</h3>
                    <p style="color:var(--color-text-muted); font-size:13px;">
                        Klik "Isi Otomatis" buat narik dari resep produk (kalau bukan custom), atau tambah/edit manual buat komposisi custom.
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="isiOtomatisResep()" style="margin-bottom:12px;">Isi Otomatis dari Resep</button>
                    <div id="bahan-container"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addBahanRow()">+ Tambah Bahan</button>
                </div>

                <button type="submit" class="btn btn-primary">Perbarui Pesanan</button>
                <a href="pesanan.php" class="btn btn-secondary">Batal</a>
            </form>

            <?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const PRODUK = <?= json_encode($produk_list) ?>;
const BAHAN = <?= json_encode($bahan_list) ?>;
const RESEP = <?= json_encode($resep_map) ?>;

const CURRENT_ITEMS = <?= json_encode($pesanan_items) ?>;
const CURRENT_BAHAN = <?= json_encode($pesanan_bahan) ?>;

let itemIndex = 0;
let bahanIndex = 0;

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function produkOptions() {
    let opts = '<option value="">-- Custom (bukan dari daftar) --</option>';
    PRODUK.forEach(p => {
        opts += `<option value="${p.id}" data-harga="${p.harga_jual}" data-ops="${p.biaya_operasional}" data-jasa="${p.biaya_jasa}" data-custom="${p.is_custom}">${p.nama}</option>`;
    });
    return opts;
}

function bahanOptions() {
    let opts = '<option value="">-- Pilih bahan --</option>';
    BAHAN.forEach(b => {
        opts += `<option value="${b.id}" data-satuan="${b.satuan_dasar}">${b.nama} (${b.satuan_dasar})</option>`;
    });
    return opts;
}

function addItemRow(produkId = '', namaItem = '', qty = 1, harga = '', ops = 0, jasa = 0) {
    if (produkId === null || produkId === 'null') {
        produkId = '';
    }
    const idx = itemIndex++;
    const div = document.createElement('div');
    div.className = 'item-row-form-order';
    
    let selectHtml = `<select onchange="onProdukChange(this, ${idx})">${produkOptions()}</select>`;
    
    div.innerHTML = `
        <div>${selectHtml}</div>
        <div><input type="text" name="items[${idx}][nama_item]" placeholder="Nama item" value="${escapeHtml(namaItem)}" required></div>
        <div><label class="mobile-label">Qty</label><input type="number" name="items[${idx}][qty]" value="${qty}" min="1" onchange="hitungTotal()" title="Qty"></div>
        <div><label class="mobile-label">Harga</label><input type="number" name="items[${idx}][harga]" placeholder="Harga jual" value="${harga !== '' ? Math.round(parseFloat(harga)) : ''}" onchange="hitungTotal()" title="Harga Jual"></div>
        <div><label class="mobile-label">Ops</label><input type="number" name="items[${idx}][biaya_operasional]" placeholder="Ops" value="${Math.round(parseFloat(ops))}" title="Biaya Operasional"></div>
        <div><label class="mobile-label">Jasa</label><input type="number" name="items[${idx}][biaya_jasa]" placeholder="Jasa" value="${Math.round(parseFloat(jasa))}" title="Biaya Jasa"></div>
        <div><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove(); hitungTotal();">✖</button></div>
        <input type="hidden" name="items[${idx}][produk_id]" value="${produkId}">
    `;
    
    if (produkId) {
        const select = div.querySelector('select');
        select.value = produkId;
    }
    
    document.getElementById('items-container').appendChild(div);
}

function onProdukChange(select, idx) {
    const row = select.closest('.item-row-form-order');
    const opt = select.selectedOptions[0];
    const produkIdInput = row.querySelector(`input[name="items[${idx}][produk_id]"]`);
    const namaInput = row.querySelector(`input[name="items[${idx}][nama_item]"]`);
    const hargaInput = row.querySelector(`input[name="items[${idx}][harga]"]`);
    const opsInput = row.querySelector(`input[name="items[${idx}][biaya_operasional]"]`);
    const jasaInput = row.querySelector(`input[name="items[${idx}][biaya_jasa]"]`);

    if (select.value) {
        produkIdInput.value = select.value;
        namaInput.value = opt.text;
        hargaInput.value = Math.round(parseFloat(opt.dataset.harga || 0));
        opsInput.value = Math.round(parseFloat(opt.dataset.ops || 0));
        jasaInput.value = Math.round(parseFloat(opt.dataset.jasa || 0));
    } else {
        produkIdInput.value = '';
        namaInput.value = '';
        hargaInput.value = '';
        opsInput.value = 0;
        jasaInput.value = 0;
    }
    hitungTotal();
}

function hitungTotal() {
    let total = 0;
    document.querySelectorAll('#items-container .item-row-form-order').forEach(row => {
        const qty = parseFloat(row.querySelector('input[name*="[qty]"]').value) || 0;
        const harga = parseFloat(row.querySelector('input[name*="[harga]"]').value) || 0;
        total += qty * harga;
    });
    document.getElementById('total-harga-display').textContent = total.toLocaleString('id-ID');
}

function addBahanRow(bahanId = '', jumlah = '') {
    const idx = bahanIndex++;
    const div = document.createElement('div');
    div.className = 'bahan-row-form';
    
    let selectHtml = `<select name="bahan[${idx}][bahan_baku_id]" required>${bahanOptions()}</select>`;
    const roundedJumlah = jumlah ? Math.round(parseFloat(jumlah)) : '';
    
    div.innerHTML = `
        ${selectHtml}
        <input type="number" name="bahan[${idx}][jumlah]" placeholder="Jumlah" value="${roundedJumlah}" required>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove();">✖</button>
    `;
    
    if (bahanId) {
        const select = div.querySelector('select');
        select.value = bahanId;
    }
    
    document.getElementById('bahan-container').appendChild(div);
}

function isiOtomatisResep() {
    const agregat = {};
    document.querySelectorAll('#items-container .item-row-form-order').forEach(row => {
        const produkId = row.querySelector('input[name*="[produk_id]"]').value;
        const qty = parseFloat(row.querySelector('input[name*="[qty]"]').value) || 0;
        if (produkId && RESEP[produkId]) {
            RESEP[produkId].forEach(r => {
                agregat[r.bahan_baku_id] = (agregat[r.bahan_baku_id] || 0) + (r.jumlah * qty);
            });
        }
    });

    if (Object.keys(agregat).length === 0) {
        alert('Tidak ada resep yang bisa ditarik otomatis. Item mungkin bertipe custom, silakan tambah bahan manual.');
        return;
    }

    document.getElementById('bahan-container').innerHTML = '';
    bahanIndex = 0;
    for (const [bahanId, jumlah] of Object.entries(agregat)) {
        addBahanRow(bahanId, Math.round(jumlah));
    }
}

// Inisialisasi data yang sudah ada
if (CURRENT_ITEMS.length > 0) {
    CURRENT_ITEMS.forEach(it => {
        addItemRow(it.produk_id, it.nama_item, it.qty, it.harga, it.biaya_operasional, it.biaya_jasa);
    });
} else {
    addItemRow();
}

if (CURRENT_BAHAN.length > 0) {
    CURRENT_BAHAN.forEach(b => {
        addBahanRow(b.bahan_baku_id, b.jumlah_dicadangkan);
    });
}

document.querySelector('form').addEventListener('submit', function(e) {
    let total = 0;
    document.querySelectorAll('#items-container .item-row-form-order').forEach(row => {
        const qty = parseFloat(row.querySelector('input[name*="[qty]"]').value) || 0;
        const harga = parseFloat(row.querySelector('input[name*="[harga]"]').value) || 0;
        total += qty * harga;
    });
    const dpInput = parseFloat(document.querySelector('input[name="dp"]').value) || 0;
    if (dpInput > total) {
        alert('Uang muka (DP) tidak boleh lebih besar dari total harga pesanan (Rp ' + total.toLocaleString('id-ID') + ').');
        e.preventDefault();
    }
});

function toggleDeliveryFields() {
    const met = document.getElementById('metode_pengiriman').value;
    const typeGroup = document.getElementById('group-ongkir-tipe');
    const nomGroup = document.getElementById('group-ongkir-nominal');
    const metGroup = document.getElementById('group-ongkir-metode');
    
    if (met === 'delivery') {
        typeGroup.style.display = 'block';
        toggleOngkirTipe();
    } else {
        typeGroup.style.display = 'none';
        nomGroup.style.display = 'none';
        metGroup.style.display = 'none';
    }
}

function toggleOngkirTipe() {
    const tipe = document.getElementById('ongkir_tipe').value;
    const nomGroup = document.getElementById('group-ongkir-nominal');
    const metGroup = document.getElementById('group-ongkir-metode');
    
    if (tipe === 'toko') {
        nomGroup.style.display = 'block';
        metGroup.style.display = 'block';
    } else {
        nomGroup.style.display = 'none';
        metGroup.style.display = 'none';
    }
}

hitungTotal();
toggleDeliveryFields();
</script>
