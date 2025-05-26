-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 08, 2025 at 07:48 AM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u754319832_bcrs`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `first_name`, `last_name`, `email`, `password`, `created_at`, `updated_at`) VALUES
(3, 'Admin', 'User', 'admin@bcrs.com', '$2y$10$zKN/DxZ5VQhNypeVuv2g9eJjox./N8dMs6JQZCzDBIz0v0CKmVbi.', '2025-05-01 15:53:02', '2025-05-01 15:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','read','responded') NOT NULL DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `masterlist`
--

CREATE TABLE `masterlist` (
  `id` int(11) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `age` int(3) NOT NULL,
  `year_of_residency` int(4) NOT NULL,
  `purok` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `masterlist`
--

INSERT INTO `masterlist` (`id`, `last_name`, `first_name`, `middle_name`, `contact_number`, `age`, `year_of_residency`, `purok`, `created_at`, `updated_at`) VALUES
(1, 'Algarme', 'Jovane', 'Bohol', '09894358077', 30, 2002, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(2, 'Alolor', 'Ej', 'Buanghug', '09582720519', 33, 2013, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(3, 'Amodia', 'Niña Via', 'Daniel', '09160414565', 64, 2019, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(4, 'Ando', 'Dante', 'Carloman', '09283599891', 62, 2009, '4', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(5, 'Añober', 'Kathleen Shanelle', 'Gilig', '09370865281', 45, 2006, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(6, 'Atillo', 'Vince', 'Arong', '09652319358', 61, 2008, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(7, 'Augusto', 'Aerl', 'Palallos', '09342711058', 47, 2019, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(8, 'Avergonzado', 'Kyla', 'Batabat', '09559311305', 25, 2008, '4', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(9, 'Baguio', 'Kristine Joy', 'Bacarisas', '09102672593', 31, 2003, '6', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(10, 'Barcenas', 'Dane Brian', 'Galagar', '09393904143', 38, 2022, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(11, 'Baricuatro', 'Jhon Dhy', 'Alvarado', '09376683913', 59, 2024, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(12, 'Barredo', 'John Lloyd', 'Solon', '09313107523', 52, 2001, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(13, 'Caberte', 'Nica', 'Jerusalem', '09630095512', 20, 2013, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(14, 'Conot', 'Evangeline', 'Casipong', '09734479795', 29, 2019, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(15, 'Dihayco', 'Joshua', NULL, '09249787599', 32, 2012, '4', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(16, 'Donaire', 'Elieboi', 'Sinugbujan', '09530767917', 63, 2022, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(17, 'Dungog', 'Ma. Teresita', 'Pahugot', '09474741847', 19, 2023, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(18, 'Ebasan', 'Kevin Raniel', 'Pasco', '09193384158', 56, 2013, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(19, 'Estrera', 'Jocel Ann', 'Bacalso', '09642895785', 56, 2016, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(20, 'Gabunada', 'Joshua', NULL, '09149403338', 31, 2021, '6', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(21, 'Gensch', 'Robert Walter', 'Aberion', '09812074277', 24, 2002, '4', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(22, 'Gomia', 'Claudette', 'Provido', '09393859825', 35, 2015, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(23, 'Guisado', 'Earl Bryan', 'Gelasing', '09640526749', 27, 2013, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(24, 'Juarez', 'Jose Ryan', 'Palomo', '09289737911', 33, 2024, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(25, 'Labadan', 'Jherminia', NULL, '09894906354', 32, 2020, '4', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(26, 'Langres', 'Noel Marie', 'Apas', '09933109118', 33, 2013, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(27, 'Mangubat', 'Joshua', 'Booc', '09267852091', 39, 2008, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(28, 'Manlegro', 'Kirt Ian', 'Mordise', '09466548159', 18, 2018, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(29, 'Maranga', 'Ivan Carlo', 'Dela Cerna', '09333122974', 30, 2019, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(30, 'Maringuran', 'Nic Bryle', 'Umpad', '09367076113', 30, 2022, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(31, 'Martel', 'Daryl', 'Oyao', '09812943514', 56, 2018, '6', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(32, 'Mendoza', 'Kyle', 'Cataylo', '09725013060', 56, 2017, '6', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(33, 'Nacorda', 'Catherine', 'Arong', '09374057365', 33, 2010, '6', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(34, 'Narciso', 'Earl Jonal', 'Lacbain', '09545448401', 46, 2013, '6', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(35, 'Nulla', 'Jaymar', 'Mawili', '09685185914', 54, 2009, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(36, 'Ondangan', 'John Brick', 'Bebanco', '09571086638', 32, 2003, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(37, 'Ortelano', 'Daniel', 'Decorion', '09748595560', 20, 2012, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(38, 'Oyao', 'Welard', 'Masong', '09489417516', 63, 2006, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(39, 'Pagula', 'Kert John', 'Siega', '09418830763', 36, 2017, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(40, 'Pongasi', 'John Emmanuel', 'Baguio', '09965019598', 60, 2002, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(41, 'Rosales', 'Manuel', 'Berdon', '09114084119', 28, 2016, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(42, 'Rosales', 'Niño James', 'Gaviola', '09531301594', 62, 2019, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(43, 'Sarona', 'Gio', 'Suerte', '09608046174', 61, 2021, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(44, 'Senining', 'Zoren Kharl', 'Remosil', '09800205906', 38, 2007, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(45, 'Tampus', 'John Jefferson', 'Camajalan', '09788681720', 51, 2014, '3', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(46, 'Tan', 'Jay Vence', 'Paguican', '09308129372', 58, 2009, '4', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(47, 'Teberio', 'Argie', 'Bagor', '09804602972', 20, 2011, '2', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(48, 'Villaflores', 'Basty Allan', 'Limpangug', '09404270712', 45, 2023, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(49, 'Villamor', 'Jenelle', 'Brufal', '09719288965', 29, 2012, '5', '2025-05-06 07:09:13', '2025-05-06 07:09:13'),
(50, 'Villaver', 'Darlyn', 'Tejada', '09633002410', 63, 2003, '1', '2025-05-06 07:09:13', '2025-05-06 07:09:13');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `link`, `is_read`, `created_at`) VALUES
(0, 5, 'Your payment for reservation #1 has been approved.', 'index.php?page=view_reservation&id=1', 1, '2025-05-07 00:11:42'),
(0, 5, 'Your payment for reservation #2 has been approved.', 'index.php?page=view_reservation&id=2', 0, '2025-05-07 00:16:52'),
(0, 5, 'Your reservation #2 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=2', 0, '2025-05-07 00:26:21'),
(0, 5, 'Your payment for reservation #3 has been approved.', 'index.php?page=view_reservation&id=3', 0, '2025-05-07 00:30:32'),
(0, 5, 'Your reservation #3 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=3', 0, '2025-05-07 00:31:24'),
(0, 4, 'Your payment for reservation #5 has been approved.', 'index.php?page=view_reservation&id=5', 0, '2025-05-07 00:36:02'),
(0, 5, 'Your reservation #1 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=1', 0, '2025-05-07 00:44:11'),
(0, 4, 'Your reservation #6 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=6', 0, '2025-05-07 01:58:57'),
(0, 6, 'Your payment for reservation #9 has been approved.', 'index.php?page=view_reservation&id=9', 1, '2025-05-07 07:21:59'),
(0, 7, 'Your payment for reservation #8 has been approved.', 'index.php?page=view_reservation&id=8', 1, '2025-05-07 12:49:30'),
(0, 11, 'Your payment for reservation #12 has been approved.', 'index.php?page=view_reservation&id=12', 1, '2025-05-07 21:09:20'),
(0, 11, 'Your payment for reservation #13 has been approved.', 'index.php?page=view_reservation&id=13', 1, '2025-05-07 21:14:09'),
(0, 16, 'Your payment for reservation #16 has been approved.', 'index.php?page=view_reservation&id=16', 1, '2025-05-08 00:11:30'),
(0, 15, 'Your payment for reservation #15 has been approved.', 'index.php?page=view_reservation&id=15', 1, '2025-05-08 00:11:44'),
(0, 17, 'Your payment for reservation #14 has been approved.', 'index.php?page=view_reservation&id=14', 1, '2025-05-08 00:11:56'),
(0, 17, 'Your payment for reservation #17 has been approved.', 'index.php?page=view_reservation&id=17', 1, '2025-05-08 00:35:05'),
(0, 3, 'New reservation request from Jenelle Villamor. Reservation ID: 20', 'index.php?page=admin&section=view_reservation&id=20', 1, '2025-05-08 00:46:31'),
(0, 3, 'New reservation request from Evangeline Conot. Reservation ID: 21', 'index.php?page=admin&section=view_reservation&id=21', 1, '2025-05-08 00:47:19'),
(0, 3, 'New reservation request from Jhon Dhy Baricuatro. Reservation ID: 22', 'index.php?page=admin&section=view_reservation&id=22', 1, '2025-05-08 01:01:58'),
(0, 3, 'New reservation request from Evangeline Conot. Reservation ID: 23', 'index.php?page=admin&section=view_reservation&id=23', 1, '2025-05-08 01:05:23'),
(0, 3, 'New reservation request from Evangeline Conot. Reservation ID: 24', 'index.php?page=admin&section=view_reservation&id=24', 1, '2025-05-08 01:06:26'),
(0, 3, 'New reservation request from Evangeline Conot. Reservation ID: 25', 'index.php?page=admin&section=view_reservation&id=25', 1, '2025-05-08 01:11:41'),
(0, 3, 'New reservation request from Evangeline Conot. Reservation ID: 26', 'index.php?page=admin&section=view_reservation&id=26', 1, '2025-05-08 01:12:54'),
(0, 3, 'New reservation request from Ma. Teresita Dungog. Reservation ID: 27', 'index.php?page=admin&section=view_reservation&id=27', 1, '2025-05-08 01:19:05'),
(0, 3, 'New reservation request from Ma. Teresita Dungog. Reservation ID: 28', 'index.php?page=admin&section=view_reservation&id=28', 1, '2025-05-08 02:25:20'),
(0, 3, 'New reservation request from Ma. Teresita Dungog. Reservation ID: 29', 'index.php?page=admin&section=view_reservation&id=29', 0, '2025-05-08 02:39:44'),
(0, 3, 'New reservation request from Jenelle Villamor. Reservation ID: 30', 'index.php?page=admin&section=view_reservation&id=30', 0, '2025-05-08 02:50:57'),
(0, 11, 'Your reservation #29 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=29', 1, '2025-05-08 02:54:07'),
(0, 11, 'Your reservation #29 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=29', 1, '2025-05-08 02:54:12'),
(0, 15, 'Your payment for reservation #30 has been approved.', 'index.php?page=view_reservation&id=30', 0, '2025-05-08 02:56:05'),
(0, 3, 'New reservation request from Ma. Teresita Dungog. Reservation ID: 31', 'index.php?page=admin&section=view_reservation&id=31', 0, '2025-05-08 03:06:49'),
(0, 11, 'Your reservation #31 has been marked as returned. Thank you for using our services!', 'index.php?page=view_reservation&id=31', 0, '2025-05-08 03:11:51'),
(0, 3, 'New reservation request from Ma. Teresita Dungog. Reservation ID: 32', 'index.php?page=admin&section=view_reservation&id=32', 0, '2025-05-08 03:25:25'),
(0, 11, 'Your payment for reservation #32 has been approved.', 'index.php?page=view_reservation&id=32', 0, '2025-05-08 03:25:57');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `address` text NOT NULL,
  `purok` varchar(255) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled','completed','for_delivery','for_pickup','picked_up','returned') NOT NULL DEFAULT 'pending',
  `payment_status` enum('not_required','pending','paid') NOT NULL DEFAULT 'not_required',
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` enum('admin','user') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `landmark`, `address`, `purok`, `start_datetime`, `end_datetime`, `purpose`, `status`, `payment_status`, `payment_amount`, `payment_proof`, `notes`, `created_at`, `updated_at`, `role`) VALUES
(31, 11, 'llcc', 'Canjulao Purok Kulo', 'Purok 1', '2025-05-11 08:00:00', '2025-05-11 12:00:00', '', 'completed', 'not_required', NULL, NULL, 'meeting', '2025-05-08 03:06:49', '2025-05-08 03:11:51', 'user'),
(32, 11, 'Kapilya', 'Canjulao Purok Kulo', 'Purok 1', '2025-05-12 08:00:00', '2025-05-12 17:00:00', '', 'picked_up', 'paid', NULL, '681c2425cfd6c_Screenshot_2025-05-08-08-32-27-51_ccc4ff946bf847a7c199bff6d87da37a.jpg', 'Meeting', '2025-05-08 03:25:25', '2025-05-08 03:51:45', 'user');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_items`
--

CREATE TABLE `reservation_items` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_items`
--

INSERT INTO `reservation_items` (`id`, `reservation_id`, `resource_id`, `quantity`, `created_at`) VALUES
(39, 31, 1, 20, '2025-05-08 03:06:49'),
(40, 32, 4, 1, '2025-05-08 03:25:25'),
(41, 32, 1, 20, '2025-05-08 03:25:25');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_status_history`
--

CREATE TABLE `reservation_status_history` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_by_admin_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_status_history`
--

INSERT INTO `reservation_status_history` (`id`, `reservation_id`, `status`, `notes`, `created_by_user_id`, `created_by_admin_id`, `created_at`) VALUES
(120, 31, 'pending', 'Reservation submitted', 11, NULL, '2025-05-08 03:06:49'),
(121, 31, 'approved', 'Reservation approved', NULL, 3, '2025-05-08 03:07:21'),
(122, 31, 'equipment_update', 'Updated Plastic Chairs quantity from 100 to 80 (deducted 20)', NULL, 3, '2025-05-08 03:07:21'),
(123, 31, 'for_delivery', 'Items set for delivery by administrator.', NULL, NULL, '2025-05-08 03:07:45'),
(124, 31, 'for_pickup', 'Items ready for pickup by user.', NULL, NULL, '2025-05-08 03:09:17'),
(125, 31, 'picked_up', 'Items have been picked up by the requester.', NULL, NULL, '2025-05-08 03:10:11'),
(126, 31, 'completed', 'Items marked as returned', NULL, 3, '2025-05-08 03:11:51'),
(127, 32, 'pending', 'Reservation submitted', 11, NULL, '2025-05-08 03:25:25'),
(128, 32, 'paid', 'Payment approved', NULL, 3, '2025-05-08 03:25:57'),
(129, 32, 'approved', 'None.', NULL, 3, '2025-05-08 03:26:12'),
(130, 32, 'equipment_update', 'Updated Plastic Chairs quantity from 100 to 80 (deducted 20)', NULL, 3, '2025-05-08 03:26:12'),
(131, 32, 'for_delivery', 'Items set for delivery by administrator.', NULL, NULL, '2025-05-08 03:26:33'),
(132, 32, 'for_pickup', 'Items ready for pickup by user.', NULL, NULL, '2025-05-08 03:50:13'),
(133, 32, 'picked_up', 'Items have been picked up by the requester.', NULL, NULL, '2025-05-08 03:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `category` enum('facility','equipment') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `availability` enum('available','reserved','maintenance') NOT NULL DEFAULT 'available',
  `requires_payment` tinyint(1) NOT NULL DEFAULT 0,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`id`, `name`, `description`, `category`, `quantity`, `status`, `availability`, `requires_payment`, `payment_amount`, `created_at`, `updated_at`, `image`) VALUES
(1, 'Plastic Chairs', 'Chairs', 'equipment', 80, 'active', 'available', 0, 0.00, '2025-05-06 13:12:31', '2025-05-08 03:26:12', 'uploads/resources/681a0abf4116c_1746537151.jpg'),
(2, 'Folding Tables', 'Tables', 'equipment', 7, 'active', 'available', 0, 0.00, '2025-05-06 13:14:03', '2025-05-08 02:27:52', 'uploads/resources/681a0b1b9d42b_1746537243.webp'),
(3, 'Tents', 'Tents', 'equipment', 50, 'active', 'available', 0, 0.00, '2025-05-06 13:15:20', '2025-05-07 01:58:57', 'uploads/resources/681a0b68eaf02_1746537320.webp'),
(4, 'Vehicles', 'Vehicles', 'facility', 6, 'active', 'reserved', 1, 500.00, '2025-05-06 13:16:53', '2025-05-08 04:28:57', 'uploads/resources/681a0bc5409ff_1746537413.webp'),
(5, 'Basketball Court', 'Gym', 'facility', 1, 'active', 'available', 1, 500.00, '2025-05-06 13:20:30', '2025-05-08 01:09:03', 'uploads/resources/681a0c9e34eef_1746537630.webp'),
(6, 'Sports Equipments', 'Equipments', 'equipment', 5, 'active', 'available', 0, 0.00, '2025-05-06 13:21:46', '2025-05-06 13:21:46', 'uploads/resources/681a0ceaaa8ad_1746537706.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'sms_enabled', '1', 'Toggle SMS notifications (0 = disabled, 1 = enabled)', '2025-05-07 19:14:41', '2025-05-07 21:10:41'),
(2, 'sms_api_key', 'bfedef40aa1c165c1a280911e28b5428', 'API key for SMS gateway service', '2025-05-07 19:14:41', '2025-05-07 19:25:40'),
(3, 'sms_sender_id', 'BARSERVE', 'Sender ID for SMS messages', '2025-05-07 19:14:41', '2025-05-07 19:25:40'),
(4, 'sms_admin_number', '09948033905', 'Admin phone number for SMS notifications', '2025-05-07 19:14:41', '2025-05-08 07:43:04'),
(5, 'sms_api_url', 'https://api.semaphore.co/api/v4/messages', NULL, '2025-05-07 19:25:40', '2025-05-07 19:27:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_initial` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `purok` varchar(50) NOT NULL,
  `id_type` varchar(50) NOT NULL,
  `id_image` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `id_proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'resident'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `middle_initial`, `email`, `password`, `contact_number`, `purok`, `id_type`, `id_image`, `address`, `id_proof`, `status`, `blacklisted`, `created_at`, `updated_at`, `role`) VALUES
(11, 'Ma. Teresita', 'Dungog', 'Pahugot', 'dungogmaria@gmail.com', '$2y$10$CMhiAO17eZ/SC/hMHjQTsu/56/myP6cLKpJHWAmXSadiA.KfZN8T6', '09927901140', 'Purok 1', 'National ID', '681bca80c8607_Messenger_creation_1624504958225827.jpeg', 'Canjulao Purok Kulo', NULL, 'approved', 0, '2025-05-07 21:02:56', '2025-05-07 21:05:05', 'resident'),
(15, 'Jenelle', 'Villamor', 'NA', 'Villamorjenelle@gmail.com', '$2y$10$mqLqapCDrlZZgEHx9VmM5uwhQuz4eCV1q2o6EZR35Urpt1RS649zu', '09876543212', 'Purok 1', 'National ID', '681bf19650f79_Capture.PNG', 'Barangay Looc', NULL, 'approved', 0, '2025-05-07 23:49:42', '2025-05-07 23:52:22', 'resident'),
(16, 'Evangeline', 'Conot', 'Casipong', 'Evaconot@gmail.com', '$2y$10$XawfDq1FifprEuR1hXgnlOZmmYRZEHWJoKBkFASEa5hG2TuLhQwWW', '09317838182', 'Purok 6', 'National ID', '681bf198dfece_download.jpg', 'ilawm', NULL, 'approved', 0, '2025-05-07 23:49:44', '2025-05-07 23:52:18', 'resident'),
(17, 'Jhon Dhy', 'Baricuatro', 'Alvarado', 'baricuatrojandi@gmail.com', '$2y$10$pcUGU.ZMwkdeK5c1YaSm9.avlDIJ0nZlq.1og1k3IXiBfYJLaXm3G', '09918346272', 'Purok 1', 'National ID', '681bf19a1b39c_494689398_2494347274262195_7054033596974246260_n.png', 'Kauswagan, Bankal Lapu Lapu City', NULL, 'approved', 0, '2025-05-07 23:49:46', '2025-05-07 23:52:37', 'resident');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `masterlist`
--
ALTER TABLE `masterlist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `reservation_status_history`
--
ALTER TABLE `reservation_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `created_by_admin_id` (`created_by_admin_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `masterlist`
--
ALTER TABLE `masterlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `reservation_items`
--
ALTER TABLE `reservation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `reservation_status_history`
--
ALTER TABLE `reservation_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD CONSTRAINT `reservation_items_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservation_items_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_status_history`
--
ALTER TABLE `reservation_status_history`
  ADD CONSTRAINT `reservation_status_history_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservation_status_history_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservation_status_history_ibfk_3` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
