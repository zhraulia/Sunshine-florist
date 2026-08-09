<?php
// pages/jual_bahan.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Penjualan Bahan Eceran';

// Ambil list bahan baku
$bahan_list = $pdo->query("SELECT id, nama, harga_beli, stok_fisik, stok_dicadangkan, satuan_dasar FROM bahan_baku ORDER BY nama ASC")->fetchAll();



$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan Bahan Eceran - Sunshine Florist</title>
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
                <a href="jual_langsung.php" class="btn btn-secondary" style="font-weight:700; padding:8px 18px; font-size:13px;">Penjualan Buket</a>
                <a href="jual_bahan.php" class="btn btn-primary" style="font-weight:700; padding:8px 18px; font-size:13px;">Penjualan Bahan Eceran</a>
            </div>

            <p style="margin-top:0; color:var(--color-text-muted); font-size:13px;">
                Buat transaksi penjualan bahan baku secara eceran — stok langsung terpotong & tercatat sebagai pendapatan serta HPP bahan eceran terjual begitu disimpan.
            </p>

            <form action="../actions/simpan_jual_bahan.php" method="POST" id="form-jual-bahan">

                <!-- ===== INFO PEMBELI & PEMBAYARAN ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Info Pembeli & Pembayaran</h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">

                        <div class="form-group">
                            <label>Nama Pembeli (opsional)</label>
                            <input type="text" name="nama_pembeli" placeholder="Boleh dikosongkan">
                        </div>
                        <div class="form-group">
                            <label>Opsi Pembayaran</label>
                            <select name="metode_pembayaran" required>
                                <option value="cash">Cash (Tunai)</option>
                                <option value="bri">Bank BRI</option>
                                <option value="seabank">SeaBank</option>
                                <option value="dana">DANA</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ===== TABEL BARIS BAHAN ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Bahan Baku Terjual</h3>
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; min-width:600px;">
                            <thead>
                                <tr>
                                    <th>Pilih Nama Bahan</th>
                                    <th style="width:120px;">Sisa Stok</th>
                                    <th style="width:140px;">Harga Modal</th>
                                    <th style="width:100px;">Qty</th>
                                    <th style="width:140px;">Harga Jual</th>
                                    <th style="width:140px; text-align:right;">Total Jual</th>
                                    <th style="width:60px; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="items-container">
                                <!-- Baris ditambahkan secara dinamis via JS -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top:14px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow()">+ Tambah Baris Bahan</button>
                    </div>

                    <!-- ===== RINGKASAN BIAYA ===== -->
                    <div style="margin-top:24px; display:flex; flex-direction:column; align-items:flex-end; gap:8px; border-top:1px dashed var(--color-border); padding-top:16px;">
                        <div style="font-size:13px; color:var(--color-text-muted);">
                            Total Harga Modal: <strong id="total-modal-display">Rp0</strong>
                        </div>
                        <div style="font-size:16px; color:var(--color-text-dark); font-weight:700;">
                            GRAND TOTAL: <span style="color:var(--color-primary);" id="grand-total-display">Rp0</span>
                        </div>
                    </div>
                </div>

                <!-- ===== CATATAN PEMBELI ===== -->
                <div class="panel" style="margin-bottom:20px;">
                    <h3>Catatan Pembeli</h3>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Catatan Tambahan (opsional)</label>
                        <input type="text" name="catatan" placeholder="Contoh: minta bahan yang masih kuncup, jangan yang mekar">
                    </div>
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="submit" class="btn btn-primary">Simpan & Proses</button>
                    <a href="jual_langsung.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </main>
    </div>

    <script>
    let rowIndex = 0;
    const bahanList = <?= json_encode($bahan_list) ?>;

    function addItemRow() {
        const tbody = document.getElementById('items-container');
        const tr = document.createElement('tr');
        tr.id = `row-${rowIndex}`;

        let options = '<option value="">-- Pilih Bahan --</option>';
        bahanList.forEach(b => {
            const stokTersedia = b.stok_fisik - b.stok_dicadangkan;
            options += `<option value="${b.id}" data-stok="${stokTersedia}" data-modal="${b.harga_beli}" data-satuan="${b.satuan_dasar}">${b.nama}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][bahan_baku_id]" onchange="onMaterialChange(this)" required style="width:100%;">
                    ${options}
                </select>
            </td>
            <td>
                <span class="sisa-stok-text">-</span>
            </td>
            <td>
                <span class="harga-modal-text">Rp0</span>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowIndex}][qty]" class="qty-input" oninput="calculateRow(this)" required min="0.01" style="width:100%;">
            </td>
            <td>
                <input type="number" step="1" name="items[${rowIndex}][harga_jual]" class="harga-jual-input" oninput="calculateRow(this)" required min="0" placeholder="Rp" style="width:100%;">
            </td>
            <td style="text-align:right; font-weight:700; color:var(--color-primary);">
                <span class="total-jual-text">Rp0</span>
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" style="background:#A43B22; border-color:#A43B22; padding:4px 8px; font-size:11px;">Hapus</button>
            </td>
        `;

        tbody.appendChild(tr);
        rowIndex++;
    }

    function removeRow(btn) {
        const row = btn.closest('tr');
        row.remove();
        calculateGrandTotal();
    }

    function onMaterialChange(select) {
        const row = select.closest('tr');
        const selectedOpt = select.options[select.selectedIndex];
        
        const sisaStokText = row.querySelector('.sisa-stok-text');
        const hargaModalText = row.querySelector('.harga-modal-text');

        if (selectedOpt && selectedOpt.value) {
            const stok = parseFloat(selectedOpt.getAttribute('data-stok') || 0);
            const modal = parseFloat(selectedOpt.getAttribute('data-modal') || 0);
            const satuan = selectedOpt.getAttribute('data-satuan');

            sisaStokText.textContent = `${stok} ${satuan}`;
            hargaModalText.textContent = `Rp${modal.toLocaleString('id-ID')}`;
        } else {
            sisaStokText.textContent = '-';
            hargaModalText.textContent = 'Rp0';
        }

        calculateRow(select);
    }

    function calculateRow(element) {
        const row = element.closest('tr');
        const select = row.querySelector('select');
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const hargaJual = parseFloat(row.querySelector('.harga-jual-input').value) || 0;
        const totalJualText = row.querySelector('.total-jual-text');

        const totalJual = qty * hargaJual;
        totalJualText.textContent = `Rp${totalJual.toLocaleString('id-ID')}`;

        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let totalModal = 0;
        let grandTotal = 0;

        const rows = document.querySelectorAll('#items-container tr');
        rows.forEach(row => {
            const select = row.querySelector('select');
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const hargaJual = parseFloat(row.querySelector('.harga-jual-input').value) || 0;

            const selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                const modal = parseFloat(selectedOpt.getAttribute('data-modal') || 0);
                totalModal += qty * modal;
            }

            grandTotal += qty * hargaJual;
        });

        document.getElementById('total-modal-display').textContent = `Rp${totalModal.toLocaleString('id-ID')}`;
        document.getElementById('grand-total-display').textContent = `Rp${grandTotal.toLocaleString('id-ID')}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Tambahkan satu baris di awal secara default
        addItemRow();

        // Validasi form sebelum submit (cek stok)
        const form = document.getElementById('form-jual-bahan');
        form.addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('#items-container tr');
            if (rows.length === 0) {
                alert('Silakan tambah minimal 1 bahan sebelum menyimpan.');
                e.preventDefault();
                return;
            }

            let valid = true;
            rows.forEach(row => {
                const select = row.querySelector('select');
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                
                const selectedOpt = select.options[select.selectedIndex];
                if (selectedOpt && selectedOpt.value) {
                    const stok = parseFloat(selectedOpt.getAttribute('data-stok') || 0);
                    if (qty > stok) {
                        const namaBahan = selectedOpt.textContent;
                        alert(`Stok bahan "${namaBahan}" tidak cukup. Dibutuhkan: ${qty}, Tersedia: ${stok}`);
                        valid = false;
                    }
                }
            });

            if (!valid) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>
