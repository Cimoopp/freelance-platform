-- =====================================================================
--  Honer Freelance — начальная схема БД
--  MySQL 5.7+, InnoDB, utf8mb4_unicode_ci
--  Важно: без CTE, оконных функций, CHECK и JSON-типов (JSON -> TEXT)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. users — пользователи
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` ENUM('admin','client','freelancer') NOT NULL DEFAULT 'client',
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','blocked','pending') NOT NULL DEFAULT 'active',
  `commission_rate` DECIMAL(5,2) DEFAULT NULL COMMENT 'Персональная комиссия, %',
  `remember_token` VARCHAR(255) DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_created_at` (`created_at`),
  KEY `idx_users_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. user_profiles — расширенный профиль
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `user_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `bio` TEXT,
  `skills` TEXT,
  `portfolio_url` VARCHAR(255) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `telegram` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. categories — категории (двухуровневые)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `commission_rate` DECIMAL(5,2) DEFAULT NULL COMMENT 'Комиссия для категории, %',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_status` (`status`),
  KEY `idx_categories_sort` (`sort_order`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. services — услуги («кворки»)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `delivery_days` INT UNSIGNED NOT NULL,
  `images` TEXT COMMENT 'JSON-массив путей к изображениям',
  `status` ENUM('active','paused','deleted') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_user` (`user_id`),
  KEY `idx_services_category` (`category_id`),
  KEY `idx_services_status` (`status`),
  KEY `idx_services_price` (`price`),
  KEY `idx_services_created_at` (`created_at`),
  CONSTRAINT `fk_services_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. projects — проекты заказчиков
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `budget_min` DECIMAL(10,2) DEFAULT NULL,
  `budget_max` DECIMAL(10,2) DEFAULT NULL,
  `deadline` DATE DEFAULT NULL,
  `status` ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projects_client` (`client_id`),
  KEY `idx_projects_category` (`category_id`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_created_at` (`created_at`),
  CONSTRAINT `fk_projects_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projects_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. bids — ставки фрилансеров на проекты
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bids` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `freelancer_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `days` INT UNSIGNED NOT NULL,
  `comment` TEXT,
  `status` ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bids_project` (`project_id`),
  KEY `idx_bids_freelancer` (`freelancer_id`),
  KEY `idx_bids_status` (`status`),
  KEY `idx_bids_created_at` (`created_at`),
  CONSTRAINT `fk_bids_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bids_freelancer` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. orders — заказы
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `project_id` INT UNSIGNED DEFAULT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `freelancer_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `commission_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending','in_progress','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
  `deadline` DATE DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_service` (`service_id`),
  KEY `idx_orders_project` (`project_id`),
  KEY `idx_orders_client` (`client_id`),
  KEY `idx_orders_freelancer` (`freelancer_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created_at` (`created_at`),
  CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_freelancer` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. transactions — движение средств
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('deposit','withdrawal','payment','commission','refund') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user` (`user_id`),
  KEY `idx_tx_order` (`order_id`),
  KEY `idx_tx_type` (`type`),
  KEY `idx_tx_status` (`status`),
  KEY `idx_tx_created_at` (`created_at`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tx_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. reviews — отзывы
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `to_user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5',
  `comment` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_order_from` (`order_id`,`from_user_id`),
  KEY `idx_reviews_from` (`from_user_id`),
  KEY `idx_reviews_to` (`to_user_id`),
  KEY `idx_reviews_created_at` (`created_at`),
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. messages — личные сообщения (чат)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `text` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_sender` (`sender_id`),
  KEY `idx_msg_receiver` (`receiver_id`),
  KEY `idx_msg_order` (`order_id`),
  KEY `idx_msg_dialog` (`sender_id`,`receiver_id`,`created_at`),
  KEY `idx_msg_unread` (`receiver_id`,`is_read`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. notifications — уведомления
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `text` VARCHAR(255) NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  KEY `idx_notif_created_at` (`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. disputes — споры
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `disputes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `initiator_id` INT UNSIGNED NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
  `admin_comment` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disputes_order` (`order_id`),
  KEY `idx_disputes_initiator` (`initiator_id`),
  KEY `idx_disputes_status` (`status`),
  KEY `idx_disputes_created_at` (`created_at`),
  CONSTRAINT `fk_disputes_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disputes_initiator` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. settings — настройки платформы (key/value)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
