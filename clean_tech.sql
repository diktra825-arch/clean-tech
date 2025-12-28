-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 28 Des 2025 pada 11.45
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clean_tech`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `discounts`
--

CREATE TABLE `discounts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_orders` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `discounts`
--

INSERT INTO `discounts` (`id`, `name`, `description`, `discount_type`, `discount_value`, `min_orders`, `is_active`, `valid_from`, `valid_until`, `created_at`) VALUES
(1, 'Loyalty Discount', 'Diskon 30% setelah 5 kali pemesanan', 'percentage', 30.00, 5, 1, NULL, NULL, '2025-12-27 06:41:29'),
(2, 'New Customer', 'Diskon 15% untuk pelanggan baru', 'percentage', 15.00, 0, 1, NULL, NULL, '2025-12-27 06:41:29'),
(3, 'Monthly Subscription', 'Diskon 20% untuk langganan bulanan', 'percentage', 20.00, 0, 1, NULL, NULL, '2025-12-27 06:41:29'),
(5, 'Monthly Subscription', 'Diskon 20% untuk langganan bulanan', 'percentage', 20.00, 0, 1, NULL, NULL, '2025-12-27 10:21:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, NULL, 'Pesanan Baru', 'Pesanan baru dengan kode CT-251227-694F92494D424 telah dibuat', 0, '2025-12-27 08:01:13'),
(2, 2, 'Status Pesanan Diubah', 'Pesanan Anda telah dikonfirmasi', 0, '2025-12-27 08:02:40'),
(3, 2, 'Status Pesanan Diubah', 'Pesanan Anda telah selesai', 0, '2025-12-27 08:03:00'),
(4, NULL, 'Pesanan Baru', 'Pesanan baru dengan kode CT-251227-694F982E4A9F9 telah dibuat', 0, '2025-12-27 08:26:22'),
(5, 2, 'Pesanan Berhasil Dibuat', 'Pesanan #CT-251227-694F982E4A9F9 berhasil dibuat. Silakan lakukan pembayaran sesuai instruksi di halaman Pembayaran.', 0, '2025-12-27 08:26:22'),
(6, 2, 'Status Pembayaran Diupdate', 'Pembayaran untuk pesanan #CT-251227-694F92494D telah dibayar', 0, '2025-12-27 08:32:37'),
(7, NULL, 'Bukti Pembayaran Baru', 'Bukti pembayaran untuk pesanan #CT-251227-694F982E4A telah diupload', 0, '2025-12-27 09:25:53'),
(8, 2, 'Pembayaran Diverifikasi', 'Pembayaran untuk pesanan #CT-251227-694F982E4A telah diverifikasi. Pesanan akan segera diproses.', 0, '2025-12-27 09:45:02'),
(9, 2, 'Status Pesanan Diubah', 'Pesanan #CT-251227-694F982E4A telah dikonfirmasi', 0, '2025-12-27 12:21:31'),
(10, NULL, 'Pesanan Baru', 'Pesanan baru dengan kode CT-251227-694FD32699210 telah dibuat', 0, '2025-12-27 12:37:58'),
(11, 2, 'Pesanan Berhasil Dibuat', 'Pesanan #CT-251227-694FD32699210 berhasil dibuat. Silakan lakukan pembayaran sesuai instruksi di halaman Pembayaran. Anda mendapatkan diskon 20.00% (Monthly Subscription).', 0, '2025-12-27 12:37:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `order_time` time NOT NULL,
  `address` text NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','processing','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `discount_id` int(11) DEFAULT NULL,
  `is_monthly_subscription` tinyint(1) DEFAULT 0,
  `subscription_months` int(11) DEFAULT 1,
  `parent_order_id` int(11) DEFAULT NULL,
  `discount_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `user_id`, `service_id`, `order_date`, `order_time`, `address`, `total_price`, `discount`, `final_price`, `status`, `payment_status`, `payment_method`, `notes`, `created_at`, `payment_proof`, `payment_notes`, `payment_date`, `discount_type`, `discount_id`, `is_monthly_subscription`, `subscription_months`, `parent_order_id`, `discount_name`) VALUES
(1, 'CT-251227-694F92494D', 2, 1, '2025-12-29', '17:00:00', 'Tangerang', 250000.00, 0.00, 250000.00, 'completed', 'paid', 'transfer_bank', '', '2025-12-27 08:01:13', NULL, NULL, NULL, NULL, NULL, 0, 1, NULL, NULL),
(2, 'CT-251227-694F982E4A', 2, 6, '2025-12-30', '12:00:00', 'Tangerang', 200000.00, 0.00, 200000.00, 'confirmed', 'paid', 'dana', '', '2025-12-27 08:26:22', 'proof_CT-251227-694F982E4A_1766827553.jpg', '\n[VERIFIKASI] Pembayaran sudah dikonfirmasi dan diproses', '2025-12-27 09:25:53', NULL, NULL, 0, 1, NULL, NULL),
(3, 'CT-251227-694FD32699', 2, 6, '2026-01-01', '13:00:00', 'Tangerang', 200000.00, 40000.00, 160000.00, 'pending', 'pending', 'e_wallet', '', '2025-12-27 12:37:58', NULL, NULL, NULL, 'monthly', 3, 1, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_history`
--

CREATE TABLE `order_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `order_history`
--

INSERT INTO `order_history` (`id`, `order_id`, `status`, `notes`, `created_at`) VALUES
(1, 1, 'pending', 'Pesanan dibuat', '2025-12-27 08:01:13'),
(2, 1, 'confirmed', '', '2025-12-27 08:02:40'),
(3, 1, 'completed', '', '2025-12-27 08:03:00'),
(4, 2, 'pending', 'Pesanan dibuat', '2025-12-27 08:26:22'),
(5, 1, 'payment_updated', 'bukti sudah dikirim dan dikonfirmasi', '2025-12-27 08:32:37'),
(6, 2, 'payment_uploaded', 'Bukti pembayaran diupload', '2025-12-27 09:25:53'),
(7, 2, 'payment_verified', 'Pembayaran diverifikasi', '2025-12-27 09:45:02'),
(8, 2, 'confirmed', '', '2025-12-27 12:21:31'),
(9, 3, 'pending', 'Pesanan dibuat', '2025-12-27 12:37:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_hours` int(11) DEFAULT 1,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `duration_hours`, `image_url`, `is_active`, `created_at`) VALUES
(1, 'Home Cleaning Reguler', 'Pembersihan rumah lengkap termasuk ruang tamu, kamar tidur, dapur, dan kamar mandi', 250000.00, 3, NULL, 1, '2025-12-27 06:41:29'),
(2, 'Office Cleaning', 'Pembersihan kantor termasuk meja kerja, area umum, dan toilet kantor', 500000.00, 4, NULL, 1, '2025-12-27 06:41:29'),
(3, 'Deep Cleaning', 'Pembersihan menyeluruh termasuk perabotan, jendela, dan area tersembunyi', 750000.00, 6, NULL, 1, '2025-12-27 06:41:29'),
(4, 'After Renovation Cleaning', 'Pembersihan pasca renovasi untuk menghilangkan debu dan sisa material', 1000000.00, 8, NULL, 1, '2025-12-27 06:41:29'),
(5, 'Carpet Cleaning', 'Pembersihan dan perawatan karpet khusus', 300000.00, 2, NULL, 1, '2025-12-27 06:41:29'),
(6, 'Window Cleaning', 'Pembersihan kaca jendela dalam dan luar', 200000.00, 2, NULL, 1, '2025-12-27 06:41:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `address`, `role`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Administrator', 'admin@cleantech.id', '$2y$10$KK5R3v3sv3yq2hT/Sy8Y1epXCsU7lps4cwcOux4THyCgjg66o.0aq', '081234567890', NULL, 'admin', '2025-12-27 06:41:29', '2025-12-27 07:55:43', 1),
(2, 'Indika Saputra', 'indika3434@gmail.com', '$2y$10$8zChOcuEoGmip/kIq9ZbwOt73aFQ1X409lrb3eSdtACvC.7JQtg0G', '0878-1344-9078-3030', 'Tangerang', 'user', '2025-12-27 06:45:08', '2025-12-27 06:45:08', 1);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `discount_id` (`discount_id`),
  ADD KEY `parent_order_id` (`parent_order_id`);

--
-- Indeks untuk tabel `order_history`
--
ALTER TABLE `order_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indeks untuk tabel `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `order_history`
--
ALTER TABLE `order_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`parent_order_id`) REFERENCES `orders` (`id`);

--
-- Ketidakleluasaan untuk tabel `order_history`
--
ALTER TABLE `order_history`
  ADD CONSTRAINT `order_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
