-- Skema database aplikasi Toko Kelontong
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS kelontong CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kelontong;

DROP TABLE IF EXISTS transaksi_detail;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS stok_mutasi;
DROP TABLE IF EXISTS produk;
DROP TABLE IF EXISTS kategori;

CREATE TABLE kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(50) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  harga DECIMAL(12,2) NOT NULL DEFAULT 0,
  satuan VARCHAR(30) NOT NULL DEFAULT 'pcs',
  kategori_id INT NULL,
  stok INT NOT NULL DEFAULT 0,
  stok_minimum INT NOT NULL DEFAULT 5,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_produk_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stok_mutasi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produk_id INT NOT NULL,
  tipe ENUM('masuk','keluar') NOT NULL,
  jumlah INT NOT NULL,
  keterangan VARCHAR(255) NULL,
  tanggal DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mutasi_produk FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transaksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_transaksi VARCHAR(50) NOT NULL UNIQUE,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  tanggal DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transaksi_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT NOT NULL,
  produk_id INT NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  qty INT NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_detail_transaksi FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
  CONSTRAINT fk_detail_produk FOREIGN KEY (produk_id) REFERENCES produk(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data awal
INSERT INTO kategori (nama) VALUES ('Makanan'), ('Minuman'), ('Sembako'), ('Kebersihan');

INSERT INTO produk (kode, nama, harga, satuan, kategori_id, stok, stok_minimum) VALUES
('P001', 'Mie Instan Goreng', 3500.00, 'bungkus', 1, 40, 10),
('P002', 'Biskuit Cokelat', 8000.00, 'pcs', 1, 15, 5),
('P003', 'Air Mineral 600ml', 4000.00, 'botol', 2, 50, 12),
('P004', 'Teh Kotak 200ml', 5000.00, 'pcs', 2, 8, 10),
('P005', 'Beras Premium 5kg', 68000.00, 'karung', 3, 12, 3),
('P006', 'Gula Pasir 1kg', 15000.00, 'pcs', 3, 20, 5),
('P007', 'Sabun Cuci Piring', 9000.00, 'botol', 4, 6, 8);


INSERT INTO stok_mutasi (produk_id, tipe, jumlah, keterangan, tanggal) VALUES
(1, 'masuk', 40, 'Stok awal', CURDATE()),
(2, 'masuk', 15, 'Stok awal', CURDATE()),
(3, 'masuk', 50, 'Stok awal', CURDATE()),
(4, 'masuk', 8, 'Stok awal', CURDATE()),
(5, 'masuk', 12, 'Stok awal', CURDATE()),
(6, 'masuk', 20, 'Stok awal', CURDATE()),
(7, 'masuk', 6, 'Stok awal', CURDATE());

SET FOREIGN_KEY_CHECKS = 1;
