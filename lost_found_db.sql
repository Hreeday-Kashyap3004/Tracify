-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/

-- Create database and use it
CREATE DATABASE IF NOT EXISTS `lost_found_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lost_found_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

START TRANSACTION;

-- Table structure for table `items`
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_type` enum('lost','found') NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `item_date` date DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('reported','claimed','closed') DEFAULT 'reported',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `claimer_id` int(11) DEFAULT NULL,
  `claim_details` text DEFAULT NULL,
  `verification_question` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `user_id` (`user_id`),
  KEY `claimer_id` (`claimer_id`),
  KEY `idx_category` (`category`),
  KEY `idx_item_type_status` (`item_type`,`status`),
  FULLTEXT KEY `idx_fulltext_search` (`item_name`,`description`,`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample data into `users`
INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `created_at`) VALUES
(1, 'raju_singh', 'raju.singh@gmail.com', '$2y$10$3goUlXcN/lZtF25haor33.xiWJ1W52agVq.MpYz..odZl8gZcoL/.', '2025-05-12 12:57:51'),
(2, 'hima', 'hima@gmail.com', '$2y$10$GBjv92NNgWI3pBQerNmPKORNtB4OIrsnfQMgAoHrW.8MBPNNppcT.', '2025-05-12 13:09:16');

-- Insert sample data into `items`
INSERT INTO `items` (`item_id`, `user_id`, `item_type`, `item_name`, `category`, `description`, `location`, `item_date`, `image_path`, `status`, `reported_at`, `claimer_id`, `claim_details`, `verification_question`) VALUES
(1, 1, 'lost', 'room key', 'Keys', 'it was a golden room key , please contact if found', 'cafeteria counter', '2025-05-12', 'uploads/item_6821f17e87ebc4.80258963.jpg', 'closed', '2025-05-12 13:02:54', NULL, NULL, NULL),
(2, 2, 'found', 'room key', 'Keys', 'someone\'s room key it seems, golden colour .', 'cafeteria counter', '2025-05-12', 'uploads/item_682201f0d24745.16571108.jpg', 'reported', '2025-05-12 14:13:04', 1, 'thank you , ive been looking for this', '');

-- Insert sample data into `notifications`
INSERT INTO `notifications` (`notification_id`, `user_id`, `item_id`, `message`, `is_read`, `created_at`) VALUES
(2, 2, 2, 'User \'raju_singh\' has submitted a claim for your found item: \'room key\'. Please review it on \'My Items\'.', 1, '2025-05-12 14:14:00');

-- Foreign Key Constraints
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`claimer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

COMMIT;
