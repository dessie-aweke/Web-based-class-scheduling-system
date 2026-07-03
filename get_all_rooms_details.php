<?php
// Include database connection
include 'database/db_connection.php';

// Check if required parameters are provided
if (!isset($_GET['day']) || !isset($_GET['start_time']) || !isset($_GET['end_time']) || !isset($_GET['batch_id'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$day = $conn->real_escape_string($_GET['day']);
$start_time = $conn->real_escape_string($_GET['start_time']);
$end_time = $conn->real_escape_string($_GET['end_time']);
$batch_id = $conn->real_escape_string($_GET['batch_id']);
$schedule_id = isset($_GET['schedule_id']) ? $conn->real_escape_string($_GET['schedule_id']) : null;

// Fetch all rooms
$rooms_query = "SELECT r.id, r.room_name, r.type, b.building_name 
                FROM rooms r 
                JOIN buildings b ON r.building_id = b.id";
$rooms_result = $conn->query($rooms_query);
if ($rooms_result === false) {
    echo json_encode(['error' => 'Error fetching rooms: ' . $conn->error]);
    exit();
}

$rooms = [];
while ($room = $rooms_result->fetch_assoc()) {
    $room_id = $room['id'];

    // Check room availability (excluding the current schedule if editing)
    $availability_query = "SELECT ss.*, c.course_title, s.section_name 
                          FROM section_schedules ss 
                          JOIN courses c ON ss.course_id = c.course_id 
                          JOIN sections s ON ss.section_id = s.id 
                          WHERE ss.room_id = '$room_id' 
                          AND ss.day = '$day' 
                          AND ss.batch_id = '$batch_id'
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
    $scheduled_sessions = [];
    if (!$is_available) {
        while ($session = $availability_result->fetch_assoc()) {
            $scheduled_sessions[] = [
                'course_title' => $session['course_title'],
                'section_name' => $session['section_name'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'schedule_type' => $session['schedule_type']
            ];
        }
    }

    // Fetch all scheduled sessions in this room for the selected day
    $all_sessions_query = "SELECT ss.*, c.course_title, s.section_name 
                           FROM section_schedules ss 
                           JOIN courses c ON ss.course_id = c.course_id 
                           JOIN sections s ON ss.section_id = s.id 
                           WHERE ss.room_id = '$room_id' 
                           AND ss.day = '$day' 
                           AND ss.batch_id = '$batch_id'";
    if ($schedule_id) {
        $all_sessions_query .= " AND ss.section_schedule_id != '$schedule_id'";
    }
    $all_sessions_result = $conn->query($all_sessions_query);
    if ($all_sessions_result === false) {
        echo json_encode(['error' => 'Error fetching scheduled sessions: ' . $conn->error]);
        exit();
    }

    $all_sessions = [];
    while ($session = $all_sessions_result->fetch_assoc()) {
        $all_sessions[] = [
            'course_title' => $session['course_title'],
            'section_name' => $session['section_name'],
            'start_time' => $session['start_time'],
            'end_time' => $session['end_time'],
            'schedule_type' => $session['schedule_type']
        ];
    }

    // Add room details to the response
    $rooms[] = [
        'id' => $room['id'],
        'room_name' => $room['room_name'],
        'building_name' => $room['building_name'],
        'type' => $room['type'],
        'is_available' => $is_available,
        'availability_status' => $is_available ? 'Free' : 'Occupied',
        'conflicting_sessions' => $scheduled_sessions,
        'all_sessions' => $all_sessions
    ];
}

// Prepare response
$response = [
    'rooms' => $rooms
];

echo json_encode($response);

$conn->close();
?>