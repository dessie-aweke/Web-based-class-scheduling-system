<?php
include 'database/db_connection.php';

// Courses table (global)
$sql_courses = "CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_title VARCHAR(100) NOT NULL,
    credit_hours INT NOT NULL,
    lecture_hours INT NOT NULL,
    course_type ENUM('Lecture', 'Lab', 'Both') NOT NULL
)";
$conn->query($sql_courses) or die("Error creating courses: " . $conn->error);

// Department-Course mapping
$sql_dept_courses = "CREATE TABLE IF NOT EXISTS department_courses (
    dept_id INT,
    course_id INT,
    PRIMARY KEY (dept_id, course_id),
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
)";
$conn->query($sql_dept_courses) or die("Error creating department_courses: " . $conn->error);

// Instructors table (global, no availability)
$sql_instructors = "CREATE TABLE IF NOT EXISTS instructors (
    instructor_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_name VARCHAR(100) NOT NULL
)";
$conn->query($sql_instructors) or die("Error creating instructors: " . $conn->error);

// Drop availability column if it exists
$conn->query("ALTER TABLE instructors DROP COLUMN IF EXISTS availability") or die("Error dropping availability: " . $conn->error);

echo "Tables updated successfully!";
$conn->close();
?>