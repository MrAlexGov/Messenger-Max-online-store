-- Схема базы данных для интернет-магазина Макс
-- Создайте базу данных max_shop и выполните этот скрипт

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Таблица пользователей
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица категорий товаров
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица товаров
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `image_token` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица корзины пользователей
DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`, `product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица заказов
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('new','processing','shipped','completed','cancelled') DEFAULT 'new',
  `payment_method` enum('card','cash','online') DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `delivery_address` text DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица элементов заказа
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица избранного
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`, `product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица промокодов
DROP TABLE IF EXISTS `promo_codes`;
CREATE TABLE `promo_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0,
  `max_uses` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица администраторов
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица логов действий
DROP TABLE IF EXISTS `action_logs`;
CREATE TABLE `action_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Вставка тестовых данных категорий
INSERT INTO `categories` (`name`, `description`, `sort_order`) VALUES
('Электроника', 'Смартфоны, ноутбуки, аксессуары', 1),
('Одежда', 'Мужская и женская одежда', 2),
('Книги', 'Художественная и техническая литература', 3),
('Для дома', 'Товары для дома и кухни', 4),
('Спорт и отдых', 'Спортивный инвентарь и одежда', 5);

-- Вставка тестовых товаров
INSERT INTO `products` (`name`, `description`, `price`, `category_id`, `stock_quantity`) VALUES
('iPhone 15 Pro', 'Смартфон Apple iPhone 15 Pro 128GB', 99999.00, 1, 10),
('Samsung Galaxy S24', 'Смартфон Samsung Galaxy S24 256GB', 89999.00, 1, 15),
('MacBook Air M3', 'Ноутбук Apple MacBook Air 13" M3', 129999.00, 1, 5),
('Футболка Nike', 'Мужская хлопковая футболка Nike Sportswear', 2999.00, 2, 50),
('Джинсы Levi\'s', 'Мужские джинсы Levi\'s 501 Original', 7999.00, 2, 30),
('Кроссовки Adidas', 'Мужские кроссовки Adidas Ultraboost 22', 12999.00, 2, 20),
('Мастер и Маргарита', 'Роман М.А. Булгакова', 899.00, 3, 100),
('Чистый код', 'Руководство по написанию читаемого кода', 1599.00, 3, 25),
('Алгоритмы и структуры данных', 'Учебник по информатике', 2299.00, 3, 15),
('Кофемашина DeLonghi', 'Автоматическая кофемашина DeLonghi Magnifica', 45999.00, 4, 8),
('Блендер Bosch', 'Стационарный блендер Bosch VitaPower', 8999.00, 4, 12),
('Микроволновка Samsung', 'Микроволновая печь Samsung ME88SUG', 12999.00, 4, 6),
('Велосипед Stels', 'Горный велосипед Stels Navigator 500', 35999.00, 5, 3),
('Теннисная ракетка Wilson', 'Профессиональная теннисная ракетка Wilson Pro Staff', 18999.00, 5, 8),
('Фитнес-браслет Xiaomi', 'Смарт-браслет Xiaomi Mi Band 8', 4999.00, 5, 25);

-- Вставка администратора по умолчанию
INSERT INTO `admins` (`user_id`, `password_hash`) VALUES
(0, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password