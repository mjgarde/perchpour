-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 24, 2026 at 01:07 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `perchpour`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$bFyXXhy1KHuCQst5.ndXqugAS9RnoeAcN1TVrHYNpZBis7nIauxCu', 'Wena Dela Cruz', '2026-09-23 07:25:18');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `staff_id`, `attendance_date`, `time_in`, `time_out`, `created_at`) VALUES
(1, 1, '2026-09-23', '2026-09-23 20:38:26', NULL, '2026-09-23 12:38:26'),
(2, 2, '2026-09-23', '2026-09-23 20:48:10', '2026-09-23 20:48:19', '2026-09-23 12:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_assignments`
--

CREATE TABLE `cleaning_assignments` (
  `assignment_id` int(11) NOT NULL,
  `task_name` varchar(100) NOT NULL,
  `area` varchar(80) NOT NULL,
  `frequency` enum('daily','weekly') NOT NULL DEFAULT 'daily',
  `staff_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `due_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','missed','cancelled') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `staff_remarks` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cleaning_assignments`
--

INSERT INTO `cleaning_assignments` (`assignment_id`, `task_name`, `area`, `frequency`, `staff_id`, `assigned_by`, `assigned_date`, `due_time`, `notes`, `status`, `started_at`, `completed_at`, `staff_remarks`, `proof_image`, `created_at`) VALUES
(1, 'Wipe Tables & Chairs', 'Dining Area', 'daily', 1, 1, '2026-09-23', '10:00:00', 'Focus sa likod na tables', 'completed', '2026-09-23 21:50:13', '2026-09-23 21:50:51', 'n', 'uploads/cleaning/clean_1_1790171451_17de55.png', '2026-09-23 13:22:12'),
(2, 'Sweep & Mop Floor', 'Dining Area', 'daily', 1, 1, '2026-09-23', '11:00:00', NULL, 'pending', NULL, NULL, NULL, NULL, '2026-09-23 13:22:12'),
(3, 'Clean Comfort Room', 'Comfort Room', 'daily', 2, 1, '2026-09-23', '09:00:00', 'Refill tissue', 'in_progress', NULL, NULL, NULL, NULL, '2026-09-23 13:22:12'),
(4, 'Sanitize Kitchen Prep', 'Kitchen', 'daily', 2, 1, '2026-09-23', '14:00:00', NULL, 'pending', NULL, NULL, NULL, NULL, '2026-09-23 13:22:12'),
(5, 'Wipe Windows', 'All Areas', 'weekly', 3, 1, '2026-09-23', '17:00:00', 'Bago magsara', 'pending', NULL, NULL, NULL, NULL, '2026-09-23 13:22:12'),
(6, 'Clean Coffee Machine', 'Bar Area', 'daily', 3, 1, '2026-09-22', '15:00:00', NULL, 'completed', NULL, NULL, NULL, NULL, '2026-09-23 13:22:12'),
(7, 'Empty Trash Bins', 'All Areas', 'daily', 1, 1, '2026-09-22', '18:00:00', NULL, 'completed', NULL, NULL, NULL, NULL, '2026-09-23 13:22:12');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `low_stock_level` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`item_id`, `item_name`, `category`, `unit`, `current_stock`, `low_stock_level`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Espresso Beans', 'Ingredients', 'kg', 5.00, 2.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07'),
(2, 'Fresh Milk', 'Ingredients', 'liters', 12.00, 3.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07'),
(3, 'Vanilla Syrup', 'Ingredients', 'liters', 2.50, 1.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07'),
(4, 'Caramel Syrup', 'Ingredients', 'liters', 2.00, 1.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07'),
(5, '12oz Cups', 'Supplies', 'pcs', 500.00, 100.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07'),
(6, '16oz Cups', 'Supplies', 'pcs', 380.00, 100.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07'),
(7, 'Cup Lids', 'Supplies', 'pcs', 420.00, 100.00, 1, '2026-09-23 19:55:07', '2026-09-23 19:55:07');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` enum('add','deduct') NOT NULL DEFAULT 'add',
  `quantity` decimal(10,2) NOT NULL,
  `before_stock` decimal(10,2) NOT NULL,
  `after_stock` decimal(10,2) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` enum('Coffee','Tea','Soda','Fruit Juice','Milk') NOT NULL,
  `price` decimal(8,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `has_size` tinyint(1) NOT NULL DEFAULT 0,
  `has_sweetness` tinyint(1) NOT NULL DEFAULT 0,
  `has_milk_type` tinyint(1) NOT NULL DEFAULT 0,
  `has_addons` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `item_name`, `category`, `price`, `image`, `is_available`, `has_size`, `has_sweetness`, `has_milk_type`, `has_addons`, `created_at`, `updated_at`) VALUES
(1, 'Ginger Ale', 'Tea', 70.00, 'menu_6ab42312091aa7.33329274.png', 1, 0, 0, 0, 0, '2026-09-23 19:05:54', '2026-09-23 19:05:54'),
(2, 'Caramel Macchiato', 'Coffee', 79.00, 'menu_6ab43e2fb23de4.86011176.png', 1, 0, 0, 0, 0, '2026-09-23 21:01:35', '2026-09-23 21:01:35'),
(3, 'Iced Americano', 'Coffee', 100.00, 'menu_6ab43e97d92318.31911346.jpg', 1, 0, 0, 0, 0, '2026-09-23 21:03:19', '2026-09-23 21:03:41');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `items_json` text NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','ready','served','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `table_id`, `items_json`, `total`, `status`, `created_at`) VALUES
(1, 6, '{\"table_number\":\"1\",\"notes\":\"d\",\"items\":[{\"menu_item_id\":0,\"item_name\":\"Ginger Ale\",\"quantity\":1,\"unit_price\":70,\"size\":null,\"sweetness\":null,\"milk_type\":null,\"addons\":\"\",\"subtotal\":70}]}', 70.00, 'pending', '2026-09-23 20:55:59'),
(2, 6, '{\"table_number\":\"1\",\"notes\":\"\",\"items\":[{\"menu_item_id\":0,\"item_name\":\"Caramel Macchiato\",\"quantity\":1,\"unit_price\":79,\"size\":null,\"sweetness\":null,\"milk_type\":null,\"addons\":\"\",\"subtotal\":79},{\"menu_item_id\":0,\"item_name\":\"Caramel Macchiato\",\"quantity\":1,\"unit_price\":79,\"size\":null,\"sweetness\":null,\"milk_type\":null,\"addons\":\"\",\"subtotal\":79}]}', 158.00, 'pending', '2026-09-23 21:58:30'),
(3, 6, '{\"table_number\":\"1\",\"notes\":\"\",\"items\":[{\"menu_item_id\":0,\"item_name\":\"Caramel Macchiato\",\"quantity\":1,\"unit_price\":79,\"size\":null,\"sweetness\":null,\"milk_type\":null,\"addons\":\"\",\"subtotal\":79}]}', 79.00, 'completed', '2026-09-23 22:34:24');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `request_type` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `handled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `qr_code` varchar(64) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `qr_code`, `full_name`, `username`, `password`, `address`, `contact_number`, `created_at`) VALUES
(1, 'PP-E0A1878178CE', 'Juan Dela Cruz', 'juan', '$2y$10$2DlJ/3bsVlrBLDS5c0qDmuGNFDF0KqXkmgNoDfg.Z2wijGnUTrwce', 'Banga, South Cotabato', '09090909877', '2026-09-23 08:52:05'),
(2, 'PP-57B879668743', 'Michael Jude', 'mike', '$2y$10$xWLkY8ZfGl/EB3QGI2N85ehD82YWWtLkCquxvaboL5f6f9T5mvoIS', 'mike mike', '09090909099', '2026-09-23 12:27:57');

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `table_id` int(11) NOT NULL,
  `table_number` varchar(20) NOT NULL,
  `qr_code` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`table_id`, `table_number`, `qr_code`, `created_at`) VALUES
(6, '1', 'TABLE-5106BE6AA861', '2026-09-23 14:18:37'),
(7, '2', 'TABLE-44730F40BBA4', '2026-09-23 14:18:40'),
(8, '3', 'TABLE-3BE8370D0EBA', '2026-09-23 14:18:42'),
(9, '4', 'TABLE-58C1696D7DE9', '2026-09-23 14:18:44'),
(10, '5', 'TABLE-ADF3F8A86B0A', '2026-09-23 14:18:46'),
(11, '6', 'TABLE-BD3D549E2E86', '2026-09-23 14:18:51'),
(13, '7', 'TABLE-47ABE184F28C', '2026-09-23 14:29:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_staff_per_day` (`staff_id`,`attendance_date`);

--
-- Indexes for table `cleaning_assignments`
--
ALTER TABLE `cleaning_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `idx_staff_date` (`staff_id`,`assigned_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_item` (`item_id`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `qr_code` (`qr_code`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`table_id`),
  ADD UNIQUE KEY `table_number` (`table_number`),
  ADD UNIQUE KEY `qr_code` (`qr_code`),
  ADD UNIQUE KEY `uk_table_number` (`table_number`),
  ADD UNIQUE KEY `uk_qr_code` (`qr_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cleaning_assignments`
--
ALTER TABLE `cleaning_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `table_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
