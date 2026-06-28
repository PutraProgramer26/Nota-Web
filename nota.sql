-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 28, 2026 at 07:37 AM
-- Server version: 10.4.25-MariaDB
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nota_web`
--

-- --------------------------------------------------------

--
-- Table structure for table `nota`
--

CREATE TABLE `nota` (
  `id` int(11) NOT NULL,
  `no_register` varchar(100) NOT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `harga_barang` decimal(15,2) NOT NULL,
  `jumlah_barang` int(11) NOT NULL,
  `satuan_barang` varchar(50) NOT NULL,
  `total_harga` decimal(15,2) GENERATED ALWAYS AS (`harga_barang` * `jumlah_barang`) STORED,
  `project` varchar(255) NOT NULL,
  `pemesan` varchar(255) NOT NULL,
  `nama_toko` varchar(255) NOT NULL,
  `tanggal_belanja` date NOT NULL,
  `keterangan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `nota`
--

INSERT INTO `nota` (`id`, `no_register`, `nama_barang`, `harga_barang`, `jumlah_barang`, `satuan_barang`, `project`, `pemesan`, `nama_toko`, `tanggal_belanja`, `keterangan`) VALUES
(1, 'BO132', 'Besi Plat', '100000.00', 5, 'lembar', 'Bus Office', 'Test User', 'Test Store', '2026-06-27', 'invoice'),
(2, 'BO132', 'Conector Male DKJ 35-50mm Hitam', '10000.00', 10, 'pcs', 'Bus Office', 'Test User', 'Test Store', '2026-06-27', 'invoice'),
(3, 'M570', 'Dinabolt 16x111 Wilson', '10000.00', 10, 'Pcs', 'MSR', 'Test User', 'Cahaya Timika', '2026-06-27', 'invoice'),
(4, 'M570', 'Gembok 60mm', '60000.00', 3, 'Pcs', 'MSR', 'Test User', 'Cahaya Timika', '2026-06-27', 'Cash'),
(5, 'M570', 'Conector Male DKJ 35-50mm Hitam', '15000.00', 25, 'pcs', 'MSR', 'Test User', 'Cahaya Timika', '2026-06-27', 'Cash'),
(6, 'SQ050', 'Plat Besi 3mm', '100000.00', 10, 'lembar', 'SQ Prambanan', 'Test User', 'Cahaya Timika', '2026-06-27', 'invoice'),
(7, 'SQ051', 'Roda PU 5\" merah mati Roha 220kg', '225000.00', 5, 'Pcs', 'SQ Prambanan', 'Test User', 'Cahaya Timika', '2026-06-26', 'invoice');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `nota`
--
ALTER TABLE `nota`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `nota`
--
ALTER TABLE `nota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
