-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2025 at 07:41 AM
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
-- Database: `hrproject`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
  `taken_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `subject_id`, `attendance_date`, `status`, `taken_by_id`, `created_at`) VALUES
(1, 16, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(2, 19, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(3, 20, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(4, 15, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(5, 14, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(6, 17, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(7, 18, 1, 5, '2025-10-02', 'Present', 1, '2025-10-02 07:38:54'),
(8, 16, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52'),
(9, 19, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52'),
(10, 20, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52'),
(11, 15, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52'),
(12, 14, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52'),
(13, 17, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52'),
(14, 18, 1, 5, '2025-10-03', 'Present', 1, '2025-10-03 11:26:52');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `section` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `name`, `section`, `created_at`) VALUES
(1, 'class 11', 'A', '2025-10-01 04:09:56'),
(2, 'Class A', 'Section 1', '2025-10-01 05:18:37'),
(3, 'python', 'a', '2025-10-03 10:57:31');

-- --------------------------------------------------------

--
-- Table structure for table `class_routines`
--

CREATE TABLE `class_routines` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_routines`
--

INSERT INTO `class_routines` (`id`, `class_id`, `teacher_id`, `subject_id`, `day_of_week`, `start_time`, `end_time`, `created_at`) VALUES
(2, 1, 34, 0, 'Monday', '12:00:00', '13:00:00', '2025-10-04 07:27:30'),
(3, 1, 2, 5, 'Monday', '08:00:00', '09:00:00', '2025-10-05 07:08:42'),
(4, 1, 34, 6, 'Monday', '09:00:00', '10:00:00', '2025-10-05 07:15:31');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `start_date`, `end_date`, `created_at`) VALUES
(1, 'navratri', '', '2025-11-11 04:11:00', '2025-12-11 06:00:00', '2025-10-03 11:45:54');

-- --------------------------------------------------------

--
-- Table structure for table `examinations`
--

CREATE TABLE `examinations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `class_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_marks` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `examinations`
--

INSERT INTO `examinations` (`id`, `name`, `class_id`, `start_date`, `end_date`, `total_marks`, `created_at`) VALUES
(1, 'mid term', 1, '2025-10-02', '2025-10-03', 100, '2025-10-02 07:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `fee_payments`
--

CREATE TABLE `fee_payments` (
  `id` int(11) NOT NULL,
  `fee_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_for_month` varchar(50) NOT NULL,
  `recorded_by_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fee_payments`
--

INSERT INTO `fee_payments` (`id`, `fee_id`, `student_id`, `class_id`, `amount`, `payment_date`, `payment_for_month`, `recorded_by_id`, `created_at`) VALUES
(1, 7, 20, 1, 400.00, '2025-10-03', 'October 2025', 1, '2025-10-03 11:39:44'),
(2, 6, 19, 1, 400.00, '2025-10-04', 'October 2025', 1, '2025-10-04 05:24:09');

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('New','Read','Archived') NOT NULL DEFAULT 'New',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`id`, `full_name`, `email`, `phone_number`, `subject`, `message`, `status`, `submitted_at`) VALUES
(1, 'harsh mangela', 'harshmangela2004@gmail.com', '06351182749', 'english', 'i need', 'New', '2025-10-02 07:01:01'),
(2, 'amit khandelwal', 'samit@gmail.comdemo', '', 'demo', 'sgkjhgsgdsghuyh', 'New', '2025-10-02 08:02:38'),
(3, 'amit khandelwal', 'samit@gmail.comdemo', '', 'demo', 'kjkjkkj', 'New', '2025-10-02 08:15:29'),
(4, 'amit khandelwal', 'amitkhandelwal0725@gmail.com', '', 'demo', 'hii', 'New', '2025-10-04 05:14:54');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_address', '123 School Lane, Education City, 12345'),
('school_email', 'contact@example.com'),
('school_logo', '../uploads/logo/logo_1759391440_Screenshot 2025-10-02 125035.png'),
('school_name', 'My Awesome School'),
('school_phone', '123-456-7890');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `student_code` varchar(50) DEFAULT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `username`, `email`, `name`, `student_code`, `class_id`, `created_at`, `reset_token`, `reset_token_expires_at`, `password`) VALUES
(1, '', '', 'Aarav Sharma', 'SMS-25-001', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(2, '', '', 'Vihaan Patel', 'SMS-25-002', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(3, '', '', 'Vivaan Singh', 'SMS-25-003', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(4, '', '', 'Aditya Kumar', 'SMS-25-004', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(5, '', '', 'Reyansh Gupta', 'SMS-25-005', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(6, '', '', 'Ayaan Khan', 'SMS-25-006', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(7, '', '', 'Krishna Yadav', 'SMS-25-007', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(8, '', '', 'Ishaan Ali', 'SMS-25-008', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(9, '', '', 'Ananya Sharma', 'SMS-25-009', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(10, '', '', 'Diya Patel', 'SMS-25-010', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(11, '', '', 'Saanvi Singh', 'SMS-25-011', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(12, '', '', 'Myra Kumar', 'SMS-25-012', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(13, '', '', 'Aadhya Gupta', 'SMS-25-013', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(14, '', '', 'Kiara Khan', 'SMS-25-014', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(15, '', '', 'Pari Yadav', 'SMS-25-015', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(16, '', '', 'Riya Ali', 'SMS-25-016', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(17, '', '', 'Arjun Mehta', 'SMS-25-017', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(18, '', '', 'Sai Joshi', 'SMS-25-018', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(19, '', '', 'Dhruv Shah', 'SMS-25-019', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(20, '', '', 'Kabir Reddy', 'SMS-25-020', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(21, '', '', 'Zayn Iyer', 'SMS-25-021', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(22, '', '', 'Advik Nair', 'SMS-25-022', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(23, '', '', 'Ishita Mehta', 'SMS-25-023', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(24, '', '', 'Navya Joshi', 'SMS-25-024', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(25, '', '', 'Zara Shah', 'SMS-25-025', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(26, '', '', 'Shanaya Reddy', 'SMS-25-026', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(27, '', '', 'Avni Iyer', 'SMS-25-027', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(28, '', '', 'Anika Nair', 'SMS-25-028', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(29, '', '', 'Aarohi Sharma', 'SMS-25-029', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(30, '', '', 'Eva Patel', 'SMS-25-030', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(31, '', '', 'Yash Singh', 'SMS-25-031', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(32, '', '', 'Rohan Kumar', 'SMS-25-032', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(33, '', '', 'Atharv Gupta', 'SMS-25-033', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(34, '', '', 'Veer Khan', 'SMS-25-034', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(35, '', '', 'Laksh Yadav', 'SMS-25-035', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(36, '', '', 'Pranav Ali', 'SMS-25-036', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(37, '', '', 'Dev Mehta', 'SMS-25-037', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(38, '', '', 'Neel Joshi', 'SMS-25-038', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(39, '', '', 'Samarth Shah', 'SMS-25-039', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(40, '', '', 'Ritvik Reddy', 'SMS-25-040', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(41, '', '', 'Ira Iyer', 'SMS-25-041', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(42, '', '', 'Amaira Nair', 'SMS-25-042', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(43, '', '', 'Khushi Sharma', 'SMS-25-043', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(44, '', '', 'Mahi Patel', 'SMS-25-044', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(45, '', '', 'Siya Singh', 'SMS-25-045', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(46, '', '', 'Aisha Kumar', 'SMS-25-046', 2, '2025-10-01 07:02:04', NULL, NULL, ''),
(47, '', '', 'Aryan Gupta', 'SMS-25-047', 3, '2025-10-01 07:02:04', NULL, NULL, ''),
(48, '', '', 'Kian Khan', 'SMS-25-048', 4, '2025-10-01 07:02:04', NULL, NULL, ''),
(49, '', '', 'Yuvaan Yadav', 'SMS-25-049', 5, '2025-10-01 07:02:04', NULL, NULL, ''),
(50, '', '', 'Aahana Ali', 'SMS-25-050', 1, '2025-10-01 07:02:04', NULL, NULL, ''),
(51, 'harsh', 'harshmangela2001@gmail.com', '', NULL, 0, '2025-10-05 06:39:37', NULL, NULL, '$2y$10$9voCWhRDGvYjOKe7UNg85.LGoL6gOhBIfPrMKSlKBZvOODRAnIgmO');

-- --------------------------------------------------------

--
-- Table structure for table `student_fees`
--

CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `fee_month` varchar(50) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  `payment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_fees`
--

INSERT INTO `student_fees` (`id`, `student_id`, `class_id`, `amount_due`, `fee_month`, `due_date`, `status`, `payment_id`, `created_at`) VALUES
(1, 14, 1, 400.00, 'October 2025', '2025-10-31', 'Unpaid', NULL, '2025-10-03 11:36:12'),
(2, 15, 1, 400.00, 'October 2025', '2025-10-31', 'Unpaid', NULL, '2025-10-03 11:36:12'),
(3, 16, 1, 400.00, 'October 2025', '2025-10-31', 'Unpaid', NULL, '2025-10-03 11:36:12'),
(4, 17, 1, 400.00, 'October 2025', '2025-10-31', 'Unpaid', NULL, '2025-10-03 11:36:12'),
(5, 18, 1, 400.00, 'October 2025', '2025-10-31', 'Unpaid', NULL, '2025-10-03 11:36:12'),
(6, 19, 1, 400.00, 'October 2025', '2025-10-31', 'Paid', 2, '2025-10-03 11:36:12'),
(7, 20, 1, 400.00, 'October 2025', '2025-10-31', 'Paid', 1, '2025-10-03 11:36:12');

-- --------------------------------------------------------

--
-- Table structure for table `study_materials`
--

CREATE TABLE `study_materials` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_materials`
--

INSERT INTO `study_materials` (`id`, `title`, `class_id`, `subject_id`, `file_name`, `file_path`, `uploaded_by_id`, `created_at`) VALUES
(1, 'a', 1, NULL, '1759391228_Screenshot 2025-08-07 111440.png', '../uploads/materials/1759391228_Screenshot 2025-08-07 111440.png', 1, '2025-10-02 07:47:08');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `class_id`, `teacher_id`, `created_at`) VALUES
(1, 'maths', 1, 2, '2025-10-01 04:41:35'),
(4, 'science', 1, NULL, '2025-10-01 05:41:52'),
(5, 'english', 1, 2, '2025-10-02 03:12:24'),
(6, 'python', 2, 4, '2025-10-02 03:14:07');

-- --------------------------------------------------------

--
-- Table structure for table `timetables`
--

CREATE TABLE `timetables` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `day` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetables`
--

INSERT INTO `timetables` (`id`, `class_id`, `teacher_id`, `subject_id`, `day`, `start_time`, `end_time`, `created_at`) VALUES
(1, 1, 1, 1, 'Monday', '09:00:00', '10:00:00', '2025-10-01 05:18:37'),
(3, 1, 4, 4, '0', '12:12:00', '13:12:00', '2025-10-01 05:42:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `email`, `password`, `role`, `class_id`, `created_at`) VALUES
(1, 'admin', NULL, NULL, 'admin@hrproject.com', 'admin123', 'admin', NULL, '2025-10-01 03:26:44'),
(2, 'harsh', 'John', 'Smith', 'harshmangela2004@gmail.com', '12345678', 'teacher', 1, '2025-10-01 03:27:10'),
(3, 'new', NULL, NULL, 'test@example.com', '$2y$10$2suR6OKg.ZaSlG28fRlbJeqH7WgSYkj2mrWPin5K8zyU9qSpFY47i', 'student', NULL, '2025-10-01 04:01:25'),
(4, 'teacher1', NULL, NULL, '', '', 'teacher', NULL, '2025-10-01 05:18:37'),
(6, 'kartavya', NULL, NULL, 'ka@gmail.com', '$2y$10$uAky.uo1l1q8rRi1rWfdUuxUL/9rhMv.O8Hx4fM86yA5Am1Gegx52', 'student', NULL, '2025-10-02 04:25:46'),
(14, 'student1', 'Rohan', 'Sharma', 'student1@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(15, 'student2', 'Priya', 'Patel', 'student2@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(16, 'student3', 'Amit', 'Singh', 'student3@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(17, 'student4', 'Sneha', 'Gupta', 'student4@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(18, 'student5', 'Vikram', 'Kumar', 'student5@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(19, 'student6', 'Anjali', 'Verma', 'student6@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(20, 'student7', 'Deepak', 'Mishra', 'student7@example.com', 'password123', 'student', 1, '2025-10-02 05:24:13'),
(21, 'student8', 'Neha', 'Chauhan', 'student8@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(22, 'student9', 'Sanjay', 'Yadav', 'student9@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(23, 'student10', 'Pooja', 'Mehta', 'student10@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(24, 'student11', 'Rahul', 'Jain', 'student11@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(25, 'student12', 'Kavita', 'Reddy', 'student12@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(26, 'student13', 'Manoj', 'Nair', 'student13@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(27, 'student14', 'Sunita', 'Biswas', 'student14@example.com', 'password123', 'student', 2, '2025-10-02 05:24:13'),
(28, 'student15', 'Arjun', 'Das', 'student15@example.com', 'password123', 'student', 3, '2025-10-02 05:24:13'),
(29, 'student16', 'Geeta', 'Iyer', 'student16@example.com', 'password123', 'student', 3, '2025-10-02 05:24:13'),
(30, 'student17', 'Rajesh', 'Malhotra', 'student17@example.com', 'password123', 'student', 3, '2025-10-02 05:24:13'),
(31, 'student18', 'Meena', 'Shah', 'student18@example.com', 'password123', 'student', 3, '2025-10-02 05:24:13'),
(32, 'student19', 'Alok', 'Trivedi', 'student19@example.com', 'password123', 'student', 3, '2025-10-02 05:24:13'),
(33, 'student20', 'Isha', 'Rao', 'student20@example.com', 'password123', 'student', 3, '2025-10-02 05:24:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance_record` (`student_id`,`attendance_date`,`subject_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `fk_attendance_taken_by` (`taken_by_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_routines`
--
ALTER TABLE `class_routines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `examinations`
--
ALTER TABLE `examinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `fee_payments`
--
ALTER TABLE `fee_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_month_fee` (`student_id`,`fee_month`);

--
-- Indexes for table `study_materials`
--
ALTER TABLE `study_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `fk_material_uploader` (`uploaded_by_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `timetables`
--
ALTER TABLE `timetables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `class_routines`
--
ALTER TABLE `class_routines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `examinations`
--
ALTER TABLE `examinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fee_payments`
--
ALTER TABLE `fee_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `study_materials`
--
ALTER TABLE `study_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `timetables`
--
ALTER TABLE `timetables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_taken_by` FOREIGN KEY (`taken_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `examinations`
--
ALTER TABLE `examinations`
  ADD CONSTRAINT `fk_exam_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `study_materials`
--
ALTER TABLE `study_materials`
  ADD CONSTRAINT `fk_material_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_material_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_material_uploader` FOREIGN KEY (`uploaded_by_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `subjects_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `timetables`
--
ALTER TABLE `timetables`
  ADD CONSTRAINT `timetables_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetables_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetables_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
