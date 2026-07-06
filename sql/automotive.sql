-- Automotive module schema (cloned from `accessories`)
-- Run once on the target database.

CREATE TABLE IF NOT EXISTS `automotive` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_type` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `owner_id` INT(11) DEFAULT NULL,
  `brand` VARCHAR(150) DEFAULT NULL,
  `model` VARCHAR(150) DEFAULT NULL,
  `name` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `manufacture_year` VARCHAR(20) DEFAULT NULL,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `lead_time` VARCHAR(100) DEFAULT NULL,
  `selling_price` DECIMAL(12,2) DEFAULT 0.00,
  `promo_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `discount_price` DECIMAL(12,2) DEFAULT NULL,
  `promo_end_date` DATETIME DEFAULT NULL,
  `promo_label` VARCHAR(100) DEFAULT NULL,
  `short_info` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `tag` VARCHAR(50) DEFAULT NULL,
  `brand_tag` VARCHAR(150) DEFAULT NULL,
  `remark` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive','sold','pending') NOT NULL DEFAULT 'active',
  `datasheet_url` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_automotive_status` (`status`),
  KEY `idx_automotive_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `automotive_images` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `automotive_id` INT(11) NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_automotive_images_automotive_id` (`automotive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
