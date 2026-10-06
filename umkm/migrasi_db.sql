-- =====================================================================
-- Migrasi database db_toko  —  JALANKAN SEKALI (phpMyAdmin > tab SQL)
-- Hanya memakai tabel yang sudah ada. Perhitungan harga, total, dan
-- stok sekarang dilakukan di PHP (keranjang.php), bukan di trigger.
-- =====================================================================

-- 1) Hapus trigger lama pada tb_detail
DROP TRIGGER IF EXISTS `trg_detail_before_insert`;
DROP TRIGGER IF EXISTS `trg_detail_after_insert`;

-- 2) Hapus tabel bantu yang hanya dipakai trigger (aman walau belum ada)
DROP TABLE IF EXISTS `tb_diskon_kuantitas`;
DROP TABLE IF EXISTS `tb_aturan_bonus`;

-- 3) Kolom baru di tb_transaksi untuk pilihan pembayaran & pengiriman.
--    Kalau sebelumnya sudah menjalankan migrasi lama dan muncul error
--    "#1060 Duplicate column name", abaikan saja bagian ini.
ALTER TABLE `tb_transaksi`
  ADD COLUMN `metode_pembayaran` varchar(50) NOT NULL DEFAULT '-' AFTER `total_harga`,
  ADD COLUMN `metode_pengiriman` varchar(50) NOT NULL DEFAULT '-' AFTER `metode_pembayaran`,
  ADD COLUMN `ongkir` int NOT NULL DEFAULT 0 AFTER `metode_pengiriman`;

-- 4) (Opsional) Hapus transaksi "yatim" yang tidak punya rincian item,
--    sisa checkout lama yang gagal (id 7, 10, 11, 12, 13 pada dump Anda).
-- DELETE FROM `tb_transaksi`
--   WHERE `id_transakasi` NOT IN (SELECT DISTINCT `id_transaksi` FROM `tb_detail`);
