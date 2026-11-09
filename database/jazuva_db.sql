-- ========================================================
-- DATABASE: jazuva_db
-- JAZUVA - Restaurant & Coffee Shop Management System
-- ========================================================

CREATE DATABASE IF NOT EXISTS `jazuva_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jazuva_db`;

-- SET FOREIGN KEY CHECKS
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Table: roles
-- --------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Table: users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NULL,
  `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Table: categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('makanan', 'minuman', 'dessert', 'snack') NOT NULL,
  `target_station` ENUM('kitchen', 'barista') NOT NULL DEFAULT 'kitchen',
  `icon` VARCHAR(50) DEFAULT 'utensils',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Table: menus
-- --------------------------------------------------------
DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `image` VARCHAR(255) NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `is_favorite` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table: restaurant_tables
-- --------------------------------------------------------
DROP TABLE IF EXISTS `restaurant_tables`;
CREATE TABLE `restaurant_tables` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_number` VARCHAR(10) NOT NULL UNIQUE,
  `capacity` INT NOT NULL DEFAULT 4,
  `status` ENUM('tersedia', 'terisi', 'dipesan', 'menunggu_pembayaran') DEFAULT 'tersedia',
  `pos_x` INT DEFAULT 1,
  `pos_y` INT DEFAULT 1,
  `qr_code_file` VARCHAR(255) NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Table: table_sessions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `table_sessions`;
CREATE TABLE `table_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_id` INT NOT NULL,
  `session_token` VARCHAR(64) NOT NULL UNIQUE,
  `customer_name` VARCHAR(100) DEFAULT 'Tamu',
  `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
  `start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `end_time` TIMESTAMP NULL,
  `total_amount` DECIMAL(12, 2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Table: orders
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `table_id` INT NOT NULL,
  `session_id` INT NULL,
  `customer_name` VARCHAR(100) DEFAULT 'Pelanggan Meja',
  `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `final_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `request_token` CHAR(36) NULL UNIQUE,
  `order_status` ENUM('menunggu', 'diproses', 'siap', 'selesai', 'dibatalkan') DEFAULT 'menunggu',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `table_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Table: order_items
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `menu_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12, 2) NOT NULL,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  `notes` VARCHAR(255) NULL,
  `station` ENUM('kitchen', 'barista') NOT NULL,
  `item_status` ENUM('menunggu', 'diproses', 'siap', 'selesai', 'dibatalkan') DEFAULT 'menunggu',
  `stock_deducted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. Table: payments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NULL,
  `session_id` INT NULL,
  `transaction_number` VARCHAR(50) NOT NULL UNIQUE,
  `payment_method` ENUM('tunai', 'qris') NOT NULL DEFAULT 'tunai',
  `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `service_charge_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `midtrans_transaction_id` VARCHAR(100) NULL,
  `qris_qr_url` TEXT NULL,
  `total_bill` DECIMAL(12, 2) NOT NULL,
  `amount_paid` DECIMAL(12, 2) NOT NULL,
  `change_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('menunggu', 'lunas', 'batal') DEFAULT 'lunas',
  `cashier_id` INT NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`session_id`) REFERENCES `table_sessions` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 10. Table: ingredients (Bahan Baku)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ingredients`;
CREATE TABLE `ingredients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `category` ENUM('makanan', 'minuman', 'bumbu', 'packaging', 'lainnya') DEFAULT 'makanan',
  `stock` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
  `min_stock` DECIMAL(12, 4) NOT NULL DEFAULT 5.0000,
  `cost_per_unit` DECIMAL(12, 2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 11. Table: recipes
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recipes`;
CREATE TABLE `recipes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `menu_id` INT NOT NULL UNIQUE,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 12. Table: recipe_details
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recipe_details`;
CREATE TABLE `recipe_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipe_id` INT NOT NULL,
  `ingredient_id` INT NOT NULL,
  `amount_required` DECIMAL(12, 4) NOT NULL,
  `unit` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 13. Table: stock_movements
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ingredient_id` INT NOT NULL,
  `movement_type` ENUM('masuk', 'keluar', 'penyesuaian', 'pesanan', 'pembatalan') NOT NULL,
  `quantity` DECIMAL(12, 4) NOT NULL,
  `previous_stock` DECIMAL(12, 4) NOT NULL,
  `current_stock` DECIMAL(12, 4) NOT NULL,
  `reference_id` INT NULL,
  `reference_type` VARCHAR(50) NULL,
  `notes` VARCHAR(255) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 14. Table: settings
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- SEED INITIAL DATA
-- ========================================================

-- Roles
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Akses penuh ke seluruh sistem'),
(2, 'cashier', 'Kasir (POS)', 'Mengelola pesanan meja, pembayaran, dan cetak struk'),
(3, 'kitchen', 'Kitchen (Dapur)', 'Memproses pesanan makanan dan snack'),
(4, 'barista', 'Barista', 'Memproses pesanan kopi dan aneka minuman'),
(5, 'inventory', 'Staff Persediaan', 'Mengelola stok bahan baku dan resep');

-- Users (Default password for all accounts: admin123)
-- Hash generated using password_hash('admin123', PASSWORD_BCRYPT)
-- $2y$10$w09OslD34jXjQk8N63oB.uCjHqFwB.eH8RfgwVv9R.j6YFjCqA7lG -> will generate fresh or use verified hash
INSERT INTO `users` (`id`, `role_id`, `username`, `password`, `full_name`, `email`, `phone`, `status`) VALUES
(1, 1, 'admin', '$2y$10$84bSMIunuBcxu93uX9B0W.8tOdNaJbLa6XvNp85uEv95LYL4I0tuK', 'Budi Santoso', 'admin@jazuva.com', '081234567890', 'active'),
(2, 2, 'kasir', '$2y$10$84bSMIunuBcxu93uX9B0W.8tOdNaJbLa6XvNp85uEv95LYL4I0tuK', 'Rivalino Kasir', 'kasir@jazuva.com', '081234567891', 'active'),
(3, 3, 'kitchen', '$2y$10$84bSMIunuBcxu93uX9B0W.8tOdNaJbLa6XvNp85uEv95LYL4I0tuK', 'Chef Jundi', 'kitchen@jazuva.com', '081234567892', 'active'),
(4, 4, 'barista', '$2y$10$84bSMIunuBcxu93uX9B0W.8tOdNaJbLa6XvNp85uEv95LYL4I0tuK', 'Barista Sarah', 'barista@jazuva.com', '081234567893', 'active'),
(5, 5, 'inventory', '$2y$10$84bSMIunuBcxu93uX9B0W.8tOdNaJbLa6XvNp85uEv95LYL4I0tuK', 'Zahra Logistik', 'inventory@jazuva.com', '081234567894', 'active');

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `type`, `target_station`, `icon`, `sort_order`, `is_active`) VALUES
(1, 'Makanan', 'makanan', 'makanan', 'kitchen', 'utensils', 1, 1),
(2, 'Kopi', 'kopi', 'minuman', 'barista', 'coffee', 2, 1),
(3, 'Minuman Non-Kopi', 'minuman-non-kopi', 'minuman', 'barista', 'glass-water', 3, 1),
(4, 'Dessert', 'dessert', 'dessert', 'kitchen', 'ice-cream', 4, 1),
(5, 'Snack', 'snack', 'snack', 'kitchen', 'cookie-bite', 5, 1);

-- Tables (Matches prototype floor plan: Meja 01 s/d 06 and beyond)
INSERT INTO `restaurant_tables` (`id`, `table_number`, `capacity`, `status`, `pos_x`, `pos_y`, `token`) VALUES
(1, '01', 4, 'terisi', 1, 1, SHA2(UUID(), 256)),
(2, '02', 4, 'terisi', 2, 1, SHA2(UUID(), 256)),
(3, '03', 4, 'dipesan', 3, 1, SHA2(UUID(), 256)),
(4, '04', 4, 'tersedia', 1, 2, SHA2(UUID(), 256)),
(5, '05', 4, 'tersedia', 2, 2, SHA2(UUID(), 256)),
(6, '06', 4, 'tersedia', 3, 2, SHA2(UUID(), 256)),
(7, '07', 2, 'tersedia', 1, 3, SHA2(UUID(), 256)),
(8, '08', 2, 'tersedia', 2, 3, SHA2(UUID(), 256)),
(9, '09', 6, 'tersedia', 3, 3, SHA2(UUID(), 256)),
(10, '10', 8, 'tersedia', 4, 3, SHA2(UUID(), 256));

-- Ingredients (Bahan Baku) matching prototype: Beras, Telur, Ayam, Biji Kopi, Susu, dll
INSERT INTO `ingredients` (`id`, `name`, `category`, `stock`, `unit`, `min_stock`, `cost_per_unit`) VALUES
(1, 'Beras', 'makanan', 10.00, 'kg', 5.00, 14000.00),
(2, 'Telur', 'makanan', 5.00, 'butir', 15.00, 2000.00),
(3, 'Ayam Fillet', 'makanan', 1.00, 'kg', 3.00, 38000.00),
(4, 'Mie Kuning Basah', 'makanan', 8.00, 'kg', 3.00, 12000.00),
(5, 'Biji Kopi Arabika', 'minuman', 4.50, 'kg', 2.00, 180000.00),
(6, 'Susu Segar (Fresh Milk)', 'minuman', 12.00, 'liter', 5.00, 20000.00),
(7, 'Bubuk Matcha Premium', 'minuman', 0.80, 'kg', 0.50, 250000.00),
(8, 'Daun Teh Melati', 'minuman', 2.00, 'kg', 1.00, 60000.00),
(9, 'Minyak Goreng', 'bumbu', 15.00, 'liter', 5.00, 16000.00),
(10, 'Gula Pasir', 'bumbu', 10.00, 'kg', 3.00, 15000.00),
(11, 'Gula Aren Cair', 'bumbu', 3.00, 'liter', 2.00, 35000.00);

-- Menus (Exact items from prototype: Nasi Goreng, Ayam Geprek, Mie Goreng, Es Teh, Cappuccino, Matcha Latte, Es Kopi Susu)
INSERT INTO `menus` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `cost_price`, `image`, `is_available`, `is_favorite`) VALUES
(1, 1, 'Nasi Goreng Spesial', 'nasi-goreng-spesial', 'Nasi goreng dengan bumbu khas Jazuva, suwiran ayam, telur mata sapi, dan kerupuk renyah.', 20000.00, 11000.00, 'nasi-goreng.jpg', 1, 1),
(2, 1, 'Ayam Geprek Sambal Bawang', 'ayam-geprek-sambal-bawang', 'Ayam goreng krispi dibalut sambal bawang pedas nampol disajikan dengan nasi hangat dan lalapan.', 18000.00, 9500.00, 'ayam-geprek.jpg', 1, 1),
(3, 1, 'Mie Goreng Jawa', 'mie-goreng-jawa', 'Mie goreng gurih manis bertabur ayam, sayuran segar, bakso, dan telur acak.', 17000.00, 8500.00, 'mie-goreng.jpg', 1, 0),
(4, 3, 'Es Teh Manis', 'es-teh-manis', 'Teh melati wangi segar disajikan dingin manis melegakan tenggorokan.', 5000.00, 1500.00, 'es-teh.jpg', 1, 0),
(5, 2, 'Cappuccino', 'cappuccino', 'Espresso klasik berpadu dengan steamed milk lembut dan busa susu tebal berpola latte art.', 15000.00, 7000.00, 'cappuccino.jpg', 1, 1),
(6, 3, 'Matcha Latte', 'matcha-latte', 'Perpaduan bubuk matcha asli Jepang dengan susu creamy dan manis lembut seimbang.', 18000.00, 9000.00, 'matcha-latte.jpg', 1, 1),
(7, 2, 'Es Kopi Susu Jazuva', 'es-kopi-susu-jazuva', 'Signature espresso khas Jazuva berpadu dengan susu murni dan gula aren organik harum legit.', 18000.00, 8000.00, 'es-kopi-susu.jpg', 1, 1),
(8, 5, 'Kentang Goreng Krispi', 'kentang-goreng-krispi', 'French fries renyah keemasan dengan taburan bumbu rempah gurih dan saus cocolan.', 15000.00, 6500.00, 'kentang-goreng.jpg', 1, 0);

-- Recipes for Menus
INSERT INTO `recipes` (`id`, `menu_id`, `notes`) VALUES
(1, 1, 'Resep Nasi Goreng Spesial'),
(2, 2, 'Resep Ayam Geprek Sambal Bawang'),
(3, 3, 'Resep Mie Goreng Jawa'),
(4, 4, 'Resep Es Teh Manis'),
(5, 5, 'Resep Cappuccino'),
(6, 6, 'Resep Matcha Latte'),
(7, 7, 'Resep Es Kopi Susu Jazuva');

-- Recipe Details (Standard ingredients per portion)
-- Nasi Goreng: Beras 0.15 kg, Telur 1 butir, Minyak 0.02 liter
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(1, 1, 0.1500, 'kg'),
(1, 2, 1.0000, 'butir'),
(1, 9, 0.0200, 'liter');

-- Ayam Geprek: Ayam 0.20 kg, Minyak 0.03 liter, Beras 0.10 kg
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(2, 3, 0.2000, 'kg'),
(2, 9, 0.0300, 'liter'),
(2, 1, 0.1000, 'kg');

-- Mie Goreng: Mie 0.20 kg, Telur 1 butir, Minyak 0.02 liter
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(3, 4, 0.2000, 'kg'),
(3, 2, 1.0000, 'butir'),
(3, 9, 0.0200, 'liter');

-- Es Teh Manis: Daun Teh 0.01 kg, Gula Pasir 0.02 kg
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(4, 8, 0.0100, 'kg'),
(4, 10, 0.0200, 'kg');

-- Cappuccino: Biji Kopi 0.018 kg (18g), Susu 0.150 liter (150ml)
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(5, 5, 0.0180, 'kg'),
(5, 6, 0.1500, 'liter');

-- Matcha Latte: Bubuk Matcha 0.015 kg (15g), Susu 0.180 liter (180ml), Gula Pasir 0.015 kg
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(6, 7, 0.0150, 'kg'),
(6, 6, 0.1800, 'liter'),
(6, 10, 0.0150, 'kg');

-- Es Kopi Susu Jazuva: Biji Kopi 0.018 kg, Susu 0.120 liter, Gula Aren 0.025 liter
INSERT INTO `recipe_details` (`recipe_id`, `ingredient_id`, `amount_required`, `unit`) VALUES
(7, 5, 0.0180, 'kg'),
(7, 6, 0.1200, 'liter'),
(7, 11, 0.0250, 'liter');

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('restaurant_name', 'Jazuva', 'Nama Restoran'),
('restaurant_tagline', 'Restaurant & Coffee Shop', 'Subjudul Restoran'),
('restaurant_slogan', 'Good Food. Good Coffee. Good Mood.', 'Slogan'),
('restaurant_address', 'Jl. Kaliurang KM 5.5, Yogyakarta', 'Alamat Restoran'),
('restaurant_phone', '0812-3456-7890', 'Nomor Kontak Restoran'),
('restaurant_email', 'info@jazuva.com', 'Email Restoran'),
('tax_percentage', '0', 'Pajak PB1 (%) (0 jika sudah termasuk)'),
('service_charge', '0', 'Service charge (%)'),
('currency_symbol', 'Rp', 'Simbol Mata Uang');

-- Sample Initial Active Orders to match Prototype displays:
-- Meja 01: Nasi Goreng x2, Ayam Geprek x1, Es Teh x1
INSERT INTO `table_sessions` (`id`, `table_id`, `session_token`, `customer_name`, `status`, `start_time`, `total_amount`) VALUES
(1, 1, 'SESS-20251028-01', 'Pelanggan Meja 01', 'active', NOW() - INTERVAL 12 MINUTE, 45000.00),
(2, 2, 'SESS-20251028-02', 'Pelanggan Meja 02', 'active', NOW() - INTERVAL 8 MINUTE, 33000.00),
(3, 3, 'SESS-20251028-03', 'Pelanggan Meja 03', 'active', NOW() - INTERVAL 20 MINUTE, 34000.00),
(4, 4, 'SESS-20251028-04', 'Pelanggan Meja 04', 'active', NOW() - INTERVAL 5 MINUTE, 28000.00);

-- Orders:
-- Order 1 (Meja 01, #001)
INSERT INTO `orders` (`id`, `order_number`, `table_id`, `session_id`, `customer_name`, `total_amount`, `final_amount`, `order_status`, `notes`, `created_at`) VALUES
(1, 'ORD-20251028-001', 1, 1, 'Pelanggan Meja 01', 45000.00, 45000.00, 'menunggu', 'Jangan terlalu pedas', NOW() - INTERVAL 2 MINUTE);

INSERT INTO `order_items` (`order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`, `notes`, `station`, `item_status`, `stock_deducted`, `created_at`) VALUES
(1, 1, 2, 20000.00, 40000.00, '1 pedas, 1 sedang', 'kitchen', 'menunggu', 0, NOW() - INTERVAL 2 MINUTE),
(1, 2, 1, 18000.00, 18000.00, 'Sambal dipisah', 'kitchen', 'menunggu', 0, NOW() - INTERVAL 2 MINUTE),
(1, 4, 1, 5000.00, 5000.00, 'Manis sedang', 'barista', 'siap', 0, NOW() - INTERVAL 2 MINUTE);

-- Order 2 (Meja 02, #002)
INSERT INTO `orders` (`id`, `order_number`, `table_id`, `session_id`, `customer_name`, `total_amount`, `final_amount`, `order_status`, `notes`, `created_at`) VALUES
(2, 'ORD-20251028-002', 2, 2, 'Pelanggan Meja 02', 33000.00, 33000.00, 'menunggu', 'Dine in', NOW() - INTERVAL 1 MINUTE);

INSERT INTO `order_items` (`order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`, `notes`, `station`, `item_status`, `stock_deducted`, `created_at`) VALUES
(2, 5, 1, 15000.00, 15000.00, 'Less sugar', 'barista', 'menunggu', 0, NOW() - INTERVAL 1 MINUTE),
(2, 6, 1, 18000.00, 18000.00, 'Oat milk jika ada', 'barista', 'menunggu', 0, NOW() - INTERVAL 1 MINUTE);

-- Order 3 (Meja 03, #003)
INSERT INTO `orders` (`id`, `order_number`, `table_id`, `session_id`, `customer_name`, `total_amount`, `final_amount`, `order_status`, `notes`, `created_at`) VALUES
(3, 'ORD-20251028-003', 3, 3, 'Pelanggan Meja 03', 34000.00, 34000.00, 'diproses', '', NOW() - INTERVAL 8 MINUTE);

INSERT INTO `order_items` (`order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`, `notes`, `station`, `item_status`, `stock_deducted`, `created_at`) VALUES
(3, 3, 2, 17000.00, 34000.00, 'Banyak sayur', 'kitchen', 'diproses', 1, NOW() - INTERVAL 8 MINUTE);

-- Order 4 (Meja 04, #004)
INSERT INTO `orders` (`id`, `order_number`, `table_id`, `session_id`, `customer_name`, `total_amount`, `final_amount`, `order_status`, `notes`, `created_at`) VALUES
(4, 'ORD-20251028-004', 4, 4, 'Pelanggan Meja 04', 28000.00, 28000.00, 'diproses', '', NOW() - INTERVAL 4 MINUTE);

INSERT INTO `order_items` (`order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`, `notes`, `station`, `item_status`, `stock_deducted`, `created_at`) VALUES
(4, 4, 2, 5000.00, 10000.00, 'Gula normal', 'barista', 'diproses', 1, NOW() - INTERVAL 4 MINUTE),
(4, 7, 1, 18000.00, 18000.00, 'Extra shot', 'barista', 'siap', 1, NOW() - INTERVAL 6 MINUTE);

-- Order 5 (Meja 05, #005)
INSERT INTO `orders` (`id`, `order_number`, `table_id`, `session_id`, `customer_name`, `total_amount`, `final_amount`, `order_status`, `notes`, `created_at`) VALUES
(5, 'ORD-20251028-005', 5, NULL, 'Pelanggan Meja 05', 20000.00, 20000.00, 'siap', '', NOW() - INTERVAL 10 MINUTE);

INSERT INTO `order_items` (`order_id`, `menu_id`, `quantity`, `unit_price`, `subtotal`, `notes`, `station`, `item_status`, `stock_deducted`, `created_at`) VALUES
(5, 1, 1, 20000.00, 20000.00, 'Pedas gurih', 'kitchen', 'siap', 1, NOW() - INTERVAL 10 MINUTE);

-- Sample Completed Transactions for Dashboard Statistics
INSERT INTO `orders` (`id`, `order_number`, `table_id`, `session_id`, `customer_name`, `total_amount`, `final_amount`, `order_status`, `notes`, `created_at`) VALUES
(6, 'ORD-20251028-000', 6, NULL, 'Selesai 01', 50000.00, 50000.00, 'selesai', '', NOW() - INTERVAL 1 HOUR);

INSERT INTO `payments` (`id`, `order_id`, `session_id`, `transaction_number`, `payment_method`, `total_bill`, `amount_paid`, `change_amount`, `payment_status`, `cashier_id`, `created_at`) VALUES
(1, 6, NULL, 'TRX20251028001', 'tunai', 45000.00, 50000.00, 5000.00, 'lunas', 2, NOW() - INTERVAL 45 MINUTE);
