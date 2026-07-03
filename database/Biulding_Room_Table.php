<?php
include 'db_connection.php';

// Create buildings table
$sql_buildings = "CREATE TABLE IF NOT EXISTS buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL
)";

if ($conn->query($sql_buildings) === TRUE) {
    echo "Table 'buildings' created successfully.";
} else {
    echo "Error creating table 'buildings': " . $conn->error;
}

// Create rooms table
$sql_rooms = "CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(255) NOT NULL,
    building_id INT,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
)";

if ($conn->query($sql_rooms) === TRUE) {
    echo "Table 'rooms' created successfully.";
} else {
    echo "Error creating table 'rooms': " . $conn->error;
}

$conn->close();
?>
