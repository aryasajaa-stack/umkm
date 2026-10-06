-- =====================================================================
-- Migrasi BONUS — JALANKAN SEKALI (phpMyAdmin > tab SQL)
-- Syarat: migrasi_db.sql sebelumnya sudah dijalankan.
--
-- TANPA TABEL BARU. Aturan bonus disimpan di kolom tb_produk, hasil
-- bonus dicatat di kolom tb_detail.
--
-- Kenapa bonus dicatat di kolom, bukan baris baru di tb_detail?
-- MySQL melarang trigger di tb_detail meng-INSERT ke tb_detail sendiri
-- (error #1442). Jadi bonus disimpan sebagai jumlah_bonus pada baris
-- pembelian yang sama.
--
-- Pembagian tugas:
--   PHP     : harga satuan, subtotal, total transaksi (tidak berubah)
--   TRIGGER : cek stok, hitung bonus, kurangi stok (beli + bonus)
-- =====================================================================

-- 1) Aturan bonus per produk (di tb_produk)
--    bonus_minimal   : minimal jumlah beli supaya dapat bonus (0 = tanpa bonus)
--    bonus_jumlah    : jumlah unit bonus yang didapat
--    bonus_id_produk : produk yang diberikan sebagai bonus (NULL = produk yang sama)
--    Kalau muncul "#1060 Duplicate column name", bagian ini sudah pernah dijalankan: abaikan.
ALTER TABLE `tb_produk`
  ADD COLUMN `bonus_minimal`   int NOT NULL DEFAULT 0 AFTER `deskripsi`,
  ADD COLUMN `bonus_jumlah`    int NOT NULL DEFAULT 0 AFTER `bonus_minimal`,
  ADD COLUMN `bonus_id_produk` int NULL DEFAULT NULL AFTER `bonus_jumlah`;

-- 2) Hasil bonus per baris pembelian (di tb_detail)
ALTER TABLE `tb_detail`
  ADD COLUMN `jumlah_bonus`    int NOT NULL DEFAULT 0 AFTER `is_bonus`,
  ADD COLUMN `id_produk_bonus` int NULL DEFAULT NULL AFTER `jumlah_bonus`;

-- 3) Bersihkan trigger lama (bila masih ada) lalu pasang trigger baru
DROP TRIGGER IF EXISTS `trg_detail_before_insert`;
DROP TRIGGER IF EXISTS `trg_detail_after_insert`;
DROP TRIGGER IF EXISTS `trg_detail_bonus_before`;
DROP TRIGGER IF EXISTS `trg_detail_bonus_after`;

DELIMITER $$

CREATE TRIGGER `trg_detail_bonus_before` BEFORE INSERT ON `tb_detail`
FOR EACH ROW
BEGIN
  DECLARE v_stok_beli  INT DEFAULT NULL;
  DECLARE v_min        INT DEFAULT 0;
  DECLARE v_jml_bonus  INT DEFAULT 0;
  DECLARE v_id_bonus   INT DEFAULT NULL;
  DECLARE v_stok_bonus INT DEFAULT 0;

  -- Kunci baris produk yang dibeli, ambil stok & aturan bonusnya
  SELECT `stok`, `bonus_minimal`, `bonus_jumlah`, COALESCE(`bonus_id_produk`, `id`)
    INTO v_stok_beli, v_min, v_jml_bonus, v_id_bonus
    FROM `tb_produk`
    WHERE `id` = NEW.`id_produk`
    FOR UPDATE;

  IF v_stok_beli IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produk tidak ditemukan.';
  END IF;

  IF v_stok_beli < NEW.`jumlah` THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok produk tidak mencukupi.';
  END IF;

  -- Nilai bonus selalu dihitung di sini; apa pun yang dikirim aplikasi ditimpa
  SET NEW.`jumlah_bonus`    = 0;
  SET NEW.`id_produk_bonus` = NULL;

  IF v_min > 0 AND v_jml_bonus > 0 AND NEW.`jumlah` >= v_min THEN

    IF v_id_bonus = NEW.`id_produk` THEN
      -- Bonus produk yang sama: sisa stok setelah dikurangi jumlah yang dibeli
      SET v_stok_bonus = v_stok_beli - NEW.`jumlah`;
    ELSE
      SELECT `stok` INTO v_stok_bonus
        FROM `tb_produk`
        WHERE `id` = v_id_bonus
        FOR UPDATE;
    END IF;

    -- Bonus tidak pernah melebihi stok yang tersisa (diberikan sebanyak yang ada)
    SET NEW.`jumlah_bonus` = LEAST(v_jml_bonus, GREATEST(IFNULL(v_stok_bonus, 0), 0));

    IF NEW.`jumlah_bonus` > 0 THEN
      SET NEW.`id_produk_bonus` = v_id_bonus;
    END IF;
  END IF;
END
$$

CREATE TRIGGER `trg_detail_bonus_after` AFTER INSERT ON `tb_detail`
FOR EACH ROW
BEGIN
  -- Kurangi stok produk yang dibeli
  UPDATE `tb_produk`
    SET `stok` = `stok` - NEW.`jumlah`
    WHERE `id` = NEW.`id_produk`;

  -- Kurangi stok produk bonus (bonus tetap diambil dari stok gudang)
  IF NEW.`jumlah_bonus` > 0 AND NEW.`id_produk_bonus` IS NOT NULL THEN
    UPDATE `tb_produk`
      SET `stok` = `stok` - NEW.`jumlah_bonus`
      WHERE `id` = NEW.`id_produk_bonus`;
  END IF;
END
$$

DELIMITER ;

-- 4) CONTOH mengisi aturan (opsional, bisa juga lewat Dashboard > Edit produk)
--    "Beli minimal 5 'ayam' (id 7), gratis 1 'ayam'":
-- UPDATE `tb_produk` SET `bonus_minimal` = 5, `bonus_jumlah` = 1 WHERE `id` = 7;
--    "Beli minimal 3 'Iphone 5' (id 6), gratis 2 'ayam' (id 7)":
-- UPDATE `tb_produk` SET `bonus_minimal` = 3, `bonus_jumlah` = 2, `bonus_id_produk` = 7 WHERE `id` = 6;
