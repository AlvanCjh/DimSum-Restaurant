-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 08:24 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 7.4.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `yobita_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `dining_tables`
--

CREATE TABLE `dining_tables` (
  `id` int(10) UNSIGNED NOT NULL,
  `table_number` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 4,
  `status` enum('available','occupied','reserved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dining_tables`
--

INSERT INTO `dining_tables` (`id`, `table_number`, `capacity`, `status`) VALUES
(1, 'T01', 4, 'available'),
(2, 'T02', 4, 'available'),
(3, 'T03', 4, 'available'),
(4, 'VIP1', 8, 'available'),
(5, 'VIP2', 8, 'available'),
(6, 'T04', 4, 'available'),
(7, 'T05', 4, 'available'),
(8, 'T06', 4, 'available'),
(9, 'VIP3', 8, 'available'),
(10, 'T07', 4, 'available'),
(11, 'T08', 4, 'available'),
(12, 'T09', 6, 'available'),
(13, 'T10', 6, 'available');

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`id`, `name`, `description`) VALUES
(1, 'Steamed Dim Sum', 'Freshly Steamed Dim Sum'),
(2, 'Porridge Rice Rolls', 'Silky steamed rice sheets rolled around savory fillings'),
(4, 'Fried Dim Sum', ''),
(5, 'Rice Dish', ''),
(6, 'Noodles', ''),
(7, 'Beverages', '');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`, `price`, `image_url`) VALUES
(1, 1, 'Chicken Siew Mai', 'A wonton wrapper wrapped with chicken meat and shrimp filling.', '7.80', 'uploads/menu/menu_69169b21b2ef74.81305960.jpg'),
(2, 1, 'Beancurd Roll', 'Seasoned pork and shrimp wrapped in a thin, fried tofu skin and steamed in a savory gravy.', '7.80', 'uploads/menu/menu_691a87159649d6.26965438.jpg'),
(3, 2, 'Rice Roll (Shrimp)', 'Steamed rice noodle rolls with succulent shrimp filling.', '7.80', 'uploads/menu/menu_691a8a4d9c5f78.37631660.png'),
(4, 6, 'Wok Hey Fried Noodle', 'Wok Hei Fried Noodles are characterized by their distinctive smoky, charred aroma and flavor, achieved through high-heat stir-frying in a wok', '19.00', 'uploads/menu/menu_691d1a5fa54c17.09865444.png'),
(5, 7, 'Jasmine Tea Pot', 'A traditional teapot filled with green tea leaves and fragrant jasmine blossoms that steep to release a soothing, floral aroma.', '5.80', 'uploads/menu/menu_6922def51385c8.58264371.png'),
(6, 4, 'Salad Prawn', 'Salad prawn features crispy, deep-fried shrimp dumplings served with a creamy, slightly sweet mayonnaise dipping sauce.', '8.80', 'uploads/menu/menu_6922e01f838953.19585583.png'),
(7, 5, 'YangZhou Fried Rice', 'Yangzhou fried rice is a classic, colorful wok-fried dish featuring fluffy rice tossed with diced BBQ pork, shrimp, egg, and scallions.', '19.00', 'uploads/menu/menu_6922e0ce9d0249.13310552.png'),
(8, 1, 'Chicken Feet', 'Steamed chicken feet, also known as \"Phoenix Claws,\" are tender, gelatinous claws braised in a savory sauce of fermented black beans and mild chili.', '6.80', 'uploads/menu/menu_6922e15e633360.57752262.png');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `table_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','prepared','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `table_id`, `user_id`, `status`, `total_amount`, `created_at`) VALUES
(1, 1, 1, 'completed', '42.00', '2025-11-14 03:16:08'),
(2, 3, 1, 'completed', '35.00', '2025-11-17 02:12:38'),
(3, 1, 1, 'completed', '54.60', '2025-11-17 04:57:15'),
(4, 4, 1, 'completed', '31.20', '2025-11-17 05:28:36'),
(5, 2, 1, 'completed', '23.40', '2025-11-17 05:30:44'),
(6, 4, 1, 'completed', '62.40', '2025-11-18 08:04:19'),
(7, 1, 1, 'completed', '46.80', '2025-11-18 08:12:01'),
(8, 2, 1, 'completed', '120.40', '2025-11-18 08:16:20'),
(9, 1, 4, 'completed', '73.60', '2025-11-19 01:33:53'),
(10, 1, 4, 'completed', '31.20', '2025-11-19 06:09:52'),
(11, 2, 4, 'completed', '34.60', '2025-11-22 01:57:51'),
(12, 3, 4, 'completed', '109.60', '2025-11-23 10:28:02'),
(13, 1, 4, 'completed', '32.20', '2025-11-24 00:40:31'),
(14, 4, 1, 'completed', '122.80', '2025-11-24 00:43:23'),
(15, 3, 4, 'completed', '51.60', '2025-11-24 00:43:59'),
(16, 2, 4, 'completed', '43.80', '2025-11-24 01:46:31'),
(17, 2, 5, 'completed', '76.00', '2025-11-24 02:53:14'),
(18, 3, 5, 'completed', '64.80', '2025-11-24 05:38:11'),
(19, 4, 5, 'completed', '98.40', '2025-11-26 05:07:02'),
(20, 2, 4, 'completed', '43.80', '2025-11-26 06:55:39'),
(21, 2, 1, 'completed', '30.20', '2025-11-27 01:23:17'),
(22, 1, 1, 'completed', '90.60', '2025-12-01 02:42:08'),
(23, 2, 1, 'completed', '82.80', '2025-12-01 02:42:23'),
(24, 3, 5, 'completed', '79.40', '2025-12-01 03:03:07'),
(25, 1, 5, 'completed', '45.80', '2025-12-01 03:08:36'),
(26, 1, 5, 'completed', '54.40', '2025-12-01 05:44:16'),
(27, 2, 5, 'completed', '43.80', '2025-12-01 05:55:14'),
(28, 2, 5, 'completed', '25.80', '2025-12-01 06:29:40'),
(29, 1, 1, 'completed', '56.00', '2025-12-01 08:44:43'),
(30, 4, 1, 'completed', '115.00', '2025-12-01 08:45:20'),
(31, 3, 1, 'completed', '63.80', '2025-12-04 23:56:36'),
(32, 4, 1, 'completed', '75.00', '2025-12-04 23:57:01'),
(33, 1, 5, 'completed', '61.40', '2025-12-05 00:24:00'),
(34, 2, 4, 'completed', '48.20', '2025-12-05 03:02:44'),
(35, 4, 5, 'completed', '52.60', '2025-12-07 04:44:29'),
(36, 4, 1, 'completed', '19.00', '2025-12-08 04:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `menu_item_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(8,2) NOT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `price`, `prepared_at`) VALUES
(3, 1, 1, 6, '7.00', NULL),
(4, 2, 1, 5, '7.00', NULL),
(5, 3, 3, 4, '7.80', NULL),
(6, 3, 1, 3, '7.80', NULL),
(7, 4, 3, 2, '7.80', NULL),
(8, 4, 2, 2, '7.80', NULL),
(9, 5, 3, 2, '7.80', NULL),
(10, 5, 2, 1, '7.80', NULL),
(11, 6, 3, 3, '7.80', NULL),
(12, 6, 2, 2, '7.80', NULL),
(13, 6, 1, 3, '7.80', NULL),
(14, 7, 3, 2, '7.80', NULL),
(15, 7, 2, 1, '7.80', NULL),
(16, 7, 1, 3, '7.80', NULL),
(17, 8, 3, 4, '7.80', NULL),
(18, 8, 2, 3, '7.80', NULL),
(19, 8, 1, 6, '7.80', NULL),
(20, 8, 4, 1, '19.00', NULL),
(21, 9, 4, 1, '19.00', NULL),
(22, 9, 3, 1, '7.80', NULL),
(23, 9, 2, 1, '7.80', NULL),
(24, 9, 1, 5, '7.80', NULL),
(25, 10, 3, 4, '7.80', NULL),
(26, 11, 4, 1, '19.00', NULL),
(27, 11, 3, 1, '7.80', NULL),
(28, 11, 2, 1, '7.80', NULL),
(29, 12, 6, 1, '8.80', NULL),
(30, 12, 5, 1, '5.80', NULL),
(31, 12, 4, 1, '19.00', NULL),
(32, 12, 3, 1, '7.80', NULL),
(33, 12, 1, 5, '7.80', NULL),
(34, 12, 8, 2, '6.80', NULL),
(35, 12, 2, 2, '7.80', NULL),
(36, 13, 6, 1, '8.80', NULL),
(37, 13, 1, 3, '7.80', NULL),
(38, 14, 8, 1, '6.80', NULL),
(39, 14, 7, 1, '19.00', NULL),
(40, 14, 6, 2, '8.80', NULL),
(41, 14, 5, 1, '5.80', NULL),
(42, 14, 4, 1, '19.00', NULL),
(43, 14, 3, 1, '7.80', NULL),
(44, 14, 2, 1, '7.80', NULL),
(45, 14, 1, 5, '7.80', NULL),
(46, 15, 8, 1, '6.80', NULL),
(47, 15, 5, 1, '5.80', NULL),
(48, 15, 3, 1, '7.80', NULL),
(49, 15, 2, 1, '7.80', NULL),
(50, 15, 1, 3, '7.80', NULL),
(51, 16, 7, 1, '19.00', NULL),
(52, 16, 5, 1, '5.80', NULL),
(53, 16, 4, 1, '19.00', NULL),
(54, 17, 6, 1, '8.80', NULL),
(55, 17, 5, 1, '5.80', NULL),
(56, 17, 4, 1, '19.00', NULL),
(57, 17, 1, 3, '7.80', NULL),
(58, 17, 7, 1, '19.00', NULL),
(59, 18, 6, 1, '8.80', NULL),
(60, 18, 1, 3, '7.80', NULL),
(61, 18, 5, 1, '5.80', NULL),
(62, 18, 3, 1, '7.80', NULL),
(63, 18, 4, 1, '19.00', NULL),
(64, 19, 8, 1, '6.80', NULL),
(65, 19, 7, 1, '19.00', NULL),
(66, 19, 6, 1, '8.80', NULL),
(67, 19, 5, 1, '5.80', NULL),
(68, 19, 4, 1, '19.00', NULL),
(69, 19, 3, 2, '7.80', NULL),
(70, 19, 2, 1, '7.80', NULL),
(71, 19, 1, 2, '7.80', NULL),
(72, 20, 7, 1, '19.00', NULL),
(73, 20, 5, 1, '5.80', NULL),
(74, 20, 4, 1, '19.00', NULL),
(75, 21, 6, 1, '8.80', NULL),
(76, 21, 5, 1, '5.80', NULL),
(77, 21, 1, 2, '7.80', NULL),
(78, 22, 5, 1, '5.80', '2025-12-01 02:42:39'),
(79, 22, 1, 3, '7.80', '2025-12-01 02:42:39'),
(80, 23, 7, 1, '19.00', '2025-12-01 02:42:40'),
(81, 23, 5, 1, '5.80', '2025-12-01 02:42:40'),
(82, 23, 4, 1, '19.00', '2025-12-01 02:42:40'),
(83, 22, 8, 1, '6.80', '2025-12-01 02:43:19'),
(84, 22, 6, 1, '8.80', '2025-12-01 02:43:19'),
(85, 22, 3, 1, '7.80', '2025-12-01 02:43:19'),
(86, 23, 1, 3, '7.80', '2025-12-01 02:43:20'),
(87, 22, 7, 1, '19.00', '2025-12-01 02:55:27'),
(88, 22, 4, 1, '19.00', '2025-12-01 02:55:27'),
(89, 23, 2, 2, '7.80', '2025-12-01 02:55:28'),
(90, 24, 1, 3, '7.80', '2025-12-01 03:03:21'),
(91, 24, 5, 1, '5.80', '2025-12-01 03:03:48'),
(92, 24, 4, 1, '19.00', '2025-12-01 03:03:48'),
(93, 24, 8, 1, '6.80', '2025-12-01 03:04:17'),
(94, 24, 6, 1, '8.80', '2025-12-01 03:06:24'),
(95, 24, 3, 2, '7.80', '2025-12-01 03:06:24'),
(96, 25, 6, 1, '8.80', '2025-12-01 03:09:09'),
(97, 25, 5, 1, '5.80', '2025-12-01 03:09:09'),
(98, 25, 3, 1, '7.80', '2025-12-01 03:09:09'),
(99, 25, 1, 3, '7.80', '2025-12-01 03:09:09'),
(100, 26, 8, 8, '6.80', '2025-12-01 05:44:28'),
(101, 27, 7, 1, '19.00', '2025-12-01 05:55:35'),
(102, 27, 5, 1, '5.80', '2025-12-01 05:55:35'),
(103, 27, 4, 1, '19.00', '2025-12-01 05:55:35'),
(104, 28, 8, 1, '6.80', '2025-12-01 06:29:50'),
(105, 28, 7, 1, '19.00', '2025-12-01 06:29:50'),
(106, 29, 5, 1, '5.80', '2025-12-01 08:51:33'),
(107, 29, 4, 1, '19.00', '2025-12-01 08:51:33'),
(108, 29, 3, 1, '7.80', '2025-12-01 08:51:33'),
(109, 29, 1, 3, '7.80', '2025-12-01 08:51:33'),
(110, 30, 8, 1, '6.80', '2025-12-01 08:51:25'),
(111, 30, 7, 1, '19.00', '2025-12-01 08:51:25'),
(112, 30, 6, 2, '8.80', '2025-12-01 08:51:25'),
(113, 30, 5, 1, '5.80', '2025-12-01 08:51:25'),
(114, 30, 4, 1, '19.00', '2025-12-01 08:51:25'),
(115, 30, 3, 1, '7.80', '2025-12-01 08:51:25'),
(116, 30, 2, 1, '7.80', '2025-12-01 08:51:25'),
(117, 30, 1, 4, '7.80', '2025-12-01 08:51:25'),
(118, 31, 6, 1, '8.80', '2025-12-05 00:05:48'),
(119, 31, 5, 1, '5.80', '2025-12-05 00:05:48'),
(120, 31, 1, 3, '7.80', '2025-12-05 00:05:48'),
(121, 32, 7, 1, '19.00', '2025-12-05 00:05:47'),
(122, 32, 5, 1, '5.80', '2025-12-05 00:05:47'),
(123, 32, 4, 1, '19.00', '2025-12-05 00:05:47'),
(124, 32, 2, 1, '7.80', '2025-12-05 00:05:47'),
(125, 31, 8, 1, '6.80', '2025-12-05 00:06:54'),
(126, 31, 4, 1, '19.00', '2025-12-05 00:06:54'),
(127, 32, 3, 1, '7.80', '2025-12-05 00:06:53'),
(128, 32, 1, 2, '7.80', '2025-12-05 00:06:53'),
(129, 33, 4, 2, '19.00', '2025-12-05 00:24:31'),
(130, 33, 1, 3, '7.80', '2025-12-05 00:24:31'),
(131, 34, 5, 1, '5.80', '2025-12-05 03:02:57'),
(132, 34, 4, 1, '19.00', '2025-12-05 03:02:57'),
(133, 34, 1, 3, '7.80', '2025-12-05 03:02:57'),
(134, 35, 8, 1, '6.80', '2025-12-07 04:44:40'),
(135, 35, 6, 1, '8.80', '2025-12-07 04:44:40'),
(136, 35, 5, 1, '5.80', '2025-12-07 04:44:40'),
(137, 35, 2, 1, '7.80', '2025-12-07 04:44:40'),
(138, 35, 1, 3, '7.80', '2025-12-07 04:44:40'),
(139, 36, 7, 1, '19.00', '2025-12-08 04:50:28');

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `calculate_running_total` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    UPDATE `orders`
    SET `total_amount` = `total_amount` + (NEW.price * NEW.quantity)
    WHERE `id` = NEW.order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `service_charge` decimal(10,2) NOT NULL,
  `sst` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','qr_pay') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `amount`, `subtotal`, `service_charge`, `sst`, `payment_method`, `payment_time`) VALUES
(1, 1, '47.04', '42.00', '2.52', '2.52', 'cash', '2025-11-17 02:04:55'),
(2, 2, '39.20', '35.00', '2.10', '2.10', '', '2025-11-17 02:13:03'),
(3, 3, '61.15', '54.60', '3.28', '3.28', 'cash', '2025-11-17 04:57:31'),
(4, 5, '26.21', '23.40', '1.40', '1.40', '', '2025-11-18 04:38:54'),
(5, 4, '34.94', '31.20', '1.87', '1.87', 'cash', '2025-11-18 04:39:12'),
(6, 6, '69.89', '62.40', '3.74', '3.74', 'cash', '2025-11-18 08:11:12'),
(7, 7, '52.42', '46.80', '2.81', '2.81', '', '2025-11-18 08:12:13'),
(8, 8, '134.85', '120.40', '7.22', '7.22', '', '2025-11-19 01:25:07'),
(9, 9, '82.43', '73.60', '4.42', '4.42', 'cash', '2025-11-19 01:34:13'),
(10, 10, '34.94', '31.20', '1.87', '1.87', '', '2025-11-19 06:10:10'),
(11, 11, '38.75', '34.60', '2.08', '2.08', '', '2025-11-22 01:57:58'),
(12, 12, '122.75', '109.60', '6.58', '6.58', 'cash', '2025-11-23 10:28:16'),
(13, 13, '36.06', '32.20', '1.93', '1.93', '', '2025-11-24 00:40:42'),
(14, 14, '137.54', '122.80', '7.37', '7.37', 'cash', '2025-11-24 00:43:33'),
(15, 15, '57.79', '51.60', '3.10', '3.10', '', '2025-11-24 00:44:06'),
(16, 16, '49.06', '43.80', '2.63', '2.63', 'cash', '2025-11-24 01:46:42'),
(17, 17, '85.12', '76.00', '4.56', '4.56', '', '2025-11-24 02:53:29'),
(18, 18, '72.58', '64.80', '3.89', '3.89', '', '2025-11-24 05:38:48'),
(19, 19, '110.21', '98.40', '5.90', '5.90', '', '2025-11-26 05:07:15'),
(20, 20, '49.06', '43.80', '2.63', '2.63', 'cash', '2025-11-26 11:13:36'),
(21, 21, '33.82', '30.20', '1.81', '1.81', '', '2025-11-27 01:23:30'),
(22, 23, '92.74', '82.80', '4.97', '4.97', '', '2025-12-01 02:59:00'),
(23, 22, '101.47', '90.60', '5.44', '5.44', 'cash', '2025-12-01 02:59:08'),
(24, 24, '88.93', '79.40', '4.76', '4.76', '', '2025-12-01 03:06:42'),
(25, 25, '51.30', '45.80', '2.75', '2.75', 'cash', '2025-12-01 03:09:26'),
(26, 26, '60.93', '54.40', '3.26', '3.26', '', '2025-12-01 05:44:39'),
(27, 27, '49.06', '43.80', '2.63', '2.63', '', '2025-12-01 05:55:46'),
(28, 28, '28.90', '25.80', '1.55', '1.55', '', '2025-12-01 06:30:30'),
(29, 30, '128.80', '115.00', '6.90', '6.90', '', '2025-12-02 07:52:19'),
(30, 29, '62.72', '56.00', '3.36', '3.36', '', '2025-12-02 07:52:28'),
(31, 32, '84.00', '75.00', '4.50', '4.50', 'cash', '2025-12-05 00:07:11'),
(32, 31, '71.46', '63.80', '3.83', '3.83', '', '2025-12-05 00:07:19'),
(33, 33, '68.77', '61.40', '3.68', '3.68', 'cash', '2025-12-05 00:24:38'),
(34, 34, '53.98', '48.20', '2.89', '2.89', '', '2025-12-05 03:03:04'),
(35, 35, '58.91', '52.60', '3.16', '3.16', 'cash', '2025-12-07 04:44:50'),
(36, 36, '21.28', '19.00', '1.14', '1.14', 'cash', '2025-12-08 05:00:16');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `check_full_payment` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE total_paid DECIMAL(10,2);
    DECLARE order_grand_total DECIMAL(10,2);

    -- Calculate total paid so far (handling NULLs)
    SELECT COALESCE(SUM(amount), 0) INTO total_paid 
    FROM payments 
    WHERE order_id = NEW.order_id;

    -- Get the order's actual grand total (Logic from Alternate DB: Base + 6% + 6%)
    SELECT (total_amount + (total_amount * 0.06) + (total_amount * 0.06)) 
    INTO order_grand_total 
    FROM orders 
    WHERE id = NEW.order_id;

    -- If paid enough, close the order and free the table
    IF total_paid >= order_grand_total THEN
        UPDATE `orders`
        SET `status` = 'completed'
        WHERE `id` = NEW.order_id;

        UPDATE `dining_tables`
        SET `status` = 'available'
        WHERE `id` = (
            SELECT `table_id` 
            FROM `orders` 
            WHERE `id` = NEW.order_id
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE `staffs` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('waiter','admin','chef') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiter',
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staffs`
--

INSERT INTO `staffs` (`id`, `username`, `email`, `password`, `role`, `profile_picture`, `created_at`) VALUES
(1, 'Chel', 'Chel@yobita.com', '$2y$10$JDRBgVKgrvEocAOtHjjaZOjxjavaXpCYio3kZUoqUu1tifc8Qm8pG', 'waiter', 'uploads/profile/profile_1_1763949548.png', '2025-11-12 05:16:57'),
(2, 'Ciaa', 'Ciaa@Admin.com', '$2y$10$uaRt5aCgNhnbgTStbbakB.0C4ljnhUFO6Y0zdTOJl2xAzmIx3K8ZK', 'admin', 'uploads/profile/profile_2_1763949802.png', '2025-11-12 07:01:25'),
(4, 'Tray', 'Tray@yobita.com', '$2y$10$v9efxdxJ5AyexEGSxjvEgOjIoYEe8hD1dE32Vpp5ER2BzeVDbWTii', 'waiter', 'uploads/profile/profile_4_1763949590.png', '2025-11-19 01:33:18'),
(5, 'Kyzh', 'Kyzh@yobita.com', '$2y$10$i/wmhDvrYM8JHjSrWZ85Bu69vFRkRa3FOn/oBYydl0xRooKou69nS', 'waiter', 'uploads/profile/profile_5_1763952565.png', '2025-11-24 02:48:56'),
(6, 'Ramsay', 'Ramsay@yobita.com', '$2y$10$bVPYGP1ZytZT2lv0tD1H7e8KTz4hhSP9k65jbjklchqDWfb4xHgM6', 'chef', 'uploads/profile/profile_6_1764557911.png', '2025-11-27 01:14:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dining_tables`
--
ALTER TABLE `dining_tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `table_number` (`table_number`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_to_category` (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_table` (`table_id`),
  ADD KEY `fk_order_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_order` (`order_id`),
  ADD KEY `fk_items_menu` (`menu_item_id`),
  ADD KEY `idx_prepared_at` (`prepared_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payment_order` (`order_id`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dining_tables`
--
ALTER TABLE `dining_tables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `staffs`
--
ALTER TABLE `staffs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_item_to_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`) REFERENCES `dining_tables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `staffs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
