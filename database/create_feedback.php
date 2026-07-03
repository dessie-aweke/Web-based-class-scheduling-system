<?php
include 'database/db_connection.php';

$sql_feedback = "CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    feedback_text TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql_feedback) === TRUE) {
    echo "Table 'feedback' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>