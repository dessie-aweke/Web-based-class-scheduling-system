<?php
include 'db_connection.php';

// Create section_schedules table
$sql_section_schedules = "CREATE TABLE IF NOT EXISTS section_schedules (
    section_schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_id INT NOT NULL,
    batch_id INT NOT NULL,
    section_id INT NOT NULL,
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    room_id INT NOT NULL,
    day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id),
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    UNIQUE (instructor_id, day, start_time), -- Prevent instructor double-booking
    UNIQUE (room_id, day, start_time) -- Prevent room double-booking
)";

if ($conn->query($sql_section_schedules) === TRUE) {
    echo "Table 'section_schedules' created successfully.<br>";
} else {
    echo "Error creating table 'section_schedules': " . $conn->error . "<br>";
}

$conn->close();
?>