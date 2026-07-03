<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "dept_head") {
    header("Location: login.php");
    exit();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'database/db_connection.php';

$user_id = $conn->real_escape_string($_SESSION["user_id"]);

// Fetch the department ID for the logged-in dept head
$dept_query = "SELECT dept_id FROM departments WHERE head_id = '$user_id'";
$dept_result = $conn->query($dept_query);
if ($dept_result === false || $dept_result->num_rows == 0) {
    die("No department assigned to this Department Head.");
}
$department = $dept_result->fetch_assoc()['dept_id'];

// Fetch semesters (only 1st and 2nd Semester)
$semester_query = "SELECT semester_id, semester_name FROM semesters ORDER BY semester_name";
$semester_result = $conn->query($semester_query);
$semesters = $semester_result ? $semester_result->fetch_all(MYSQLI_ASSOC) : [];

// Handle batch submission
if (isset($_POST['submit_batch'])) {
    $batch_name = $conn->real_escape_string($_POST['batch_name']);
    $num_sections = (int)$_POST['num_sections'];
    $semester_id = $conn->real_escape_string($_POST['semester_id']);
    
    // Check if batch already has two semesters
    $semester_count = $conn->query("SELECT COUNT(*) FROM batches WHERE dept_id = '$department' AND batch_name = '$batch_name'")->fetch_row()[0];
    if ($semester_count >= 2) {
        $error = "Batch '$batch_name' already has both 1st and 2nd Semester.";
    } else {
        // Check for duplicate batch-semester combination
        $check_duplicate = $conn->query("SELECT id FROM batches WHERE dept_id = '$department' AND batch_name = '$batch_name' AND semester_id = '$semester_id'");
        if ($check_duplicate->num_rows > 0) {
            $error = "Batch '$batch_name' already exists for this semester.";
        } else {
            $sql = "INSERT INTO batches (dept_id, batch_name, num_sections, semester_id) VALUES ('$department', '$batch_name', '$num_sections', '$semester_id')";
            if ($conn->query($sql)) {
                $batch_id = $conn->insert_id;
                for ($i = 1; $i <= $num_sections; $i++) {
                    $section_name = "Section " . chr(64 + $i);
                    $section_sql = "INSERT INTO sections (batch_id, section_name, num_students) VALUES ('$batch_id', '$section_name', 0)";
                    if (!$conn->query($section_sql)) {
                        $error = "Error adding section $section_name: " . $conn->error;
                        break;
                    }
                }
                if (!isset($error)) {
                    $success = "Batch and sections added successfully!";
                }
            } else {
                $error = "Error adding batch: " . $conn->error;
            }
        }
    }
}

// Handle batch edit
if (isset($_POST['edit_batch'])) {
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    $batch_name = $conn->real_escape_string($_POST['batch_name']);
    $num_sections = (int)$_POST['num_sections'];
    $semester_id = $conn->real_escape_string($_POST['semester_id']);
    
    // Check if batch already has two semesters (excluding current batch)
    $semester_count = $conn->query("SELECT COUNT(*) FROM batches WHERE dept_id = '$department' AND batch_name = '$batch_name' AND id != '$batch_id'")->fetch_row()[0];
    if ($semester_count >= 2) {
        $error = "Batch '$batch_name' already has both 1st and 2nd Semester.";
    } else {
        // Check for duplicate batch-semester combination (excluding current batch)
        $check_duplicate = $conn->query("SELECT id FROM batches WHERE dept_id = '$department' AND batch_name = '$batch_name' AND semester_id = '$semester_id' AND id != '$batch_id'");
        if ($check_duplicate->num_rows > 0) {
            $error = "Batch '$batch_name' already exists for this semester.";
        } else {
            $sql = "UPDATE batches SET batch_name='$batch_name', num_sections='$num_sections', semester_id='$semester_id' WHERE id='$batch_id' AND dept_id='$department'";
            if ($conn->query($sql)) {
                // Sync sections with new num_sections
                $existing_count = $conn->query("SELECT COUNT(*) FROM sections WHERE batch_id = '$batch_id'")->fetch_row()[0];
                if ($existing_count < $num_sections) {
                    for ($i = $existing_count + 1; $i <= $num_sections; $i++) {
                        $section_name = "Section " . chr(64 + $i);
                        $section_sql = "INSERT INTO sections (batch_id, section_name, num_students) VALUES ('$batch_id', '$section_name', 0)";
                        $conn->query($section_sql);
                    }
                } elseif ($existing_count > $num_sections) {
                    $conn->query("DELETE FROM sections WHERE batch_id = '$batch_id' AND id NOT IN (SELECT id FROM (SELECT id FROM sections WHERE batch_id = '$batch_id' ORDER BY id LIMIT $num_sections) AS temp)");
                }
                $success = "Batch updated successfully!";
            } else {
                $error = "Error updating batch: " . $conn->error;
            }
        }
    }
}

// Handle batch deletion
if (isset($_POST['delete_batch'])) {
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    // Check if batch is linked to schedules
    $schedule_check = $conn->query("SELECT COUNT(*) FROM section_schedules WHERE section_id IN (SELECT id FROM sections WHERE batch_id = '$batch_id')");
    if ($schedule_check->fetch_row()[0] > 0) {
        $error = "Cannot delete this batch because it is linked to course assignments. Please remove all associated course assignments before deleting.";
    } else {
        // Delete sections and batch
        $conn->query("DELETE FROM sections WHERE batch_id='$batch_id'");
        $sql = "DELETE FROM batches WHERE id='$batch_id' AND dept_id='$department'";
        if ($conn->query($sql)) {
            $success = "Batch deleted successfully!";
        } else {
            $error = "Error deleting batch: " . $conn->error;
        }
    }
}

// Handle section submission
if (isset($_POST['submit_sections'])) {
    $batch_id = $conn->real_escape_string($_POST['batch_id']);
    $section_names = $_POST['section_names'] ?? [];
    $section_students = $_POST['num_students'] ?? [];
    $has_non_empty = false;

    // Validate input arrays
    if (count($section_names) !== count($section_students)) {
        $error = "Mismatch between section names and student counts.";
    } else {
        // Fetch existing sections
        $section_result = $conn->query("SELECT id, section_name, num_students FROM sections WHERE batch_id = '$batch_id' ORDER BY id");
        $existing_sections = $section_result ? $section_result->fetch_all(MYSQLI_ASSOC) : [];

        // Start transaction
        $conn->begin_transaction();
        try {
            $scheduled_sections = [];
            if (!empty($existing_sections)) {
                $stmt = $conn->prepare("SELECT section_id FROM section_schedules WHERE section_id = ?");
                foreach ($existing_sections as $section) {
                    $stmt->bind_param("i", $section['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $scheduled_sections[] = $section['id'];
                    }
                }
                $stmt->close();
            }

            $updates_made = false;
            $max_sections = max(count($existing_sections), count($section_names));
            for ($index = 0; $index < $max_sections; $index++) {
                $name = isset($section_names[$index]) ? trim($section_names[$index]) : '';
                $students = isset($section_students[$index]) ? (int)$section_students[$index] : 0;

                if ($students < 0) {
                    throw new Exception("Number of students must be non-negative for section $name.");
                }

                if (!empty($name) || $students >= 0) {
                    $has_non_empty = true;

                    if (isset($existing_sections[$index])) {
                        $section_id = $existing_sections[$index]['id'];
                        $existing_name = $existing_sections[$index]['section_name'];

                        if ($name !== $existing_name && !in_array($section_id, $scheduled_sections)) {
                            $update_name_stmt = $conn->prepare("UPDATE sections SET section_name = ? WHERE id = ?");
                            $update_name_stmt->bind_param("si", $name, $section_id);
                            if (!$update_name_stmt->execute()) {
                                throw new Exception("Error updating section name for ID $section_id: " . $update_name_stmt->error);
                            }
                            $update_name_stmt->close();
                            $updates_made = true;
                        }

                        $update_students_stmt = $conn->prepare("UPDATE sections SET num_students = ? WHERE id = ?");
                        $update_students_stmt->bind_param("ii", $students, $section_id);
                        if (!$update_students_stmt->execute()) {
                            throw new Exception("Error updating student count for section $name: " . $update_students_stmt->error);
                        }
                        $update_students_stmt->close();
                        $updates_made = true;
                    } else if (empty($scheduled_sections)) {
                        $insert_stmt = $conn->prepare("INSERT INTO sections (batch_id, section_name, num_students) VALUES (?, ?, ?)");
                        $insert_stmt->bind_param("isi", $batch_id, $name, $students);
                        if (!$insert_stmt->execute()) {
                            throw new Exception("Error adding section $name: " . $insert_stmt->error);
                        }
                        $insert_stmt->close();
                        $updates_made = true;
                    }
                }
            }

            if ($has_non_empty && $updates_made) {
                $conn->commit();
                $success = "Sections and student counts updated successfully!";
            } elseif (!$has_non_empty) {
                $conn->rollback();
                $error = "Please provide at least one non-empty section name.";
            } else {
                $conn->rollback();
                $error = "No changes made to sections or student counts.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Fetch batches and sections
$batches_query = "SELECT b.*, s.semester_name 
                  FROM batches b 
                  LEFT JOIN semesters s ON b.semester_id = s.semester_id 
                  WHERE b.dept_id='$department' 
                  ORDER BY b.batch_name, s.semester_name";
$batches_result = $conn->query($batches_query);
$batches = $batches_result ? $batches_result->fetch_all(MYSQLI_ASSOC) : [];
$sections = [];
foreach ($batches as $batch) {
    $section_result = $conn->query("SELECT * FROM sections WHERE batch_id='{$batch['id']}' ORDER BY id");
    $sections[$batch['id']] = $section_result ? $section_result->fetch_all(MYSQLI_ASSOC) : [];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Department Information</title>
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

        button {
            background: var(--gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            margin: 0.25rem;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        button:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--text);
            color: var(--card-bg);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0.9;
        }

        .edit-btn {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }

        .edit-btn:hover {
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
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
        <h1>Submit Department Information</h1>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <nav class="sidebar" id="sidebar">
</br>
        <a href="dept_head_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="submit_dept_info.php"><i class="fas fa-building"></i> Submit Dept Info</a>
        <a href="submit_course_instructor.php"><i class="fas fa-chalkboard-teacher"></i> Manage Courses & Instructors</a>
        <a href="manage_schedule.php"><i class="fas fa-calendar"></i> Manage Schedules</a>
        <a href="manage_feedback.php"><i class="fas fa-comment"></i> View Feedback</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <main class="main-content" id="main-content">
        <div class="card">
            <h2>Manage Batches and Sections</h2>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Batch Form -->
            <div class="card">
                <h3>Add New Batch</h3>
                <form method="post">
                    <div class="form-group">
                        <input type="text" name="batch_name" id="batch_name" placeholder=" " required>
                        <label for="batch_name">Batch Name</label>
                    </div>
                    <div class="form-group">
                        <input type="number" name="num_sections" id="num_sections" min="1" placeholder=" " required>
                        <label for="num_sections">Number of Sections</label>
                    </div>
                    <div class="form-group">
                        <select name="semester_id" id="semester_id" required>
                            <option value="">-- Select Semester --</option>
                            <?php foreach ($semesters as $semester): ?>
                                <option value="<?php echo $semester['semester_id']; ?>">
                                    <?php echo htmlspecialchars($semester['semester_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="semester_id">Semester</label>
                    </div>
                    <button type="submit" name="submit_batch" data-tooltip="Add Batch"><i class="fas fa-plus"></i> Add Batch</button>
                </form>
            </div>

            <!-- Batches and Sections -->
            <div class="card table-container">
                <h3>Batches and Sections</h3>
                <?php if (!empty($batches)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Batch Name</th>
                                <th>Semester</th>
                                <th>Sections</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($batch['semester_name'] ?: 'Not Assigned'); ?></td>
                                    <td><?php echo count($sections[$batch['id']] ?? []); ?> Section(s)</td>
                                    <td>
                                        <button class="edit-btn" data-tooltip="Edit Batch" onclick="openEditModal(
                                            <?php echo $batch['id']; ?>,
                                            '<?php echo htmlspecialchars($batch['batch_name']); ?>',
                                            <?php echo $batch['num_sections']; ?>,
                                            '<?php echo $batch['semester_id'] ?: ''; ?>'
                                        )"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="edit-btn" data-tooltip="Edit Sections" onclick="openSectionsModal(
                                            <?php echo $batch['id']; ?>,
                                            '<?php echo htmlspecialchars(json_encode($sections[$batch['id']] ?? [])); ?>'
                                        )"><i class="fas fa-list"></i> Sections</button>
                                        <button class="delete-btn" data-tooltip="Delete Batch" onclick="openDeleteModal(<?php echo $batch['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No batches added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit Batch Modal -->
    <div class="modal" id="editBatchModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">×</span>
            <h3>Edit Batch</h3>
            <form method="post" id="editBatchForm">
                <input type="hidden" name="batch_id" id="edit_batch_id">
                <div class="form-group">
                    <input type="text" name="batch_name" id="edit_batch_name" placeholder=" " required>
                    <label for="edit_batch_name">Batch Name</label>
                </div>
                <div class="form-group">
                    <input type="number" name="num_sections" id="edit_num_sections" min="1" placeholder=" " required>
                    <label for="edit_num_sections">Number of Sections</label>
                </div>
                <div class="form-group">
                    <select name="semester_id" id="edit_semester_id" required>
                        <option value="">-- Select Semester --</option>
                        <?php foreach ($semesters as $semester): ?>
                            <option value="<?php echo $semester['semester_id']; ?>">
                                <?php echo htmlspecialchars($semester['semester_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="edit_semester_id">Semester</label>
                </div>
                <button type="submit" name="edit_batch" data-tooltip="Update Batch"><i class="fas fa-save"></i> Update Batch</button>
                <button type="button" class="cancel-btn" data-tooltip="Cancel" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Sections Modal -->
    <div class="modal" id="editSectionsModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeSectionsModal()">×</span>
            <h3>Edit Sections</h3>
            <form method="post" id="editSectionsForm">
                <input type="hidden" name="batch_id" id="sections_batch_id">
                <div id="sectionsContainer"></div>
                <button type="submit" name="submit_sections" data-tooltip="Update Sections"><i class="fas fa-save"></i> Update Sections</button>
                <button type="button" class="cancel-btn" data-tooltip="Cancel" onclick="closeSectionsModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Batch Modal -->
    <div class="modal" id="deleteBatchModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteModal()">×</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this batch? This will also delete all associated sections.</p>
            <form method="post" id="deleteBatchForm">
                <input type="hidden" name="batch_id" id="delete_batch_id">
                <button type="submit" name="delete_batch" class="delete-btn" data-tooltip="Confirm Delete"><i class="fas fa-trash"></i> Delete</button>
                <button type="button" class="cancel-btn" data-tooltip="Cancel" onclick="closeDeleteModal()">Cancel</button>
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
        const batchData = <?php echo json_encode($batches); ?>;
        localStorage.setItem('batchData', JSON.stringify(batchData));

        // Load cached data if offline
        if (!navigator.onLine) {
            const cachedData = JSON.parse(localStorage.getItem('batchData') || '[]');
            if (cachedData.length) {
                const tableBody = document.querySelector('table tbody');
                if (tableBody) {
                    tableBody.innerHTML = '';
                    cachedData.forEach(batch => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${batch.batch_name}</td>
                            <td>${batch.semester_name || 'Not Assigned'}</td>
                            <td>Sections: ${batch.num_sections}</td>
                            <td>
                                <button class="edit-btn" disabled data-tooltip="Edit Batch"><i class="fas fa-edit"></i> Edit</button>
                                <button class="edit-btn" disabled data-tooltip="Edit Sections"><i class="fas fa-list"></i> Sections</button>
                                <button class="delete-btn" disabled data-tooltip="Delete Batch"><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                }
            }
        }

        // Edit Batch Modal
        function openEditModal(batchId, batchName, numSections, semesterId) {
            document.getElementById('edit_batch_id').value = batchId;
            document.getElementById('edit_batch_name').value = batchName;
            document.getElementById('edit_num_sections').value = numSections;
            document.getElementById('edit_semester_id').value = semesterId;
            document.getElementById('editBatchModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editBatchModal').style.display = 'none';
        }

        // Edit Sections Modal
        function openSectionsModal(batchId, sectionsJson) {
            document.getElementById('sections_batch_id').value = batchId;
            const sections = JSON.parse(sectionsJson);
            const container = document.getElementById('sectionsContainer');
            container.innerHTML = '';
            sections.forEach((section, index) => {
                container.innerHTML += `
                    <div class="form-group">
                        <input type="text" name="section_names[]" id="section_${batchId}_${index}" 
                               value="${section.section_name}" placeholder=" " required>
                        <label for="section_${batchId}_${index}">Section ${index + 1} Name</label>
                    </div>
                    <div class="form-group">
                        <input type="number" name="num_students[]" id="students_${batchId}_${index}" 
                               value="${section.num_students}" min="0" placeholder=" " required>
                        <label for="students_${batchId}_${index}">Number of Students</label>
                    </div>
                `;
            });
            document.getElementById('editSectionsModal').style.display = 'flex';
        }

        function closeSectionsModal() {
            document.getElementById('editSectionsModal').style.display = 'none';
        }

        // Delete Batch Modal
        function openDeleteModal(batchId) {
            document.getElementById('delete_batch_id').value = batchId;
            document.getElementById('deleteBatchModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteBatchModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editBatchModal');
            const sectionsModal = document.getElementById('editSectionsModal');
            const deleteModal = document.getElementById('deleteBatchModal');
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === sectionsModal) {
                closeSectionsModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };

        // Redirect with delay if success
        <?php if (isset($success)): ?>
            setTimeout(() => {
                window.location.href = 'submit_dept_info.php';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>