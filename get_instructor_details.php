<?php
// Include database connection
include 'database/db_connection.php';

// Check if required parameters are provided (at least batch_id and optionally instructor_id)
if (!isset($_GET['batch_id'])) {
    echo json_encode(['error' => 'Missing required parameter: batch_id']);
    exit();
}

$batch_id = $conn->real_escape_string($_GET['batch_id']);
$instructor_id = isset($_GET['instructor_id']) ? $conn->real_escape_string($_GET['instructor_id']) : null;
$day = isset($_GET['day']) ? $conn->real_escape_string($_GET['day']) : null;
$start_time = isset($_GET['start_time']) ? $conn->real_escape_string($_GET['start_time']) : null;
$end_time = isset($_GET['end_time']) ? $conn->real_escape_string($_GET['end_time']) : null;
$schedule_id = isset($_GET['schedule_id']) ? $conn->real_escape_string($_GET['schedule_id']) : null;

$instructors = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Fetch instructor details if instructor_id is provided, otherwise fetch all instructors
if ($instructor_id) {
    $instructors_query = "SELECT i.instructor_id, i.instructor_name, d.dept_name 
                         FROM instructors i 
                         LEFT JOIN departments d ON i.dept_id = d.dept_id 
                         WHERE i.instructor_id = '$instructor_id'";
} else {
    $instructors_query = "SELECT i.instructor_id, i.instructor_name, d.dept_name 
                         FROM instructors i 
                         LEFT JOIN departments d ON i.dept_id = d.dept_id";
}
$instructors_result = $conn->query($instructors_query);
if ($instructors_result === false) {
    echo json_encode(['error' => 'Error fetching instructors: ' . $conn->error]);
    exit();
}

while ($instructor = $instructors_result->fetch_assoc()) {
    $instructor_id = $instructor['instructor_id'];

    // Check specific availability if day, start_time, and end_time are provided
    $is_available = true;
    $conflicting_sessions = [];
    if ($day && $start_time && $end_time) {
        $availability_query = "SELECT ss.* 
                              FROM section_schedules ss 
                              WHERE ss.instructor_id = '$instructor_id' 
                              AND ss.day = '$day' 
                              AND (
                                  (ss.start_time <= '$start_time' AND ss.end_time > '$start_time') OR 
                                  (ss.start_time < '$end_time' AND ss.end_time >= '$end_time') OR 
                                  (ss.start_time >= '$start_time' AND ss.end_time <= '$end_time')
                              )";
        if ($schedule_id) {
            $availability_query .= " AND ss.section_schedule_id != '$schedule_id'";
        }
        $availability_result = $conn->query($availability_query);
        if ($availability_result === false) {
            echo json_encode(['error' => 'Error checking availability: ' . $conn->error]);
            exit();
        }

        $is_available = $availability_result->num_rows == 0;
        if (!$is_available) {
            while ($session = $availability_result->fetch_assoc()) {
                $conflicting_sessions[] = [
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                    'day' => $session['day'],
                    'schedule_type' => $session['schedule_type']
                ];
            }
        }
    }

    // Fetch instructor's availability across all days
    $availability = [];
    foreach ($days as $d) {
        $day_schedules_query = "SELECT ss.start_time, ss.end_time 
                               FROM section_schedules ss 
                               WHERE ss.instructor_id = '$instructor_id' 
                               AND ss.day = '$d'";
        if ($schedule_id) {
            $day_schedules_query .= " AND ss.section_schedule_id != '$schedule_id'";
        }
        $day_schedules_result = $conn->query($day_schedules_query);
        if ($day_schedules_result === false) {
            echo json_encode(['error' => 'Error fetching day schedules: ' . $conn->error]);
            exit();
        }

        $day_busy_times = [];
        while ($schedule = $day_schedules_result->fetch_assoc()) {
            $day_busy_times[] = $schedule['start_time'] . ' to ' . $schedule['end_time'];
        }

        $availability[$d] = count($day_busy_times) > 0 ? $day_busy_times : 'Free all day';
    }

    // Fetch courses already assigned to the instructor across all batches
    $courses_query = "SELECT DISTINCT c.course_title 
                      FROM section_schedules ss 
                      JOIN courses c ON ss.course_id = c.course_id 
                      WHERE ss.instructor_id = '$instructor_id'";
    if ($schedule_id) {
        $courses_query .= " AND ss.section_schedule_id != '$schedule_id'";
    }
    $courses_result = $conn->query($courses_query);
    if ($courses_result === false) {
        echo json_encode(['error' => 'Error fetching assigned courses: ' . $conn->error]);
        exit();
    }

    $assigned_courses = [];
    while ($course = $courses_result->fetch_assoc()) {
        $assigned_courses[] = $course['course_title'];
    }

    // Add instructor details to the response
    $instructors[] = [
        'id' => $instructor['instructor_id'],
        'name' => $instructor['instructor_name'],
        'department' => $instructor['dept_name'] ?: 'Not specified',
        'is_available' => $is_available,
        'availability_status' => $day && $start_time && $end_time ? ($is_available ? 'Free' : 'Occupied') : 'Check day and time',
        'conflicting_sessions' => $conflicting_sessions,
        'availability' => $availability,
        'assigned_courses' => $assigned_courses
    ];
}

// Prepare response
$response = [
    'instructors' => $instructors
];

echo json_encode($response);

$conn->close();
?>