-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2026 at 06:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ifindpeace`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'User logged in', '::1', '2026-11-18 14:14:08'),
(2, 1, 'logout', 'User logged out', '::1', '2026-11-18 14:15:34'),
(3, 1, 'login', 'User logged in', '::1', '2026-11-18 14:26:42'),
(4, 1, 'logout', 'User logged out', '::1', '2026-11-18 14:27:59'),
(5, 1, 'login', 'User logged in', '::1', '2026-11-18 14:30:02'),
(6, 1, 'logout', 'User logged out', '::1', '2026-11-18 14:30:39'),
(7, 1, 'login', 'User logged in', '::1', '2026-11-18 14:32:00'),
(8, 1, 'logout', 'User logged out', '::1', '2026-11-18 14:33:18'),
(9, NULL, 'register', 'New user registered', '::1', '2026-11-18 14:35:48'),
(10, NULL, 'login', 'User logged in', '::1', '2026-11-18 14:35:56'),
(11, NULL, 'logout', 'User logged out', '::1', '2026-11-18 14:39:51'),
(12, 1, 'login', 'User logged in', '::1', '2026-11-18 14:41:08'),
(13, 1, 'logout', 'User logged out', '::1', '2026-11-18 14:41:47'),
(14, 1, 'login', 'User logged in', '::1', '2026-11-18 14:48:43'),
(15, 1, 'logout', 'User logged out', '::1', '2026-11-18 14:52:55'),
(16, NULL, 'login', 'User logged in', '::1', '2026-11-18 14:53:11'),
(17, NULL, 'logout', 'User logged out', '::1', '2026-11-18 14:56:35'),
(18, NULL, 'login', 'User logged in', '::1', '2026-11-18 14:56:54'),
(19, NULL, 'logout', 'User logged out', '::1', '2026-11-18 14:57:50'),
(20, 1, 'login', 'User logged in', '::1', '2026-11-18 14:57:59'),
(21, 1, 'login', 'User logged in', '::1', '2026-11-18 15:05:49'),
(22, 1, 'logout', 'User logged out', '::1', '2026-11-18 15:05:54'),
(23, NULL, 'login', 'User logged in', '::1', '2026-11-18 15:06:28'),
(24, NULL, 'report_found', 'Reported found item: wallet', '::1', '2026-11-18 15:11:01'),
(25, NULL, 'logout', 'User logged out', '::1', '2026-11-18 15:11:17'),
(26, 1, 'login', 'User logged in', '::1', '2026-11-18 15:11:24'),
(27, 1, 'report_lost', 'Reported lost item: wallet', '::1', '2026-11-18 15:12:28'),
(28, 1, 'claim_item', 'Claimed item: wallet', '::1', '2026-11-18 15:14:56'),
(29, 1, 'logout', 'User logged out', '::1', '2026-11-18 15:15:48'),
(30, 1, 'login', 'User logged in', '::1', '2026-11-18 15:15:56'),
(31, 1, 'update_claim_status', 'Updated claim 1 status to pending', '::1', '2026-11-18 15:16:43'),
(32, 1, 'update_claim_status', 'Updated claim 1 status to pending', '::1', '2026-11-18 15:16:52'),
(33, 1, 'logout', 'User logged out', '::1', '2026-11-18 15:16:59'),
(34, NULL, 'login', 'User logged in', '::1', '2026-11-18 15:17:10'),
(35, NULL, 'update_claim_status', 'Updated claim 1 status to completed', '::1', '2026-11-18 15:17:59'),
(36, NULL, 'logout', 'User logged out', '::1', '2026-11-18 15:18:58'),
(37, 1, 'login', 'User logged in', '::1', '2026-11-18 15:19:16'),
(38, 1, 'logout', 'User logged out', '::1', '2026-11-18 15:24:16'),
(39, 1, 'login', 'User logged in', '::1', '2026-11-18 15:26:40'),
(40, 1, 'login', 'User logged in', '::1', '2026-11-18 15:33:36'),
(41, 1, 'logout', 'User logged out', '::1', '2026-11-18 15:42:43'),
(42, 3, 'register', 'New user registered', '::1', '2026-11-18 15:43:38'),
(43, 3, 'login', 'User logged in', '::1', '2026-11-18 15:43:48'),
(44, 3, 'report_found', 'Reported found item: Jacket', '::1', '2026-11-18 15:55:35'),
(45, 3, 'logout', 'User logged out', '::1', '2026-11-18 15:55:52'),
(46, 4, 'register', 'New user registered', '::1', '2026-11-18 15:57:08'),
(47, 4, 'login', 'User logged in', '::1', '2026-11-18 15:57:18'),
(48, 4, 'report_lost', 'Reported lost item: Jacket', '::1', '2026-11-18 15:58:05'),
(49, 4, 'claim_item', 'Claimed item: Jacket', '::1', '2026-11-18 15:59:12'),
(50, 4, 'logout', 'User logged out', '::1', '2026-11-18 15:59:40'),
(51, 3, 'login', 'User logged in', '::1', '2026-11-18 15:59:50'),
(52, 3, 'update_claim_status', 'Updated claim 2 status to completed', '::1', '2026-11-18 16:01:07'),
(53, 3, 'logout', 'User logged out', '::1', '2026-11-18 16:01:45'),
(54, 4, 'login', 'User logged in', '::1', '2026-11-18 16:01:53'),
(55, 3, 'login', 'User logged in', '::1', '2026-11-19 00:56:54'),
(56, 3, 'logout', 'User logged out', '::1', '2026-11-19 01:21:19'),
(57, 1, 'login', 'User logged in', '::1', '2026-11-19 01:21:27'),
(58, 3, 'login', 'User logged in', '::1', '2026-11-19 01:55:29'),
(59, 3, 'logout', 'User logged out', '::1', '2026-11-19 01:55:48'),
(60, 3, 'login', 'User logged in', '::1', '2026-11-19 01:58:03'),
(61, 3, 'logout', 'User logged out', '::1', '2026-11-19 02:03:21'),
(62, 3, 'login', 'User logged in', '::1', '2026-11-19 02:05:07'),
(63, 1, 'login', 'User logged in', '::1', '2026-11-19 02:13:48'),
(64, 1, 'logout', 'User logged out', '::1', '2026-11-19 02:14:09'),
(65, 3, 'login', 'User logged in', '::1', '2026-11-19 02:14:25'),
(66, 3, 'login', 'User logged in', '::1', '2026-11-19 02:47:11'),
(67, 3, 'logout', 'User logged out', '::1', '2026-11-19 03:19:10'),
(68, 1, 'login', 'User logged in', '::1', '2026-11-19 04:08:49'),
(69, 1, 'logout', 'User logged out', '::1', '2026-11-19 04:28:51'),
(70, 3, 'login', 'User logged in', '::1', '2026-11-19 04:29:00'),
(71, 3, 'logout', 'User logged out', '::1', '2026-11-19 04:33:24'),
(72, 4, 'login', 'User logged in', '::1', '2026-11-19 04:33:38'),
(73, 4, 'logout', 'User logged out', '::1', '2026-11-19 04:39:58'),
(74, 3, 'login', 'User logged in', '::1', '2026-11-19 04:40:08'),
(75, 1, 'login', 'User logged in', '::1', '2026-11-19 05:07:14'),
(76, 1, 'logout', 'User logged out', '::1', '2026-11-19 05:07:25'),
(77, 3, 'login', 'User logged in', '::1', '2026-11-19 05:08:02'),
(78, 3, 'logout', 'User logged out', '::1', '2026-11-19 05:10:01'),
(79, 3, 'login', 'User logged in', '::1', '2026-11-19 05:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `category_description`, `icon`, `created_at`) VALUES
(1, 'Electronics', 'Devices such as phones, laptops, tablets, etc.', 'fa-laptop', '2026-11-18 13:52:56'),
(2, 'IDs & Documents', 'Student IDs, passports, licenses, etc.', 'fa-id-card', '2026-11-18 13:52:56'),
(3, 'Keys', 'Room keys, car keys, locker keys, etc.', 'fa-key', '2026-11-18 13:52:56'),
(4, 'Clothing', 'Jackets, uniforms, accessories, etc.', 'fa-shirt', '2026-11-18 13:52:56'),
(5, 'Books & Supplies', 'Textbooks, notebooks, school supplies', 'fa-book', '2026-11-18 13:52:56'),
(6, 'Bags & Wallets', 'Backpacks, handbags, wallets, purses', 'fa-bag-shopping', '2026-11-18 13:52:56'),
(7, 'Jewelry', 'Rings, necklaces, watches, accessories', 'fa-gem', '2026-11-18 13:52:56'),
(8, 'Others', 'Items that do not fit in other categories', 'fa-box', '2026-11-18 13:52:56');

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `claim_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `claim_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_proof_path` varchar(255) DEFAULT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `claim_status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `deadline` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`claim_id`, `item_id`, `user_id`, `claim_date`, `id_proof_path`, `security_question`, `security_answer`, `claim_status`, `admin_notes`, `deadline`) VALUES
(2, 3, 4, '2026-11-18 15:59:12', 'id_proofs/6afdcb50ec08f.jpg', 'Red', 'Red', 'completed', 'Sayo na', '2026-11-25');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_type` enum('lost','found') NOT NULL,
  `description` text NOT NULL,
  `location_found` varchar(200) DEFAULT NULL,
  `location_lost` varchar(200) DEFAULT NULL,
  `date_found` date DEFAULT NULL,
  `date_lost` date DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `status` enum('pending','claimed','returned','donated','expired') DEFAULT 'pending',
  `is_verified` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `user_id`, `category_id`, `item_name`, `item_type`, `description`, `location_found`, `location_lost`, `date_found`, `date_lost`, `image_path`, `color`, `brand`, `status`, `is_verified`, `view_count`, `created_at`, `updated_at`) VALUES
(3, 3, 4, 'Jacket', 'found', 'Bape Jacket', 'Clinic', NULL, '2026-11-18', NULL, 'items/6afdca7774513.jpg', 'Red', 'Bape', 'claimed', 0, 8, '2026-11-18 15:55:35', '2026-11-19 04:40:44'),
(4, 4, 4, 'Jacket', 'lost', 'Bape Jacket', NULL, 'Clinic', NULL, '2026-11-18', 'items/6afdcb0d7d7ca.jpg', 'Red', 'Bape', 'pending', 0, 6, '2026-11-18 15:58:05', '2026-11-19 04:33:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `notification_type` enum('match_found','claim_approved','claim_rejected','item_expired','deadline_reminder','admin_message') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `item_id`, `notification_type`, `message`, `is_read`, `created_at`) VALUES
(4, 4, 4, 'match_found', 'Potential matches found for your lost item. Check the browse items page.', 1, '2026-11-18 15:58:05'),
(5, 3, 3, 'admin_message', 'Someone claimed your found item: Jacket. Open the item page to review the claim.', 1, '2026-11-18 15:59:12'),
(6, 4, 3, 'claim_approved', 'Your claim for Jacket has been approved!', 1, '2026-11-18 16:01:07');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `user_type` enum('user','admin') DEFAULT 'user',
  `profile_picture` varchar(255) DEFAULT NULL,
  `honesty_pledge_signed` tinyint(1) DEFAULT 0,
  `pledge_signed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `student_id`, `full_name`, `email`, `password`, `phone`, `course`, `year_level`, `user_type`, `profile_picture`, `honesty_pledge_signed`, `pledge_signed_date`, `created_at`, `updated_at`, `status`) VALUES
(1, 'ADMIN001', 'System Administrator', 'admin@ifindpeace.com', '$2y$10$FXRQJTOaE6VzE3ZIy8N.jO8kcexX4HlQxZUiJZF4YVz1rLR9pPFnm', NULL, NULL, NULL, 'admin', NULL, 1, NULL, '2026-11-18 13:52:56', '2026-11-18 14:03:26', 'active'),
(3, '2024-0089', 'ItsMeZi', 'zi@gmail.com', '$2y$10$ITCSpH3OjRf82HygQRDKTOhNOh.CP1bBPY1fUSsGp2QZzJllz.BkG', '09102986681', 'BSIT', '3rd Year', 'user', NULL, 1, '2026-11-18 23:43:38', '2026-11-18 15:43:38', '2026-11-18 15:43:38', 'active'),
(4, '2024-00228', 'Jeo Ong', 'jeo@gmail.com', '$2y$10$BqlMefF30IATLFcyBxbf8ebYpTGSZRDDXYvVJ.aKB5qWPMoTPpjQG', '09425675321', 'BSBA', '1st Year', 'user', NULL, 1, '2026-11-18 23:57:08', '2026-11-18 15:57:08', '2026-11-18 15:57:08', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_claims_status` (`claim_status`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_items_type` (`item_type`),
  ADD KEY `idx_items_status` (`status`),
  ADD KEY `idx_items_category` (`category_id`),
  ADD KEY `idx_items_user` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE SET NULL;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
