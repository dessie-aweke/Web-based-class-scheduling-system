<?php
include 'database/db_connection.php';

$sql_dept = "CREATE TABLE IF NOT EXISTS departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(50) NOT NULL,
    dept_code VARCHAR(10) UNIQUE NOT NULL,
    head_id INT,
    FOREIGN KEY (head_id) REFERENCES users(user_id)
)";
$conn->query($sql_dept) or die("Error creating departments: " . $conn->error);

$sql_batches = "CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dept_id INT,
    batch_name VARCHAR(50) NOT NULL,
    num_sections INT NOT NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
)";
$conn->query($sql_batches) or die("Error creating batches: " . $conn->error);

$sql_sections = "CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT,
    section_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (batch_id) REFERENCES batches(id)
)";
$conn->query($sql_sections) or die("Error creating sections: " . $conn->error);

echo "Tables created successfully!";
$conn->close();
?>