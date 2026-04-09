-- Database: airbiru
-- Jalankan SQL ini di phpMyAdmin atau terminal MySQL Laragon

CREATE DATABASE IF NOT EXISTS airbiru CHARACTER SET utf8 COLLATE utf8_general_ci;
USE airbiru;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `no_hp` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('pelanggan','admin','driver') NOT NULL DEFAULT 'pelanggan',
  `tgl_daftar` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `negara` varchar(50) DEFAULT 'Indonesia',
  `provinsi` varchar(100) NOT NULL,
  `kota` varchar(100) NOT NULL,
  `kecamatan` varchar(100) NOT NULL,
  `rt_rw` varchar(20) NOT NULL,
  `kode_pos` varchar(10) NOT NULL,
  `alamat` text NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `produk` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `jadwal` varchar(100) DEFAULT 'Sekarang (1-2 jam)',
  `catatan` text DEFAULT NULL,
  `status` enum('Diproses','Disiapkan','Diantar','Selesai') NOT NULL DEFAULT 'Diproses',
  `tgl_pesan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_pesanan_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `laporan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `no_pesanan` varchar(20) DEFAULT NULL,
  `kategori` varchar(100) NOT NULL,
  `deskripsi` text NOT NULL,
  `status` enum('Masuk','Diproses','Selesai') NOT NULL DEFAULT 'Masuk',
  `tgl_laporan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_laporan_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- =============================================
-- SEED DATA: Admin & Driver default
-- Password: admin123 & driver123
-- =============================================
INSERT INTO `users` (`nama`, `email`, `no_hp`, `password`, `role`) VALUES
('Admin Air Biru', 'admin@airbiru.com', '081234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Driver Budi',    'driver@airbiru.com', '082345678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver')
ON DUPLICATE KEY UPDATE id=id;

-- =============================================
-- Password untuk seed di atas adalah: password
-- Ganti dengan password_hash("admin123") dll
-- Atau jalankan script PHP ini sekali:
-- =============================================
-- <?php echo password_hash('admin123', PASSWORD_DEFAULT); ?>
-- Lalu UPDATE users SET password='[hasil]' WHERE email='admin@airbiru.com';
