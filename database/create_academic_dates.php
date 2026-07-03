<?php
include 'db_connection.php';

$sql_dates = "CREATE TABLE academic_dates (
    date_id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year VARCHAR(9) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_dates) === TRUE) {
    echo "Table 'academic_dates' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>