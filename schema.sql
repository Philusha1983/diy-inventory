-- DIY Lab Inventory System — Database Schema
-- Run this in phpMyAdmin after creating the database: diy_lab_db

CREATE TABLE IF NOT EXISTS `inventory` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255) NOT NULL,
  `model`          VARCHAR(255) DEFAULT NULL,
  `category`       VARCHAR(100) DEFAULT NULL,
  `quantity`       INT(11)      DEFAULT 0,
  `status`         ENUM('New', 'Used', 'Refurbished') DEFAULT 'New',
  `specs`          TEXT         DEFAULT NULL,
  `image_paths`    TEXT         DEFAULT NULL,
  `location`       VARCHAR(255) DEFAULT NULL,
  `product_url`    VARCHAR(500) DEFAULT NULL,
  `datasheet_url`  VARCHAR(500) DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `purchase_price` DECIMAL(8,2) DEFAULT NULL,
  `enriched_data`  TEXT         DEFAULT NULL,
  `enriched_at`    DATETIME     DEFAULT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('ai_provider', 'gemini'),
  ('api_key', '');
