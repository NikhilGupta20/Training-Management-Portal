-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2025 at 04:42 PM
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
-- Database: `ncrb_training`
--

-- --------------------------------------------------------

--
-- Table structure for table `accepted_participants`
--

CREATE TABLE `accepted_participants` (
  `id` int(6) UNSIGNED NOT NULL,
  `registration_id` varchar(50) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `participant_name_hindi` varchar(255) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_name_hindi` varchar(50) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `rank_hindi` varchar(100) NOT NULL,
  `other_rank` varchar(100) DEFAULT NULL,
  `other_rank_hindi` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `qualifications` text NOT NULL,
  `residential_address` text NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_name_hindi` varchar(100) NOT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `official_address` text DEFAULT NULL,
  `residential_phone` varchar(15) NOT NULL,
  `delhi_address` text DEFAULT NULL,
  `course_expectation` text NOT NULL,
  `officer_designation` varchar(100) NOT NULL,
  `officer_phone` varchar(15) DEFAULT NULL,
  `officer_address` text DEFAULT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_credentials`
--

CREATE TABLE `admin_credentials` (
  `id` int(6) UNSIGNED NOT NULL,
  `admin_username` varchar(255) NOT NULL,
  `admin_password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aibe_exam_marks`
--

CREATE TABLE `aibe_exam_marks` (
  `id` int(6) UNSIGNED NOT NULL,
  `registration_id` int(6) UNSIGNED NOT NULL,
  `theory_marks` decimal(5,2) NOT NULL,
  `practical_marks` decimal(5,2) NOT NULL,
  `viva_marks` decimal(5,2) NOT NULL,
  `theory_max` decimal(5,2) NOT NULL DEFAULT 100.00,
  `practical_max` decimal(5,2) NOT NULL DEFAULT 100.00,
  `viva_max` decimal(5,2) NOT NULL DEFAULT 100.00,
  `total_marks` decimal(5,2) GENERATED ALWAYS AS (`theory_marks` + `practical_marks` + `viva_marks`) STORED,
  `total_max` decimal(5,2) GENERATED ALWAYS AS (`theory_max` + `practical_max` + `viva_max`) STORED,
  `percentage` decimal(5,2) GENERATED ALWAYS AS ((`theory_marks` + `practical_marks` + `viva_marks`) * 100 / (`theory_max` + `practical_max` + `viva_max`)) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aibe_reappear_marks`
--

CREATE TABLE `aibe_reappear_marks` (
  `id` int(6) UNSIGNED NOT NULL,
  `registration_id` int(6) UNSIGNED NOT NULL,
  `subject` enum('theory','practical','viva') NOT NULL,
  `marks` decimal(5,2) NOT NULL,
  `max_marks` decimal(5,2) NOT NULL DEFAULT 100.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_heads`
--

CREATE TABLE `budget_heads` (
  `id` int(11) NOT NULL,
  `head_code` varchar(50) NOT NULL,
  `head_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_name_hindi` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `category_name_hindi`) VALUES
(1, 'Police', 'पुलिस'),
(2, 'Judicial', 'न्यायिक'),
(3, 'Prison', 'जेल'),
(4, 'Prosecution', 'अभियोजन');

-- --------------------------------------------------------

--
-- Table structure for table `course_materials`
--

CREATE TABLE `course_materials` (
  `material_id` int(11) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `event_id` int(6) UNSIGNED DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_general` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `district_id` int(11) NOT NULL,
  `district_name` varchar(100) NOT NULL,
  `state_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`district_id`, `district_name`, `state_name`) VALUES
(1, 'Anantapur', 'Andhra Pradesh'),
(2, 'Chittoor', 'Andhra Pradesh'),
(3, 'East Godavari', 'Andhra Pradesh'),
(4, 'Guntur', 'Andhra Pradesh'),
(5, 'Krishna', 'Andhra Pradesh'),
(6, 'Kurnool', 'Andhra Pradesh'),
(7, 'Nellore', 'Andhra Pradesh'),
(8, 'Prakasam', 'Andhra Pradesh'),
(9, 'Srikakulam', 'Andhra Pradesh'),
(10, 'Visakhapatnam', 'Andhra Pradesh'),
(11, 'Vizianagaram', 'Andhra Pradesh'),
(12, 'West Godavari', 'Andhra Pradesh'),
(13, 'YSR Kadapa', 'Andhra Pradesh'),
(14, 'Parvathipuram Manyam', 'Andhra Pradesh'),
(15, 'Alluri Sitharama Raju', 'Andhra Pradesh'),
(16, 'Nandyal', 'Andhra Pradesh'),
(17, 'Bapatla', 'Andhra Pradesh'),
(18, 'Palnadu', 'Andhra Pradesh'),
(19, 'Annamayya', 'Andhra Pradesh'),
(20, 'Kona Seema', 'Andhra Pradesh'),
(21, 'Eluru', 'Andhra Pradesh'),
(22, 'NTR', 'Andhra Pradesh'),
(23, 'Tawang', 'Arunachal Pradesh'),
(24, 'West Kameng', 'Arunachal Pradesh'),
(25, 'East Kameng', 'Arunachal Pradesh'),
(26, 'Papum Pare', 'Arunachal Pradesh'),
(27, 'Kurung Kumey', 'Arunachal Pradesh'),
(28, 'Upper Subansiri', 'Arunachal Pradesh'),
(29, 'Lower Subansiri', 'Arunachal Pradesh'),
(30, 'West Siang', 'Arunachal Pradesh'),
(31, 'East Siang', 'Arunachal Pradesh'),
(32, 'Upper Siang', 'Arunachal Pradesh'),
(33, 'Lower Dibang Valley', 'Arunachal Pradesh'),
(34, 'Dibang Valley', 'Arunachal Pradesh'),
(35, 'Anjaw', 'Arunachal Pradesh'),
(36, 'Lohit', 'Arunachal Pradesh'),
(37, 'Namsai', 'Arunachal Pradesh'),
(38, 'Changlang', 'Arunachal Pradesh'),
(39, 'Tirap', 'Arunachal Pradesh'),
(40, 'Longding', 'Arunachal Pradesh'),
(41, 'Baksa', 'Assam'),
(42, 'Barpeta', 'Assam'),
(43, 'Biswanath', 'Assam'),
(44, 'Bongaigaon', 'Assam'),
(45, 'Cachar', 'Assam'),
(46, 'Charaideo', 'Assam'),
(47, 'Chirang', 'Assam'),
(48, 'Darrang', 'Assam'),
(49, 'Dhemaji', 'Assam'),
(50, 'Dhubri', 'Assam'),
(51, 'Dibrugarh', 'Assam'),
(52, 'Dima Hasao', 'Assam'),
(53, 'Goalpara', 'Assam'),
(54, 'Golaghat', 'Assam'),
(55, 'Hailakandi', 'Assam'),
(56, 'Hojai', 'Assam'),
(57, 'Jorhat', 'Assam'),
(58, 'Kamrup Metropolitan', 'Assam'),
(59, 'Kamrup', 'Assam'),
(60, 'Karbi Anglong', 'Assam'),
(61, 'Karimganj', 'Assam'),
(62, 'Kokrajhar', 'Assam'),
(63, 'Lakhimpur', 'Assam'),
(64, 'Majuli', 'Assam'),
(65, 'Morigaon', 'Assam'),
(66, 'Nagaon', 'Assam'),
(67, 'Nalbari', 'Assam'),
(68, 'Sivasagar', 'Assam'),
(69, 'Sonitpur', 'Assam'),
(70, 'South Salmara-Mankachar', 'Assam'),
(71, 'Tinsukia', 'Assam'),
(72, 'Udalguri', 'Assam'),
(73, 'West Karbi Anglong', 'Assam'),
(74, 'Araria', 'Bihar'),
(75, 'Arwal', 'Bihar'),
(76, 'Aurangabad', 'Bihar'),
(77, 'Banka', 'Bihar'),
(78, 'Begusarai', 'Bihar'),
(79, 'Bhagalpur', 'Bihar'),
(80, 'Bhojpur', 'Bihar'),
(81, 'Buxar', 'Bihar'),
(82, 'Darbhanga', 'Bihar'),
(83, 'East Champaran', 'Bihar'),
(84, 'Gaya', 'Bihar'),
(85, 'Gopalganj', 'Bihar'),
(86, 'Jamui', 'Bihar'),
(87, 'Jehanabad', 'Bihar'),
(88, 'Kaimur', 'Bihar'),
(89, 'Katihar', 'Bihar'),
(90, 'Khagaria', 'Bihar'),
(91, 'Kishanganj', 'Bihar'),
(92, 'Lakhisarai', 'Bihar'),
(93, 'Madhepura', 'Bihar'),
(94, 'Madhubani', 'Bihar'),
(95, 'Munger', 'Bihar'),
(96, 'Muzaffarpur', 'Bihar'),
(97, 'Nalanda', 'Bihar'),
(98, 'Nawada', 'Bihar'),
(99, 'Patna', 'Bihar'),
(100, 'Purnia', 'Bihar'),
(101, 'Rohtas', 'Bihar'),
(102, 'Saharsa', 'Bihar'),
(103, 'Samastipur', 'Bihar'),
(104, 'Saran', 'Bihar'),
(105, 'Sheikhpura', 'Bihar'),
(106, 'Sheohar', 'Bihar'),
(107, 'Sitamarhi', 'Bihar'),
(108, 'Siwan', 'Bihar'),
(109, 'Supaul', 'Bihar'),
(110, 'Vaishali', 'Bihar'),
(111, 'West Champaran', 'Bihar'),
(112, 'Balod', 'Chhattisgarh'),
(113, 'Baloda Bazar', 'Chhattisgarh'),
(114, 'Balrampur', 'Chhattisgarh'),
(115, 'Bastar', 'Chhattisgarh'),
(116, 'Bemetara', 'Chhattisgarh'),
(117, 'Bijapur', 'Chhattisgarh'),
(118, 'Bilaspur', 'Chhattisgarh'),
(119, 'Dantewada', 'Chhattisgarh'),
(120, 'Dhamtari', 'Chhattisgarh'),
(121, 'Durg', 'Chhattisgarh'),
(122, 'Gariaband', 'Chhattisgarh'),
(123, 'Janjgir-Champa', 'Chhattisgarh'),
(124, 'Jashpur', 'Chhattisgarh'),
(125, 'Kabirdham', 'Chhattisgarh'),
(126, 'Kanker', 'Chhattisgarh'),
(127, 'Kondagaon', 'Chhattisgarh'),
(128, 'Korba', 'Chhattisgarh'),
(129, 'Koriya', 'Chhattisgarh'),
(130, 'Mahasamund', 'Chhattisgarh'),
(131, 'Mungeli', 'Chhattisgarh'),
(132, 'Narayanpur', 'Chhattisgarh'),
(133, 'Raigarh', 'Chhattisgarh'),
(134, 'Raipur', 'Chhattisgarh'),
(135, 'Rajnandgaon', 'Chhattisgarh'),
(136, 'Sukma', 'Chhattisgarh'),
(137, 'Surajpur', 'Chhattisgarh'),
(138, 'Surguja', 'Chhattisgarh'),
(139, 'North Goa', 'Goa'),
(140, 'South Goa', 'Goa'),
(141, 'Ahmedabad', 'Gujarat'),
(142, 'Amreli', 'Gujarat'),
(143, 'Anand', 'Gujarat'),
(144, 'Aravalli', 'Gujarat'),
(145, 'Banaskantha', 'Gujarat'),
(146, 'Bharuch', 'Gujarat'),
(147, 'Bhavnagar', 'Gujarat'),
(148, 'Botad', 'Gujarat'),
(149, 'Chhota Udaipur', 'Gujarat'),
(150, 'Dahod', 'Gujarat'),
(151, 'Dang', 'Gujarat'),
(152, 'Devbhoomi Dwarka', 'Gujarat'),
(153, 'Gandhinagar', 'Gujarat'),
(154, 'Gir Somnath', 'Gujarat'),
(155, 'Jamnagar', 'Gujarat'),
(156, 'Junagadh', 'Gujarat'),
(157, 'Kachchh', 'Gujarat'),
(158, 'Kheda', 'Gujarat'),
(159, 'Mahisagar', 'Gujarat'),
(160, 'Mehsana', 'Gujarat'),
(161, 'Morbi', 'Gujarat'),
(162, 'Narmada', 'Gujarat'),
(163, 'Navsari', 'Gujarat'),
(164, 'Panchmahal', 'Gujarat'),
(165, 'Patan', 'Gujarat'),
(166, 'Porbandar', 'Gujarat'),
(167, 'Rajkot', 'Gujarat'),
(168, 'Sabarkantha', 'Gujarat'),
(169, 'Surat', 'Gujarat'),
(170, 'Surendranagar', 'Gujarat'),
(171, 'Tapi', 'Gujarat'),
(172, 'Vadodara', 'Gujarat'),
(173, 'Valsad', 'Gujarat'),
(174, 'Ambala', 'Haryana'),
(175, 'Bhiwani', 'Haryana'),
(176, 'Charkhi Dadri', 'Haryana'),
(177, 'Faridabad', 'Haryana'),
(178, 'Fatehabad', 'Haryana'),
(179, 'Gurgaon', 'Haryana'),
(180, 'Hisar', 'Haryana'),
(181, 'Jhajjar', 'Haryana'),
(182, 'Jind', 'Haryana'),
(183, 'Kaithal', 'Haryana'),
(184, 'Karnal', 'Haryana'),
(185, 'Kurukshetra', 'Haryana'),
(186, 'Mahendragarh', 'Haryana'),
(187, 'Nuh', 'Haryana'),
(188, 'Palwal', 'Haryana'),
(189, 'Panchkula', 'Haryana'),
(190, 'Panipat', 'Haryana'),
(191, 'Rewari', 'Haryana'),
(192, 'Rohtak', 'Haryana'),
(193, 'Sirsa', 'Haryana'),
(194, 'Sonipat', 'Haryana'),
(195, 'Yamunanagar', 'Haryana'),
(230, 'Chamba', 'Himachal Pradesh'),
(231, 'Hamirpur', 'Himachal Pradesh'),
(232, 'Kangra', 'Himachal Pradesh'),
(233, 'Kinnaur', 'Himachal Pradesh'),
(234, 'Kullu', 'Himachal Pradesh'),
(235, 'Lahaul and Spiti', 'Himachal Pradesh'),
(236, 'Mandi', 'Himachal Pradesh'),
(237, 'Shimla', 'Himachal Pradesh'),
(238, 'Sirmaur', 'Himachal Pradesh'),
(239, 'Solan', 'Himachal Pradesh'),
(240, 'Una', 'Himachal Pradesh'),
(241, 'Bokaro', 'Jharkhand'),
(242, 'Chatra', 'Jharkhand'),
(243, 'Deoghar', 'Jharkhand'),
(244, 'Dhanbad', 'Jharkhand'),
(245, 'Dumka', 'Jharkhand'),
(246, 'East Singhbhum', 'Jharkhand'),
(247, 'Garhwa', 'Jharkhand'),
(248, 'Giridih', 'Jharkhand'),
(249, 'Godda', 'Jharkhand'),
(250, 'Gumla', 'Jharkhand'),
(251, 'Hazaribagh', 'Jharkhand'),
(252, 'Jamtara', 'Jharkhand'),
(253, 'Khunti', 'Jharkhand'),
(254, 'Koderma', 'Jharkhand'),
(255, 'Latehar', 'Jharkhand'),
(256, 'Lohardaga', 'Jharkhand'),
(257, 'Pakur', 'Jharkhand'),
(258, 'Palamu', 'Jharkhand'),
(259, 'Ramgarh', 'Jharkhand'),
(260, 'Ranchi', 'Jharkhand'),
(261, 'Sahibganj', 'Jharkhand'),
(262, 'Saraikela Kharsawan', 'Jharkhand'),
(263, 'Simdega', 'Jharkhand'),
(264, 'Bagalkot', 'Karnataka'),
(265, 'Ballari', 'Karnataka'),
(266, 'Belagavi', 'Karnataka'),
(267, 'Bengaluru Rural', 'Karnataka'),
(268, 'Bengaluru Urban', 'Karnataka'),
(269, 'Bidar', 'Karnataka'),
(270, 'Chamarajanagar', 'Karnataka'),
(271, 'Chikballapur', 'Karnataka'),
(272, 'Chikkamagaluru', 'Karnataka'),
(273, 'Chitradurga', 'Karnataka'),
(274, 'Dakshina Kannada', 'Karnataka'),
(275, 'Davanagere', 'Karnataka'),
(276, 'Dharwad', 'Karnataka'),
(277, 'Gadag', 'Karnataka'),
(278, 'Hassan', 'Karnataka'),
(279, 'Haveri', 'Karnataka'),
(280, 'Kalaburagi', 'Karnataka'),
(281, 'Kodagu', 'Karnataka'),
(282, 'Kolar', 'Karnataka'),
(283, 'Koppal', 'Karnataka'),
(284, 'Mandya', 'Karnataka'),
(285, 'Mysuru', 'Karnataka'),
(286, 'Raichur', 'Karnataka'),
(287, 'Ramanagara', 'Karnataka'),
(288, 'Shivamogga', 'Karnataka'),
(289, 'Tumakuru', 'Karnataka'),
(290, 'Udupi', 'Karnataka'),
(291, 'Uttara Kannada', 'Karnataka'),
(292, 'Vijayanagara', 'Karnataka'),
(293, 'Yadgir', 'Karnataka'),
(294, 'Alappuzha', 'Kerala'),
(295, 'Ernakulam', 'Kerala'),
(296, 'Idukki', 'Kerala'),
(297, 'Kannur', 'Kerala'),
(298, 'Kasaragod', 'Kerala'),
(299, 'Kollam', 'Kerala'),
(300, 'Kottayam', 'Kerala'),
(301, 'Kozhikode', 'Kerala'),
(302, 'Malappuram', 'Kerala'),
(303, 'Palakkad', 'Kerala'),
(304, 'Pathanamthitta', 'Kerala'),
(305, 'Thiruvananthapuram', 'Kerala'),
(306, 'Thrissur', 'Kerala'),
(307, 'Wayanad', 'Kerala'),
(308, 'Agar Malwa', 'Madhya Pradesh'),
(309, 'Alirajpur', 'Madhya Pradesh'),
(310, 'Anuppur', 'Madhya Pradesh'),
(311, 'Ashoknagar', 'Madhya Pradesh'),
(312, 'Balaghat', 'Madhya Pradesh'),
(313, 'Barwani', 'Madhya Pradesh'),
(314, 'Betul', 'Madhya Pradesh'),
(315, 'Bhind', 'Madhya Pradesh'),
(316, 'Bhopal', 'Madhya Pradesh'),
(317, 'Burhanpur', 'Madhya Pradesh'),
(318, 'Chhatarpur', 'Madhya Pradesh'),
(319, 'Chhindwara', 'Madhya Pradesh'),
(320, 'Damoh', 'Madhya Pradesh'),
(321, 'Datia', 'Madhya Pradesh'),
(322, 'Dewas', 'Madhya Pradesh'),
(323, 'Dhar', 'Madhya Pradesh'),
(324, 'Dindori', 'Madhya Pradesh'),
(325, 'Guna', 'Madhya Pradesh'),
(326, 'Gwalior', 'Madhya Pradesh'),
(327, 'Harda', 'Madhya Pradesh'),
(328, 'Hoshangabad', 'Madhya Pradesh'),
(329, 'Indore', 'Madhya Pradesh'),
(330, 'Jabalpur', 'Madhya Pradesh'),
(331, 'Jhabua', 'Madhya Pradesh'),
(332, 'Katni', 'Madhya Pradesh'),
(333, 'Khandwa', 'Madhya Pradesh'),
(334, 'Khargone', 'Madhya Pradesh'),
(335, 'Mandla', 'Madhya Pradesh'),
(336, 'Mandsaur', 'Madhya Pradesh'),
(337, 'Morena', 'Madhya Pradesh'),
(338, 'Narsinghpur', 'Madhya Pradesh'),
(339, 'Neemuch', 'Madhya Pradesh'),
(340, 'Panna', 'Madhya Pradesh'),
(341, 'Raisen', 'Madhya Pradesh'),
(342, 'Rajgarh', 'Madhya Pradesh'),
(343, 'Ratlam', 'Madhya Pradesh'),
(344, 'Rewa', 'Madhya Pradesh'),
(345, 'Sagar', 'Madhya Pradesh'),
(346, 'Satna', 'Madhya Pradesh'),
(347, 'Sehore', 'Madhya Pradesh'),
(348, 'Seoni', 'Madhya Pradesh'),
(349, 'Shahdol', 'Madhya Pradesh'),
(350, 'Shajapur', 'Madhya Pradesh'),
(351, 'Sheopur', 'Madhya Pradesh'),
(352, 'Shivpuri', 'Madhya Pradesh'),
(353, 'Sidhi', 'Madhya Pradesh'),
(354, 'Singrauli', 'Madhya Pradesh'),
(355, 'Tikamgarh', 'Madhya Pradesh'),
(356, 'Ujjain', 'Madhya Pradesh'),
(357, 'Umaria', 'Madhya Pradesh'),
(358, 'Vidisha', 'Madhya Pradesh'),
(395, 'Ahmednagar', 'Maharashtra'),
(396, 'Akola', 'Maharashtra'),
(397, 'Amravati', 'Maharashtra'),
(398, 'Beed', 'Maharashtra'),
(399, 'Bhandara', 'Maharashtra'),
(400, 'Buldhana', 'Maharashtra'),
(401, 'Chandrapur', 'Maharashtra'),
(402, 'Dhule', 'Maharashtra'),
(403, 'Gadchiroli', 'Maharashtra'),
(404, 'Gondia', 'Maharashtra'),
(405, 'Hingoli', 'Maharashtra'),
(406, 'Jalgaon', 'Maharashtra'),
(407, 'Jalna', 'Maharashtra'),
(408, 'Kolhapur', 'Maharashtra'),
(409, 'Latur', 'Maharashtra'),
(410, 'Mumbai City', 'Maharashtra'),
(411, 'Mumbai Suburban', 'Maharashtra'),
(412, 'Nagpur', 'Maharashtra'),
(413, 'Nanded', 'Maharashtra'),
(414, 'Nandurbar', 'Maharashtra'),
(415, 'Nashik', 'Maharashtra'),
(416, 'Osmanabad', 'Maharashtra'),
(417, 'Palghar', 'Maharashtra'),
(418, 'Parbhani', 'Maharashtra'),
(419, 'Pune', 'Maharashtra'),
(420, 'Raigad', 'Maharashtra'),
(421, 'Ratnagiri', 'Maharashtra'),
(422, 'Sangli', 'Maharashtra'),
(423, 'Satara', 'Maharashtra'),
(424, 'Sindhudurg', 'Maharashtra'),
(425, 'Solapur', 'Maharashtra'),
(426, 'Thane', 'Maharashtra'),
(427, 'Wardha', 'Maharashtra'),
(428, 'Washim', 'Maharashtra'),
(429, 'Yavatmal', 'Maharashtra'),
(430, 'Bishnupur', 'Manipur'),
(431, 'Chandel', 'Manipur'),
(432, 'Churachandpur', 'Manipur'),
(433, 'Imphal East', 'Manipur'),
(434, 'Imphal West', 'Manipur'),
(435, 'Jiribam', 'Manipur'),
(436, 'Kakching', 'Manipur'),
(437, 'Kamjong', 'Manipur'),
(438, 'Kangpokpi', 'Manipur'),
(439, 'Noney', 'Manipur'),
(440, 'Pherzawl', 'Manipur'),
(441, 'Senapati', 'Manipur'),
(442, 'Tamenglong', 'Manipur'),
(443, 'Tengnoupal', 'Manipur'),
(444, 'Thoubal', 'Manipur'),
(445, 'Ukhrul', 'Manipur'),
(446, 'East Garo Hills', 'Meghalaya'),
(447, 'East Jaintia Hills', 'Meghalaya'),
(448, 'East Khasi Hills', 'Meghalaya'),
(449, 'North Garo Hills', 'Meghalaya'),
(450, 'Ri Bhoi', 'Meghalaya'),
(451, 'South Garo Hills', 'Meghalaya'),
(452, 'South West Garo Hills', 'Meghalaya'),
(453, 'South West Khasi Hills', 'Meghalaya'),
(454, 'West Garo Hills', 'Meghalaya'),
(455, 'West Jaintia Hills', 'Meghalaya'),
(456, 'West Khasi Hills', 'Meghalaya'),
(457, 'Aizawl', 'Mizoram'),
(458, 'Champhai', 'Mizoram'),
(459, 'Kolasib', 'Mizoram'),
(460, 'Lawngtlai', 'Mizoram'),
(461, 'Lunglei', 'Mizoram'),
(462, 'Mamit', 'Mizoram'),
(463, 'Saiha', 'Mizoram'),
(464, 'Serchhip', 'Mizoram'),
(465, 'Dimapur', 'Nagaland'),
(466, 'Kiphire', 'Nagaland'),
(467, 'Kohima', 'Nagaland'),
(468, 'Longleng', 'Nagaland'),
(469, 'Mokokchung', 'Nagaland'),
(470, 'Mon', 'Nagaland'),
(471, 'Peren', 'Nagaland'),
(472, 'Phek', 'Nagaland'),
(473, 'Tuensang', 'Nagaland'),
(474, 'Wokha', 'Nagaland'),
(475, 'Zunheboto', 'Nagaland'),
(476, 'Angul', 'Odisha'),
(477, 'Balangir', 'Odisha'),
(478, 'Balasore', 'Odisha'),
(479, 'Bargarh', 'Odisha'),
(480, 'Bhadrak', 'Odisha'),
(481, 'Boudh', 'Odisha'),
(482, 'Cuttack', 'Odisha'),
(483, 'Debagarh', 'Odisha'),
(484, 'Dhenkanal', 'Odisha'),
(485, 'Gajapati', 'Odisha'),
(486, 'Ganjam', 'Odisha'),
(487, 'Jagatsinghpur', 'Odisha'),
(488, 'Jajpur', 'Odisha'),
(489, 'Jharsuguda', 'Odisha'),
(490, 'Kalahandi', 'Odisha'),
(491, 'Kandhamal', 'Odisha'),
(492, 'Kendrapara', 'Odisha'),
(493, 'Kendujhar (Keonjhar)', 'Odisha'),
(494, 'Khordha', 'Odisha'),
(495, 'Koraput', 'Odisha'),
(496, 'Malkangiri', 'Odisha'),
(497, 'Mayurbhanj', 'Odisha'),
(498, 'Nabarangpur', 'Odisha'),
(499, 'Nayagarh', 'Odisha'),
(500, 'Nuapada', 'Odisha'),
(501, 'Puri', 'Odisha'),
(502, 'Rayagada', 'Odisha'),
(503, 'Sambalpur', 'Odisha'),
(504, 'Sonepur', 'Odisha'),
(505, 'Sundergarh', 'Odisha'),
(506, 'Amritsar', 'Punjab'),
(507, 'Barnala', 'Punjab'),
(508, 'Bathinda', 'Punjab'),
(509, 'Faridkot', 'Punjab'),
(510, 'Fatehgarh Sahib', 'Punjab'),
(511, 'Fazilka', 'Punjab'),
(512, 'Ferozepur', 'Punjab'),
(513, 'Gurdaspur', 'Punjab'),
(514, 'Hoshiarpur', 'Punjab'),
(515, 'Jalandhar', 'Punjab'),
(516, 'Kapurthala', 'Punjab'),
(517, 'Ludhiana', 'Punjab'),
(518, 'Mansa', 'Punjab'),
(519, 'Moga', 'Punjab'),
(520, 'Muktsar', 'Punjab'),
(521, 'Pathankot', 'Punjab'),
(522, 'Patiala', 'Punjab'),
(523, 'Rupnagar', 'Punjab'),
(524, 'Sangrur', 'Punjab'),
(525, 'Shaheed Bhagat Singh Nagar', 'Punjab'),
(526, 'Tarn Taran', 'Punjab'),
(527, 'Ajmer', 'Rajasthan'),
(528, 'Alwar', 'Rajasthan'),
(529, 'Banswara', 'Rajasthan'),
(530, 'Baran', 'Rajasthan'),
(531, 'Barmer', 'Rajasthan'),
(532, 'Bharatpur', 'Rajasthan'),
(533, 'Bhilwara', 'Rajasthan'),
(534, 'Bikaner', 'Rajasthan'),
(535, 'Bundi', 'Rajasthan'),
(536, 'Chittorgarh', 'Rajasthan'),
(537, 'Churu', 'Rajasthan'),
(538, 'Dausa', 'Rajasthan'),
(539, 'Dholpur', 'Rajasthan'),
(540, 'Dungarpur', 'Rajasthan'),
(541, 'Hanumangarh', 'Rajasthan'),
(542, 'Jaipur', 'Rajasthan'),
(543, 'Jaisalmer', 'Rajasthan'),
(544, 'Jalore', 'Rajasthan'),
(545, 'Jhalawar', 'Rajasthan'),
(546, 'Jhunjhunu', 'Rajasthan'),
(547, 'Jodhpur', 'Rajasthan'),
(548, 'Karauli', 'Rajasthan'),
(549, 'Kota', 'Rajasthan'),
(550, 'Nagaur', 'Rajasthan'),
(551, 'Pali', 'Rajasthan'),
(552, 'Pratapgarh', 'Rajasthan'),
(553, 'Rajsamand', 'Rajasthan'),
(554, 'Sawai Madhopur', 'Rajasthan'),
(555, 'Sikar', 'Rajasthan'),
(556, 'Sirohi', 'Rajasthan'),
(557, 'Sri Ganganagar', 'Rajasthan'),
(558, 'Tonk', 'Rajasthan'),
(559, 'Udaipur', 'Rajasthan'),
(560, 'East Sikkim', 'Sikkim'),
(561, 'North Sikkim', 'Sikkim'),
(562, 'South Sikkim', 'Sikkim'),
(563, 'West Sikkim', 'Sikkim'),
(564, 'Ariyalur', 'Tamil Nadu'),
(565, 'Chennai', 'Tamil Nadu'),
(566, 'Coimbatore', 'Tamil Nadu'),
(567, 'Cuddalore', 'Tamil Nadu'),
(568, 'Dharmapuri', 'Tamil Nadu'),
(569, 'Dindigul', 'Tamil Nadu'),
(570, 'Erode', 'Tamil Nadu'),
(571, 'Kanchipuram', 'Tamil Nadu'),
(572, 'Kanyakumari', 'Tamil Nadu'),
(573, 'Karur', 'Tamil Nadu'),
(574, 'Krishnagiri', 'Tamil Nadu'),
(575, 'Madurai', 'Tamil Nadu'),
(576, 'Nagapattinam', 'Tamil Nadu'),
(577, 'Namakkal', 'Tamil Nadu'),
(578, 'Perambalur', 'Tamil Nadu'),
(579, 'Pudukkottai', 'Tamil Nadu'),
(580, 'Ramanathapuram', 'Tamil Nadu'),
(581, 'Salem', 'Tamil Nadu'),
(582, 'Sivaganga', 'Tamil Nadu'),
(583, 'Thanjavur', 'Tamil Nadu'),
(584, 'Theni', 'Tamil Nadu'),
(585, 'Thoothukudi (Tuticorin)', 'Tamil Nadu'),
(586, 'Tiruchirappalli', 'Tamil Nadu'),
(587, 'Tirunelveli', 'Tamil Nadu'),
(588, 'Tiruppur', 'Tamil Nadu'),
(589, 'Tiruvallur', 'Tamil Nadu'),
(590, 'Tiruvarur', 'Tamil Nadu'),
(591, 'Vellore', 'Tamil Nadu'),
(592, 'Viluppuram', 'Tamil Nadu'),
(593, 'Virudhunagar', 'Tamil Nadu'),
(594, 'Adilabad', 'Telangana'),
(595, 'Bhadradri Kothagudem', 'Telangana'),
(596, 'Hyderabad', 'Telangana'),
(597, 'Jagtial', 'Telangana'),
(598, 'Jangaon', 'Telangana'),
(599, 'Jayashankar Bhupalpally', 'Telangana'),
(600, 'Jogulamba Gadwal', 'Telangana'),
(601, 'Kamareddy', 'Telangana'),
(602, 'Karimnagar', 'Telangana'),
(603, 'Khammam', 'Telangana'),
(604, 'Komaram Bheem Asifabad', 'Telangana'),
(605, 'Mahabubabad', 'Telangana'),
(606, 'Mahabubnagar', 'Telangana'),
(607, 'Mancherial', 'Telangana'),
(608, 'Medak', 'Telangana'),
(609, 'Medchal–Malkajgiri', 'Telangana'),
(610, 'Nagarkurnool', 'Telangana'),
(611, 'Nalgonda', 'Telangana'),
(612, 'Nirmal', 'Telangana'),
(613, 'Nizamabad', 'Telangana'),
(614, 'Peddapalli', 'Telangana'),
(615, 'Rajanna Sircilla', 'Telangana'),
(616, 'Rangareddy', 'Telangana'),
(617, 'Sangareddy', 'Telangana'),
(618, 'Siddipet', 'Telangana'),
(619, 'Suryapet', 'Telangana'),
(620, 'Vikarabad', 'Telangana'),
(621, 'Wanaparthy', 'Telangana'),
(622, 'Warangal (Rural)', 'Telangana'),
(623, 'Warangal (Urban)', 'Telangana'),
(624, 'Yadadri Bhuvanagiri', 'Telangana'),
(625, 'Dhalai', 'Tripura'),
(626, 'Gomati', 'Tripura'),
(627, 'Khowai', 'Tripura'),
(628, 'North Tripura', 'Tripura'),
(629, 'Sepahijala', 'Tripura'),
(630, 'South Tripura', 'Tripura'),
(631, 'Unakoti', 'Tripura'),
(632, 'West Tripura', 'Tripura'),
(887, 'Agra', 'Uttar Pradesh'),
(888, 'Aligarh', 'Uttar Pradesh'),
(889, 'Allahabad (Prayagraj)', 'Uttar Pradesh'),
(890, 'Ambedkar Nagar', 'Uttar Pradesh'),
(891, 'Amethi', 'Uttar Pradesh'),
(892, 'Amroha', 'Uttar Pradesh'),
(893, 'Auraiya', 'Uttar Pradesh'),
(894, 'Azamgarh', 'Uttar Pradesh'),
(895, 'Baghpat', 'Uttar Pradesh'),
(896, 'Bahraich', 'Uttar Pradesh'),
(897, 'Ballia', 'Uttar Pradesh'),
(898, 'Banda', 'Uttar Pradesh'),
(899, 'Barabanki', 'Uttar Pradesh'),
(900, 'Bareilly', 'Uttar Pradesh'),
(901, 'Basti', 'Uttar Pradesh'),
(902, 'Bhadohi', 'Uttar Pradesh'),
(903, 'Bijnor', 'Uttar Pradesh'),
(904, 'Bulandshahr', 'Uttar Pradesh'),
(905, 'Chandauli', 'Uttar Pradesh'),
(906, 'Chitrakoot', 'Uttar Pradesh'),
(907, 'Deoria', 'Uttar Pradesh'),
(908, 'Etah', 'Uttar Pradesh'),
(909, 'Etawah', 'Uttar Pradesh'),
(910, 'Farrukhabad', 'Uttar Pradesh'),
(911, 'Fatehpur', 'Uttar Pradesh'),
(912, 'Firozabad', 'Uttar Pradesh'),
(913, 'Gautam Buddha Nagar', 'Uttar Pradesh'),
(914, 'Ghaziabad', 'Uttar Pradesh'),
(915, 'Ghazipur', 'Uttar Pradesh'),
(916, 'Gonda', 'Uttar Pradesh'),
(917, 'Gorakhpur', 'Uttar Pradesh'),
(918, 'Hapur', 'Uttar Pradesh'),
(919, 'Hardoi', 'Uttar Pradesh'),
(920, 'Hathras', 'Uttar Pradesh'),
(921, 'Jalaun', 'Uttar Pradesh'),
(922, 'Jaunpur', 'Uttar Pradesh'),
(923, 'Jhansi', 'Uttar Pradesh'),
(924, 'Kannauj', 'Uttar Pradesh'),
(925, 'Kanpur Dehat', 'Uttar Pradesh'),
(926, 'Kanpur Nagar', 'Uttar Pradesh'),
(927, 'Kasganj', 'Uttar Pradesh'),
(928, 'Kaushambi', 'Uttar Pradesh'),
(929, 'Kushinagar', 'Uttar Pradesh'),
(930, 'Lakhimpur Kheri', 'Uttar Pradesh'),
(931, 'Lalitpur', 'Uttar Pradesh'),
(932, 'Lucknow', 'Uttar Pradesh'),
(933, 'Maharajganj', 'Uttar Pradesh'),
(934, 'Mahoba', 'Uttar Pradesh'),
(935, 'Mainpuri', 'Uttar Pradesh'),
(936, 'Mathura', 'Uttar Pradesh'),
(937, 'Mau', 'Uttar Pradesh'),
(938, 'Meerut', 'Uttar Pradesh'),
(939, 'Mirzapur', 'Uttar Pradesh'),
(940, 'Moradabad', 'Uttar Pradesh'),
(941, 'Muzaffarnagar', 'Uttar Pradesh'),
(942, 'Pilibhit', 'Uttar Pradesh'),
(943, 'Prayagraj', 'Uttar Pradesh'),
(944, 'Raebareli', 'Uttar Pradesh'),
(945, 'Rampur', 'Uttar Pradesh'),
(946, 'Saharanpur', 'Uttar Pradesh'),
(947, 'Sambhal', 'Uttar Pradesh'),
(948, 'Sant Kabir Nagar', 'Uttar Pradesh'),
(949, 'Shahjahanpur', 'Uttar Pradesh'),
(950, 'Shamli', 'Uttar Pradesh'),
(951, 'Shrawasti', 'Uttar Pradesh'),
(952, 'Siddharthnagar', 'Uttar Pradesh'),
(953, 'Sitapur', 'Uttar Pradesh'),
(954, 'Sonbhadra', 'Uttar Pradesh'),
(955, 'Sultanpur', 'Uttar Pradesh'),
(956, 'Unnao', 'Uttar Pradesh'),
(957, 'Varanasi', 'Uttar Pradesh'),
(958, 'Almora', 'Uttarakhand'),
(959, 'Bageshwar', 'Uttarakhand'),
(960, 'Chamoli', 'Uttarakhand'),
(961, 'Champawat', 'Uttarakhand'),
(962, 'Dehradun', 'Uttarakhand'),
(963, 'Haridwar', 'Uttarakhand'),
(964, 'Nainital', 'Uttarakhand'),
(965, 'Pauri Garhwal', 'Uttarakhand'),
(966, 'Pithoragarh', 'Uttarakhand'),
(967, 'Rudraprayag', 'Uttarakhand'),
(968, 'Tehri Garhwal', 'Uttarakhand'),
(969, 'Udham Singh Nagar', 'Uttarakhand'),
(970, 'Uttarkashi', 'Uttarakhand'),
(971, 'Alipurduar', 'West Bengal'),
(972, 'Bankura', 'West Bengal'),
(973, 'Birbhum', 'West Bengal'),
(974, 'Cooch Behar', 'West Bengal'),
(975, 'Darjeeling', 'West Bengal'),
(976, 'Hooghly', 'West Bengal'),
(977, 'Howrah', 'West Bengal'),
(978, 'Jalpaiguri', 'West Bengal'),
(979, 'Jhargram', 'West Bengal'),
(980, 'Kalimpong', 'West Bengal'),
(981, 'Kolkata', 'West Bengal'),
(982, 'Malda', 'West Bengal'),
(983, 'Murshidabad', 'West Bengal'),
(984, 'Nadia', 'West Bengal'),
(985, 'North 24 Parganas', 'West Bengal'),
(986, 'Paschim Bardhaman', 'West Bengal'),
(987, 'Paschim Medinipur', 'West Bengal'),
(988, 'Purba Bardhaman', 'West Bengal'),
(989, 'Purba Medinipur', 'West Bengal'),
(990, 'Purulia', 'West Bengal'),
(991, 'South 24 Parganas', 'West Bengal'),
(992, 'Uttar Dinajpur', 'West Bengal'),
(993, 'Nicobar', 'Andaman and Nicobar Islands'),
(994, 'North and Middle Andaman', 'Andaman and Nicobar Islands'),
(995, 'South Andaman', 'Andaman and Nicobar Islands'),
(996, 'Chandigarh', 'Chandigarh'),
(997, 'Dadra and Nagar Haveli', 'Dadra and Nagar Haveli and Daman and Diu'),
(998, 'Daman', 'Dadra and Nagar Haveli and Daman and Diu'),
(999, 'Diu', 'Dadra and Nagar Haveli and Daman and Diu'),
(1000, 'Central Delhi', 'Delhi'),
(1001, 'East Delhi', 'Delhi'),
(1002, 'New Delhi', 'Delhi'),
(1003, 'North Delhi', 'Delhi'),
(1004, 'North East Delhi', 'Delhi'),
(1005, 'North West Delhi', 'Delhi'),
(1006, 'Shahdara', 'Delhi'),
(1007, 'South Delhi', 'Delhi'),
(1008, 'South East Delhi', 'Delhi'),
(1009, 'South West Delhi', 'Delhi'),
(1010, 'West Delhi', 'Delhi'),
(1011, 'Anantnag', 'Jammu and Kashmir'),
(1012, 'Bandipora', 'Jammu and Kashmir'),
(1013, 'Baramulla', 'Jammu and Kashmir'),
(1014, 'Budgam', 'Jammu and Kashmir'),
(1015, 'Doda', 'Jammu and Kashmir'),
(1016, 'Ganderbal', 'Jammu and Kashmir'),
(1017, 'Jammu', 'Jammu and Kashmir'),
(1018, 'Kathua', 'Jammu and Kashmir'),
(1019, 'Kishtwar', 'Jammu and Kashmir'),
(1020, 'Kulgam', 'Jammu and Kashmir'),
(1021, 'Kupwara', 'Jammu and Kashmir'),
(1022, 'Poonch', 'Jammu and Kashmir'),
(1023, 'Pulwama', 'Jammu and Kashmir'),
(1024, 'Rajouri', 'Jammu and Kashmir'),
(1025, 'Ramban', 'Jammu and Kashmir'),
(1026, 'Reasi', 'Jammu and Kashmir'),
(1027, 'Samba', 'Jammu and Kashmir'),
(1028, 'Shopian', 'Jammu and Kashmir'),
(1029, 'Srinagar', 'Jammu and Kashmir'),
(1030, 'Udhampur', 'Jammu and Kashmir'),
(1031, 'Kargil', 'Ladakh'),
(1032, 'Leh', 'Ladakh'),
(1033, 'Lakshadweep', 'Lakshadweep'),
(1034, 'Karaikal', 'Puducherry'),
(1035, 'Mahe', 'Puducherry'),
(1036, 'Puducherry', 'Puducherry'),
(1037, 'Yanam', 'Puducherry');

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE `email_log` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `email_type` enum('creation','cancellation','reminder','update') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','failed') NOT NULL,
  `recipient_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_reminders`
--

CREATE TABLE `event_reminders` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `reminder_date` date NOT NULL,
  `days_before` int(11) NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenditure_details`
--

CREATE TABLE `expenditure_details` (
  `id` int(11) NOT NULL,
  `head_id` int(11) NOT NULL,
  `detail_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `ncrb_course_id` int(6) UNSIGNED DEFAULT NULL,
  `rpctc_course_id` int(6) UNSIGNED DEFAULT NULL,
  `course_source` varchar(10) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expenditure_detail` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `bill_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_master`
--

CREATE TABLE `faculty_master` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expertise` varchar(255) NOT NULL,
  `institute` varchar(255) NOT NULL,
  `eminent` tinyint(1) DEFAULT 0,
  `professional_degree` varchar(255) NOT NULL,
  `pan` varchar(20) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_no` varchar(30) NOT NULL,
  `ifsc` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_ncrb`
--

CREATE TABLE `faculty_ncrb` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `rating` decimal(3,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_other`
--

CREATE TABLE `faculty_other` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `rating` decimal(3,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(6) UNSIGNED NOT NULL,
  `userid` varchar(50) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `participant_name_hindi` varchar(100) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `category_name` varchar(50) DEFAULT NULL,
  `category_name_hindi` varchar(50) DEFAULT NULL,
  `rank` varchar(100) DEFAULT NULL,
  `rank_hindi` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `qualifications` varchar(100) DEFAULT NULL,
  `residential_address` text DEFAULT NULL,
  `state_name` varchar(100) DEFAULT NULL,
  `state_name_hindi` varchar(100) DEFAULT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `official_address` text DEFAULT NULL,
  `residential_phone` varchar(20) DEFAULT NULL,
  `delhi_address` text DEFAULT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `course_experience` text DEFAULT NULL,
  `rating` float DEFAULT NULL,
  `other_rank` varchar(100) DEFAULT NULL,
  `other_rank_hindi` varchar(100) DEFAULT NULL,
  `course_objectives` int(1) DEFAULT NULL,
  `background_material` int(1) DEFAULT NULL,
  `visual_aids` int(1) DEFAULT NULL,
  `programme_rating` int(1) DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `financial_year` int(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hostel_allotment`
--

CREATE TABLE `hostel_allotment` (
  `id` int(11) NOT NULL,
  `registration_id` varchar(50) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `residential_phone` varchar(20) NOT NULL,
  `need_hostel` enum('yes','no') NOT NULL DEFAULT 'no',
  `hostel_from` date DEFAULT NULL,
  `hostel_to` date DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lecture_ratings`
--

CREATE TABLE `lecture_ratings` (
  `rating_id` int(6) UNSIGNED NOT NULL,
  `feedback_id` int(6) UNSIGNED NOT NULL,
  `lecture_day_id` varchar(20) NOT NULL,
  `lecture_number` int(2) NOT NULL,
  `lecture_relevance` float DEFAULT NULL,
  `lecture_contents` float DEFAULT NULL,
  `lecture_presentation` float DEFAULT NULL,
  `rating` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nominee`
--

CREATE TABLE `nominee` (
  `id` int(6) UNSIGNED NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `participant_name_hindi` varchar(255) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_name_hindi` varchar(50) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `rank_hindi` varchar(100) NOT NULL,
  `other_rank` varchar(100) DEFAULT NULL,
  `other_rank_hindi` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `qualifications` text NOT NULL,
  `residential_address` text NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_name_hindi` varchar(100) NOT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `official_address` text DEFAULT NULL,
  `residential_phone` varchar(15) NOT NULL,
  `delhi_address` text DEFAULT NULL,
  `course_expectation` text NOT NULL,
  `officer_designation` varchar(100) NOT NULL,
  `officer_phone` varchar(15) DEFAULT NULL,
  `officer_address` text DEFAULT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_users`
--

CREATE TABLE `participant_users` (
  `id` int(6) UNSIGNED NOT NULL,
  `userid` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_feedback`
--

CREATE TABLE `portal_feedback` (
  `id` int(6) UNSIGNED NOT NULL,
  `topic` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `feedback` text NOT NULL,
  `visitor_name` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `rating` int(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_images`
--

CREATE TABLE `program_images` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) NOT NULL DEFAULT '',
  `use_for` enum('home','course') NOT NULL DEFAULT 'course',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_videos`
--

CREATE TABLE `program_videos` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `video_path` varchar(255) NOT NULL,
  `caption` varchar(255) NOT NULL DEFAULT '',
  `use_for` enum('home','course') NOT NULL DEFAULT 'course',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

CREATE TABLE `ranks` (
  `rank_id` int(11) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `rank_hindi` varchar(100) DEFAULT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_name_hindi` varchar(50) DEFAULT NULL,
  `hierarchy_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`rank_id`, `rank`, `rank_hindi`, `category_name`, `category_name_hindi`, `hierarchy_level`) VALUES
(1, 'Constable', 'आरक्षक', 'Police', 'पुलिस', 1),
(2, 'Head Constable', 'मुख्य आरक्षक', 'Police', 'पुलिस', 2),
(3, 'Assistant Sub-Inspector (ASI)', 'सहायक उप निरीक्षक', 'Police', 'पुलिस', 3),
(4, 'Sub-Inspector (SI)', 'उप निरीक्षक', 'Police', 'पुलिस', 4),
(5, 'Inspector', 'निरीक्षक', 'Police', 'पुलिस', 5),
(6, 'Assistant Superintendent of Police (ASP)', 'सहायक पुलिस अधीक्षक', 'Police', 'पुलिस', 6),
(7, 'Deputy Superintendent of Police (DySP)', 'पुलिस उप अधीक्षक', 'Police', 'पुलिस', 7),
(8, 'Additional Superintendent of Police (Addl. SP)', 'अपर पुलिस अधीक्षक', 'Police', 'पुलिस', 8),
(9, 'Superintendent of Police (SP)', 'पुलिस अधीक्षक', 'Police', 'पुलिस', 9),
(10, 'Senior Superintendent of Police (SSP)', 'वरिष्ठ पुलिस अधीक्षक', 'Police', 'पुलिस', 10),
(11, 'Deputy Inspector General of Police (DIG)', 'पुलिस उप महानिरीक्षक', 'Police', 'पुलिस', 11),
(12, 'Inspector General of Police (IG)', 'पुलिस महानिरीक्षक', 'Police', 'पुलिस', 12),
(13, 'Additional Director General of Police (ADGP)', 'अपर पुलिस महानिदेशक', 'Police', 'पुलिस', 13),
(14, 'Director General of Police (DGP)', 'पुलिस महानिदेशक', 'Police', 'पुलिस', 14),
(15, 'Junior Division Civil Judge', 'जूनियर डिवीजन सिविल जज', 'Judicial', 'न्यायिक', 1),
(16, 'Senior Division Civil Judge', 'सीनियर डिवीजन सिविल जज', 'Judicial', 'न्यायिक', 2),
(17, 'Chief Judicial Magistrate', 'मुख्य न्यायिक मजिस्ट्रेट', 'Judicial', 'न्यायिक', 3),
(18, 'Additional District Judge', 'अपर जिला न्यायाधीश', 'Judicial', 'न्यायिक', 4),
(19, 'District Judge', 'जिला न्यायाधीश', 'Judicial', 'न्यायिक', 5),
(20, 'District and Sessions Judge', 'जिला एवं सत्र न्यायाधीश', 'Judicial', 'न्यायिक', 6),
(21, 'Registrar (High Court)', 'रजिस्ट्रार (उच्च न्यायालय)', 'Judicial', 'न्यायिक', 7),
(22, 'Additional Judge (High Court)', 'अतिरिक्त न्यायाधीश (उच्च न्यायालय)', 'Judicial', 'न्यायिक', 8),
(23, 'Permanent Judge (High Court)', 'स्थायी न्यायाधीश (उच्च न्यायालय)', 'Judicial', 'न्यायिक', 9),
(24, 'Chief Justice (High Court)', 'मुख्य न्यायाधीश (उच्च न्यायालय)', 'Judicial', 'न्यायिक', 10),
(25, 'Judge (Supreme Court)', 'न्यायाधीश (सर्वोच्च न्यायालय)', 'Judicial', 'न्यायिक', 11),
(26, 'Chief Justice of India', 'भारत के मुख्य न्यायाधीश', 'Judicial', 'न्यायिक', 12),
(27, 'Warder', 'वार्डर', 'Prison', 'जेल', 1),
(28, 'Head Warder', 'हेड वार्डर', 'Prison', 'जेल', 2),
(29, 'Assistant Jailor', 'सहायक जेलर', 'Prison', 'जेल', 3),
(30, 'Jailor', 'जेलर', 'Prison', 'जेल', 4),
(31, 'Deputy Superintendent of Jail', 'उप अधीक्षक जेल', 'Prison', 'जेल', 5),
(32, 'Superintendent of Jail', 'अधीक्षक जेल', 'Prison', 'जेल', 6),
(33, 'Deputy Inspector General of Prisons', 'उप महानिरीक्षक जेल', 'Prison', 'जेल', 7),
(34, 'Inspector General of Prisons', 'महानिरीक्षक जेल', 'Prison', 'जेल', 8),
(35, 'Additional Director General of Prisons', 'अपर महानिदेशक जेल', 'Prison', 'जेल', 9),
(36, 'Director General of Prisons', 'महानिदेशक जेल', 'Prison', 'जेल', 10),
(37, 'Assistant Public Prosecutor', 'सहायक लोक अभियोजक', 'Prosecution', 'अभियोजन', 1),
(38, 'Public Prosecutor', 'लोक अभियोजक', 'Prosecution', 'अभियोजन', 2),
(39, 'Senior Public Prosecutor', 'वरिष्ठ लोक अभियोजक', 'Prosecution', 'अभियोजन', 3),
(40, 'Chief Public Prosecutor', 'मुख्य लोक अभियोजक', 'Prosecution', 'अभियोजन', 4),
(41, 'Additional Public Prosecutor (High Court)', 'अपर लोक अभियोजक (उच्च न्यायालय)', 'Prosecution', 'अभियोजन', 5),
(42, 'Public Prosecutor (High Court)', 'लोक अभियोजक (उच्च न्यायालय)', 'Prosecution', 'अभियोजन', 6),
(43, 'Additional Solicitor General', 'अपर सॉलिसिटर जनरल', 'Prosecution', 'अभियोजन', 7),
(44, 'Solicitor General of India', 'भारत का सॉलिसिटर जनरल', 'Prosecution', 'अभियोजन', 8),
(45, 'Additional Attorney General', 'अपर अटार्नी जनरल', 'Prosecution', 'अभियोजन', 9),
(46, 'Attorney General of India', 'भारत का अटार्नी जनरल', 'Prosecution', 'अभियोजन', 10);

-- --------------------------------------------------------

--
-- Table structure for table `reappear_exam_dates`
--

CREATE TABLE `reappear_exam_dates` (
  `id` int(6) UNSIGNED NOT NULL,
  `registration_id` int(6) UNSIGNED NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recipients`
--

CREATE TABLE `recipients` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `state_name` varchar(100) NOT NULL DEFAULT '',
  `course_code` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration`
--

CREATE TABLE `registration` (
  `id` int(6) UNSIGNED NOT NULL,
  `userid` varchar(50) NOT NULL,
  `registration_id` varchar(50) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `participant_name_hindi` varchar(255) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_name_hindi` varchar(50) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `rank_hindi` varchar(100) NOT NULL,
  `other_rank` varchar(100) DEFAULT NULL,
  `other_rank_hindi` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `qualifications` text NOT NULL,
  `residential_address` text NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_name_hindi` varchar(100) NOT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `official_address` text DEFAULT NULL,
  `residential_phone` varchar(20) NOT NULL,
  `delhi_address` text DEFAULT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `course_expectation` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rejected_participants`
--

CREATE TABLE `rejected_participants` (
  `id` int(6) UNSIGNED NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `participant_name_hindi` varchar(255) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_name_hindi` varchar(50) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `rank_hindi` varchar(100) NOT NULL,
  `other_rank` varchar(100) DEFAULT NULL,
  `other_rank_hindi` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `qualifications` text NOT NULL,
  `residential_address` text NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_name_hindi` varchar(100) NOT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `official_address` text DEFAULT NULL,
  `residential_phone` varchar(15) NOT NULL,
  `delhi_address` text DEFAULT NULL,
  `course_expectation` text NOT NULL,
  `officer_designation` varchar(100) NOT NULL,
  `officer_phone` varchar(15) DEFAULT NULL,
  `officer_address` text DEFAULT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `rejection_reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpctc_admin_credentials`
--

CREATE TABLE `rpctc_admin_credentials` (
  `id` int(6) UNSIGNED NOT NULL,
  `center` enum('Lucknow','Kolkata','Hyderabad','Gujarat') NOT NULL,
  `rpctc_username` varchar(255) NOT NULL,
  `rpctc_password` varchar(255) NOT NULL,
  `status` enum('active','deleted') NOT NULL DEFAULT 'active',
  `last_updated_by` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpctc_lucknow_email_log`
--

CREATE TABLE `rpctc_lucknow_email_log` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `email_type` enum('creation','cancellation','reminder','update') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','failed') NOT NULL,
  `recipient_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpctc_lucknow_event_reminders`
--

CREATE TABLE `rpctc_lucknow_event_reminders` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `reminder_date` date NOT NULL,
  `days_before` int(11) NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpctc_lucknow_recipients`
--

CREATE TABLE `rpctc_lucknow_recipients` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `state_name` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpctc_lucknow_training_events`
--

CREATE TABLE `rpctc_lucknow_training_events` (
  `id` int(6) UNSIGNED NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) DEFAULT '',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(100) DEFAULT 'NCRB Training Center',
  `duration` int(11) DEFAULT 0,
  `eligibility` text DEFAULT '',
  `color` varchar(7) DEFAULT '#ff6b6b',
  `status` enum('active','cancelled') DEFAULT 'active',
  `reminders_active` tinyint(1) NOT NULL DEFAULT 1,
  `reminders_paused` tinyint(1) NOT NULL DEFAULT 0,
  `reminders_enabled` tinyint(1) DEFAULT 1,
  `objectives` text DEFAULT '\'\''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpctc_training_events`
--

CREATE TABLE `rpctc_training_events` (
  `id` int(6) UNSIGNED NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(255) NOT NULL,
  `duration` int(3) DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#ff6b6b',
  `status` varchar(20) DEFAULT 'active',
  `reminders_active` tinyint(1) NOT NULL DEFAULT 1,
  `reminders_paused` tinyint(1) DEFAULT 0,
  `reminders_enabled` tinyint(1) DEFAULT 1,
  `objectives` text DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `state_id` int(11) NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_name_hindi` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`state_id`, `state_name`, `state_name_hindi`) VALUES
(1, 'Andhra Pradesh', 'आंध्र प्रदेश '),
(2, 'Arunachal Pradesh', 'अरुणाचल प्रदेश '),
(3, 'Assam', 'असम '),
(4, 'Bihar', 'बिहार '),
(5, 'Chhattisgarh', 'छत्तीसगढ़ '),
(6, 'Goa', 'गोआ'),
(7, 'Gujarat', 'गुजरात '),
(8, 'Haryana', 'हरयाणा '),
(9, 'Himachal Pradesh', 'हिमाचल प्रदेश '),
(10, 'Jharkhand', 'झारखंड '),
(11, 'Karnataka', 'कर्नाटक '),
(12, 'Kerala', 'केरल '),
(13, 'Madhya Pradesh', 'मध्य प्रदेश '),
(14, 'Maharashtra', 'महाराष्ट्र '),
(15, 'Manipur', 'मणिपुर '),
(16, 'Meghalaya', 'मेघालय '),
(17, 'Mizoram', 'मिज़ोरम '),
(18, 'Nagaland', 'नागालैंड '),
(19, 'Odisha', 'ओडिशा'),
(20, 'Punjab', 'पंजाब '),
(21, 'Rajasthan', 'राजस्थान '),
(22, 'Sikkim', 'सिक्किम '),
(23, 'Tamil Nadu', 'तमिल नाडु '),
(24, 'Telangana', 'तेलंगाना '),
(25, 'Tripura', 'त्रिपुरा '),
(26, 'Uttar Pradesh', 'उत्तर प्रदेश '),
(27, 'Uttarakhand', 'उत्तराखंड '),
(28, 'West Bengal', 'पश्चिम बंगाल '),
(29, 'Andaman and Nicobar Islands', 'अंडमान और निकोबार द्वीप '),
(30, 'Chandigarh', 'चण्डीगढ़ '),
(31, 'Dadra and Nagar Haveli and Daman and Diu', 'दादरा और नागर हवेली तथा दमन और ड्यू '),
(32, 'Delhi', 'दिल्ली '),
(33, 'Jammu and Kashmir', 'जम्मू और कश्मीर '),
(34, 'Ladakh', 'लद्दाख '),
(35, 'Lakshadweep', 'लक्षद्वीप'),
(36, 'Puducherry', 'पुडुचेरी ');

-- --------------------------------------------------------

--
-- Table structure for table `training_budget`
--

CREATE TABLE `training_budget` (
  `id` int(11) NOT NULL,
  `head_id` int(11) NOT NULL,
  `allocated_budget` decimal(10,2) NOT NULL,
  `expenses` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_events`
--

CREATE TABLE `training_events` (
  `id` int(6) UNSIGNED NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_name_hindi` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(255) NOT NULL,
  `duration` int(3) DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#ff6b6b',
  `status` varchar(20) DEFAULT 'active',
  `reminders_active` tinyint(1) NOT NULL DEFAULT 1,
  `reminders_paused` tinyint(1) DEFAULT 0,
  `reminders_enabled` tinyint(1) DEFAULT 1,
  `objectives` text DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accepted_participants`
--
ALTER TABLE `accepted_participants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_credentials`
--
ALTER TABLE `admin_credentials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `aibe_exam_marks`
--
ALTER TABLE `aibe_exam_marks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registration_id` (`registration_id`);

--
-- Indexes for table `aibe_reappear_marks`
--
ALTER TABLE `aibe_reappear_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`,`subject`);

--
-- Indexes for table `budget_heads`
--
ALTER TABLE `budget_heads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course_materials`
--
ALTER TABLE `course_materials`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_reminders`
--
ALTER TABLE `event_reminders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenditure_details`
--
ALTER TABLE `expenditure_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `head_id` (`head_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `ncrb_course_id` (`ncrb_course_id`);

--
-- Indexes for table `faculty_master`
--
ALTER TABLE `faculty_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faculty_ncrb`
--
ALTER TABLE `faculty_ncrb`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `faculty_other`
--
ALTER TABLE `faculty_other`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`);

--
-- Indexes for table `hostel_allotment`
--
ALTER TABLE `hostel_allotment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lecture_ratings`
--
ALTER TABLE `lecture_ratings`
  ADD PRIMARY KEY (`rating_id`);

--
-- Indexes for table `nominee`
--
ALTER TABLE `nominee`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `participant_users`
--
ALTER TABLE `participant_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `portal_feedback`
--
ALTER TABLE `portal_feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `program_images`
--
ALTER TABLE `program_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `program_videos`
--
ALTER TABLE `program_videos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reappear_exam_dates`
--
ALTER TABLE `reappear_exam_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`);

--
-- Indexes for table `recipients`
--
ALTER TABLE `recipients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registration`
--
ALTER TABLE `registration`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rejected_participants`
--
ALTER TABLE `rejected_participants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rpctc_admin_credentials`
--
ALTER TABLE `rpctc_admin_credentials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rpctc_lucknow_email_log`
--
ALTER TABLE `rpctc_lucknow_email_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rpctc_lucknow_event_reminders`
--
ALTER TABLE `rpctc_lucknow_event_reminders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rpctc_lucknow_recipients`
--
ALTER TABLE `rpctc_lucknow_recipients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rpctc_lucknow_training_events`
--
ALTER TABLE `rpctc_lucknow_training_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `training_budget`
--
ALTER TABLE `training_budget`
  ADD PRIMARY KEY (`id`),
  ADD KEY `head_id` (`head_id`);

--
-- Indexes for table `training_events`
--
ALTER TABLE `training_events`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accepted_participants`
--
ALTER TABLE `accepted_participants`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin_credentials`
--
ALTER TABLE `admin_credentials`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `aibe_exam_marks`
--
ALTER TABLE `aibe_exam_marks`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `aibe_reappear_marks`
--
ALTER TABLE `aibe_reappear_marks`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `budget_heads`
--
ALTER TABLE `budget_heads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `course_materials`
--
ALTER TABLE `course_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `event_reminders`
--
ALTER TABLE `event_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenditure_details`
--
ALTER TABLE `expenditure_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `faculty_master`
--
ALTER TABLE `faculty_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `faculty_ncrb`
--
ALTER TABLE `faculty_ncrb`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `faculty_other`
--
ALTER TABLE `faculty_other`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hostel_allotment`
--
ALTER TABLE `hostel_allotment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lecture_ratings`
--
ALTER TABLE `lecture_ratings`
  MODIFY `rating_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `nominee`
--
ALTER TABLE `nominee`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `participant_users`
--
ALTER TABLE `participant_users`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `portal_feedback`
--
ALTER TABLE `portal_feedback`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `program_images`
--
ALTER TABLE `program_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `program_videos`
--
ALTER TABLE `program_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reappear_exam_dates`
--
ALTER TABLE `reappear_exam_dates`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recipients`
--
ALTER TABLE `recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `registration`
--
ALTER TABLE `registration`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rejected_participants`
--
ALTER TABLE `rejected_participants`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rpctc_admin_credentials`
--
ALTER TABLE `rpctc_admin_credentials`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rpctc_lucknow_email_log`
--
ALTER TABLE `rpctc_lucknow_email_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rpctc_lucknow_event_reminders`
--
ALTER TABLE `rpctc_lucknow_event_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rpctc_lucknow_recipients`
--
ALTER TABLE `rpctc_lucknow_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rpctc_lucknow_training_events`
--
ALTER TABLE `rpctc_lucknow_training_events`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_budget`
--
ALTER TABLE `training_budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_events`
--
ALTER TABLE `training_events`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aibe_exam_marks`
--
ALTER TABLE `aibe_exam_marks`
  ADD CONSTRAINT `aibe_exam_marks_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registration` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `aibe_reappear_marks`
--
ALTER TABLE `aibe_reappear_marks`
  ADD CONSTRAINT `aibe_reappear_marks_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registration` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_materials`
--
ALTER TABLE `course_materials`
  ADD CONSTRAINT `course_materials_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `training_events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenditure_details`
--
ALTER TABLE `expenditure_details`
  ADD CONSTRAINT `expenditure_details_ibfk_1` FOREIGN KEY (`head_id`) REFERENCES `budget_heads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `training_budget` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`ncrb_course_id`) REFERENCES `training_events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `faculty_ncrb`
--
ALTER TABLE `faculty_ncrb`
  ADD CONSTRAINT `faculty_ncrb_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_other`
--
ALTER TABLE `faculty_other`
  ADD CONSTRAINT `faculty_other_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reappear_exam_dates`
--
ALTER TABLE `reappear_exam_dates`
  ADD CONSTRAINT `reappear_exam_dates_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registration` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_budget`
--
ALTER TABLE `training_budget`
  ADD CONSTRAINT `training_budget_ibfk_1` FOREIGN KEY (`head_id`) REFERENCES `budget_heads` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `cleanup_old_hostel_data` ON SCHEDULE EVERY 6 MONTH STARTS '2025-07-30 19:24:46' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
            DELETE FROM hostel_allotment WHERE created_at < NOW() - INTERVAL 6 MONTH;
        END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
