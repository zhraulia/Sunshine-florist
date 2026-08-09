<?php
// config/migrations.php
// Script migrasi dinamis otomatis (self-healing database)

if (isset($pdo)) {
    // 1. Buat Tabel kas_operasional
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kas_operasional (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                jenis ENUM('masuk', 'keluar') NOT NULL,
                kategori VARCHAR(100) NOT NULL,
                metode_pembayaran ENUM('cash', 'bri', 'seabank', 'dana') NOT NULL DEFAULT 'cash',
                nominal DECIMAL(12,2) NOT NULL DEFAULT 0,
                keterangan TEXT NULL,
                foto_nota VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    // 2. Tambah / Ubah Kolom ke Tabel penjualan
    try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE penjualan MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}

    // 3. Tambah / Ubah Kolom ke Tabel pesanan
    try { $pdo->exec("ALTER TABLE pesanan ADD COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pesanan MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}

    // 4. Tambah / Ubah Kolom ke Tabel riwayat_stok & kas_operasional
    try { $pdo->exec("ALTER TABLE riwayat_stok ADD COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE riwayat_stok MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE kas_operasional MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'qris', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE riwayat_stok ADD COLUMN biaya_ongkir DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE riwayat_stok ADD COLUMN status_po ENUM('lunas', 'pending_po', 'selesai_po') NOT NULL DEFAULT 'lunas'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE riwayat_stok ADD COLUMN total_biaya DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Exception $e) {}

    // 5. Migrasi data 'qris' menjadi 'dana' di seluruh database
    try { $pdo->exec("UPDATE kas_operasional SET metode_pembayaran = 'dana' WHERE metode_pembayaran = 'qris'"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE penjualan SET metode_pembayaran = 'dana' WHERE metode_pembayaran = 'qris'"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE pesanan SET metode_pembayaran = 'dana' WHERE metode_pembayaran = 'qris'"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE riwayat_stok SET metode_pembayaran = 'dana' WHERE metode_pembayaran = 'qris'"); } catch (Exception $e) {}

    // 6. Ubah enum final hapus 'qris'
    try { $pdo->exec("ALTER TABLE kas_operasional MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE penjualan MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pesanan MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE riwayat_stok MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'dana') NOT NULL DEFAULT 'cash'"); } catch (Exception $e) {}

    // 7. Update data lama jika NULL & sync metode_pembayaran dari pesanan ke penjualan
    try { $pdo->exec("UPDATE penjualan SET metode_pembayaran = 'cash' WHERE metode_pembayaran IS NULL"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE pesanan SET metode_pembayaran = 'cash' WHERE metode_pembayaran IS NULL"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE riwayat_stok SET metode_pembayaran = 'cash' WHERE metode_pembayaran IS NULL"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE riwayat_stok SET biaya_ongkir = 0 WHERE biaya_ongkir IS NULL"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE riwayat_stok SET status_po = 'lunas' WHERE status_po IS NULL"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE riwayat_stok SET total_biaya = 0 WHERE total_biaya IS NULL"); } catch (Exception $e) {}

    try {
        $pdo->exec("
            UPDATE penjualan pj
            JOIN pesanan p ON p.id = pj.pesanan_id
            SET pj.metode_pembayaran = p.metode_pembayaran
            WHERE p.metode_pembayaran IS NOT NULL AND p.metode_pembayaran != ''
        ");
    } catch (Exception $e) {}

    // 8. Buat tabel snapshot_persediaan
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS snapshot_persediaan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bulan VARCHAR(7) NOT NULL UNIQUE,
                nilai_persediaan DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    // 9. Tambah kolom biaya_operasional & biaya_jasa ke detail_penjualan_item
    try { $pdo->exec("ALTER TABLE detail_penjualan_item ADD COLUMN biaya_operasional DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE detail_penjualan_item ADD COLUMN biaya_jasa DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Exception $e) {}

    // 10. Sinkronkan biaya_operasional & biaya_jasa lama dari detail_pesanan ke detail_penjualan_item
    try {
        $pdo->exec("
            UPDATE detail_penjualan_item dpi
            JOIN penjualan pj ON pj.id = dpi.penjualan_id
            JOIN detail_pesanan dp ON dp.pesanan_id = pj.pesanan_id 
              AND (dp.produk_id = dpi.produk_id OR (dp.produk_id IS NULL AND dpi.produk_id IS NULL AND dp.nama_item = dpi.nama_item))
            SET dpi.biaya_operasional = dp.biaya_operasional, 
                dpi.biaya_jasa = dp.biaya_jasa
            WHERE pj.pesanan_id IS NOT NULL
        ");
    } catch (Exception $e) {}

    // 11. Tambah kolom ongkir delivery ke tabel pesanan & penjualan
    try { $pdo->exec("ALTER TABLE pesanan ADD COLUMN ongkir_tipe VARCHAR(20) NOT NULL DEFAULT 'kurir'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pesanan ADD COLUMN ongkir_nominal DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pesanan ADD COLUMN ongkir_metode_kurir ENUM('cash', 'bri', 'seabank', 'dana') NULL"); } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN ongkir_tipe VARCHAR(20) NOT NULL DEFAULT 'kurir'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN ongkir_nominal DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN ongkir_metode_kurir ENUM('cash', 'bri', 'seabank', 'dana') NULL"); } catch (Exception $e) {}

    // 12. Modifikasi kolom status_po di riwayat_stok untuk mendukung 'saldo_awal'
    try { $pdo->exec("ALTER TABLE riwayat_stok MODIFY COLUMN status_po ENUM('lunas', 'pending_po', 'selesai_po', 'saldo_awal') NOT NULL DEFAULT 'lunas'"); } catch (Exception $e) {}

    // 13. Ubah metode_pembayaran di riwayat_stok agar memperbolehkan NULL (untuk saldo awal)
    try { $pdo->exec("ALTER TABLE riwayat_stok MODIFY COLUMN metode_pembayaran ENUM('cash', 'bri', 'seabank', 'dana') NULL DEFAULT NULL"); } catch (Exception $e) {}

    // 14. Buat tabel pesanan_foto untuk mendukung banyak foto custom per pesanan
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pesanan_foto (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pesanan_id INT NOT NULL,
                foto_url VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    // Migrasikan foto_url lama dari tabel pesanan ke pesanan_foto jika belum dimigrasi
    try {
        $pdo->exec("
            INSERT INTO pesanan_foto (pesanan_id, foto_url)
            SELECT id, foto_url FROM pesanan 
            WHERE foto_url IS NOT NULL AND foto_url != ''
              AND id NOT IN (SELECT DISTINCT pesanan_id FROM pesanan_foto)
        ");
    } catch (Exception $e) {}
}
