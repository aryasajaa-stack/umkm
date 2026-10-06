-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 06, 2026 at 02:11 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_toko`
--

-- --------------------------------------------------------

--
-- Table structure for table `tb_detail`
--

CREATE TABLE `tb_detail` (
  `id_detail` int NOT NULL,
  `id_transaksi` int NOT NULL,
  `id_produk` int NOT NULL,
  `jumlah` int NOT NULL,
  `harga_satuan` int NOT NULL DEFAULT '0',
  `diskon_persen` int NOT NULL DEFAULT '0',
  `subtotal` int NOT NULL DEFAULT '0',
  `is_bonus` tinyint(1) NOT NULL DEFAULT '0',
  `jumlah_bonus` int NOT NULL DEFAULT '0',
  `id_produk_bonus` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_detail`
--

INSERT INTO `tb_detail` (`id_detail`, `id_transaksi`, `id_produk`, `jumlah`, `harga_satuan`, `diskon_persen`, `subtotal`, `is_bonus`, `jumlah_bonus`, `id_produk_bonus`) VALUES
(1, 1, 6, 1, 231000, 0, 231000, 0, 0, NULL),
(2, 1, 7, 1, 1231000, 0, 1231000, 0, 0, NULL),
(8, 14, 6, 1, 231000, 0, 231000, 0, 0, NULL);

--
-- Triggers `tb_detail`
--
DELIMITER $$
CREATE TRIGGER `trg_detail_bonus_after` AFTER INSERT ON `tb_detail` FOR EACH ROW BEGIN
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
DELIMITER $$
CREATE TRIGGER `trg_detail_bonus_before` BEFORE INSERT ON `tb_detail` FOR EACH ROW BEGIN
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
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tb_kategori`
--

CREATE TABLE `tb_kategori` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tb_kategori`
--

INSERT INTO `tb_kategori` (`id_kategori`, `nama_kategori`) VALUES
(1, 'Makanan'),
(2, 'Minuman');

-- --------------------------------------------------------

--
-- Table structure for table `tb_produk`
--

CREATE TABLE `tb_produk` (
  `id` int NOT NULL,
  `nama` varchar(250) COLLATE utf8mb4_general_ci NOT NULL,
  `harga` int NOT NULL,
  `stok` int NOT NULL,
  `foto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `id_kategori` int NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci NOT NULL,
  `bonus_minimal` int NOT NULL DEFAULT '0',
  `bonus_jumlah` int NOT NULL DEFAULT '0',
  `bonus_id_produk` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_produk`
--

INSERT INTO `tb_produk` (`id`, `nama`, `harga`, `stok`, `foto`, `id_kategori`, `deskripsi`, `bonus_minimal`, `bonus_jumlah`, `bonus_id_produk`) VALUES
(6, 'Iphone 5', 231, 131, 'SnapTik.Net_7518348278111472927_9.jpeg', 2, '', 0, 0, NULL),
(7, 'ayam', 1231, 12313, 'wallpaper', 1, '', 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tb_transaksi`
--

CREATE TABLE `tb_transaksi` (
  `id_transakasi` int NOT NULL,
  `id_pelanggan` int NOT NULL,
  `tanggal` date NOT NULL,
  `total_harga` int NOT NULL,
  `metode_pembayaran` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '-',
  `metode_pengiriman` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '-',
  `ongkir` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_transaksi`
--

INSERT INTO `tb_transaksi` (`id_transakasi`, `id_pelanggan`, `tanggal`, `total_harga`, `metode_pembayaran`, `metode_pengiriman`, `ongkir`) VALUES
(1, 6, '2026-09-23', 1462000, '-', '-', 0),
(7, 7, '2026-09-24', 1462000, '-', '-', 0),
(10, 8, '2026-09-25', 231000, '-', '-', 0),
(11, 8, '2026-09-25', 1231000, '-', '-', 0),
(12, 8, '2026-09-25', 1231000, '-', '-', 0),
(13, 8, '2026-09-25', 1231000, '-', '-', 0),
(14, 8, '2026-10-01', 231000, 'COD (Bayar di Tempat)', 'Kurir Toko', 10000);

-- --------------------------------------------------------

--
-- Table structure for table `tb_user`
--

CREATE TABLE `tb_user` (
  `id` int NOT NULL,
  `nama` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `hp` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('pelanggan','admin') COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_user`
--

INSERT INTO `tb_user` (`id`, `nama`, `email`, `username`, `password`, `hp`, `alamat`, `role`) VALUES
(1, 'admin', 'admin@gmail.com', 'admin', '$2y$10$7eHijuvCA20iUAoY38q6I.8aesTpfKnUcOUMEPYVQagz3eXUHdxqG', '496253264', 'gasgsargsarg', 'admin'),
(6, 'ak', 'apaaja@gmail.com', 'bagas', 'reset123', '1231312312', 'adasdasdasda', 'pelanggan'),
(7, NULL, NULL, 'akmal', '12345', NULL, NULL, 'pelanggan'),
(8, 'rakha', 'apaaja@gmail.com', 'rakha', '12345', '1231312312', 'adadasdasda', 'pelanggan');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tb_detail`
--
ALTER TABLE `tb_detail`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `tb_kategori`
--
ALTER TABLE `tb_kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `tb_produk`
--
ALTER TABLE `tb_produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `tb_transaksi`
--
ALTER TABLE `tb_transaksi`
  ADD PRIMARY KEY (`id_transakasi`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indexes for table `tb_user`
--
ALTER TABLE `tb_user`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tb_detail`
--
ALTER TABLE `tb_detail`
  MODIFY `id_detail` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tb_kategori`
--
ALTER TABLE `tb_kategori`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tb_produk`
--
ALTER TABLE `tb_produk`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tb_transaksi`
--
ALTER TABLE `tb_transaksi`
  MODIFY `id_transakasi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tb_user`
--
ALTER TABLE `tb_user`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tb_detail`
--
ALTER TABLE `tb_detail`
  ADD CONSTRAINT `tb_detail_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `tb_transaksi` (`id_transakasi`),
  ADD CONSTRAINT `tb_detail_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `tb_produk` (`id`);

--
-- Constraints for table `tb_transaksi`
--
ALTER TABLE `tb_transaksi`
  ADD CONSTRAINT `tb_transaksi_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `tb_user` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
