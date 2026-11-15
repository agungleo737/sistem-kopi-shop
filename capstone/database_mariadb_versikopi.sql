-- =====================================================================
-- DATABASE MARIADB / MYSQL : VERSI KOPI POS & SHARED INVENTORY SYSTEM
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `versikopi_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `versikopi_db`;

-- 1. Tabel Users (Admin & Kasir)
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'kasir',
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Personal Access Tokens (Laravel Sanctum)
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` TEXT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Sessions & Cache
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Settings (Identitas & Pengaturan Toko)
CREATE TABLE IF NOT EXISTS `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Ingredients Stock (Stok Bahan Baku Gudang Bersama gr / ml)
CREATE TABLE IF NOT EXISTS `ingredients_stock` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'ml',
  `current_stock` DOUBLE NOT NULL DEFAULT 0,
  `max_stock` DOUBLE NOT NULL DEFAULT 1000,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ingredients_stock_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Products (Menu Satuan & Menu Paketan / Bundling)
CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(60) NOT NULL,
  `item_type` VARCHAR(20) NOT NULL DEFAULT 'satuan',
  `description` TEXT NULL,
  `bundle_items` TEXT NULL,
  `price_m` INT NOT NULL DEFAULT 0,
  `price_l` INT NOT NULL DEFAULT 0,
  `stock` INT NOT NULL DEFAULT 60,
  `max_stock` INT NOT NULL DEFAULT 60,
  `emoji` VARCHAR(30) NULL DEFAULT 'KOPI',
  `gradient` VARCHAR(60) NULL DEFAULT '#D8B493,#A67C58',
  `image_url` VARCHAR(255) NULL DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `products_category_index` (`category`),
  KEY `products_item_type_index` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel Recipes (Standar Resep & Takaran Bahan per Menu Satuan)
CREATE TABLE IF NOT EXISTS `recipes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `prep_time` VARCHAR(40) NOT NULL DEFAULT '2 Menit',
  `ingredients` LONGTEXT NOT NULL,
  `steps` TEXT NULL,
  `barista_note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recipes_product_id_unique` (`product_id`),
  CONSTRAINT `fk_recipes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabel Promos (Kode Voucher & Promo Toko)
CREATE TABLE IF NOT EXISTS `promos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL,
  `type` VARCHAR(20) NOT NULL DEFAULT 'percent',
  `value` INT NOT NULL DEFAULT 0,
  `min_subtotal` INT NOT NULL DEFAULT 0,
  `max_discount` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promos_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabel Orders (Riwayat Transaksi Kasir POS)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `cashier_name` VARCHAR(100) NULL DEFAULT NULL,
  `customer_name` VARCHAR(100) NULL DEFAULT NULL,
  `order_type` VARCHAR(20) NOT NULL DEFAULT 'dine_in',
  `table_number` VARCHAR(20) NULL DEFAULT NULL,
  `items` LONGTEXT NOT NULL,
  `subtotal` INT NOT NULL DEFAULT 0,
  `promo_code` VARCHAR(32) NULL DEFAULT NULL,
  `discount` INT NOT NULL DEFAULT 0,
  `tax` INT NOT NULL DEFAULT 0,
  `service` INT NOT NULL DEFAULT 0,
  `total` INT NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'cash',
  `cash_received` INT NOT NULL DEFAULT 0,
  `change_amount` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Tabel Inventory Logs (Mutasi Bahan Baku Gudang IN / OUT)
CREATE TABLE IF NOT EXISTS `inventory_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `type` VARCHAR(10) NOT NULL DEFAULT 'in',
  `qty` VARCHAR(50) NOT NULL,
  `stock_after` VARCHAR(50) NULL DEFAULT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `reference` VARCHAR(60) NULL DEFAULT NULL,
  `actor_name` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `inventory_logs_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
