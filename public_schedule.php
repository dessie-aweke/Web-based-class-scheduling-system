<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
include 'database/db_connection.php';

// Fetch departments
$departments_query = "SELECT dept_id, dept_name FROM departments";
$departments_result = $conn->query($departments_query);
if ($departments_result === false) {
    die("Departments query failed: " . $conn->error);
}
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

// Initialize variables for filters
$selected_dept_id = isset($_GET['dept_id']) ? $conn->real_escape_string($_GET['dept_id']) : '';
$selected_batch_id = null;
$selected_semester_id = null;
$selected_section_id = isset($_GET['section_id']) ? $conn->real_escape_string($_GET['section_id']) : '';
if (isset($_GET['batch_semester']) && !empty($_GET['batch_semester'])) {
    list($selected_batch_id, $selected_semester_id) = explode(':', $conn->real_escape_string($_GET['batch_semester']));
    $selected_batch_id = $conn->real_escape_string($selected_batch_id);
    $selected_semester_id = $conn->real_escape_string($selected_semester_id);
}

// Fetch department name for print
$dept_name = '';
if ($selected_dept_id) {
    $dept_query = "SELECT dept_name FROM departments WHERE dept_id = '$selected_dept_id'";
    $dept_result = $conn->query($dept_query);
    if ($dept_result && $dept_result->num_rows > 0) {
        $dept_row = $dept_result->fetch_assoc();
        $dept_name = htmlspecialchars($dept_row['dept_name']);
    }
}

// Fetch batch-semester combinations based on selected department
$batch_semesters = [];
$batch_name = '';
$semester_name = '';
if ($selected_dept_id) {
    $batch_semesters_query = "SELECT b.id AS batch_id, b.batch_name, s.semester_id, s.semester_name
                             FROM batches b
                             JOIN batch_course_assignments bca ON b.id = bca.batch_id
                             JOIN semesters s ON bca.semester_id = s.semester_id
                             WHERE b.dept_id = '$selected_dept_id'
                             ORDER BY b.batch_name, s.semester_name";
    $batch_semesters_result = $conn->query($batch_semesters_query);
    if ($batch_semesters_result === false) {
        die("Batch-semester query failed: " . $conn->error);
    }
    $batch_semesters = $batch_semesters_result->fetch_all(MYSQLI_ASSOC);

    // Fetch batch and semester names for print
    if ($selected_batch_id && $selected_semester_id) {
        $batch_query = "SELECT batch_name FROM batches WHERE id = '$selected_batch_id'";
        $batch_result = $conn->query($batch_query);
        if ($batch_result && $batch_result->num_rows > 0) {
            $batch_row = $batch_result->fetch_assoc();
            $batch_name = htmlspecialchars($batch_row['batch_name']);
        }

        $semester_query = "SELECT semester_name FROM semesters WHERE semester_id = '$selected_semester_id'";
        $semester_result = $conn->query($semester_query);
        if ($semester_result && $semester_result->num_rows > 0) {
            $semester_row = $semester_result->fetch_assoc();
            $semester_name = htmlspecialchars($semester_row['semester_name'] ?: 'Unknown');
        }
    }
}

// Fetch sections based on selected batch
$sections = [];
$section_name = '';
if ($selected_batch_id) {
    $sections_query = "SELECT id, section_name FROM sections WHERE batch_id = '$selected_batch_id'";
    $sections_result = $conn->query($sections_query);
    if ($sections_result === false) {
        die("Sections query failed: " . $conn->error);
    }
    $sections = $sections_result->fetch_all(MYSQLI_ASSOC);

    // Fetch section name for print
    if ($selected_section_id) {
        $section_query = "SELECT section_name FROM sections WHERE id = '$selected_section_id'";
        $section_result = $conn->query($section_query);
        if ($section_result && $section_result->num_rows > 0) {
            $section_row = $section_result->fetch_assoc();
            $section_name = htmlspecialchars($section_row['section_name']);
        }
    }
}

// Fetch academic dates based on selected semester
$academic_year = '';
$start_date = '';
$end_date = '';
if ($selected_semester_id) {
    $academic_query = "SELECT academic_year, start_date, end_date 
                      FROM academic_dates 
                      WHERE semester = (SELECT semester_name FROM semesters WHERE semester_id = '$selected_semester_id')";
    $academic_result = $conn->query($academic_query);
    if ($academic_result && $academic_result->num_rows > 0) {
        $academic_row = $academic_result->fetch_assoc();
        $academic_year = htmlspecialchars($academic_row['academic_year']);
        $start_date = date('d-m-Y', strtotime($academic_row['start_date']));
        $end_date = date('d-m-Y', strtotime($academic_row['end_date']));
    }
}

// Fetch schedules based on filters
$schedules = [];
if ($selected_section_id && $selected_semester_id) {
    $schedules_query = "SELECT ss.*, s.section_name, c.course_code, c.course_title, c.course_type, ss.schedule_type, 
                               i.instructor_name, r.room_name, b.building_name 
                        FROM section_schedules ss 
                        JOIN sections s ON ss.section_id = s.id 
                        JOIN courses c ON ss.course_id = c.course_id 
                        JOIN instructors i ON ss.instructor_id = i.instructor_id 
                        JOIN rooms r ON ss.room_id = r.id 
                        JOIN buildings b ON r.building_id = b.id 
                        WHERE ss.section_id = '$selected_section_id' AND ss.semester_id = '$selected_semester_id'";
    $schedules_result = $conn->query($schedules_query);
    if ($schedules_result === false) {
        die("Schedules query failed: " . $conn->error);
    }
    $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Schedule - Class Scheduling</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            background-image: url('image/photo2.jpg');
            background-size: contain;
            background-position: center;
            background-repeat: repeat;
            min-height: 100vh;
        }
        header {
            background-color: rgba(0, 0, 255, 0.8);
            color: white;
            padding: 10px 0;
            text-align: center;
        }
        header img {
            max-width: 100px;
        }
        nav ul {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }
        nav ul li {
            display: inline;
            margin: 0 15px;
        }
        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            transition: background-color 0.3s;
        }
        nav ul li a:hover {
            background-color: rgba(0, 255, 0, 0.7);
        }
        main {
            text-align: center;
            padding: 20px;
            background-color: rgba(160, 160, 160, 0.7);
            margin: 20px auto;
            width: 90%;
            max-width: 1100px;
            border-radius: 5px;
        }
        h1 {
            color: #333;
        }
        .filter-form {
            margin-bottom: 20px;
        }
        .filter-form label {
            display: inline-block;
            width: 120px;
            text-align: right;
            margin-right: 10px;
        }
        select {
            padding: 8px;
            margin: 5px;
            width: 250px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #555;
            color: white;
        }
        td {
            background-color: #752;
            color: white;
        }
        p {
            font-size: 1.1em;
            color: #666;
        }
        .print-button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }
        .print-details {
            margin-bottom: 10px;
            font-size: 1em;
        }
        .print-details span {
            font-weight: bold;
        }
        .filter-display {
            margin-bottom: 15px;
            font-size: 1.1em;
            color: #333;
        }
        .offline-message {
            display: none;
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            animation: slideIn 0.5s;
        }
        @keyframes slideIn {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        /* Print-specific styles */
        @media print {
            body {
                background: none;
                margin: 0;
            }
            header, nav, .filter-form, .print-button, h1, .filter-display, .offline-message {
                display: none;
            }
            main {
                background: none;
                background-color: rgba(0, 102, 102, 0.8);
                margin: 0;
                padding: 10px;
                width: 100%;
                max-width: 100%;
                border-radius: 0;
                box-shadow: none;
            }
            .print-header {
                display: block;
                font-size: 1.5em;
                font-weight: bold;
            }
            .print-details {
                display: block;
            }
            h2 {
                font-size: 1.2em;
                margin-bottom: 10px;
            }
            table {
                background-color: rgba(0, 102, 102, 0.8);
                width: 100%;
                font-size: 12pt;
                page-break-inside: auto;
            }
            th, td {
                padding: 5px;
                border: 1px solid #000;
            }
            th {
                background-color: #ddd;
                color: #000;
            }
            p {
                display: none;
            }
        }

        /* Responsive design */
        @media (max-width: 600px) {
            .filter-form label {
                display: block;
                text-align: left;
                width: auto;
            }
            select {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
    <script>
        function updateFilters() {
            document.getElementById('filterForm').submit();
        }

        function printSchedule() {
            window.print();
        }

        // Offline handling
        window.addEventListener('online', () => {
            document.getElementById('offline-message').style.display = 'none';
        });

        window.addEventListener('offline', () => {
            document.getElementById('offline-message').style.display = 'block';
        });
    </script>
</head>
<body>
    <header>
        <img src="image/DMUlog.png" alt="Markos University Logo">
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="public_schedule.php">View Schedule</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Class Schedule</h1>
        <form id="filterForm" method="get" class="filter-form">
            <div>
                <label for="dept_id">Department:</label>
                <select name="dept_id" id="dept_id" onchange="updateFilters()">
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['dept_id']; ?>" <?php echo $selected_dept_id == $dept['dept_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="batch_semester">Batch (Semester):</label>
                <select name="batch_semester" id="batch_semester" onchange="updateFilters()" <?php echo !$selected_dept_id ? 'disabled' : ''; ?>>
                    <option value="">-- Select Batch (Semester) --</option>
                    <?php foreach ($batch_semesters as $bs): ?>
                        <option value="<?php echo $bs['batch_id'] . ':' . $bs['semester_id']; ?>" 
                                <?php echo ($selected_batch_id == $bs['batch_id'] && $selected_semester_id == $bs['semester_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bs['batch_name'] . ' (' . ($bs['semester_name'] ?: 'Unknown') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="section_id">Section:</label>
                <select name="section_id" id="section_id" onchange="updateFilters()" <?php echo !$selected_batch_id ? 'disabled' : ''; ?>>
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>" <?php echo $selected_section_id == $section['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($selected_section_id && $selected_semester_id): ?>
            <div class="filter-display">
                Showing schedules for: 
                <strong><?php echo $dept_name; ?>, 
                <?php echo $batch_name . ' (' . $semester_name . ')'; ?>, 
                Section <?php echo $section_name; ?></strong>
            </div>

            <div class="print-header">
                Debre Markos University Burie Campus
                Class Schedule
                <div class="print-details">
                    <span>Department:</span> <?php echo $dept_name; ?> | 
                    <span>Batch:</span> <?php echo $batch_name; ?> | 
                    <span>Semester:</span> <?php echo $semester_name; ?> | 
                    <br>
                    <span>Section:</span> <?php echo $section_name; ?>
                    <?php if ($academic_year && $start_date && $end_date && $academic_year !== 'Not Set' && $start_date !== 'Not Set' && $end_date !== 'Not Set'): ?>
                         | <span>Academic Year:</span> <?php echo $academic_year; ?> | 
                         <span>Semester Dates:</span> <?php echo $start_date . ' to ' . $end_date; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($schedules)): ?>
                <table>
                    <tr>
                        <th>Course</th>
                        <th>Instructor</th>
                        <th>Room</th>
                        <th>Day</th>
                        <th>Time</th>
                    </tr>
                    <?php foreach ($schedules as $schedule): 
                        $course_display = htmlspecialchars($schedule['course_title'] . ' (' . $schedule['schedule_type'] . ')');
                    ?>
                        <tr>
                            <td><?php echo $course_display; ?></td>
                            <td><?php echo htmlspecialchars($schedule['instructor_name']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['room_name'] . ' (' . $schedule['building_name'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($schedule['day']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['start_time'] . ' - ' . $schedule['end_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <button class="print-button" onclick="printSchedule()">Print Schedule</button>
            <?php else: ?>
                <p>No schedules found for this section in this semester.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>Please select a department, batch (semester), and section to view the schedule.</p>
        <?php endif; ?>
    </main>

    <!-- Offline message -->
    <div class="offline-message" id="offline-message">You are offline. Displaying cached data.</div>

    <script>
        // Cache academic dates for offline use
        const academicDates = {
            semester_id: '<?php echo $selected_semester_id; ?>',
            academic_year: '<?php echo $academic_year; ?>',
            start_date: '<?php echo $start_date; ?>',
            end_date: '<?php echo $end_date; ?>'
        };
        if (academicDates.semester_id && academicDates.academic_year !== 'Not Set' && academicDates.start_date !== 'Not Set' && academicDates.end_date !== 'Not Set') {
            localStorage.setItem('academicDates_' + academicDates.semester_id, JSON.stringify(academicDates));
        }

        // Load cached academic dates if offline
        if (!navigator.onLine && '<?php echo $selected_semester_id; ?>') {
            const cachedDates = JSON.parse(localStorage.getItem('academicDates_' + '<?php echo $selected_semester_id; ?>') || '{}');
            if (cachedDates.academic_year && cachedDates.start_date && cachedDates.end_date) {
                const printDetails = document.querySelector('.print-details');
                if (printDetails) {
                    printDetails.innerHTML = `
                        <span>Department:</span> <?php echo $dept_name; ?> | 
                        <span>Batch:</span> <?php echo $batch_name; ?> | 
                        <span>Semester:</span> <?php echo $semester_name; ?> | 
                        <br>
                        <span>Section:</span> <?php echo $section_name; ?> | 
                        <span>Academic Year:</span> ${cachedDates.academic_year} | 
                        <span>Semester Dates:</span> ${cachedDates.start_date} to ${cachedDates.end_date}
                    `;
                }
            } else {
                const printDetails = document.querySelector('.print-details');
                if (printDetails) {
                    printDetails.innerHTML = `
                        <span>Department:</span> <?php echo $dept_name; ?> | 
                        <span>Batch:</span> <?php echo $batch_name; ?> | 
                        <span>Semester:</span> <?php echo $semester_name; ?> | 
                        <br>
                        <span>Section:</span> <?php echo $section_name; ?>
                    `;
                }
            }
        }
    </script>

<?php
$conn->close();
?>
</body>
</html>