-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 15, 2025 at 05:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nakaiact_testrp`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'คอมพิวเตอร์', 'ปัญหาเกี่ยวกับคอมพิวเตอร์และอุปกรณ์ต่อพ่วง', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(2, 'เครือข่าย', 'ปัญหาเกี่ยวกับระบบเครือข่ายและอินเทอร์เน็ต', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(3, 'ระบบไฟฟ้า', 'ปัญหาเกี่ยวกับระบบไฟฟ้าภายในอาคาร', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(4, 'เครื่องปรับอากาศ', 'ปัญหาเกี่ยวกับเครื่องปรับอากาศ', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(5, 'เฟอร์นิเจอร์', 'ปัญหาเกี่ยวกับเฟอร์นิเจอร์และอุปกรณ์สำนักงาน', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(6, 'อื่นๆ', 'ปัญหาอื่นๆ ที่ไม่เข้าหมวดหมู่ข้างต้น', '2025-05-01 07:56:45', '2025-05-01 07:56:45');

-- --------------------------------------------------------

--
-- Table structure for table `repair_requests`
--

CREATE TABLE `repair_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
  `image` varchar(255) DEFAULT NULL,
  `admin_remark` text DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repair_requests`
--

INSERT INTO `repair_requests` (`request_id`, `user_id`, `category_id`, `title`, `description`, `location`, `priority`, `status`, `image`, `admin_remark`, `completed_date`, `created_at`, `updated_at`) VALUES
(2, 2, 3, 'พัดลมเสีย', 'พัดลมไม่ทำงาน', 'ห้องครัว', 'high', 'pending', 'uploads/68142efa93067.jpg', 'รองบประมาณในการซ่อม', NULL, '2025-05-02 02:33:30', '2025-12-08 03:22:49'),
(3, 2, 4, 'แอร์เสีย', 'แอร์เปิดไม่ติด', 'อาคาร 20', 'high', 'in_progress', '', '', NULL, '2025-12-08 03:12:26', '2025-12-08 03:21:17'),
(4, 5, 5, 'ประตูเสีย', 'ประตูพัง', 'อาคาร 20', 'medium', 'completed', '', '', '2025-12-08 10:20:06', '2025-12-08 03:17:19', '2025-12-08 03:20:06'),
(5, 6, 3, 'ระบบไฟฟ้า หลอดไฟ ชำกรุด', 'หลอดไฟ ชำรุด จำนวน 2 หลอด', 'อาคาร 20 ชั้น 1 ห้องงานพัสดุ', 'high', 'completed', 'uploads/69364861d47bd.jpg', 'เสร็จสิ้น', '2025-12-08 10:59:59', '2025-12-08 03:39:13', '2025-12-08 03:59:59'),
(6, 2, 1, 'คอมพิวเตอร์ดับ', 'คอมพิวเตอร์เปิดไม่ติด', '304', 'medium', 'pending', '', NULL, NULL, '2025-12-11 06:20:21', '2025-12-11 06:20:21'),
(7, 6, 2, 'อินเตอร์เน็ตใช้งานไม่ได้', 'คอมพิวเตอร์ใช้งานอินเตอร์เน็ตไม่ได้ทุกเครื่องในห้องการเงิน ตั้งแต่ 08.00น.', 'ห้องการเงิน 20104 อาคาร 20 ชั้น 1', 'urgent', 'in_progress', '', '', NULL, '2025-12-12 03:24:39', '2025-12-12 03:28:04');

-- --------------------------------------------------------

--
-- Table structure for table `request_history`
--

CREATE TABLE `request_history` (
  `history_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_history`
--

INSERT INTO `request_history` (`history_id`, `request_id`, `user_id`, `status`, `remark`, `created_at`, `image`) VALUES
(5, 2, 2, 'in_progress', 'รองบประมาณในการซ่อม', '2025-05-02 02:33:50', NULL),
(6, 3, 2, 'pending', 'สร้างรายการแจ้งซ่อมใหม่', '2025-12-08 03:12:26', NULL),
(7, 4, 5, 'pending', 'สร้างรายการแจ้งซ่อมใหม่', '2025-12-08 03:17:19', NULL),
(8, 4, 2, 'completed', '', '2025-12-08 03:20:06', NULL),
(9, 3, 2, 'in_progress', '', '2025-12-08 03:21:17', NULL),
(10, 2, 2, 'rejected', 'รองบประมาณในการซ่อม', '2025-12-08 03:22:30', NULL),
(11, 2, 2, 'pending', 'รองบประมาณในการซ่อม', '2025-12-08 03:22:49', NULL),
(12, 5, 6, 'pending', 'สร้างรายการแจ้งซ่อมใหม่', '2025-12-08 03:39:13', NULL),
(13, 5, 2, 'completed', 'เสร็จสิ้น', '2025-12-08 03:59:59', NULL),
(14, 6, 2, 'pending', 'สร้างรายการแจ้งซ่อมใหม่', '2025-12-11 06:20:21', NULL),
(15, 7, 6, 'pending', 'สร้างรายการแจ้งซ่อมใหม่', '2025-12-12 03:24:39', NULL),
(16, 7, 2, 'in_progress', '', '2025-12-12 03:28:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_name`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'telegram_bot_token', '', 'โทเค็นของ Bot Telegram สำหรับการแจ้งเตือน', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(2, 'telegram_chat_id', '', 'Chat ID สำหรับส่งการแจ้งเตือนไปยัง Telegram', '2025-05-01 07:56:45', '2025-05-01 07:56:45'),
(3, 'site_name', 'แจ้งซ่อมออนไลน์', 'ชื่อระบบ', '2025-05-01 07:56:45', '2025-12-11 07:08:26'),
(4, 'site_description', 'ชื่อระบบ', 'คำอธิบายระบบ', '2025-05-01 07:56:45', '2025-12-11 07:08:17'),
(5, 'notification_enabled', 'true', 'เปิด/ปิดการแจ้งเตือนผ่าน Telegram', '2025-05-01 07:56:45', '2025-05-01 07:56:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `fullname`, `email`, `department`, `phone`, `role`, `created_at`, `updated_at`) VALUES
(2, 'admin', '$2y$10$FgXMbyaxowbwDCVYDg0a6etTuYQhfkD19Y5ZC19FpWo8FjsRtmjkm', 'admin', 'admin@admin.com', 'มจพ. ITI', '111111111', 'admin', '2025-05-01 07:57:47', '2025-12-04 04:31:33'),
(3, 'setta_0711', '$2y$10$JQLCBk3YAlmLksCdcxftEu6sc1GEbEHK81vA/8WEqLJsiMIch0XgW', 'เศรษฐพงศ์ จังเลิศคณาพงศ์', 'settapong0711@gmail.com', 'พัสดุ', '0651031932', 'admin', '2025-12-04 04:06:27', '2025-12-08 03:10:30'),
(4, 'test01', '$2y$10$PZ7K1P0YTtFQbdWOj.9NJej0Tho/V3meUsKOcsdrohU5hFfokWhc6', 'test01', 'test@test.com', 'นักศึกษา', '123456789', 'user', '2025-12-04 04:40:00', '2025-12-04 04:40:00'),
(5, 'test', '$2y$10$Isuzk62k7ar4H5zwxzmdw.VfM1wJnK6fkz/J/sk0TFezcx0m65562', 'test', 'test1@test.com', 'test', '02111111111', 'user', '2025-12-08 03:13:55', '2025-12-08 03:13:55'),
(6, 'User', '$2y$10$FNpyQs3HDsehhacsUkiQ5u5/K2b22w456r1rIpD72WTBhEypSs1DW', 'user01', 'user01@test.com', 'งานพัสดุ', '0877777777', 'user', '2025-12-08 03:27:28', '2025-12-08 03:27:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `request_history`
--
ALTER TABLE `request_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `repair_requests`
--
ALTER TABLE `repair_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `request_history`
--
ALTER TABLE `request_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD CONSTRAINT `repair_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repair_requests_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `request_history`
--
ALTER TABLE `request_history`
  ADD CONSTRAINT `request_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `repair_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
