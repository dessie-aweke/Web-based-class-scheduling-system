-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2025 at 04:44 AM
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
-- Database: `dmu_class_scheduling`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_dates`
--

CREATE TABLE `academic_dates` (
  `date_id` int(11) NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_dates`
--

INSERT INTO `academic_dates` (`date_id`, `academic_year`, `semester`, `start_date`, `end_date`, `created_at`) VALUES
(1, '2025-2026', '1st semster', '2025-03-03', '2025-03-06', '2025-03-31 19:38:41'),
(2, '2025-2026', '2nd semter', '2025-06-15', '2025-09-15', '2025-04-01 09:32:58');

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` int(11) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `batch_name` varchar(50) NOT NULL,
  `num_sections` int(11) NOT NULL,
  `academic_date_id` int(11) NOT NULL,
  `semester_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`id`, `dept_id`, `batch_name`, `num_sections`, `academic_date_id`, `semester_id`) VALUES
(30, 7, '2nd year', 2, 0, 1),
(31, 7, '3rd year', 3, 0, 1),
(32, 7, '2nd year', 2, 0, 2),
(33, 7, '4th year', 2, 0, 1),
(36, 7, '3rd year', 1, 0, 2),
(37, 7, '4th year', 1, 0, 2);

-- --------------------------------------------------------

--
-- Table structure for table `batch_course_assignments`
--

CREATE TABLE `batch_course_assignments` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `semester_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_course_assignments`
--

INSERT INTO `batch_course_assignments` (`id`, `batch_id`, `course_id`, `semester_id`) VALUES
(28, 30, 9, 1),
(25, 32, 7, 2),
(26, 33, 20, 1);

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `building_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `building_name`, `location`, `is_active`) VALUES
(1, 'B1', 'new biulding', 1),
(2, 'B2', 'new biulding', 1),
(4, 'w1', '11', 1),
(6, 'B3', '22', 1);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(100) NOT NULL,
  `credit_hours` int(11) NOT NULL,
  `lecture_hours` int(11) NOT NULL,
  `course_type` enum('Lecture','Lab','Both') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_title`, `credit_hours`, `lecture_hours`, `course_type`) VALUES
(7, 'cs101', 'introduction of distrubuted system', 3, 6, 'Both'),
(8, 'cs103', 'network and system administration', 3, 6, 'Both'),
(9, 'cs104', 'selected topic in computer science ', 3, 3, 'Lecture'),
(10, 'cs105', 'entrepreneurship and business development', 2, 2, 'Lecture'),
(11, 'cs106', 'Elective 2', 3, 6, 'Both'),
(12, 'mg1', 'accounting finance', 3, 3, 'Lecture'),
(17, '204', 'ma niga', 3, 3, 'Lecture'),
(18, '101', 'tele', 3, 3, 'Both'),
(20, 'En543', 'enterprenurship', 3, 3, 'Lecture'),
(21, 't54', 'finance', 3, 3, 'Lecture'),
(24, 'fd32', 'pschology', 3, 3, 'Lecture');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(50) NOT NULL,
  `dept_code` varchar(10) NOT NULL,
  `head_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_code`, `head_id`) VALUES
(7, 'computer science', 'cs1', 6),
(10, 'management', 'mg1', 7);

-- --------------------------------------------------------

--
-- Table structure for table `department_courses`
--

CREATE TABLE `department_courses` (
  `dept_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_courses`
--

INSERT INTO `department_courses` (`dept_id`, `course_id`) VALUES
(7, 7),
(7, 8),
(7, 9),
(7, 10),
(7, 11),
(10, 12);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `feedback_message` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `user_id`, `role`, `name`, `feedback_message`, `submitted_at`, `is_read`) VALUES
(9, 0, 'Student', 'dfg', 'asdvffvvfcvvghjjjhjm', '2025-05-18 22:41:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `instructors`
--

CREATE TABLE `instructors` (
  `instructor_id` int(11) NOT NULL,
  `instructor_name` varchar(100) NOT NULL,
  `dept_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instructors`
--

INSERT INTO `instructors` (`instructor_id`, `instructor_name`, `dept_id`) VALUES
(2, 'robel M', NULL),
(3, 'Kassahun A', NULL),
(4, 'debalkew G', NULL),
(5, 'TBA', NULL),
(6, 'mewuded Y', NULL),
(7, 'yhalem A', NULL),
(9, 'tha', NULL),
(13, 'antenh', NULL),
(14, 'ABEBE ', NULL),
(15, 'ENDESHEW ', NULL),
(16, 'alemnh', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(255) DEFAULT NULL,
  `building_id` int(11) DEFAULT NULL,
  `type` enum('Lab','Regular') NOT NULL DEFAULT 'Regular',
  `capacity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `building_id`, `type`, `capacity`, `is_active`) VALUES
(9, '103', 1, 'Regular', 60, 1),
(18, '108', 4, 'Regular', 50, 1),
(22, '201', 1, 'Regular', 60, 1),
(23, '001', 6, 'Regular', 70, 1),
(24, '003', 6, 'Regular', 70, 1),
(25, '004', 6, 'Regular', 70, 1),
(26, '005', 6, 'Regular', 70, 1),
(27, '101', 6, 'Regular', 70, 1),
(28, '102', 6, 'Regular', 70, 1),
(29, '103', 6, 'Regular', 60, 1),
(30, '104', 6, 'Regular', 60, 1),
(31, '105', 6, 'Regular', 60, 1),
(32, '201', 6, 'Lab', 50, 1);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `section_name` varchar(50) NOT NULL,
  `num_students` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `batch_id`, `section_name`, `num_students`) VALUES
(86, 30, 'Section A', 50),
(87, 30, 'Section B', 50),
(88, 31, 'Section A', 0),
(89, 31, 'Section B', 0),
(90, 31, 'Section C', 0),
(91, 32, 'Section A', 45),
(92, 32, 'Section B', 50),
(94, 33, 'Section A', 50),
(95, 33, 'Section B', 50),
(126, 36, 'Section A', 52),
(127, 37, 'Section A', 0);

-- --------------------------------------------------------

--
-- Table structure for table `section_schedules`
--

CREATE TABLE `section_schedules` (
  `section_schedule_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `academic_date_id` int(11) NOT NULL,
  `schedule_type` enum('Lecture','Lab') NOT NULL,
  `semester_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_schedules`
--

INSERT INTO `section_schedules` (`section_schedule_id`, `dept_id`, `batch_id`, `section_id`, `course_id`, `instructor_id`, `room_id`, `day`, `start_time`, `end_time`, `academic_date_id`, `schedule_type`, `semester_id`) VALUES
(29, 7, 30, 86, 9, 3, 22, 'Monday', '02:00:00', '05:00:00', 0, 'Lecture', NULL),
(30, 7, 32, 91, 7, 3, 18, 'Wednesday', '02:01:00', '04:19:00', 0, 'Lecture', 2),
(33, 7, 30, 86, 9, 16, 25, 'Wednesday', '02:10:00', '04:30:00', 0, 'Lecture', 1);

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `semester_id` int(11) NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`semester_id`, `semester_name`, `created_at`) VALUES
(1, '1st semester', '2025-05-17 23:10:28'),
(2, '2nd semester', '2025-05-17 23:10:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','dept_head','instructor','student') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `employee_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `email`, `full_name`, `role`, `department_id`, `department`, `created_at`, `is_active`, `employee_id`) VALUES
(5, 'dessie', '$2y$10$S03gB8PsJy7nHEMmSN2tMOyuR5sX3IJLZw5EdYCP4dfoLOS0JChsC', 'awekedessie250@gmail.com', 'dessie aweke', 'admin', NULL, '', '2025-03-30 20:16:28', 1, 'dmu1405913'),
(6, 'yhalem', '$2y$10$tWoz1kYOsVKpinjSTCOxaOH8VqxJXcLUPfd2NgESYDcfqT7QgEFuK', 'yhalemayalu@gmail.com', 'yhalem ayalu', 'dept_head', 7, 'computer science', '2025-03-30 20:18:33', 1, 'dmu1405314'),
(7, 'shibeshi', '$2y$10$sPNRnmMrD6fn2vI4zLC.0ufDz3AjimSjZVHp9TMut4gGLOqt8K5He', 'bosstikva27@gmail.com', 'shibeshi tadele', 'dept_head', 10, 'management', '2025-03-30 20:19:31', 1, 'dmu140567');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_dates`
--
ALTER TABLE `academic_dates`
  ADD PRIMARY KEY (`date_id`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_semester` (`dept_id`,`batch_name`,`semester_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `fk_batches_semester` (`semester_id`);

--
-- Indexes for table `batch_course_assignments`
--
ALTER TABLE `batch_course_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_course_semester` (`batch_id`,`course_id`,`semester_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `fk_batch_course_semester` (`semester_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `dept_code` (`dept_code`),
  ADD KEY `head_id` (`head_id`);

--
-- Indexes for table `department_courses`
--
ALTER TABLE `department_courses`
  ADD PRIMARY KEY (`dept_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `instructors`
--
ALTER TABLE `instructors`
  ADD PRIMARY KEY (`instructor_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `building_id` (`building_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `section_schedules`
--
ALTER TABLE `section_schedules`
  ADD PRIMARY KEY (`section_schedule_id`),
  ADD UNIQUE KEY `instructor_id` (`instructor_id`,`day`,`start_time`),
  ADD UNIQUE KEY `room_id` (`room_id`,`day`,`start_time`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `fk_section_schedules_semester` (`semester_id`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`semester_id`),
  ADD UNIQUE KEY `semester_name` (`semester_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_dates`
--
ALTER TABLE `academic_dates`
  MODIFY `date_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `batch_course_assignments`
--
ALTER TABLE `batch_course_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `instructors`
--
ALTER TABLE `instructors`
  MODIFY `instructor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `section_schedules`
--
ALTER TABLE `section_schedules`
  MODIFY `section_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `fk_batches_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`) ON DELETE SET NULL;

--
-- Constraints for table `batch_course_assignments`
--
ALTER TABLE `batch_course_assignments`
  ADD CONSTRAINT `batch_course_assignments_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`),
  ADD CONSTRAINT `batch_course_assignments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `fk_batch_course_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`head_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `department_courses`
--
ALTER TABLE `department_courses`
  ADD CONSTRAINT `department_courses_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `department_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `instructors`
--
ALTER TABLE `instructors`
  ADD CONSTRAINT `instructors_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`);

--
-- Constraints for table `section_schedules`
--
ALTER TABLE `section_schedules`
  ADD CONSTRAINT `fk_section_schedules_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `section_schedules_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `section_schedules_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`),
  ADD CONSTRAINT `section_schedules_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `section_schedules_ibfk_4` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `section_schedules_ibfk_5` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`instructor_id`),
  ADD CONSTRAINT `section_schedules_ibfk_6` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`dept_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
