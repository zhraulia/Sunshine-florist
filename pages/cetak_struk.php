<?php
// pages/cetak_struk.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID transaksi tidak ditemukan.');
}

// Resolve penjualan_id if via=pesanan is passed
if (isset($_GET['via']) && $_GET['via'] === 'pesanan') {
    $stmt = $pdo->prepare("SELECT id FROM penjualan WHERE pesanan_id = ?");
    $stmt->execute([$id]);
    $penjualan_id = $stmt->fetchColumn();
    if (!$penjualan_id) {
        die('Transaksi penjualan untuk pesanan ini belum tercatat (pesanan harus dikonfirmasi diambil terlebih dahulu).');
    }
} else {
    $penjualan_id = $id;
}

// Data transaksi utama
$stmt = $pdo->prepare("
    SELECT pj.*, p.nama_pemesan, p.tanggal_jemput, p.jam_jemput, p.dp
    FROM penjualan pj
    LEFT JOIN pesanan p ON p.id = pj.pesanan_id
    WHERE pj.id = ?
");
$stmt->execute([$penjualan_id]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    die('Transaksi tidak ditemukan.');
}

// Item yang dibeli
$stmt = $pdo->prepare("SELECT * FROM detail_penjualan_item WHERE penjualan_id = ?");
$stmt->execute([$penjualan_id]);
$items = $stmt->fetchAll();

// Ambil nama toko dari pengaturan
$pengaturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();

$dp = $transaksi['dp'] ?? 0;
$sisa_bayar = max(0, $transaksi['total_harga'] - $dp);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk - Sunshine Florist</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #000;
            width: 80mm;              /* <-- ganti ke 58mm kalau printer kamu ukuran itu */
            margin: 0 auto;
            padding: 10px;
        }
        .center { text-align: center; }
        .toko-nama { font-size: 15px; font-weight: bold; }
        .toko-tagline { font-size: 10px; font-style: italic; margin-bottom: 6px; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; font-size: 12px; }
        td { padding: 2px 0; vertical-align: top; }
        .item-row td:first-child { width: 60%; }
        .item-row td:last-child { text-align: right; }
        .total-row td:last-child { text-align: right; font-weight: bold; }
        .footer-note { text-align: center; font-size: 11px; margin-top: 10px; }

        /* Custom buttons styling */
        .btn {
            display: inline-block;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
            user-select: none;
            border: 1.5px solid transparent;
            padding: 8px 16px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-primary {
            background-color: #D4A5AA;
            color: #fff;
            border-color: #D4A5AA;
        }
        .btn-primary:hover {
            background-color: #B76E79;
            border-color: #B76E79;
        }
        .btn-secondary {
            background-color: #FAF5F2;
            color: #8E7873;
            border-color: #EADCD9;
        }
        .btn-secondary:hover {
            background-color: #EADCD9;
        }

        .no-print {
            margin-top: 24px;
            text-align: center;
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        @media print {
            .no-print { display: none !important; }
            body { width: 80mm; background: #fff; }
            #struk-wrap { padding: 0 !important; }
        }
    </style>
    <!-- Include html2canvas for screenshot download feature -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</head>
<body style="background:#FAF5F2; padding: 20px 0;">
    <div id="struk-wrap" style="background:#ffffff; max-width:80mm; margin:0 auto; padding:15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-radius: 8px;">
        <div class="center">
            <div class="toko-nama"><?= htmlspecialchars($pengaturan['nama_toko'] ?? 'Sunshine Florist') ?></div>
            <?php if (!empty($pengaturan['tagline'])): ?>
                <div class="toko-tagline"><?= htmlspecialchars($pengaturan['tagline']) ?></div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <table>
            <tr>
                <td>No. Struk</td>
                <td style="text-align:right;">#<?= str_pad($transaksi['id'], 5, '0', STR_PAD_LEFT) ?></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td style="text-align:right;"><?= date('d/m/Y H:i', strtotime($transaksi['tanggal'])) ?></td>
            </tr>
            <?php if ($transaksi['sumber'] === 'pesanan'): ?>
            <tr>
                <td>Pemesan</td>
                <td style="text-align:right;"><?= htmlspecialchars($transaksi['nama_pemesan'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Jemput</td>
                <td style="text-align:right;">
                    <?= $transaksi['tanggal_jemput'] ? date('d/m/Y', strtotime($transaksi['tanggal_jemput'])) : '-' ?>
                    <?= $transaksi['jam_jemput'] ? date(' H:i', strtotime($transaksi['jam_jemput'])) : '' ?>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td>Jenis</td>
                <td style="text-align:right;">Walk-in</td>
            </tr>
            <?php if (!empty($transaksi['nama_pembeli'])): ?>
            <tr>
                <td>Pembeli</td>
                <td style="text-align:right;"><?= htmlspecialchars($transaksi['nama_pembeli']) ?></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>

        <div class="divider"></div>

        <table>
            <?php foreach ($items as $item): ?>
            <tr class="item-row">
                <td>
                    <?= htmlspecialchars($item['nama_item']) ?><br>
                    <span style="font-size:10px;"><?= (float)$item['qty'] ?> x Rp<?= number_format($item['harga'], 0, ',', '.') ?></span>
                </td>
                <td>Rp<?= number_format($item['qty'] * $item['harga'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="divider"></div>

        <table>
            <?php 
            $is_ongkir_toko = (($transaksi['ongkir_tipe'] ?? 'kurir') === 'toko' && (float)($transaksi['ongkir_nominal'] ?? 0) > 0);
            $total_bayar_all = (float)$transaksi['total_harga'];
            if ($is_ongkir_toko) {
                $total_bayar_all += (float)$transaksi['ongkir_nominal'];
            }
            $sisa_bayar_all = max(0, $total_bayar_all - $dp);
            ?>
            
            <?php if ($is_ongkir_toko): ?>
            <tr>
                <td>Total Produk</td>
                <td style="text-align:right;">Rp<?= number_format($transaksi['total_harga'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Ongkir Delivery</td>
                <td style="text-align:right;">Rp<?= number_format($transaksi['ongkir_nominal'], 0, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            
            <tr class="total-row">
                <td>Total Tagihan</td>
                <td>Rp<?= number_format($total_bayar_all, 0, ',', '.') ?></td>
            </tr>
            
            <?php if ($dp > 0): ?>
            <tr>
                <td>DP Dibayar</td>
                <td style="text-align:right;">Rp<?= number_format($dp, 0, ',', '.') ?></td>
            </tr>
            <tr class="total-row">
                <td>Sisa Pelunasan</td>
                <td>Rp<?= number_format($sisa_bayar_all, 0, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="divider"></div>

        <div class="footer-note">
            Terima kasih telah berbelanja<br>
            Sampai jumpa lagi!
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Cetak</button>
        <button id="download-btn" class="btn btn-secondary" style="color:var(--color-accent);">Download</button>
        <button onclick="tutupStruk()" class="btn btn-secondary">Tutup</button>
    </div>

    <script>
        function tutupStruk() {
            try {
                window.close();
            } catch (e) {}
            
            setTimeout(function() {
                if (document.referrer && document.referrer.includes(window.location.host) && !document.referrer.includes('cetak_struk')) {
                    window.location.href = document.referrer;
                } else {
                    window.location.href = 'jual_langsung.php';
                }
            }, 100);
        }

        // Download PNG click handler
        document.getElementById('download-btn').addEventListener('click', function () {
            const wrap = document.getElementById('struk-wrap');
            
            // Temporary styles for capturing
            const originalShadow = wrap.style.boxShadow;
            const originalBorder = wrap.style.borderRadius;
            
            // Remove shadow and border-radius for clean receipt output
            wrap.style.boxShadow = 'none';
            wrap.style.borderRadius = '0';
            
            html2canvas(wrap, {
                scale: 2, // High resolution
                backgroundColor: '#ffffff'
            }).then(canvas => {
                // Restore original styles
                wrap.style.boxShadow = originalShadow;
                wrap.style.borderRadius = originalBorder;
                
                // Trigger download
                const link = document.createElement('a');
                link.download = 'Struk_Sunshine_Florist_#' + String(<?= $transaksi['id'] ?>).padStart(5, '0') + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        });
    </script>