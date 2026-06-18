-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 18, 2026 at 04:10 PM
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
-- Database: `attendiq`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `verified_at` datetime DEFAULT current_timestamp(),
  `method` enum('biometric','manual') NOT NULL DEFAULT 'biometric'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `att_sessions`
--

CREATE TABLE `att_sessions` (
  `id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `opened_by` int(11) NOT NULL COMMENT 'class_reps.id',
  `closed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_reps`
--

CREATE TABLE `class_reps` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `matric_no` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL DEFAULT 'Computer Science',
  `level` varchar(10) NOT NULL DEFAULT '200',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_reps`
--

INSERT INTO `class_reps` (`id`, `full_name`, `email`, `matric_no`, `password`, `department`, `level`, `created_at`) VALUES
(1, 'Class Representative', 'rep@attendiq.com', 'CSC/2021/REP', 'password123', 'Computer Science', '200', '2026-06-18 13:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `title` varchar(120) NOT NULL,
  `units` tinyint(4) NOT NULL DEFAULT 3,
  `instructor` varchar(120) NOT NULL,
  `instructor_email` varchar(120) DEFAULT NULL,
  `semester` enum('First','Second') NOT NULL DEFAULT 'First',
  `session` varchar(12) NOT NULL DEFAULT '2024/2025',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `title`, `units`, `instructor`, `instructor_email`, `semester`, `session`, `is_active`, `created_at`) VALUES
(1, 'COM221', 'Basic Computer Networking', 3, 'Mr. Peace Adenuga', 'Peace@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 13:29:07'),
(2, 'COM222', 'Basic Hardware Maintenance', 3, 'Mr. Adeleke', 'Adeleke@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 13:29:07'),
(3, 'COM223', 'Management Information System', 3, 'Mr. Femi Oyetoke', 'Femi@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 13:29:07'),
(4, 'COM225', 'File Organization & Management', 3, 'Mr. Adeleke', 'Adeleke@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 13:29:07'),
(5, 'COM224', 'Web Technology', 3, 'Mr. Oluwanimilo Abdullahi', 'Abdullahi@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 13:29:07'),
(6, 'COM226', 'Computer System Troubleshooting', 3, 'Mr. Oluwanimilo Abdullahi', 'Abdullahi@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 13:29:07'),
(7, 'COM227', 'Project', 4, 'Mr. Haastrup', 'Haastrup@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 15:01:14'),
(8, 'GNS202', 'Communication in English II', 2, 'Mr. Oyedokun', 'Oyedokun@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 15:02:06'),
(9, 'SBS101', 'Web Design', 2, 'Mr. Olayinka Jeremiah', 'Olayinka@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 15:03:22'),
(10, 'SIW219', 'Siwes', 4, 'Mr. Ojo Patrick', 'Ojo@soteria.edu.ng', 'Second', '2024/2025', 1, '2026-06-18 15:05:41'),
(11, 'CP', 'Computer Practical', 0, '-', '', 'Second', '2024/2025', 1, '2026-06-18 15:23:43');

-- --------------------------------------------------------

--
-- Table structure for table `qr_tokens`
--

CREATE TABLE `qr_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(80) NOT NULL,
  `student_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `action` enum('register','verify') NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `matric_no` varchar(40) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `matric_no`, `full_name`, `email`, `phone`, `gender`, `is_active`, `created_at`) VALUES
(1, 'SBS/NDCOM/24/013', 'Afolabi Oluwaseun', 'seun@soteria.edu.ng', '09027389234', 'Male', 1, '2026-06-18 13:29:07'),
(7, 'SBS/NDCOM/24/009', 'Oladeji Samson', 'Samson@sbs.edu.ng', '08160716514', 'Male', 1, '2026-06-18 13:29:07'),
(8, 'SBS/NDCOM/24/001', 'Adeleye Blossom', 'Blossom@soteria.edu.ng', '09027389234', 'Male', 1, '2026-06-18 13:29:07'),
(9, 'SBS/NDCOM/24/002', 'Otene Elijah', 'Elijah@soteria.edu.ng', '09027389234', 'Male', 1, '2026-06-18 13:29:07'),
(10, 'SBS/NDCOM/24/008', 'Kolawole Theophilus', 'Theophilus@Soteria.edu.ng', '09027389234', 'Male', 1, '2026-06-18 13:29:07'),
(15, 'SBS/NDCOM/24/010', 'Ogunjobi Eniola', 'Eniola@student.edu.ng', '09027389234', 'Female', 1, '2026-06-18 13:29:07'),
(16, 'SBS/NDCOM/24/012', 'Salahudeen Esther', 'Esther@soteria.edu.ng', '09027389234', 'Female', 1, '2026-06-18 13:29:07'),
(17, 'CSC/2021/016', 'Omozu Joshua', 'Joshua@student.edu.ng', '09027389234', 'Male', 1, '2026-06-18 13:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `venue` varchar(60) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `course_id`, `day_of_week`, `start_time`, `end_time`, `venue`, `created_at`) VALUES
(1, 8, 1, '09:00:00', '11:00:00', 'Class', '2026-06-18 13:29:07'),
(2, 11, 1, '11:00:00', '12:00:00', 'Computer Lab', '2026-06-18 13:29:07'),
(3, 5, 1, '13:00:00', '16:00:00', 'Class', '2026-06-18 13:29:07'),
(4, 1, 2, '09:00:00', '12:00:00', 'Class', '2026-06-18 13:29:07'),
(5, 2, 2, '12:00:00', '16:00:00', 'Class', '2026-06-18 13:29:07'),
(6, 6, 3, '09:00:00', '12:00:00', 'Hardware Lab', '2026-06-18 13:29:07'),
(7, 3, 3, '13:00:00', '16:00:00', 'Class', '2026-06-18 13:29:07'),
(8, 7, 4, '09:00:00', '12:00:00', 'Class', '2026-06-18 13:29:07'),
(9, 4, 4, '13:00:00', '16:00:00', 'Class', '2026-06-18 13:29:07'),
(10, 3, 5, '08:00:00', '10:00:00', 'LT-D', '2026-06-18 13:29:07'),
(11, 9, 5, '13:00:00', '16:00:00', 'Computer Lab', '2026-06-18 13:29:07'),
(12, 6, 5, '14:00:00', '16:00:00', 'LT-B', '2026-06-18 13:29:07'),
(13, 10, 4, '16:00:00', '17:00:00', 'Class', '2026-06-18 15:28:59');

-- --------------------------------------------------------

--
-- Table structure for table `webauthn_credentials`
--

CREATE TABLE `webauthn_credentials` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `credential_id` text NOT NULL,
  `public_key` text NOT NULL,
  `sign_count` int(11) NOT NULL DEFAULT 0,
  `registered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `webauthn_credentials`
--

INSERT INTO `webauthn_credentials` (`id`, `student_id`, `credential_id`, `public_key`, `sign_count`, `registered_at`) VALUES
(2, 8, '01gZKvsddLp5mzbty1m7KUvScvpW0hqoTbIt0Ge23sQ=', '-----BEGIN PUBLIC KEY-----\ncGxhdGZvcm1fYXV0aGVudGljYXRvcl84XzE3ODE3OTM1OTc=\n-----END PUBLIC KEY-----', 0, '2026-06-18 15:39:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_att` (`session_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `att_sessions`
--
ALTER TABLE `att_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session` (`timetable_id`,`session_date`),
  ADD KEY `opened_by` (`opened_by`);

--
-- Indexes for table `class_reps`
--
ALTER TABLE `class_reps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `matric_no` (`matric_no`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matric_no` (`matric_no`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slot` (`course_id`,`day_of_week`,`start_time`);

--
-- Indexes for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `att_sessions`
--
ALTER TABLE `att_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `class_reps`
--
ALTER TABLE `class_reps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `att_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `att_sessions`
--
ALTER TABLE `att_sessions`
  ADD CONSTRAINT `att_sessions_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `att_sessions_ibfk_2` FOREIGN KEY (`opened_by`) REFERENCES `class_reps` (`id`);

--
-- Constraints for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD CONSTRAINT `qr_tokens_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qr_tokens_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `att_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD CONSTRAINT `webauthn_credentials_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
