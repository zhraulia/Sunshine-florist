<?php
// pages/riwayat_jual_bahan.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Riwayat Penjualan Eceran';
$sales_list = [];
$details_map = [];

try {
    // Fetch all retail sales
    $stmt = $pdo->prepare("
        SELECT id, nama_pembeli, total_harga, metode_pembayaran, catatan, tanggal
        FROM penjualan
        WHERE sumber = 'eceran'
        ORDER BY id DESC
    ");
    $stmt->execute();
    $sales_list = $stmt->fetchAll();

    // Fetch items detail map for all retail sales
    $stmt_det = $pdo->query("
        SELECT dpi.penjualan_id, dpi.nama_item, dpi.harga, dpi.qty, b.satuan_dasar
        FROM detail_penjualan_item dpi
        JOIN penjualan pj ON pj.id = dpi.penjualan_id
        LEFT JOIN bahan_baku b ON b.nama = REPLACE(dpi.nama_item, ' (Eceran)', '')
        WHERE pj.sumber = 'eceran'
    ");
    foreach ($stmt_det->fetchAll() as $d) {
        $details_map[$d['penjualan_id']][] = [
            'nama_item' => str_replace(' (Eceran)', '', $d['nama_item']),
            'harga' => (float)$d['harga'],
            'qty' => (float)$d['qty'],
            'satuan' => $d['satuan_dasar'] ?: 'unit'
        ];
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage() . "<br>Line: " . $e->getLine());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Penjualan Eceran - Sunshine Florist</title>
    <link rel="icon" href="../assets/images/logo-sunshine.png">
    <link rel="stylesheet" href="../assets/css/style_v2.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <div>
                    <h2 style="margin:0;">Riwayat Penjualan Eceran</h2>
                    <p style="margin:4px 0 0 0; color:var(--color-text-muted); font-size:13px;">
                        Daftar seluruh transaksi penjualan bahan eceran yang sudah selesai diproses.
                    </p>
                </div>
                <a href="jual_bahan.php" class="btn btn-secondary" style="font-weight:700;">← Kembali ke Input Eceran</a>
            </div>

            <!-- ===== TABEL DAFTAR TRANSAKSI ===== -->
            <div class="panel">
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; min-width:800px; font-size:13px; line-height:1.6;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--color-border);">
                                <th style="padding:10px 8px; width:120px;">No. Transaksi</th>
                                <th style="padding:10px 8px; width:150px;">Tanggal & Waktu</th>
                                <th style="padding:10px 8px;">Nama Pembeli</th>
                                <th style="padding:10px 8px; width:160px;">Opsi Pembayaran</th>
                                <th style="padding:10px 8px; width:140px; text-align:right;">Grand Total</th>
                                <th style="padding:10px 8px; width:120px; text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:20px; color:var(--color-text-muted);">Belum ada riwayat penjualan bahan eceran.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_list as $s): ?>
                                    <tr style="border-bottom:1px solid var(--color-border); font-family:'Plus Jakarta Sans', sans-serif;">
                                        <td style="padding:10px 8px; font-weight:700; color:var(--color-text-dark);">
                                            #<?= $s['id'] ?>
                                        </td>
                                        <td style="padding:10px 8px; color:var(--color-text-muted);">
                                            <?= date('d/m/Y - H:i', strtotime($s['tanggal'])) ?>
                                        </td>
                                        <td style="padding:10px 8px; font-weight:700; color:var(--color-text-dark);">
                                            <?= htmlspecialchars($s['nama_pembeli'] ?: 'Umum') ?>
                                        </td>
                                        <td style="padding:10px 8px;">
                                            <span class="badge badge-secondary" style="text-transform:uppercase; font-weight:700; font-size:10px; padding:3px 8px;">
                                                <?= htmlspecialchars($s['metode_pembayaran'] === 'qris' ? 'DANA' : $s['metode_pembayaran']) ?>
                                            </span>
                                        </td>
                                        <td style="padding:10px 8px; text-align:right; font-weight:700; color:var(--color-primary);">
                                            Rp<?= number_format($s['total_harga'], 0, ',', '.') ?>
                                        </td>
                                        <td style="padding:10px 8px; text-align:center;">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="showDetail(<?= $s['id'] ?>)" style="padding:4px 10px; font-size:11px; font-weight:700;">
                                                Lihat Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===== MODAL DETAIL RINCIAN ===== -->
            <div id="detailModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
                <div class="panel" style="width:100%; max-width:550px; margin:20px; position:relative; box-sizing:border-box;">
                    <h3 style="margin-top:0; margin-bottom:16px;">Rincian Transaksi <span id="modal-trx-title" style="color:var(--color-primary);"></span></h3>
                    
                    <div style="max-height:300px; overflow-y:auto; margin-bottom:20px;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px; line-height:1.5;">
                            <thead>
                                <tr style="border-bottom:2px solid var(--color-border); font-weight:700; color:var(--color-text-dark);">
                                    <th style="padding:8px 6px; text-align:left;">Nama Bahan</th>
                                    <th style="padding:8px 6px; text-align:right; width:90px;">Qty</th>
                                    <th style="padding:8px 6px; text-align:right; width:120px;">Harga Jual</th>
                                    <th style="padding:8px 6px; text-align:right; width:130px;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="modal-items-body">
                                <!-- Ditulis via JS -->
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex; justify-content:flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()" style="font-weight:700;">Tutup</button>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </main>
    </div>

    <script>
    const detailsMap = <?= json_encode($details_map) ?>;

    function showDetail(id) {
        const modal = document.getElementById('detailModal');
        const titleSpan = document.getElementById('modal-trx-title');
        const tbody = document.getElementById('modal-items-body');

        titleSpan.textContent = `#${id}`;
        tbody.innerHTML = '';

        const items = detailsMap[id] || [];
        if (items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:15px; color:var(--color-text-muted);">Tidak ada rincian item.</td></tr>`;
        } else {
            items.forEach(item => {
                const subtotal = item.qty * item.harga;
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid var(--color-border)';
                tr.innerHTML = `
                    <td style="padding:8px 6px; font-weight:700; color:var(--color-text-dark);">${escapeHtml(item.nama_item)}</td>
                    <td style="padding:8px 6px; text-align:right; color:var(--color-text-muted);">${item.qty} ${escapeHtml(item.satuan)}</td>
                    <td style="padding:8px 6px; text-align:right; color:var(--color-text-muted);">Rp${item.harga.toLocaleString('id-ID')}</td>
                    <td style="padding:8px 6px; text-align:right; font-weight:700; color:var(--color-primary);">Rp${subtotal.toLocaleString('id-ID')}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        modal.style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('detailModal').style.display = 'none';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
    }

    // Tutup modal jika klik di luar kotak modal panel
    window.onclick = function(event) {
        const modal = document.getElementById('detailModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>
