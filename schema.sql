-- DIY Lab Inventory System — Database Schema
-- Run once after creating your database (diy_lab_db) in phpMyAdmin or MySQL CLI:
--   mysql -u root -p diy_lab_db < schema.sql

CREATE TABLE IF NOT EXISTS `inventory` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255) NOT NULL,
  `model`          VARCHAR(255) DEFAULT NULL,
  `category`       VARCHAR(100) DEFAULT NULL,
  `quantity`       INT(11)      DEFAULT 0,
  `status`         ENUM('New', 'Used', 'Refurbished') DEFAULT 'New',
  `specs`          TEXT         DEFAULT NULL,
  `image_paths`    TEXT         DEFAULT NULL,    -- JSON array of relative paths, e.g. ["uploads/abc.jpg"]
  `location`       VARCHAR(255) DEFAULT NULL,    -- Free-text storage location (box, shelf, room)
  `product_url`    TEXT         DEFAULT NULL,
  `datasheet_url`  TEXT         DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `purchase_price` DECIMAL(8,2) DEFAULT NULL,
  `enriched_data`  TEXT         DEFAULT NULL,    -- Cached plain-text from product/datasheet URL
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

-- Default settings — edit API keys via the ⚙️ Settings page in the app, not here
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('ai_provider',    'gemini'),   -- 'gemini' or 'openai'
  ('api_key',        ''),         -- Active provider's API key (set in Settings UI)
  ('gemini_api_key', ''),         -- Google Gemini API key
  ('gemini_model',   'gemini-2.0-flash'),
  ('openai_api_key', ''),         -- OpenAI API key
  ('lab_password',   '$2y$12$Xl3UzNWiKjmSPFGMwwU4w.p2aL/tqhqT9GFOkbJKrFDEMHnXN.7B2'), -- Default: 1234 (bcrypt). Change via Settings UI.
  -- Personalization (all editable via User Settings)
  ('lab_name',         'DIY Lab'),
  ('lab_tagline',      'Inventory & AI Orchestrator'),
  ('lab_mini_tagline', 'Inventory System'),
  ('lab_logo_url',     '');       -- Leave blank to use default SVG logo

