<?php
// pages/jual_langsung.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Jual Langsung';

$produk_list = $pdo->query("SELECT id, nama, harga_jual, biaya_operasional, biaya_jasa, is_custom FROM produk ORDER BY nama ASC")->fetchAll();
$bahan_list  = $pdo->query("SELECT id, nama, satuan_dasar FROM bahan_baku ORDER BY nama ASC")->fetchAll();

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
    <title>Jual Langsung - Sunshine Florist</title>
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

            <!-- ===== TAB NAVIGASI PENJUALAN ===== -->
            <div style="display:flex; gap:12px; margin-bottom:20px; border-bottom:1px solid var(--color-border); padding-bottom:12px; margin-top:-6px;">
                <a href="jual_langsung.php" class="btn btn-primary" style="font-weight:700; padding:8px 18px; font-size:13px;">Penjualan Buket</a>
                <a href="jual_bahan.php" class="btn btn-secondary" style="font-weight:700; padding:8px 18px; font-size:13px;">Penjualan Bahan Eceran</a>
            </div>

            <p style="margin-top:0; color:var(--color-text-muted); font-size:13px;">
                Buat transaksi jual langsung (walk-in) — stok langsung terpotong & tercatat sebagai penjualan begitu disimpan, tanpa tahap "menunggu jemput".
            </p>

            <form action="../actions/simpan_walkin.php" method="POST" id="form-walkin">

                <!-- ===== INFO PEMBELI & PEMBAYARAN ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Info Pembeli & Pembayaran</h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
                        <div class="form-group">
                            <label>Nama Pembeli (opsional)</label>
                            <input type="text" name="nama_pembeli" placeholder="Boleh dikosongkan">
                        </div>
                        <div class="form-group">
                            <label>Kontak (opsional)</label>
                            <input type="text" name="kontak" placeholder="08xxxxxxxxxx">
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
                    </div>
                </div>

                <!-- ===== ITEM TERJUAL ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Item Terjual</h3>
                    <div id="items-container"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow()">+ Tambah Item</button>
                    <div style="margin-top:12px; font-weight:700;">
                        Total: Rp<span id="total-harga-display">0</span>
                    </div>
                </div>

                <!-- ===== BAHAN TERPAKAI ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Bahan yang Terpakai</h3>
                    <p style="color:var(--color-text-muted); font-size:13px;">
                        Ini langsung memotong stok fisik permanen begitu disimpan (bukan dicadangkan dulu).
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="isiOtomatisResep()" style="margin-bottom:12px;">Isi Otomatis dari Resep</button>
                    <div id="bahan-container"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addBahanRow()">+ Tambah Bahan</button>
                </div>

                <button type="submit" class="btn btn-primary">Simpan & Cetak Struk</button>
                <a href="dashboard.php" class="btn btn-secondary">Batal</a>
            </form>

            <?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const PRODUK = <?= json_encode($produk_list) ?>;
const BAHAN = <?= json_encode($bahan_list) ?>;
const RESEP = <?= json_encode($resep_map) ?>;

let itemIndex = 0;
let bahanIndex = 0;

function produkOptions() {
    let opts = '<option value="">-- Custom (bukan dari daftar) --</option>';
    PRODUK.forEach(p => {
        opts += `<option value="${p.id}" data-harga="${p.harga_jual}">${p.nama}</option>`;
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

function addItemRow() {
    const idx = itemIndex++;
    const div = document.createElement('div');
    div.className = 'item-row-form-walkin';
    div.innerHTML = `
        <div><select onchange="onProdukChange(this, ${idx})">${produkOptions()}</select></div>
        <div><input type="text" name="items[${idx}][nama_item]" placeholder="Nama item" required></div>
        <div><label class="mobile-label">Qty</label><input type="number" name="items[${idx}][qty]" value="1" min="1" onchange="hitungTotal()"></div>
        <div><label class="mobile-label">Harga</label><input type="number" name="items[${idx}][harga]" placeholder="Harga" onchange="hitungTotal()"></div>
        <div><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove(); hitungTotal();">✖</button></div>
        <input type="hidden" name="items[${idx}][produk_id]" value="">
    `;
    document.getElementById('items-container').appendChild(div);
}

function onProdukChange(select, idx) {
    const row = select.closest('.item-row-form-walkin');
    const opt = select.selectedOptions[0];
    const produkIdInput = row.querySelector(`input[name="items[${idx}][produk_id]"]`);
    const namaInput = row.querySelector(`input[name="items[${idx}][nama_item]"]`);
    const hargaInput = row.querySelector(`input[name="items[${idx}][harga]"]`);

    if (select.value) {
        produkIdInput.value = select.value;
        namaInput.value = opt.text;
        hargaInput.value = Math.round(parseFloat(opt.dataset.harga || 0));
    } else {
        produkIdInput.value = '';
        namaInput.value = '';
        hargaInput.value = '';
    }
    hitungTotal();
}

function hitungTotal() {
    let total = 0;
    document.querySelectorAll('#items-container .item-row-form-walkin').forEach(row => {
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
    
    // Populate select HTML directly with options
    let selectHtml = `<select name="bahan[${idx}][bahan_baku_id]" required>${bahanOptions()}</select>`;
    
    // Format to rounded integer
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
    document.querySelectorAll('#items-container .item-row-form-walkin').forEach(row => {
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

addItemRow();
</script>