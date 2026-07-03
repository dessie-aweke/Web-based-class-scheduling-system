<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "dept_head") {
    header("Location: login.php");
    exit();
}

include 'database/db_connection.php';

$user_id = $conn->real_escape_string($_SESSION["user_id"]);

// Fetch departments for this dept head
$dept_query = "SELECT dept_id, dept_name FROM departments WHERE head_id = '$user_id'";
$dept_result = $conn->query($dept_query);
if ($dept_result === false) {
    die("Department query failed: " . $conn->error);
}
$departments = $dept_result->fetch_all(MYSQLI_ASSOC);

// Handle batch-semester selection
$selected_batch_id = null;
$selected_semester_id = null;
if (isset($_GET['batch_semester'])) {
    list($selected_batch_id, $selected_semester_id) = explode(':', $conn->real_escape_string($_GET['batch_semester']));
    $selected_batch_id = $conn->real_escape_string($selected_batch_id);
    $selected_semester_id = $conn->real_escape_string($selected_semester_id);
}

// Fetch batch-semester combinations
$batch_semesters_query = "SELECT b.id AS batch_id, b.batch_name, s.semester_id, s.semester_name, d.dept_id
                         FROM batches b
                         JOIN batch_course_assignments bca ON b.id = bca.batch_id
                         JOIN semesters s ON bca.semester_id = s.semester_id
                         JOIN departments d ON b.dept_id = d.dept_id
                         WHERE d.head_id = '$user_id'
                         ORDER BY b.batch_name, s.semester_name";
$batch_semesters_result = $conn->query($batch_semesters_query);
if ($batch_semesters_result === false) {
    die("Batch-semester query failed: " . $conn->error);
}
$batch_semesters = $batch_semesters_result->fetch_all(MYSQLI_ASSOC);

// Fetch courses assigned to the selected batch and semester
$courses = [];
if ($selected_batch_id && $selected_semester_id) {
    $courses_query = "SELECT c.* FROM courses c
                      JOIN batch_course_assignments bc ON c.course_id = bc.course_id
                      WHERE bc.batch_id = '$selected_batch_id' AND bc.semester_id = '$selected_semester_id'";
    $courses_result = $conn->query($courses_query);
    if ($courses_result === false) {
        die("Courses query failed: " . $conn->error);
    }
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
}

$instructors_query = "SELECT * FROM instructors";
$instructors_result = $conn->query($instructors_query);
if ($instructors_result === false) {
    die("Instructors query failed: " . $conn->error);
}
$instructors = $instructors_result->fetch_all(MYSQLI_ASSOC);

$rooms_query = "SELECT r.id, r.room_name, r.type, b.building_name
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id";
$rooms_result = $conn->query($rooms_query);
if ($rooms_result === false) {
    die("Rooms query failed: " . $conn->error);
}
$rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);

// Fetch schedules and sections for the selected batch and semester
$schedules = [];
$sections = [];
if ($selected_batch_id && $selected_semester_id) {
    $schedules_query = "SELECT ss.*, s.section_name, c.course_code, c.course_title, c.course_type, ss.schedule_type, i.instructor_id, i.instructor_name, r.id as room_id, r.room_name, b.building_name, sem.semester_name
                        FROM section_schedules ss
                        JOIN sections s ON ss.section_id = s.id
                        JOIN courses c ON ss.course_id = c.course_id
                        JOIN instructors i ON ss.instructor_id = i.instructor_id
                        JOIN rooms r ON ss.room_id = r.id
                        JOIN buildings b ON r.building_id = b.id
                        JOIN semesters sem ON ss.semester_id = sem.semester_id
                        WHERE ss.batch_id = '$selected_batch_id' AND ss.semester_id = '$selected_semester_id'";
    $schedules_result = $conn->query($schedules_query);
    if ($schedules_result === false) {
        die("Schedules query failed: " . $conn->error);
    }
    $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);

    $sections_query = "SELECT * FROM sections WHERE batch_id = '$selected_batch_id'";
    $sections_result = $conn->query($sections_query);
    if ($sections_result === false) {
        die("Sections query failed: " . $conn->error);
    }
    $sections = $sections_result->fetch_all(MYSQLI_ASSOC);
}

// Handle schedule to edit
$edit_schedule = null;
if (isset($_GET['edit_schedule_id'])) {
    $edit_schedule_id = $conn->real_escape_string($_GET['edit_schedule_id']);
    $edit_query = "SELECT ss.*, s.section_name, c.course_code, c.course_title, c.course_type, ss.schedule_type, i.instructor_id, i.instructor_name, r.id as room_id, r.room_name, b.building_name, sem.semester_name, sem.semester_id
                   FROM section_schedules ss
                   JOIN sections s ON ss.section_id = s.id
                   JOIN courses c ON ss.course_id = c.course_id
                   JOIN instructors i ON ss.instructor_id = i.instructor_id
                   JOIN rooms r ON ss.room_id = r.id
                   JOIN buildings b ON r.building_id = b.id
                   JOIN semesters sem ON ss.semester_id = sem.semester_id
                   WHERE ss.section_schedule_id = '$edit_schedule_id'";
    $edit_result = $conn->query($edit_query);
    if ($edit_result && $edit_result->num_rows > 0) {
        $edit_schedule = $edit_result->fetch_assoc();
        $selected_batch_id = $edit_schedule['batch_id'];
        $selected_semester_id = $edit_schedule['semester_id'];
    } else {
        $error = "Schedule not found for editing.";
    }
}

// Input validation function
function validateScheduleInput($data, &$error) {
    // Validate numeric IDs
    $numeric_fields = ['batch_id', 'semester_id', 'section_id', 'course_id', 'instructor_id', 'room_id', 'dept_id'];
    foreach ($numeric_fields as $field) {
        if (!isset($data[$field]) || !is_numeric($data[$field]) || $data[$field] <= 0) {
            $error = "Invalid $field provided.";
            return false;
        }
    }

    // Validate day
    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    if (!isset($data['day']) || !in_array($data['day'], $valid_days)) {
        $error = "Invalid day provided.";
        return false;
    }

    // Validate times
    if (!isset($data['start_time']) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $data['start_time'])) {
        $error = "Invalid start time provided.";
        return false;
    }
    if (!isset($data['end_time']) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $data['end_time'])) {
        $error = "Invalid end time provided.";
        return false;
    }
    if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
        $error = "End time must be after start time.";
        return false;
    }

    // Validate schedule type
    if (!isset($data['schedule_type']) || !in_array($data['schedule_type'], ['Lecture', 'Lab'])) {
        $error = "Invalid schedule type provided.";
        return false;
    }

    return true;
}

// Handle schedule update
if (isset($_POST['update_schedule'])) {
    $schedule_id = $conn->real_escape_string($_POST['schedule_id']);
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    $semester_id = $conn->real_escape_string($_POST['semester_id']);
    $section_id = $conn->real_escape_string($_POST['section_id']);
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $instructor_id = $conn->real_escape_string($_POST['instructor_id']);
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $day = $conn->real_escape_string($_POST['day']);
    $start_time = $conn->real_escape_string($_POST['start_time']);
    $end_time = $conn->real_escape_string($_POST['end_time']);
    $dept_id = $conn->real_escape_string($_POST['dept_id']);
    $schedule_type = $conn->real_escape_string($_POST['schedule_type']);
    $academic_date_id = 0;

    // Validate inputs
    $input_data = [
        'batch_id' => $batch_id,
        'semester_id' => $semester_id,
        'section_id' => $section_id,
        'course_id' => $course_id,
        'instructor_id' => $instructor_id,
        'room_id' => $room_id,
        'dept_id' => $dept_id,
        'day' => $day,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'schedule_type' => $schedule_type,
        'schedule_id' => $schedule_id
    ];
    if (!validateScheduleInput($input_data, $error)) {
        // Error is set in validateScheduleInput
    } else {
        // Verify course assignment
        $course_batch_check = "SELECT * FROM batch_course_assignments bc
                              WHERE bc.course_id = '$course_id' AND bc.batch_id = '$batch_id' AND bc.semester_id = '$semester_id'";
        $course_batch_result = $conn->query($course_batch_check);
        if ($course_batch_result === false) {
            $error = "Error verifying course assignment: " . $conn->error;
        } elseif ($course_batch_result->num_rows == 0) {
            $error = "The selected course is not assigned to this batch and semester.";
        }

        // Check instructor overlap
        if (!isset($error)) {
            $overlap_query = "SELECT ss.*, s.section_name, c.course_title, b.batch_name
                             FROM section_schedules ss
                             JOIN sections s ON ss.section_id = s.id
                             JOIN courses c ON ss.course_id = c.course_id
                             JOIN batches b ON ss.batch_id = b.id
                             WHERE ss.instructor_id = '$instructor_id'
                             AND ss.day = '$day'
                             AND ss.semester_id = '$semester_id'
                             AND ss.section_schedule_id != '$schedule_id'
                             AND (
                                 (ss.start_time <= '$start_time' AND ss.end_time > '$start_time') OR
                                 (ss.start_time < '$end_time' AND ss.end_time >= '$end_time') OR
                                 (ss.start_time >= '$start_time' AND ss.end_time <= '$end_time')
                             )";
            $overlap_result = $conn->query($overlap_query);
            if ($overlap_result === false) {
                $error = "Error checking instructor overlaps: " . $conn->error;
            } elseif ($overlap_result->num_rows > 0) {
                $error = "This instructor is already scheduled during this time on $day in this semester.";
            }
        }

        // Check room availability
        if (!isset($error)) {
            $room_overlap_query = "SELECT ss.*, s.section_name, c.course_title, b.batch_name
                                  FROM section_schedules ss
                                  JOIN sections s ON ss.section_id = s.id
                                  JOIN courses c ON ss.course_id = c.course_id
                                  JOIN batches b ON ss.batch_id = b.id
                                  WHERE ss.room_id = '$room_id'
                                  AND ss.day = '$day'
                                  AND ss.batch_id = '$batch_id'
                                  AND ss.semester_id = '$semester_id'
                                  AND ss.section_schedule_id != '$schedule_id'
                                  AND (
                                      (ss.start_time <= '$start_time' AND ss.end_time > '$start_time') OR
                                      (ss.start_time < '$end_time' AND ss.end_time >= '$end_time') OR
                                      (ss.start_time >= '$start_time' AND ss.end_time <= '$end_time')
                                  )";
            $room_overlap_result = $conn->query($room_overlap_query);
            if ($room_overlap_result === false) {
                $error = "Error checking room overlaps: " . $conn->error;
            } elseif ($room_overlap_result->num_rows > 0) {
                $error = "This room is already booked during this time on $day in this batch and semester.";
            }
        }

        // Check room type compatibility
        if (!isset($error)) {
            $room_type_query = "SELECT type FROM rooms WHERE id = '$room_id'";
            $room_type_result = $conn->query($room_type_query);
            if ($room_type_result && $room_type_result->num_rows > 0) {
                $room_type = $room_type_result->fetch_assoc()['type'];
                if ($schedule_type === 'Lab' && $room_type !== 'Lab') {
                    $error = "A Lab schedule must be assigned to a Lab room.";
                } elseif ($schedule_type === 'Lecture' && $room_type === 'Lab') {
                    $warning = "A Lecture schedule is being assigned to a Lab room.";
                }
            }
        }

        // Check section scheduling constraints
        if (!isset($error)) {
            $lecture_check_query = "SELECT * FROM section_schedules
                                   WHERE section_id = '$section_id'
                                   AND course_id = '$course_id'
                                   AND semester_id = '$semester_id'
                                   AND schedule_type = 'Lecture'
                                   AND section_schedule_id != '$schedule_id'";
            $lecture_check_result = $conn->query($lecture_check_query);
            if ($lecture_check_result === false) {
                $error = "Error checking lecture schedules: " . $conn->error;
            } elseif ($schedule_type === 'Lecture' && $lecture_check_result->num_rows > 0) {
                $error = "This section already has a lecture for this course in this semester.";
            }

            if (!isset($error)) {
                $lab_check_query = "SELECT * FROM section_schedules
                                   WHERE section_id = '$section_id'
                                   AND course_id = '$course_id'
                                   AND semester_id = '$semester_id'
                                   AND schedule_type = 'Lab'
                                   AND section_schedule_id != '$schedule_id'";
                $lab_check_result = $conn->query($lab_check_query);
                if ($lab_check_result === false) {
                    $error = "Error checking lab schedules: " . $conn->error;
                } elseif ($schedule_type === 'Lab' && $lab_check_result->num_rows > 0) {
                    $error = "This section already has a lab for this course in this semester.";
                } elseif ($lab_check_result->num_rows > 0 && $schedule_type === 'Lecture') {
                    while ($existing_lab = $lab_check_result->fetch_assoc()) {
                        if ($existing_lab['day'] === $day && (
                            ($existing_lab['start_time'] <= $start_time && $existing_lab['end_time'] > $start_time) ||
                            ($existing_lab['start_time'] < '$end_time' && $existing_lab['end_time'] >= '$end_time') ||
                            ($existing_lab['start_time'] >= '$start_time' && $existing_lab['end_time'] <= '$end_time')
                        )) {
                            $error = "Lecture time overlaps with lab on $day.";
                        }
                    }
                } elseif ($lecture_check_result->num_rows > 0 && $schedule_type === 'Lab') {
                    while ($existing_lecture = $lecture_check_result->fetch_assoc()) {
                        if ($existing_lecture['day'] === $day && (
                            ($existing_lecture['start_time'] <= $start_time && $existing_lecture['end_time'] > $start_time) ||
                            ($existing_lecture['start_time'] < '$end_time' && $existing_lecture['end_time'] >= '$end_time') ||
                            ($existing_lecture['start_time'] >= '$start_time' && $existing_lecture['end_time'] <= '$end_time')
                        )) {
                            $error = "Lab time overlaps with lecture on $day.";
                        }
                    }
                }
            }
        }

        // Update schedule
        if (!isset($error)) {
            $sql = "UPDATE section_schedules
                    SET section_id = '$section_id', course_id = '$course_id', instructor_id = '$instructor_id',
                        room_id = '$room_id', day = '$day', start_time = '$start_time', end_time = '$end_time',
                        schedule_type = '$schedule_type', semester_id = '$semester_id'
                    WHERE section_schedule_id = '$schedule_id'";
            if ($conn->query($sql)) {
                $success = "Schedule updated successfully!" . (isset($warning) ? " Warning: $warning" : "");
                $schedules_query = "SELECT ss.*, s.section_name, c.course_code, c.course_title, c.course_type, ss.schedule_type, i.instructor_id, i.instructor_name, r.id as room_id, r.room_name, b.building_name, sem.semester_name
                                    FROM section_schedules ss
                                    JOIN sections s ON ss.section_id = s.id
                                    JOIN courses c ON ss.course_id = c.course_id
                                    JOIN instructors i ON ss.instructor_id = i.instructor_id
                                    JOIN rooms r ON ss.room_id = r.id
                                    JOIN buildings b ON r.building_id = b.id
                                    JOIN semesters sem ON ss.semester_id = sem.semester_id
                                    WHERE ss.batch_id = '$batch_id' AND ss.semester_id = '$semester_id'";
                $schedules_result = $conn->query($schedules_query);
                $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
                $edit_schedule = null;
            } else {
                $error = "Error updating schedule: " . $conn->error;
            }
        }
    }
}

// Handle schedule submission
if (isset($_POST['add_schedule'])) {
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    $semester_id = $conn->real_escape_string($_POST['semester_id']);
    $section_id = $conn->real_escape_string($_POST['section_id']);
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $instructor_id = $conn->real_escape_string($_POST['instructor_id']);
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $day = $conn->real_escape_string($_POST['day']);
    $start_time = $conn->real_escape_string($_POST['start_time']);
    $end_time = $conn->real_escape_string($_POST['end_time']);
    $dept_id = $conn->real_escape_string($_POST['dept_id']);
    $schedule_type = $conn->real_escape_string($_POST['schedule_type']);
    $academic_date_id = 0;

    // Validate inputs
    $input_data = [
        'batch_id' => $batch_id,
        'semester_id' => $semester_id,
        'section_id' => $section_id,
        'course_id' => $course_id,
        'instructor_id' => $instructor_id,
        'room_id' => $room_id,
        'dept_id' => $dept_id,
        'day' => $day,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'schedule_type' => $schedule_type
    ];
    if (!validateScheduleInput($input_data, $error)) {
        // Error is set in validateScheduleInput
    } else {
        // Verify course assignment
        $course_batch_check = "SELECT * FROM batch_course_assignments bc
                              WHERE bc.course_id = '$course_id' AND bc.batch_id = '$batch_id' AND bc.semester_id = '$semester_id'";
        $course_batch_result = $conn->query($course_batch_check);
        if ($course_batch_result === false) {
            $error = "Error verifying course assignment: " . $conn->error;
        } elseif ($course_batch_result->num_rows == 0) {
            $error = "The selected course is not assigned to this batch and semester.";
        }

        // Check instructor overlap
        if (!isset($error)) {
            $overlap_query = "SELECT ss.*, s.section_name, c.course_title, b.batch_name
                             FROM section_schedules ss
                             JOIN sections s ON ss.section_id = s.id
                             JOIN courses c ON ss.course_id = c.course_id
                             JOIN batches b ON ss.batch_id = b.id
                             WHERE ss.instructor_id = '$instructor_id'
                             AND ss.day = '$day'
                             AND ss.semester_id = '$semester_id'
                             AND (
                                 (ss.start_time <= '$start_time' AND ss.end_time > '$start_time') OR
                                 (ss.start_time < '$end_time' AND ss.end_time >= '$end_time') OR
                                 (ss.start_time >= '$start_time' AND ss.end_time <= '$end_time')
                             )";
            $overlap_result = $conn->query($overlap_query);
            if ($overlap_result === false) {
                $error = "Error checking instructor overlaps: " . $conn->error;
            } elseif ($overlap_result->num_rows > 0) {
                $error = "This instructor is already scheduled during this time on $day in this semester.";
            }
        }

        // Check room availability
        if (!isset($error)) {
            $room_overlap_query = "SELECT ss.*, s.section_name, c.course_title, b.batch_name
                                  FROM section_schedules ss
                                  JOIN sections s ON ss.section_id = s.id
                                  JOIN courses c ON ss.course_id = c.course_id
                                  JOIN batches b ON ss.batch_id = b.id
                                  WHERE ss.room_id = '$room_id'
                                  AND ss.day = '$day'
                                  AND ss.batch_id = '$batch_id'
                                  AND ss.semester_id = '$semester_id'
                                  AND (
                                      (ss.start_time <= '$start_time' AND ss.end_time > '$start_time') OR
                                      (ss.start_time < '$end_time' AND ss.end_time >= '$end_time') OR
                                      (ss.start_time >= '$start_time' AND ss.end_time <= '$end_time')
                                  )";
            $room_overlap_result = $conn->query($room_overlap_query);
            if ($room_overlap_result === false) {
                $error = "Error checking room overlaps: " . $conn->error;
            } elseif ($room_overlap_result->num_rows > 0) {
                $error = "This room is already booked during this time on $day in this batch and semester.";
            }
        }

        // Check room type compatibility
        if (!isset($error)) {
            $room_type_query = "SELECT type FROM rooms WHERE id = '$room_id'";
            $room_type_result = $conn->query($room_type_query);
            if ($room_type_result && $room_type_result->num_rows > 0) {
                $room_type = $room_type_result->fetch_assoc()['type'];
                if ($schedule_type === 'Lab' && $room_type !== 'Lab') {
                    $error = "A Lab schedule must be assigned to a Lab room.";
                } elseif ($schedule_type === 'Lecture' && $room_type === 'Lab') {
                    $warning = "A Lecture schedule is being assigned to a Lab room.";
                }
            }
        }

        // Check section scheduling constraints
        if (!isset($error)) {
            $lecture_check_query = "SELECT * FROM section_schedules
                                   WHERE section_id = '$section_id'
                                   AND course_id = '$course_id'
                                   AND semester_id = '$semester_id'
                                   AND schedule_type = 'Lecture'";
            $lecture_check_result = $conn->query($lecture_check_query);
            if ($lecture_check_result === false) {
                $error = "Error checking lecture schedules: " . $conn->error;
            } elseif ($schedule_type === 'Lecture' && $lecture_check_result->num_rows > 0) {
                $error = "This section already has a lecture for this course in this semester.";
            }

            if (!isset($error)) {
                $lab_check_query = "SELECT * FROM section_schedules
                                   WHERE section_id = '$section_id'
                                   AND course_id = '$course_id'
                                   AND semester_id = '$semester_id'
                                   AND schedule_type = 'Lab'";
                $lab_check_result = $conn->query($lab_check_query);
                if ($lab_check_result === false) {
                    $error = "Error checking lab schedules: " . $conn->error;
                } elseif ($schedule_type === 'Lab' && $lab_check_result->num_rows > 0) {
                    $error = "This section already has a lab for this course in this semester.";
                } elseif ($lab_check_result->num_rows > 0 && $schedule_type === 'Lecture') {
                    while ($existing_lab = $lab_check_result->fetch_assoc()) {
                        if ($existing_lab['day'] === $day && (
                            ($existing_lab['start_time'] <= $start_time && $existing_lab['end_time'] > $start_time) ||
                            ($existing_lab['start_time'] < '$end_time' && $existing_lab['end_time'] >= '$end_time') ||
                            ($existing_lab['start_time'] >= '$start_time' && $existing_lab['end_time'] <= '$end_time')
                        )) {
                            $error = "Lecture time overlaps with lab on $day.";
                        }
                    }
                } elseif ($lecture_check_result->num_rows > 0 && $schedule_type === 'Lab') {
                    while ($existing_lecture = $lecture_check_result->fetch_assoc()) {
                        if ($existing_lecture['day'] === $day && (
                            ($existing_lecture['start_time'] <= $start_time && $existing_lecture['end_time'] > $start_time) ||
                            ($existing_lecture['start_time'] < '$end_time' && $existing_lecture['end_time'] >= '$end_time') ||
                            ($existing_lecture['start_time'] >= '$start_time' && $existing_lecture['end_time'] <= '$end_time')
                        )) {
                            $error = "Lab time overlaps with lecture on $day.";
                        }
                    }
                }
            }
        }

        // Insert schedule
        if (!isset($error)) {
            $sql = "INSERT INTO section_schedules (dept_id, batch_id, section_id, course_id, instructor_id, room_id, day, start_time, end_time, academic_date_id, schedule_type, semester_id)
                    VALUES ('$dept_id', '$batch_id', '$section_id', '$course_id', '$instructor_id', '$room_id', '$day', '$start_time', '$end_time', '$academic_date_id', '$schedule_type', '$semester_id')";
            if ($conn->query($sql)) {
                $success = "Schedule added successfully!" . (isset($warning) ? " Warning: $warning" : "");
                $schedules_query = "SELECT ss.*, s.section_name, c.course_code, c.course_title, c.course_type, ss.schedule_type, i.instructor_id, i.instructor_name, r.id as room_id, r.room_name, b.building_name, sem.semester_name
                                    FROM section_schedules ss
                                    JOIN sections s ON ss.section_id = s.id
                                    JOIN courses c ON ss.course_id = c.course_id
                                    JOIN instructors i ON ss.instructor_id = i.instructor_id
                                    JOIN rooms r ON ss.room_id = r.id
                                    JOIN buildings b ON r.building_id = b.id
                                    JOIN semesters sem ON ss.semester_id = sem.semester_id
                                    WHERE ss.batch_id = '$batch_id' AND ss.semester_id = '$semester_id'";
                $schedules_result = $conn->query($schedules_query);
                $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
            } else {
                $error = "Error adding schedule: " . $conn->error;
            }
        }
    }
}

// Handle schedule deletion
if (isset($_POST['delete_schedule'])) {
    $schedule_id = $conn->real_escape_string($_POST['schedule_id']);
    $sql = "DELETE FROM section_schedules WHERE section_schedule_id = '$schedule_id'";
    if ($conn->query($sql)) {
        $success = "Schedule deleted successfully!";
        $schedules_query = "SELECT ss.*, s.section_name, c.course_code, c.course_title, c.course_type, ss.schedule_type, i.instructor_id, i.instructor_name, r.id as room_id, r.room_name, b.building_name, sem.semester_name
                            FROM section_schedules ss
                            JOIN sections s ON ss.section_id = s.id
                            JOIN courses c ON ss.course_id = c.course_id
                            JOIN instructors i ON ss.instructor_id = i.instructor_id
                            JOIN rooms r ON ss.room_id = r.id
                            JOIN buildings b ON r.building_id = b.id
                            JOIN semesters sem ON ss.semester_id = sem.semester_id
                            WHERE ss.batch_id = '$selected_batch_id' AND ss.semester_id = '$selected_semester_id'";
        $schedules_result = $conn->query($schedules_query);
        $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Error deleting schedule: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #28a745;
            --secondary: #17a2b8;
            --background: #f4f4f4;
            --card-bg: #ffffff;
            --text: #333;
            --sidebar-bg: #2c3e50;
            --gradient: linear-gradient(135deg, #28a745, #17a2b8);
            --error: #dc3545;
            --warning: #f39c12;
        }

        [data-theme="dark"] {
            --background: #1a1a1a;
            --card-bg: #2c2c2c;
            --text: #e0e0e0;
            --sidebar-bg: #1f2a44;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', Arial, sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }

        .header {
            background: var(--gradient);
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header img.logo {
            height: 40px;
        }

        .header h1 {
            font-size: 1.5rem;
            margin: 0;
        }

        .hamburger {
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
        }

        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            transform: translateX(0);
            transition: transform 0.3s;
            padding-top: 4rem;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: white;
            text-decoration: none;
            transition: background 0.2s;
        }

        .sidebar a i {
            margin-right: 0.5rem;
        }

        .sidebar a:hover {
            background: var(--primary);
        }

        .sidebar a.logout {
            background: var(--error);
            margin: 1rem;
            border-radius: 5px;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .success {
            color: var(--primary);
            background: rgba(40, 167, 69, 0.1);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .error {
            color: var(--error);
            background: rgba(220, 53, 69, 0.1);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .warning {
            color: var(--warning);
            background: rgba(243, 156, 18, 0.1);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .form-group {
            position: relative;
            margin: 1rem 0;
        }

        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dcdcdc;
            border-radius: 5px;
            background: var(--card-bg);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(40, 167, 69, 0.3);
            outline: none;
        }

        label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            color: #7f8c8d;
            transition: all 0.3s ease;
            pointer-events: none;
            background: var(--card-bg);
            padding: 0 0.25rem;
        }

        input:focus + label,
        input:not(:placeholder-shown) + label,
        select:focus + label,
        select:not(:placeholder-shown) + label {
            top: 0;
            font-size: 0.75rem;
            color: var(--primary);
        }

        button {
            background: var(--gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .edit-btn {
            background: linear-gradient(45deg, #2196F3, #1976D2);
        }

        .edit-btn:hover {
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }

        .delete-btn {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }

        .delete-btn:hover {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .cancel-btn {
            background: linear-gradient(45deg, #6c757d, #5a6268);
        }

        .cancel-btn:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 0.75rem;
            border: 1px solid #ecf0f1;
            text-align: center;
        }

        th {
            background: var(--gradient);
            color: white;
            font-weight: 600;
        }

        tr {
            transition: background 0.3s;
        }

        tr:hover {
            background: rgba(40, 167, 69, 0.1);
        }

        .theme-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .offline-message {
            display: none;
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            background: var(--error);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            animation: slideIn 0.5s;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-content h3 {
            margin-top: 0;
        }

        .close-modal {
            float: right;
            font-size: 1.2rem;
            cursor: pointer;
        }

        #rooms-details, #instructors-details {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: var(--card-bg);
        }

        #rooms-details p, #instructors-details p {
            margin: 0.5rem 0;
        }

        .room-info, .instructor-info {
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .occupied {
            color: var(--error);
        }

        .free {
            color: var(--primary);
        }

        @keyframes slideIn {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .hamburger {
                display: block;
            }

            .header h1 {
                font-size: 1.2rem;
            }

            .header img.logo {
                height: 30px;
            }
        }

        @media print {
            .header, .sidebar, .theme-toggle, .hamburger {
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <img src="image/markoslogo.jpg" alt="Logo" class="logo">
        <h1>Manage Schedules</h1>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <nav class="sidebar" id="sidebar">
        <a href="dept_head_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="submit_dept_info.php"><i class="fas fa-building"></i> Submit Dept Info</a>
        <a href="submit_course_instructor.php"><i class="fas fa-chalkboard-teacher"></i> Manage Courses & Instructors</a>
        <a href="manage_schedule.php"><i class="fas fa-calendar"></i> Manage Schedules</a>
        <a href="manage_feedback.php"><i class="fas fa-comment"></i> View Feedback</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <main class="main-content" id="main-content">
        <div class="card">
            <h2>Manage Schedules</h2>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Batch-Semester Selection -->
            <form method="get" class="card">
                <h3>Select Batch and Semester</h3>
                <div class="form-group">
                    <select name="batch_semester" id="batch_semester" onchange="this.form.submit()" required>
                        <option value="">-- Select Batch (Semester) --</option>
                        <?php foreach ($batch_semesters as $bs): ?>
                            <option value="<?php echo $bs['batch_id'] . ':' . $bs['semester_id']; ?>"
                                    <?php echo ($selected_batch_id == $bs['batch_id'] && $selected_semester_id == $bs['semester_id']) ? 'selected' : ''; ?>
                                    data-dept-id="<?php echo $bs['dept_id']; ?>">
                                <?php echo htmlspecialchars($bs['batch_name'] . ' (' . ($bs['semester_name'] ?: 'Unknown') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="batch_semester">Select Batch (Semester)</label>
                </div>
            </form>

            <?php if ($selected_batch_id && $selected_semester_id): ?>
                <!-- Schedule Form -->
                <div class="card">
                    <h3><?php
                        $selected_bs = array_filter($batch_semesters, function($bs) use ($selected_batch_id, $selected_semester_id) {
                            return $bs['batch_id'] == $selected_batch_id && $bs['semester_id'] == $selected_semester_id;
                        });
                        $selected_bs = reset($selected_bs);
                        echo isset($edit_schedule) ? 'Edit Schedule' : 'Add Schedule for ' . htmlspecialchars($selected_bs['batch_name'] . ' (' . ($selected_bs['semester_name'] ?: 'Unknown') . ')');
                    ?></h3>
                    <form method="post" id="scheduleForm">
                        <input type="hidden" name="batch_id" value="<?php echo $selected_batch_id; ?>">
                        <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>">
                        <input type="hidden" name="dept_id" value="<?php echo $selected_bs['dept_id']; ?>">
                        <?php if (isset($edit_schedule)): ?>
                            <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule['section_schedule_id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <select name="section_id" id="section_id" required>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>" <?php echo (isset($edit_schedule) && $edit_schedule['section_id'] == $section['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="section_id">Section</label>
                        </div>

                        <div class="form-group">
                            <select name="course_id" id="course_id" onchange="updateScheduleType()" required>
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>" <?php echo (isset($edit_schedule) && $edit_schedule['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="course_id">Course</label>
                        </div>

                        <div id="schedule_type_div" style="display: none;" class="form-group">
                            <select name="schedule_type" id="schedule_type" required>
                                <option value="">-- Select Schedule Type --</option>
                                <option value="Lecture" <?php echo (isset($edit_schedule) && $edit_schedule['schedule_type'] == 'Lecture') ? 'selected' : ''; ?>>Lecture</option>
                                <option value="Lab" <?php echo (isset($edit_schedule) && $edit_schedule['schedule_type'] == 'Lab') ? 'selected' : ''; ?>>Lab</option>
                            </select>
                            <label for="schedule_type">Schedule Type</label>
                        </div>

                        <div class="form-group">
                            <select name="instructor_id" id="instructor_id" onchange="updateInstructorsDetails(this.value)" required>
                                <option value="">-- Select Instructor --</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo $instructor['instructor_id']; ?>" <?php echo (isset($edit_schedule) && $edit_schedule['instructor_id'] == $instructor['instructor_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($instructor['instructor_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="instructor_id">Instructor</label>
                        </div>

                        <div id="instructors-details">
                            <p>Please select an instructor to view details.</p>
                        </div>

                        <div class="form-group">
                            <select name="day" id="day" onchange="updateRoomsDetails(); updateInstructorsDetails()" required>
                                <option value="">-- Select Day --</option>
                                <?php
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                foreach ($days as $day): ?>
                                    <option value="<?php echo $day; ?>" <?php echo (isset($edit_schedule) && $edit_schedule['day'] == $day) ? 'selected' : ''; ?>>
                                        <?php echo $day; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="day">Day</label>
                        </div>

                        <div class="form-group">
                            <input type="time" name="start_time" id="start_time" value="<?php echo isset($edit_schedule) ? $edit_schedule['start_time'] : ''; ?>" onchange="updateRoomsDetails(); updateInstructorsDetails()" required>
                            <label for="start_time">Start Time</label>
                        </div>

                        <div class="form-group">
                            <input type="time" name="end_time" id="end_time" value="<?php echo isset($edit_schedule) ? $edit_schedule['end_time'] : ''; ?>" onchange="updateRoomsDetails(); updateInstructorsDetails()" required>
                            <label for="end_time">End Time</label>
                        </div>

                        <div id="rooms-details">
                            <p>Please select a day, start time, and end time to view room details.</p>
                        </div>

                        <div class="form-group">
                            <select name="room_id" id="room_id" required>
                                <option value="">-- Select Room --</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" <?php echo (isset($edit_schedule) && $edit_schedule['room_id'] == $room['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($room['room_name'] . ' (' . $room['building_name'] . ', ' . $room['type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="room_id">Room</label>
                        </div>

                        <button type="submit" name="<?php echo isset($edit_schedule) ? 'update_schedule' : 'add_schedule'; ?>">
                            <?php echo isset($edit_schedule) ? 'Update Schedule' : 'Add Schedule'; ?>
                        </button>
                        <?php if (isset($edit_schedule)): ?>
                            <a href="manage_schedule.php?batch_semester=<?php echo $selected_batch_id . ':' . $selected_semester_id; ?>">
                                <button type="button" class="cancel-btn">Cancel Edit</button>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Display Schedules -->
                <div class="card table-container">
                    <h3>Schedules for <?php echo htmlspecialchars($selected_bs['batch_name'] . ' (' . ($selected_bs['semester_name'] ?: 'Unknown') . ')'); ?></h3>
                    <?php if (!empty($schedules)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Course</th>
                                    <th>Semester</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Schedule Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $schedule):
                                    $course_display = htmlspecialchars($schedule['course_title'] . ' (' . $schedule['schedule_type'] . ')');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['section_name']); ?></td>
                                        <td><?php echo $course_display; ?></td>
                                        <td><?php echo htmlspecialchars($schedule['semester_name'] ?: 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['instructor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['room_name'] . ' (' . $schedule['building_name'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['day']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['start_time'] . ' - ' . $schedule['end_time']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['schedule_type']); ?></td>
                                        <td>
                                            <button class="edit-btn" onclick="openEditScheduleModal(
                                                <?php echo $schedule['section_schedule_id']; ?>,
                                                '<?php echo $schedule['section_id']; ?>',
                                                '<?php echo $schedule['course_id']; ?>',
                                                '<?php echo $schedule['instructor_id']; ?>',
                                                '<?php echo $schedule['room_id']; ?>',
                                                '<?php echo $schedule['day']; ?>',
                                                '<?php echo $schedule['start_time']; ?>',
                                                '<?php echo $schedule['end_time']; ?>',
                                                '<?php echo $schedule['schedule_type']; ?>',
                                                '<?php echo $schedule['semester_id']; ?>'
                                            )">Edit</button>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['section_schedule_id']; ?>">
                                                <button type="submit" name="delete_schedule" class="delete-btn"
                                                        onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No schedules set for this batch and semester yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Schedule Modal -->
    <div class="modal" id="editScheduleModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditScheduleModal()">×</span>
            <form method="post">
                <h3>Edit Schedule</h3>
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <input type="hidden" name="batch_id" id="edit_batch_id">
                <input type="hidden" name="semester_id" id="edit_semester_id">
                <input type="hidden" name="dept_id" id="edit_dept_id">

                <div class="form-group">
                    <select name="section_id" id="edit_section_id" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>">
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="edit_section_id">Section</label>
                </div>

                <div class="form-group">
                    <select name="course_id" id="edit_course_id" onchange="updateEditScheduleType()" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="edit_course_id">Course</label>
                </div>

                <div id="edit_schedule_type_div" style="display: none;" class="form-group">
                    <select name="schedule_type" id="edit_schedule_type" required>
                        <option value="">-- Select Schedule Type --</option>
                        <option value="Lecture">Lecture</option>
                        <option value="Lab">Lab</option>
                    </select>
                    <label for="edit_schedule_type">Schedule Type</label>
                </div>

                <div class="form-group">
                    <select name="instructor_id" id="edit_instructor_id" onchange="updateInstructorsDetails(this.value)" required>
                        <option value="">-- Select Instructor --</option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?php echo $instructor['instructor_id']; ?>">
                                <?php echo htmlspecialchars($instructor['instructor_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="edit_instructor_id">Instructor</label>
                </div>

                <div class="form-group">
                    <select name="day" id="edit_day" onchange="updateRoomsDetails(); updateInstructorsDetails()" required>
                        <option value="">-- Select Day --</option>
                        <?php foreach ($days as $day): ?>
                            <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="edit_day">Day</label>
                </div>

                <div class="form-group">
                    <input type="time" name="start_time" id="edit_start_time" onchange="updateRoomsDetails(); updateInstructorsDetails()" required>
                    <label for="edit_start_time">Start Time</label>
                </div>

                <div class="form-group">
                    <input type="time" name="end_time" id="edit_end_time" onchange="updateRoomsDetails(); updateInstructorsDetails()" required>
                    <label for="edit_end_time">End Time</label>
                </div>

                <div class="form-group">
                    <select name="room_id" id="edit_room_id" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>">
                                <?php echo htmlspecialchars($room['room_name'] . ' (' . $room['building_name'] . ', ' . $room['type'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="edit_room_id">Room</label>
                </div>

                <button type="submit" name="update_schedule">Update Schedule</button>
            </form>
        </div>
    </div>

    <div class="offline-message" id="offline-message">You are offline. Displaying cached data.</div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('active');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('full-width');
        }

        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.querySelector('.theme-toggle i');
            const currentTheme = body.getAttribute('data-theme');
            if (currentTheme === 'dark') {
                body.removeAttribute('data-theme');
                themeIcon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                themeIcon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('theme', 'dark');
            }
        }

        if (localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.querySelector('.theme-toggle i').classList.replace('fa-moon', 'fa-sun');
        }

        window.addEventListener('online', () => {
            document.getElementById('offline-message').style.display = 'none';
        });

        window.addEventListener('offline', () => {
            document.getElementById('offline-message').style.display = 'block';
        });

        const scheduleData = <?php echo json_encode($schedules); ?>;
        localStorage.setItem('scheduleData', JSON.stringify(scheduleData));

        if (!navigator.onLine) {
            const cachedSchedules = JSON.parse(localStorage.getItem('scheduleData') || '[]');
            if (cachedSchedules.length) {
                const scheduleTableBody = document.querySelector('.table-container tbody');
                if (scheduleTableBody) {
                    scheduleTableBody.innerHTML = '';
                    cachedSchedules.forEach(s => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${s.section_name}</td>
                            <td>${s.course_title} (${s.schedule_type})</td>
                            <td>${s.semester_name || 'Unknown'}</td>
                            <td>${s.instructor_name}</td>
                            <td>${s.room_name} (${s.building_name})</td>
                            <td>${s.day}</td>
                            <td>${s.start_time} - ${s.end_time}</td>
                            <td>${s.schedule_type}</td>
                            <td><button disabled>Edit</button> <button disabled>Delete</button></td>
                        `;
                        scheduleTableBody.appendChild(row);
                    });
                }
            }
        }

        function updateScheduleType() {
            const courseSelect = document.getElementById('course_id');
            const scheduleTypeDiv = document.getElementById('schedule_type_div');
            const scheduleTypeSelect = document.getElementById('schedule_type');
            const selectedCourseId = courseSelect.value;

            const courseTypes = <?php echo json_encode(array_column($courses, 'course_type', 'course_id')); ?>;
            const courseType = courseTypes[selectedCourseId];

            if (courseType === 'Both') {
                scheduleTypeDiv.style.display = 'block';
                scheduleTypeSelect.value = '';
            } else {
                scheduleTypeDiv.style.display = 'none';
                scheduleTypeSelect.value = courseType === 'Lab' ? 'Lab' : 'Lecture';
            }
        }

        function updateEditScheduleType() {
            const courseSelect = document.getElementById('edit_course_id');
            const scheduleTypeDiv = document.getElementById('edit_schedule_type_div');
            const scheduleTypeSelect = document.getElementById('edit_schedule_type');
            const selectedCourseId = courseSelect.value;

            const courseTypes = <?php echo json_encode(array_column($courses, 'course_type', 'course_id')); ?>;
            const courseType = courseTypes[selectedCourseId];

            if (courseType === 'Both') {
                scheduleTypeDiv.style.display = 'block';
                scheduleTypeSelect.value = '';
            } else {
                scheduleTypeDiv.style.display = 'none';
                scheduleTypeSelect.value = courseType === 'Lab' ? 'Lab' : 'Lecture';
            }
        }

        function updateRoomsDetails() {
            const daySelect = document.getElementById('day') || document.getElementById('edit_day');
            const startTimeInput = document.getElementById('start_time') || document.getElementById('edit_start_time');
            const endTimeInput = document.getElementById('end_time') || document.getElementById('edit_end_time');
            const batchId = '<?php echo $selected_batch_id; ?>';
            const semesterId = '<?php echo $selected_semester_id; ?>';
            const scheduleId = '<?php echo isset($edit_schedule) ? $edit_schedule['section_schedule_id'] : ''; ?>';
            const roomsDetailsDiv = document.getElementById('rooms-details');

            if (!daySelect.value || !startTimeInput.value || !endTimeInput.value || !batchId || !semesterId) {
                roomsDetailsDiv.innerHTML = '<p>Please select a day, start time, and end time to view room details.</p>';
                return;
            }

            const url = `get_all_rooms_details.php?day=${daySelect.value}&start_time=${startTimeInput.value}&end_time=${endTimeInput.value}&batch_id=${batchId}&semester_id=${semesterId}${scheduleId ? '&schedule_id=' + scheduleId : ''}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        roomsDetailsDiv.innerHTML = `<p class="error">${data.error}</p>`;
                        return;
                    }

                    let html = '';
                    data.rooms.forEach(room => {
                        html += `<div class="room-info">`;
                        html += `<p><strong>Room:</strong> ${room.room_name} (${room.building_name})</p>`;
                        html += `<p><strong>Room Type:</strong> ${room.type}</p>`;
                        html += `<p><strong>Availability:</strong> <span class="${room.is_available ? 'free' : 'occupied'}">${room.availability_status}</span></p>`;
                        if (!room.is_available) {
                            html += `<p><strong>Conflicting Sessions:</strong></p><ul>`;
                            room.conflicting_sessions.forEach(session => {
                                html += `<li>${session.course_title} (${session.schedule_type}) - ${session.section_name}: ${session.start_time} to ${session.end_time}</li>`;
                            });
                            html += `</ul>`;
                        }
                        html += `<p><strong>All Scheduled Sessions on ${daySelect.value}:</strong></p>`;
                        if (room.all_sessions.length > 0) {
                            html += `<ul>`;
                            room.all_sessions.forEach(session => {
                                html += `<li>${session.course_title} (${session.schedule_type}) - ${session.section_name}: ${session.start_time} to ${session.end_time}</li>`;
                            });
                            html += `</ul>`;
                        } else {
                            html += `<p>No other sessions scheduled in this room on ${daySelect.value}.</p>`;
                        }
                        html += `</div>`;
                    });

                    roomsDetailsDiv.innerHTML = html;
                })
                .catch(error => {
                    roomsDetailsDiv.innerHTML = `<p class="error">Error fetching rooms details: ${error.message}</p>`;
                });
        }

        function updateInstructorsDetails(instructorId = null) {
            const daySelect = document.getElementById('day') || document.getElementById('edit_day');
            const startTimeInput = document.getElementById('start_time') || document.getElementById('edit_start_time');
            const endTimeInput = document.getElementById('end_time') || document.getElementById('edit_end_time');
            const batchId = '<?php echo $selected_batch_id; ?>';
            const semesterId = '<?php echo $selected_semester_id; ?>';
            const scheduleId = '<?php echo isset($edit_schedule) ? $edit_schedule['section_schedule_id'] : ''; ?>';
            const instructorSelect = document.getElementById('instructor_id') || document.getElementById('edit_instructor_id');
            const instructorsDetailsDiv = document.getElementById('instructors-details');
            const selectedInstructorId = instructorId || instructorSelect.value;

            if (!selectedInstructorId || !batchId) {
                instructorsDetailsDiv.innerHTML = '<p>Please select an instructor to view details.</p>';
                return;
            }

            let url = `get_instructor_details.php?instructor_id=${selectedInstructorId}&batch_id=${batchId}&semester_id=${semesterId}${scheduleId ? '&schedule_id=' + scheduleId : ''}`;
            if (daySelect.value && startTimeInput.value && endTimeInput.value) {
                url += `&day=${daySelect.value}&start_time=${startTimeInput.value}&end_time=${endTimeInput.value}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        instructorsDetailsDiv.innerHTML = `<p class="error">${data.error}</p>`;
                        return;
                    }

                    let html = '';
                    data.instructors.forEach(instructor => {
                        html += `<div class="instructor-info">`;
                        html += `<p><strong>Instructor:</strong> ${instructor.name}</p>`;
                        html += `<p><strong>Department:</strong> ${instructor.department}</p>`;
                        if (daySelect.value && startTimeInput.value && endTimeInput.value) {
                            html += `<p><strong>Availability on ${daySelect.value} at ${startTimeInput.value} - ${startTimeInput.value}:</strong> <span class="${instructor.is_available ? 'free' : 'occupied'}">${instructor.availability_status}</span></p>`;
                            if (!instructor.is_available) {
                                html += `<p><strong>Conflicting Sessions:</strong></p><ul>`;
                                instructor.conflicting_sessions.forEach(session => {
                                    html += `<li>${session.day}: ${session.start_time} to ${session.end_time} (${session.schedule_type})</li>`;
                                });
                                html += `</ul>`;
                            }
                        }
                        html += `<p><strong>Weekly Availability:</strong></p><ul>`;
                        for (const [day, times] of Object.entries(instructor.availability)) {
                            if (times === 'Free all day') {
                                html += `<li>${day}: Free all day</li>`;
                            } else {
                                html += `<li>${day}: Busy from ${times.join(', ')}</li>`;
                            }
                        }
                        html += `</ul>`;
                        html += `<p><strong>Courses Already Assigned:</strong></p>`;
                        if (instructor.assigned_courses.length > 0) {
                            html += `<ul>`;
                            instructor.assigned_courses.forEach(course => {
                                html += `<li>${course}</li>`;
                            });
                            html += `</ul>`;
                        } else {
                            html += `<p>No courses assigned in this batch.</p>`;
                        }
                        html += `</div>`;
                    });

                    instructorsDetailsDiv.innerHTML = html;
                })
                .catch(error => {
                    instructorsDetailsDiv.innerHTML = `<p class="error">Error fetching instructors details: ${error.message}</p>`;
                });
        }

        function openEditScheduleModal(id, sectionId, courseId, instructorId, roomId, day, startTime, endTime, scheduleType, semesterId) {
            const modal = document.getElementById('editScheduleModal');
            const batchId = '<?php echo $selected_batch_id; ?>';
            const deptId = '<?php echo $selected_bs['dept_id']; ?>';
            document.getElementById('edit_schedule_id').value = id;
            document.getElementById('edit_batch_id').value = batchId;
            document.getElementById('edit_semester_id').value = semesterId;
            document.getElementById('edit_dept_id').value = deptId;
            document.getElementById('edit_section_id').value = sectionId;
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_instructor_id').value = instructorId;
            document.getElementById('edit_room_id').value = roomId;
            document.getElementById('edit_day').value = day;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_schedule_type').value = scheduleType;
            updateEditScheduleType();
            modal.style.display = 'flex';
            updateInstructorsDetails(instructorId);
            updateRoomsDetails();
        }

        function closeEditScheduleModal() {
            document.getElementById('editScheduleModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editScheduleModal');
            if (event.target === modal) {
                closeEditScheduleModal();
            }
        };

        <?php if (isset($success)): ?>
            setTimeout(() => {
                window.location.href = 'manage_schedule.php?batch_semester=<?php echo $selected_batch_id . ':' . $selected_semester_id; ?>';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
$conn->close();
?>