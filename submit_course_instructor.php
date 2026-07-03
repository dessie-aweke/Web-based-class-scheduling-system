<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "dept_head") {
    header("Location: login.php");
    exit();
}

include 'database/db_connection.php';

$user_id = $conn->real_escape_string($_SESSION["user_id"]);

// Fetch department for the logged-in dept head
$dept_query = "SELECT dept_id, dept_name FROM departments WHERE head_id = '$user_id'";
$dept_result = $conn->query($dept_query);
if ($dept_result === false || $dept_result->num_rows == 0) {
    die("No department assigned to this Department Head.");
}
$department = $dept_result->fetch_assoc();
$dept_id = $department['dept_id'];

// Fetch all batches with semester for this department
$batches_query = "SELECT b.id, b.batch_name, s.semester_name, b.semester_id 
                  FROM batches b 
                  LEFT JOIN semesters s ON b.semester_id = s.semester_id 
                  WHERE b.dept_id = '$dept_id' 
                  ORDER BY b.batch_name, s.semester_name";
$all_batches = $conn->query($batches_query)->fetch_all(MYSQLI_ASSOC);

// Fetch all courses (global)
$courses_query = "SELECT * FROM courses ORDER BY course_code";
$all_courses = $conn->query($courses_query)->fetch_all(MYSQLI_ASSOC);

// Fetch all instructors (global)
$instructors_query = "SELECT instructor_id, instructor_name FROM instructors ORDER BY instructor_name";
$all_instructors = $conn->query($instructors_query)->fetch_all(MYSQLI_ASSOC);

// Initialize success/error messages
$success = '';
$error = '';

// Handle course addition
if (isset($_POST['add_course'])) {
    $course_code = strtolower(trim($conn->real_escape_string($_POST['course_code'])));
    $course_title = $conn->real_escape_string($_POST['course_title']);
    $credit_hours = (int)$_POST['credit_hours'];
    $lecture_hours = (int)$_POST['lecture_hours'];
    $course_type = $conn->real_escape_string($_POST['course_type']);
    
    $check_duplicate = $conn->query("SELECT course_code FROM courses WHERE LOWER(TRIM(course_code)) = '$course_code'");
    if ($check_duplicate->num_rows > 0) {
        $error = "This course is already stored in the system.";
    } else {
        $sql = "INSERT INTO courses (course_code, course_title, credit_hours, lecture_hours, course_type) 
                VALUES ('$course_code', '$course_title', '$credit_hours', '$lecture_hours', '$course_type')";
        if ($conn->query($sql)) {
            $success = "Course added successfully!";
        } else {
            $error = "Error adding course: " . $conn->error;
        }
    }
}

// Handle course edit
if (isset($_POST['edit_course'])) {
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $course_code = $conn->real_escape_string($_POST['course_code']);
    $course_title = $conn->real_escape_string($_POST['course_title']);
    $credit_hours = (int)$_POST['credit_hours'];
    $lecture_hours = (int)$_POST['lecture_hours'];
    $course_type = $conn->real_escape_string($_POST['course_type']);
    $sql = "UPDATE courses SET course_code='$course_code', course_title='$course_title', 
            credit_hours='$credit_hours', lecture_hours='$lecture_hours', course_type='$course_type' 
            WHERE course_id='$course_id'";
    if ($conn->query($sql)) {
        $success = "Course updated successfully!";
    } else {
        $error = "Error editing course: " . $conn->error;
    }
}

// Handle course deletion
if (isset($_POST['delete_course'])) {
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $conn->query("DELETE FROM batch_course_assignments WHERE course_id='$course_id'");
    if ($conn->query("DELETE FROM courses WHERE course_id='$course_id'")) {
        $success = "Course deleted successfully!";
    } else {
        $error = "Error deleting course: " . $conn->error;
    }
}

// Handle course assignment to batch
if (isset($_POST['assign_course'])) {
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $semester_id = $conn->real_escape_string($_POST['semester_id']);
    
    // Validate semester_id belongs to the batch
    $batch_check = $conn->query("SELECT semester_id FROM batches WHERE id='$batch_id' AND semester_id='$semester_id'");
    if ($batch_check->num_rows == 0) {
        $error = "Invalid semester for the selected batch.";
    } else {
        $sql = "INSERT IGNORE INTO batch_course_assignments (batch_id, course_id, semester_id) 
                VALUES ('$batch_id', '$course_id', '$semester_id')";
        if ($conn->query($sql)) {
            // Fetch batch and semester names for success message
            $batch_info = $conn->query("SELECT b.batch_name, s.semester_name 
                                        FROM batches b 
                                        JOIN semesters s ON b.semester_id = s.semester_id 
                                        WHERE b.id='$batch_id'")->fetch_assoc();
            $course_code = $conn->query("SELECT course_code FROM courses WHERE course_id='$course_id'")->fetch_assoc()['course_code'];
            $success = "Course $course_code assigned to {$batch_info['batch_name']} - {$batch_info['semester_name']} successfully!";
        } else {
            $error = "Error assigning course: " . $conn->error;
        }
    }
}

// Handle course unassignment from batch
if (isset($_POST['unassign_course'])) {
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    $course_id = $conn->real_escape_string($_POST['course_id']);
    $semester_id = $conn->real_escape_string($_POST['semester_id']);
    $sql = "DELETE FROM batch_course_assignments WHERE batch_id='$batch_id' AND course_id='$course_id' AND semester_id='$semester_id'";
    if ($conn->query($sql)) {
        // Fetch batch and semester names for success message
        $batch_info = $conn->query("SELECT b.batch_name, s.semester_name 
                                    FROM batches b 
                                    JOIN semesters s ON b.semester_id = s.semester_id 
                                    WHERE b.id='$batch_id'")->fetch_assoc();
        $course_code = $conn->query("SELECT course_code FROM courses WHERE course_id='$course_id'")->fetch_assoc()['course_code'];
        $success = "Course $course_code unassigned from {$batch_info['batch_name']} - {$batch_info['semester_name']} successfully!";
    } else {
        $error = "Error unassigning course: " . $conn->error;
    }
}

// Handle instructor addition
if (isset($_POST['add_instructor'])) {
    $instructor_name = strtolower(trim($conn->real_escape_string($_POST['instructor_name'])));
    
    $check_duplicate = $conn->query("SELECT instructor_name FROM instructors WHERE LOWER(TRIM(instructor_name)) = '$instructor_name'");
    if ($check_duplicate->num_rows > 0) {
        $error = "This instructor is already stored in the system.";
    } else {
        $sql = "INSERT INTO instructors (instructor_name) VALUES ('$instructor_name')";
        if ($conn->query($sql)) {
            $success = "Instructor added successfully!";
        } else {
            $error = "Error adding instructor: " . $conn->error;
        }
    }
}

// Handle instructor edit
if (isset($_POST['edit_instructor'])) {
    $instructor_id = $conn->real_escape_string($_POST['instructor_id']);
    $instructor_name = $conn->real_escape_string($_POST['instructor_name']);
    $sql = "UPDATE instructors SET instructor_name='$instructor_name' WHERE instructor_id='$instructor_id'";
    if ($conn->query($sql)) {
        $success = "Instructor updated successfully!";
    } else {
        $error = "Error editing instructor: " . $conn->error;
    }
}

// Handle instructor deletion
if (isset($_POST['delete_instructor'])) {
    $instructor_id = $conn->real_escape_string($_POST['instructor_id']);
    if ($conn->query("DELETE FROM instructors WHERE instructor_id='$instructor_id'")) {
        $success = "Instructor deleted successfully!";
    } else {
        $error = "Error deleting instructor: " . $conn->error;
    }
}

// Fetch assigned courses for each batch
$assigned_courses = [];
foreach ($all_batches as $batch) {
    $assigned_query = "SELECT c.*, s.semester_name 
                       FROM courses c 
                       JOIN batch_course_assignments bc ON c.course_id = bc.course_id 
                       JOIN semesters s ON bc.semester_id = s.semester_id 
                       WHERE bc.batch_id = '{$batch['id']}'";
    $assigned_courses[$batch['id']] = $conn->query($assigned_query)->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses & Instructors</title>
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

        .toggle-btn {
            background: var(--gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            display: block;
            margin: 1rem auto;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .toggle-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        form {
            display: none;
        }

        .form-shown {
            display: block !important;
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
            color: var(--text);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(40, 167, 69, 0.3);
            outline: none;
        }

        input[readonly] {
            background: #e9ecef;
            cursor: not-allowed;
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
        select:not([value=""]) + label {
            top: 0;
            font-size: 0.75rem;
            color: var(--primary);
        }

        button[type="submit"] {
            background: var(--gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .delete-btn {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }

        .delete-btn:hover {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .unassign-btn {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .unassign-btn:hover {
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.4);
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

        .search-input {
            width: 100%;
            max-width: 400px;
            padding: 0.75rem;
            margin: 1rem 0;
            border: 1px solid #dcdcdc;
            border-radius: 5px;
            background: var(--card-bg);
            color: var(--text);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(40, 167, 69, 0.3);
            outline: none;
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
            padding: 20px;
        }

        .modal-content {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
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

        .hidden {
            display: none;
            opacity: 0;
            transform: translateY(20px);
        }

        .transition {
            transition: all 0.5s ease;
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

            .modal-content {
                max-height: 70vh;
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
        <img src="image/markoslogo.jpg" alt="Markos University Logo" class="logo" onerror="this.src='https://via.placeholder.com/40';">
        <h1>Manage Courses & Instructors</h1>
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
            <h2>Welcome, Department Head of <?php echo htmlspecialchars($department['dept_name']); ?>!</h2>
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add New Course Form -->
            <button class="toggle-btn" onclick="toggleForm('addCourseForm')">Add Course</button>
            <form method="post" id="addCourseForm" class="card form-shown">
                <h3>Add New Course</h3>
                <div class="form-group">
                    <input type="text" name="course_code" id="course_code" placeholder=" " required>
                    <label for="course_code">Course Code</label>
                </div>
                <div class="form-group">
                    <input type="text" name="course_title" id="course_title" placeholder=" " required>
                    <label for="course_title">Course Title</label>
                </div>
                <div class="form-group">
                    <input type="number" name="credit_hours" id="credit_hours" min="1" placeholder=" " required>
                    <label for="credit_hours">Credit Hours</label>
                </div>
                <div class="form-group">
                    <input type="number" name="lecture_hours" id="lecture_hours" min="1" placeholder=" " required>
                    <label for="lecture_hours">Lecture Hours Per Week</label>
                </div>
                <div class="form-group">
                    <select name="course_type" id="course_type" required>
                        <option value="" disabled selected></option>
                        <option value="Lecture">Lecture</option>
                        <option value="Lab">Lab</option>
                        <option value="Both">Both</option>
                    </select>
                    <label for="course_type">Course Type</label>
                </div>
                <button type="submit" name="add_course">Add Course</button>
            </form>

            <!-- Assign Course to Batch Form -->
            <button class="toggle-btn" onclick="toggleForm('assignCourseForm')">Assign Course</button>
            <form method="post" id="assignCourseForm" class="card">
                <h3>Assign Existing Course to Batch</h3>
                <div class="form-group">
                    <select name="batch_id" id="batch_id" required onchange="updateSemester()">
                        <option value="" disabled selected></option>
                        <?php foreach ($all_batches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" data-semester-id="<?php echo $b['semester_id']; ?>" data-semester-name="<?php echo htmlspecialchars($b['semester_name']); ?>">
                                <?php echo htmlspecialchars($b['batch_name'] . ' - ' . $b['semester_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="batch_id">Batch - Semester</label>
                </div>
                <div class="form-group">
                    <input type="text" name="semester_name" id="semester_name" readonly placeholder=" " required>
                    <input type="hidden" name="semester_id" id="semester_id">
                    <label for="semester_name">Semester</label>
                </div>
                <div class="form-group">
                    <select name="course_id" id="course_id" required>
                        <option value="" disabled selected></option>
                        <?php foreach ($all_courses as $c): ?>
                            <option value="<?php echo $c['course_id']; ?>">
                                <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="course_id">Course</label>
                </div>
                <button type="submit" name="assign_course">Assign Course</button>
            </form>

            <!-- All Courses Table -->
            <button class="toggle-btn" onclick="toggleTable('allCoursesTable')">Show All Courses</button>
            <div id="allCoursesTable" class="card table-container hidden transition">
                <h3>All Courses</h3>
                <input type="text" class="search-input" id="courseSearch" placeholder="Search courses..." onkeyup="searchTable('courseSearch', 'allCoursesTable')">
                <?php if (!empty($all_courses)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Credit Hours</th>
                                <th>Lecture Hours</th>
                                <th>Course Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_courses as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                                    <td><?php echo $c['credit_hours']; ?></td>
                                    <td><?php echo $c['lecture_hours']; ?></td>
                                    <td><?php echo htmlspecialchars($c['course_type']); ?></td>
                                    <td>
                                        <button onclick="openEditCourseModal(
                                            <?php echo $c['course_id']; ?>,
                                            '<?php echo htmlspecialchars($c['course_code']); ?>',
                                            '<?php echo htmlspecialchars($c['course_title']); ?>',
                                            <?php echo $c['credit_hours']; ?>,
                                            <?php echo $c['lecture_hours']; ?>,
                                            '<?php echo htmlspecialchars($c['course_type']); ?>'
                                        )">Edit</button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="course_id" value="<?php echo $c['course_id']; ?>">
                                            <button type="submit" name="delete_course" class="delete-btn" 
                                                    onclick="return confirm('Are you sure? This will remove it from all batches.');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No courses added yet.</p>
                <?php endif; ?>
            </div>

            <!-- Add New Instructor Form -->
            <button class="toggle-btn" onclick="toggleForm('addInstructorForm')">Add Instructor</button>
            <form method="post" id="addInstructorForm" class="card">
                <h3>Add New Instructor</h3>
                <div class="form-group">
                    <input type="text" name="instructor_name" id="instructor_name" placeholder=" " required>
                    <label for="instructor_name">Instructor Name</label>
                </div>
                <button type="submit" name="add_instructor">Add Instructor</button>
            </form>

            <!-- All Instructors Table -->
            <button class="toggle-btn" onclick="toggleTable('allInstructorsTable')">Show All Instructors</button>
            <div id="allInstructorsTable" class="card table-container hidden transition">
                <h3>All Instructors</h3>
                <input type="text" class="search-input" id="instructorSearch" placeholder="Search instructors..." onkeyup="searchTable('instructorSearch', 'allInstructorsTable')">
                <?php if (!empty($all_instructors)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Instructor Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_instructors as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['instructor_name']); ?></td>
                                    <td>
                                        <button onclick="openEditInstructorModal(
                                            <?php echo $i['instructor_id']; ?>,
                                            '<?php echo htmlspecialchars($i['instructor_name']); ?>'
                                        )">Edit</button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="instructor_id" value="<?php echo $i['instructor_id']; ?>">
                                            <button type="submit" name="delete_instructor" class="delete-btn" 
                                                    onclick="return confirm('Are you sure?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No instructors added yet.</p>
                <?php endif; ?>
            </div>

            <!-- Assigned Courses by Batch Table -->
            <button class="toggle-btn" onclick="toggleTable('assignedCoursesTable')">Show Assigned Courses</button>
            <div id="assignedCoursesTable" class="card table-container hidden transition">
                <h3>Assigned Courses by Batch</h3>
                <?php foreach ($all_batches as $b): ?>
                    <h4><?php echo htmlspecialchars($b['batch_name'] . ' - ' . $b['semester_name']); ?></h4>
                    <?php if (!empty($assigned_courses[$b['id']])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Semester</th>
                                    <th>Credit Hours</th>
                                    <th>Lecture Hours</th>
                                    <th>Course Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_courses[$b['id']] as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                        <td><?php echo htmlspecialchars($course['semester_name']); ?></td>
                                        <td><?php echo $course['credit_hours']; ?></td>
                                        <td><?php echo $course['lecture_hours']; ?></td>
                                        <td><?php echo htmlspecialchars($course['course_type']); ?></td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="batch_id" value="<?php echo $b['id']; ?>">
                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                <input type="hidden" name="semester_id" value="<?php echo $b['semester_id']; ?>">
                                                <button type="submit" name="unassign_course" class="unassign-btn" 
                                                        onclick="return confirm('Are you sure you want to unassign <?php echo htmlspecialchars($course['course_code']); ?> from <?php echo htmlspecialchars($b['batch_name'] . ' - ' . $b['semester_name']); ?>?');">Unassign</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No courses assigned yet.</p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Edit Course Modal -->
    <div class="modal" id="editCourseModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditCourseModal()">×</span>
            <form method="post">
                <h3>Edit Course</h3>
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="form-group">
                    <input type="text" name="course_code" id="edit_course_code" placeholder=" " required>
                    <label for="edit_course_code">Course Code</label>
                </div>
                <div class="form-group">
                    <input type="text" name="course_title" id="edit_course_title" placeholder=" " required>
                    <label for="edit_course_title">Course Title</label>
                </div>
                <div class="form-group">
                    <input type="number" name="credit_hours" id="edit_credit_hours" min="1" placeholder=" " required>
                    <label for="edit_credit_hours">Credit Hours</label>
                </div>
                <div class="form-group">
                    <input type="number" name="lecture_hours" id="edit_lecture_hours" min="1" placeholder=" " required>
                    <label for="edit_lecture_hours">Lecture Hours Per Week</label>
                </div>
                <div class="form-group">
                    <select name="course_type" id="edit_course_type" required>
                        <option value="Lecture">Lecture</option>
                        <option value="Lab">Lab</option>
                        <option value="Both">Both</option>
                    </select>
                    <label for="edit_course_type">Course Type</label>
                </div>
                <button type="submit" name="edit_course">Update Course</button>
            </form>
        </div>
    </div>

    <!-- Edit Instructor Modal -->
    <div class="modal" id="editInstructorModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditInstructorModal()">×</span>
            <form method="post">
                <h3>Edit Instructor</h3>
                <input type="hidden" name="instructor_id" id="edit_instructor_id">
                <div class="form-group">
                    <input type="text" name="instructor_name" id="edit_instructor_name" placeholder=" " required>
                    <label for="edit_instructor_name">Instructor Name</label>
                </div>
                <button type="submit" name="edit_instructor">Update Instructor</button>
            </form>
        </div>
    </div>

    <div class="offline-message" id="offline-message">You are offline. Displaying cached data.</div>

    <script>
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            sidebar.classList.toggle('active');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('full-width');
        }

        // Theme toggle
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

        // Load theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.querySelector('.theme-toggle i').classList.replace('fa-moon', 'fa-sun');
        }

        // Offline handling
        window.addEventListener('online', () => {
            document.getElementById('offline-message').style.display = 'none';
        });

        window.addEventListener('offline', () => {
            document.getElementById('offline-message').style.display = 'block';
        });

        // Cache data
        const courseData = <?php echo json_encode($all_courses); ?>;
        const instructorData = <?php echo json_encode($all_instructors); ?>;
        const assignedData = <?php echo json_encode($assigned_courses); ?>;
        const batchData = <?php echo json_encode($all_batches); ?>;
        localStorage.setItem('courseData', JSON.stringify(courseData));
        localStorage.setItem('instructorData', JSON.stringify(instructorData));
        localStorage.setItem('assignedData', JSON.stringify(assignedData));
        localStorage.setItem('batchData', JSON.stringify(batchData));

        // Load cached data if offline
        if (!navigator.onLine) {
            const cachedCourses = JSON.parse(localStorage.getItem('courseData') || '[]');
            const cachedInstructors = JSON.parse(localStorage.getItem('instructorData') || '[]');
            const cachedAssigned = JSON.parse(localStorage.getItem('assignedData') || '{}');
            const cachedBatches = JSON.parse(localStorage.getItem('batchData') || '[]');

            if (cachedCourses.length) {
                const courseTableBody = document.querySelector('#allCoursesTable tbody');
                if (courseTableBody) {
                    courseTableBody.innerHTML = '';
                    cachedCourses.forEach(c => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${c.course_code}</td>
                            <td>${c.course_title}</td>
                            <td>${c.credit_hours}</td>
                            <td>${c.lecture_hours}</td>
                            <td>${c.course_type}</td>
                            <td><button disabled>Edit</button> <button disabled>Delete</button></td>
                        `;
                        courseTableBody.appendChild(row);
                    });
                }
            }

            if (cachedInstructors.length) {
                const instructorTableBody = document.querySelector('#allInstructorsTable tbody');
                if (instructorTableBody) {
                    instructorTableBody.innerHTML = '';
                    cachedInstructors.forEach(i => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${i.instructor_name}</td>
                            <td><button disabled>Edit</button> <button disabled>Delete</button></td>
                        `;
                        instructorTableBody.appendChild(row);
                    });
                }
            }

            if (Object.keys(cachedAssigned).length) {
                const assignedContainer = document.getElementById('assignedCoursesTable');
                assignedContainer.innerHTML = '<h3>Assigned Courses by Batch</h3>';
                cachedBatches.forEach(b => {
                    const batchSection = document.createElement('div');
                    batchSection.innerHTML = `<h4>${b.batch_name} - ${b.semester_name}</h4>`;
                    const courses = cachedAssigned[b.id] || [];
                    if (courses.length) {
                        let table = `
                            <table>
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Semester</th>
                                        <th>Credit Hours</th>
                                        <th>Lecture Hours</th>
                                        <th>Course Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        courses.forEach(c => {
                            table += `
                                <tr>
                                    <td>${c.course_code}</td>
                                    <td>${c.course_title}</td>
                                    <td>${c.semester_name}</td>
                                    <td>${c.credit_hours}</td>
                                    <td>${c.lecture_hours}</td>
                                    <td>${c.course_type}</td>
                                    <td><button disabled>Unassign</button></td>
                                </tr>
                            `;
                        });
                        table += '</tbody></table>';
                        batchSection.innerHTML += table;
                    } else {
                        batchSection.innerHTML += '<p>No courses assigned yet.</p>';
                    }
                    assignedContainer.appendChild(batchSection);
                });
            }
        }

        // Form and table toggle
        function toggleForm(id) {
            const form = document.getElementById(id);
            form.classList.toggle('form-shown');
        }

        function toggleTable(id) {
            const table = document.getElementById(id);
            table.classList.toggle('hidden');
            if (!table.classList.contains('hidden')) {
                table.style.opacity = '1';
                table.style.transform = 'translateY(0)';
            }
        }

        // Search table
        function searchTable(searchId, tableId) {
            const input = document.getElementById(searchId);
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                for (let j = 0; j < td.length; j++) {
                    const text = td[j].textContent || td[j].innerText;
                    if (text.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Update semester field based on batch selection
        function updateSemester() {
            const batchSelect = document.getElementById('batch_id');
            const semesterNameInput = document.getElementById('semester_name');
            const semesterIdInput = document.getElementById('semester_id');
            const selectedOption = batchSelect.options[batchSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                semesterNameInput.value = selectedOption.getAttribute('data-semester-name') || '';
                semesterIdInput.value = selectedOption.getAttribute('data-semester-id') || '';
            } else {
                semesterNameInput.value = '';
                semesterIdInput.value = '';
            }
        }

        // Modal handling for course
        function openEditCourseModal(id, code, title, creditHours, lectureHours, type) {
            const modal = document.getElementById('editCourseModal');
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_code').value = code;
            document.getElementById('edit_course_title').value = title;
            document.getElementById('edit_credit_hours').value = creditHours;
            document.getElementById('edit_lecture_hours').value = lectureHours;
            document.getElementById('edit_course_type').value = type;
            modal.style.display = 'flex';
        }

        function closeEditCourseModal() {
            document.getElementById('editCourseModal').style.display = 'none';
        }

        // Modal handling for instructor
        function openEditInstructorModal(id, name) {
            const modal = document.getElementById('editInstructorModal');
            document.getElementById('edit_instructor_id').value = id;
            document.getElementById('edit_instructor_name').value = name;
            modal.style.display = 'flex';
        }

        function closeEditInstructorModal() {
            document.getElementById('editInstructorModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const courseModal = document.getElementById('editCourseModal');
            const instructorModal = document.getElementById('editInstructorModal');
            if (event.target === courseModal) {
                closeEditCourseModal();
            }
            if (event.target === instructorModal) {
                closeEditInstructorModal();
            }
        };

        // Redirect with delay if success
        <?php if ($success): ?>
            setTimeout(() => {
                window.location.href = 'submit_course_instructor.php';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>