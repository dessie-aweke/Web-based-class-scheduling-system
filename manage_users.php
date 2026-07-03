<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'database/db_connection.php';

// Fetch departments for the department dropdown
$departments_result = $conn->query("SELECT dept_id, dept_name FROM departments");
$departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];

// Function to validate input data
function validateInput($data, $field, $conn) {
    $errors = [];

    switch ($field) {
        case 'employee_id':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Employee ID is required.";
            } elseif (!preg_match('/^[a-zA-Z0-9]{1,50}$/', $data)) {
                $errors[] = "Employee ID must be alphanumeric and up to 50 characters.";
            } else {
                // Check uniqueness
                $query = "SELECT employee_id FROM users WHERE employee_id = '" . $conn->real_escape_string($data) . "'";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                    $errors[] = "Employee ID already exists.";
                }
            }
            break;

        case 'username':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Username is required.";
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $data)) {
                $errors[] = "Username must be 3–30 characters, alphanumeric with underscores.";
            } else {
                // Check uniqueness
                $query = "SELECT username FROM users WHERE username = '" . $conn->real_escape_string($data) . "'";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                    $errors[] = "Username already exists.";
                }
            }
            break;

        case 'email':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Email is required.";
            } elseif (!filter_var($data, FILTER_VALIDATE_EMAIL) || strlen($data) > 100) {
                $errors[] = "Invalid email format or email exceeds 100 characters.";
            }
            break;

        case 'full_name':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Full Name is required.";
            } elseif (!preg_match("/^[a-zA-Z\s\-\']{1,100}$/", $data)) {
                $errors[] = "Full Name must be 1–100 characters, letters, spaces, hyphens, or apostrophes.";
            }
            break;

        case 'password':
            if (empty($data)) {
                $errors[] = "Password is required.";
            } elseif (strlen($data) < 6) {
                $errors[] = "Password must be at least 6 characters.";
            }
            break;

        case 'password_optional':
            if (!empty($data) && strlen($data) < 6) {
                $errors[] = "Password must be at least 6 characters if provided.";
            }
            break;

        case 'role':
            if (!in_array($data, ['dept_head', 'admin'])) {
                $errors[] = "Invalid role selected.";
            }
            break;

        case 'department_selection_type':
            if (!in_array($data, ['existing', 'new'])) {
                $errors[] = "Invalid department selection type.";
            }
            break;

        case 'new_department_name':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "New department name is required.";
            } elseif (!preg_match('/^[a-zA-Z\s\-]{3,50}$/', $data)) {
                $errors[] = "Department name must be 3–50 characters, letters, spaces, or hyphens.";
            } else {
                // Check uniqueness
                $query = "SELECT dept_id FROM departments WHERE dept_name = '" . $conn->real_escape_string($data) . "'";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                    $errors[] = "Department name already exists.";
                }
            }
            break;
    }

    return $errors;
}

// Handle user creation
if (isset($_POST['create_user'])) {
    $employee_id = $_POST["employee_id"];
    $username = $_POST["username"];
    $email = $_POST["email"];
    $full_name = $_POST["full_name"];
    $role = $_POST["role"];
    $password = $_POST["password"];
    $department_selection_type = $_POST["department_selection_type"];
    $department_id = null;
    $dept_name = null;

    // Collect validation errors
    $validation_errors = [];
    $validation_errors = array_merge($validation_errors, validateInput($employee_id, 'employee_id', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($username, 'username', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($email, 'email', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($full_name, 'full_name', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($password, 'password', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($role, 'role', $conn));

    if ($role === "dept_head") {
        $validation_errors = array_merge($validation_errors, validateInput($department_selection_type, 'department_selection_type', $conn));
        if ($department_selection_type === "existing") {
            $department_id = $conn->real_escape_string($_POST["department_id"]);
            $dept_check_query = "SELECT dept_id, dept_name FROM departments WHERE dept_id = '$department_id'";
            $dept_check_result = $conn->query($dept_check_query);
            if ($dept_check_result->num_rows === 0) {
                $validation_errors[] = "Invalid department selected. Please choose a valid department.";
            } else {
                $dept_row = $dept_check_result->fetch_assoc();
                $dept_name = $dept_row['dept_name'];
            }
        } elseif ($department_selection_type === "new") {
            $new_dept_name = $_POST["new_department_name"];
            $validation_errors = array_merge($validation_errors, validateInput($new_dept_name, 'new_department_name', $conn));
            if (empty($validation_errors)) {
                $new_dept_name = $conn->real_escape_string(trim($new_dept_name));
                $insert_dept_query = "INSERT INTO departments (dept_name, head_id) VALUES ('$new_dept_name', NULL)";
                if ($conn->query($insert_dept_query) === TRUE) {
                    $department_id = $conn->insert_id;
                    $dept_name = $new_dept_name;
                } else {
                    $validation_errors[] = "Error creating new department: " . $conn->error;
                }
            }
        }

        if (empty($validation_errors) && empty($department_id)) {
            $validation_errors[] = "A department must be selected or created for the Department Head.";
        }
    } else {
        $department_id = null;
        $dept_name = null;
    }

    if (empty($validation_errors)) {
        // Check for duplicate email
        $email_check_query = "SELECT email FROM users WHERE email = '" . $conn->real_escape_string($email) . "'";
        $email_check_result = $conn->query($email_check_query);
        if ($email_check_result->num_rows > 0) {
            $validation_errors[] = "An account with this email already exists. Please use a different email.";
        } else {
            // Insert the new user
            $password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (employee_id, username, email, full_name, password_hash, role, department_id, department, is_active) 
                      VALUES ('" . $conn->real_escape_string($employee_id) . "', '" . $conn->real_escape_string($username) . "', '" . $conn->real_escape_string($email) . "', '" . $conn->real_escape_string($full_name) . "', '$password', '$role', " . ($department_id ? "'$department_id'" : "NULL") . ", " . ($dept_name ? "'$dept_name'" : "NULL") . ", 1)";
            if ($conn->query($query) === TRUE) {
                $success = "$role account created successfully!";
                // Refresh departments list
                $departments_result = $conn->query("SELECT dept_id, dept_name FROM departments");
                $departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];
            } else {
                $validation_errors[] = "Error creating account: " . $conn->error;
            }
        }
    }

    if (!empty($validation_errors)) {
        $error = implode("<br>", $validation_errors);
    }
}

// Handle user edit
if (isset($_POST['edit_user'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $employee_id = $_POST["employee_id"];
    $username = $_POST["username"];
    $email = $_POST["email"];
    $full_name = $_POST["full_name"];
    $role = $_POST["role"];
    $department_selection_type = $_POST["department_selection_type"];
    $password = $_POST["password"];
    $department_id = null;
    $dept_name = null;

    // Collect validation errors
    $validation_errors = [];
    $validation_errors = array_merge($validation_errors, validateInput($employee_id, 'employee_id', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($username, 'username', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($email, 'email', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($full_name, 'full_name', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($password, 'password_optional', $conn));
    $validation_errors = array_merge($validation_errors, validateInput($role, 'role', $conn));

    // Adjust uniqueness checks for edit (exclude current user)
    if (empty($validation_errors)) {
        $query = "SELECT employee_id FROM users WHERE employee_id = '" . $conn->real_escape_string($employee_id) . "' AND user_id != '$user_id'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $validation_errors[] = "Employee ID already exists.";
        }

        $query = "SELECT username FROM users WHERE username = '" . $conn->real_escape_string($username) . "' AND user_id != '$user_id'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $validation_errors[] = "Username already exists.";
        }
    }

    if ($role === "dept_head") {
        $validation_errors = array_merge($validation_errors, validateInput($department_selection_type, 'department_selection_type', $conn));
        if ($department_selection_type === "existing") {
            $department_id = $conn->real_escape_string($_POST["department_id"]);
            $dept_check_query = "SELECT dept_id, dept_name FROM departments WHERE dept_id = '$department_id'";
            $dept_check_result = $conn->query($dept_check_query);
            if ($dept_check_result->num_rows === 0) {
                $validation_errors[] = "Invalid department selected. Please choose a valid department.";
            } else {
                $dept_row = $dept_check_result->fetch_assoc();
                $dept_name = $dept_row['dept_name'];
            }
        } elseif ($department_selection_type === "new") {
            $new_dept_name = $_POST["new_department_name"];
            $validation_errors = array_merge($validation_errors, validateInput($new_dept_name, 'new_department_name', $conn));
            if (empty($validation_errors)) {
                $new_dept_name = $conn->real_escape_string(trim($new_dept_name));
                $insert_dept_query = "INSERT INTO departments (dept_name, head_id) VALUES ('$new_dept_name', NULL)";
                if ($conn->query($insert_dept_query) === TRUE) {
                    $department_id = $conn->insert_id;
                    $dept_name = $new_dept_name;
                } else {
                    $validation_errors[] = "Error creating new department: " . $conn->error;
                }
            }
        }

        if (empty($validation_errors) && empty($department_id)) {
            $validation_errors[] = "A department must be selected or created for the Department Head.";
        }
    } else {
        $department_id = null;
        $dept_name = null;
    }

    if (empty($validation_errors)) {
        // Check for duplicate email (exclude current user)
        $email_check_query = "SELECT email FROM users WHERE email = '" . $conn->real_escape_string($email) . "' AND user_id != '$user_id'";
        $email_check_result = $conn->query($email_check_query);
        if ($email_check_result->num_rows > 0) {
            $validation_errors[] = "An account with this email already exists. Please use a different email.";
        } else {
            $update_query = "UPDATE users SET 
                             employee_id = '" . $conn->real_escape_string($employee_id) . "',
                             username = '" . $conn->real_escape_string($username) . "', 
                             email = '" . $conn->real_escape_string($email) . "', 
                             full_name = '" . $conn->real_escape_string($full_name) . "', 
                             role = '$role',
                             department_id = " . ($department_id ? "'$department_id'" : "NULL") . ",
                             department = " . ($dept_name ? "'$dept_name'" : "NULL");
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_query .= ", password_hash = '$password_hash'";
            }
            $update_query .= " WHERE user_id = '$user_id'";

            if ($conn->query($update_query) === TRUE) {
                $success = "User account updated successfully!";
                // Refresh departments list
                $departments_result = $conn->query("SELECT dept_id, dept_name FROM departments");
                $departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];
            } else {
                $validation_errors[] = "Error updating account: " . $conn->error;
            }
        }
    }

    if (!empty($validation_errors)) {
        $error = implode("<br>", $validation_errors);
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $admin_id = $_SESSION["user_id"];
    $username_query = $conn->query("SELECT username FROM users WHERE user_id = '$user_id'");
    $username = $username_query->fetch_assoc()['username'];

    $sql = "DELETE FROM users WHERE user_id = '$user_id'";
    if ($conn->query($sql) === TRUE) {
        $conn->query("INSERT INTO user_deletion_logs (user_id, username, deleted_by) VALUES ('$user_id', '$username', '$admin_id')");
        $success = "User account deleted successfully!";
    } else {
        $error = "Cannot delete this user account because they are assigned as a Department Head. Please reassign or remove their Department Head role before deleting.";
    }
}

// Handle user activation/deactivation
if (isset($_POST['toggle_active'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);
    $is_active = $conn->real_escape_string($_POST['is_active']);
    $new_status = ($is_active == '1') ? '0' : '1';
    $sql = "UPDATE users SET is_active = '$new_status' WHERE user_id = '$user_id'";
    if ($conn->query($sql) === TRUE) {
        $success = "User account status updated successfully!";
    } else {
        $error = "Error updating account status: " . $conn->error;
    }
}

// Fetch all users (dept_head and admin)
$users_result = $conn->query("SELECT u.*, d.dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.dept_id WHERE u.role IN ('dept_head', 'admin')");
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
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

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dcdcdc;
            border-radius: 5px;
            background: var(--card-bg);
            color: var(--text);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus,
        select:focus {
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
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .delete-btn {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }

        .delete-btn:hover {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .toggle-btn {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .toggle-btn:hover {
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.4);
        }

        .edit-btn {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }

        .edit-btn:hover {
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
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

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0.25rem;
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
    <!-- Header with sticky navigation and theme toggle -->
    <header class="header">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <img src="image/markoslogo.jpg" alt="Markos University Logo" class="logo" onerror="this.src='https://via.placeholder.com/40';">
        <h1>Manage Users</h1>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <!-- Collapsible sidebar -->
    <nav class="sidebar" id="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_rooms.php"><i class="fas fa-building"></i> Manage Buildings & Rooms</a>
        <a href="register_department.php"><i class="fas fa-building"></i> Manage Departments</a>
        <a href="manage_feedback.php"><i class="fas fa-comment"></i> Manage Feedback</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <!-- Main content area -->
    <main class="main-content" id="main-content">
        <div class="card">
            <h2>Manage Users</h2>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Create New User -->
            <div class="card">
                <h3>Create New User</h3>
                <form method="post" id="createUserForm">
                    <div class="form-group">
                        <input type="text" name="employee_id" id="employee_id" required placeholder=" ">
                        <label for="employee_id">Employee ID</label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="username" id="username" required placeholder=" ">
                        <label for="username">Username</label>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" id="email" required placeholder=" ">
                        <label for="email">Email</label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="full_name" id="full_name" required placeholder=" ">
                        <label for="full_name">Full Name</label>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" id="password" required placeholder=" ">
                        <label for="password">Password</label>
                    </div>
                    <div class="form-group">
                        <select name="role" id="role" required>
                            <option value="">-- Select Role --</option>
                            <option value="dept_head">Department Head</option>
                            <option value="admin">Admin</option>
                        </select>
                        <label for="role">Role</label>
                    </div>
                    <div id="department_selection" style="display: none;">
                        <div class="form-group">
                            <select name="department_selection_type" id="department_selection_type">
                                <option value="existing">Select Existing Department</option>
                                <option value="new">Create New Department</option>
                            </select>
                            <label for="department_selection_type">Department Selection Type</label>
                        </div>
                        <div id="existing_department">
                            <div class="form-group">
                                <select name="department_id" id="department_id">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>">
                                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="department_id">Department (Required for Department Head)</label>
                            </div>
                        </div>
                        <div id="new_department" style="display: none;">
                            <div class="form-group">
                                <input type="text" name="new_department_name" id="new_department_name" placeholder=" ">
                                <label for="new_department_name">New Department Name</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="create_user" id="createUserButton" disabled><i class="fas fa-plus"></i> Create Account</button>
                </form>
            </div>

            <!-- Users List -->
            <div class="card table-container">
                <h3>Users List</h3>
                <?php if (!empty($users)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Employee ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo $user['employee_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['dept_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td>
                                    <td>
                                        <button class="action-btn edit-btn" 
                                                onclick="openEditModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['employee_id']); ?>', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo $user['role']; ?>', '<?php echo $user['department_id'] ?: ''; ?>', '<?php echo htmlspecialchars($user['dept_name'] ?: ''); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="action-btn toggle-btn" 
                                                onclick="openToggleModal(<?php echo $user['user_id']; ?>, '<?php echo $user['is_active']; ?>')">
                                            <i class="fas fa-power-off"></i> <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <button class="action-btn delete-btn" 
                                                onclick="openDeleteModal(<?php echo $user['user_id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h3>Edit User</h3>
            <form method="post" id="editUserForm">
                <input type="hidden" name="user_id" id="modal_user_id">
                <div class="form-group">
                    <input type="text" name="employee_id" id="modal_employee_id" required placeholder=" ">
                    <label for="modal_employee_id">Employee ID</label>
                </div>
                <div class="form-group">
                    <input type="text" name="username" id="modal_username" required placeholder=" ">
                    <label for="modal_username">Username</label>
                </div>
                <div class="form-group">
                    <input type="email" name="email" id="modal_email" required placeholder=" ">
                    <label for="modal_email">Email</label>
                </div>
                <div class="form-group">
                    <input type="text" name="full_name" id="modal_full_name" required placeholder=" ">
                    <label for="modal_full_name">Full Name</label>
                </div>
                <div class="form-group">
                    <input type="password" name="password" id="modal_password" placeholder="Leave blank to keep current password">
                    <label for="modal_password">New Password (optional)</label>
                </div>
                <div class="form-group">
                    <select name="role" id="modal_role" required>
                        <option value="">-- Select Role --</option>
                        <option value="dept_head">Department Head</option>
                        <option value="admin">Admin</option>
                    </select>
                    <label for="modal_role">Role</label>
                </div>
                <div id="modal_department_selection" style="display: none;">
                    <div class="form-group">
                        <select name="department_selection_type" id="modal_department_selection_type">
                            <option value="existing">Select Existing Department</option>
                            <option value="new">Create New Department</option>
                        </select>
                        <label for="modal_department_selection_type">Department Selection Type</label>
                    </div>
                    <div id="modal_existing_department">
                        <div class="form-group">
                            <select name="department_id" id="modal_department_id">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>">
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="modal_department_id">Department (Required for Department Head)</label>
                        </div>
                    </div>
                    <div id="modal_new_department" style="display: none;">
                        <div class="form-group">
                            <input type="text" name="new_department_name" id="modal_new_department_name" placeholder=" ">
                            <label for="modal_new_department_name">New Department Name</label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="edit_user"><i class="fas fa-edit"></i> Update Account</button>
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Toggle Active Modal -->
    <div class="modal" id="toggleActiveModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeToggleModal()">&times;</span>
            <h3>Toggle User Status</h3>
            <p>Are you sure you want to <span id="toggle_action"></span> this user?</p>
            <form method="post" id="toggleActiveForm">
                <input type="hidden" name="user_id" id="toggle_user_id">
                <input type="hidden" name="is_active" id="toggle_is_active">
                <button type="submit" name="toggle_active" class="toggle-btn">Confirm</button>
                <button type="button" class="cancel-btn" onclick="closeToggleModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal" id="deleteUserModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this user?</p>
            <form method="post" id="deleteUserForm">
                <input type="hidden" name="user_id" id="delete_user_id">
                <button type="submit" name="delete_user" class="delete-btn">Delete</button>
                <button type="button" class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Offline message -->
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

        // Cache users data
        const usersData = <?php echo json_encode($users); ?>;
        localStorage.setItem('usersData', JSON.stringify(usersData));

        // Load cached data if offline
        if (!navigator.onLine) {
            const cachedUsers = JSON.parse(localStorage.getItem('usersData') || '[]');
            if (cachedUsers.length) {
                const usersTableBody = document.querySelector('.table-container tbody');
                if (usersTableBody) {
                    usersTableBody.innerHTML = '';
                    cachedUsers.forEach(user => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${user.user_id}</td>
                            <td>${user.employee_id}</td>
                            <td>${user.username}</td>
                            <td>${user.email}</td>
                            <td>${user.full_name}</td>
                            <td>${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</td>
                            <td>${user.dept_name || 'N/A'}</td>
                            <td>${user.is_active ? 'Active' : 'Inactive'}</td>
                            <td>
                                <button class="action-btn edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                                <button class="action-btn toggle-btn" disabled><i class="fas fa-power-off"></i> ${user.is_active ? 'Deactivate' : 'Activate'}</button>
                                <button class="action-btn delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                        usersTableBody.appendChild(row);
                    });
                }
            }
        }

        // Edit User Modal
        function openEditModal(userId, employeeId, username, email, fullName, role, departmentId, deptName) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_employee_id').value = employeeId;
            document.getElementById('modal_username').value = username;
            document.getElementById('modal_email').value = email;
            document.getElementById('modal_full_name').value = fullName;
            document.getElementById('modal_role').value = role;
            const modalDeptSelectionType = document.getElementById('modal_department_selection_type');
            const modalExistingDept = document.getElementById('modal_existing_department');
            const modalNewDept = document.getElementById('modal_new_department');
            const modalDeptSelect = document.getElementById('modal_department_id');
            const modalNewDeptInput = document.getElementById('modal_new_department_name');

            if (role === 'dept_head' && departmentId && deptName) {
                modalDeptSelectionType.value = 'existing';
                modalExistingDept.style.display = 'block';
                modalNewDept.style.display = 'none';
                modalDeptSelect.value = departmentId;
                modalNewDeptInput.value = '';
            } else {
                modalDeptSelectionType.value = 'existing';
                modalDeptSelect.value = '';
                modalNewDeptInput.value = '';
            }

            updateDepartmentSelectVisibility('modal_role', 'modal_department_selection');
            document.getElementById('editUserModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Toggle Active Modal
        function openToggleModal(userId, isActive) {
            document.getElementById('toggle_user_id').value = userId;
            document.getElementById('toggle_is_active').value = isActive;
            document.getElementById('toggle_action').textContent = isActive === '1' ? 'deactivate' : 'activate';
            document.getElementById('toggleActiveModal').style.display = 'flex';
        }

        function closeToggleModal() {
            document.getElementById('toggleActiveModal').style.display = 'none';
        }

        // Delete User Modal
        function openDeleteModal(userId) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editUserModal');
            const toggleModal = document.getElementById('toggleActiveModal');
            const deleteModal = document.getElementById('deleteUserModal');
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === toggleModal) {
                closeToggleModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };

        // Show/hide department section based on role
        function updateDepartmentSelectVisibility(roleSelectId, deptSectionId) {
            const roleSelect = document.getElementById(roleSelectId);
            const deptSection = document.getElementById(deptSectionId);
            const deptSelectionType = document.getElementById(roleSelectId === 'role' ? 'department_selection_type' : 'modal_department_selection_type');
            const existingDept = document.getElementById(roleSelectId === 'role' ? 'existing_department' : 'modal_existing_department');
            const newDept = document.getElementById(roleSelectId === 'role' ? 'new_department' : 'modal_new_department');

            if (roleSelect.value === 'dept_head') {
                deptSection.style.display = 'block';
                deptSelectionType.value = 'existing';
                existingDept.style.display = 'block';
                newDept.style.display = 'none';
            } else {
                deptSection.style.display = 'none';
                deptSelectionType.value = 'existing';
                existingDept.style.display = 'block';
                newDept.style.display = 'none';
            }
        }

        // Toggle between existing and new department inputs
        function toggleDepartmentInput(selectionTypeId, existingDeptId, newDeptId, deptSelectId, newDeptInputId, createButtonId) {
            const selectionType = document.getElementById(selectionTypeId);
            const existingDept = document.getElementById(existingDeptId);
            const newDept = document.getElementById(newDeptId);
            const deptSelect = document.getElementById(deptSelectId);
            const newDeptInput = document.getElementById(newDeptInputId);
            const createButton = createButtonId ? document.getElementById(createButtonId) : null;
            const roleSelect = document.getElementById(createButtonId ? 'role' : 'modal_role');

            if (selectionType.value === 'existing') {
                existingDept.style.display = 'block';
                newDept.style.display = 'none';
                newDeptInput.required = false;
                deptSelect.required = true;
            } else {
                existingDept.style.display = 'none';
                newDept.style.display = 'block';
                deptSelect.required = false;
                newDeptInput.required = true;
                deptSelect.value = '';
            }

            if (createButton) {
                createButton.disabled = (roleSelect.value === "") || (roleSelect.value === 'dept_head' && selectionType.value === 'existing' && deptSelect.value === "") || (roleSelect.value === 'dept_head' && selectionType.value === 'new' && newDeptInput.value.trim() === "");
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const createForm = document.querySelector("#createUserForm");
            const roleSelect = document.querySelector("#role");
            const departmentSelectionType = document.querySelector("#department_selection_type");
            const departmentSelect = document.querySelector("#department_id");
            const newDepartmentInput = document.querySelector("#new_department_name");
            const createButton = document.querySelector("#createUserButton");
            const passwordInput = document.querySelector("#password");

            // Initialize department visibility
            updateDepartmentSelectVisibility('role', 'department_selection');

            // Role change handler
            roleSelect.addEventListener("change", function() {
                updateDepartmentSelectVisibility('role', 'department_selection');
                toggleDepartmentInput('department_selection_type', 'existing_department', 'new_department', 'department_id', 'new_department_name', 'createUserButton');
                createButton.disabled = (this.value === "") || (this.value === 'dept_head' && departmentSelectionType.value === 'existing' && departmentSelect.value === "");
            });

            // Department selection type change
            departmentSelectionType.addEventListener("change", function() {
                toggleDepartmentInput('department_selection_type', 'existing_department', 'new_department', 'department_id', 'new_department_name', 'createUserButton');
            });

            // Department select change
            departmentSelect.addEventListener("change", function() {
                createButton.disabled = (roleSelect.value === "") || (roleSelect.value === 'dept_head' && departmentSelectionType.value === 'existing' && this.value === "");
            });

            // New department input change
            newDepartmentInput.addEventListener("input", function() {
                createButton.disabled = (roleSelect.value === "") || (roleSelect.value === 'dept_head' && departmentSelectionType.value === 'new' && this.value.trim() === "");
            });

            // Form validation
            createForm.addEventListener("submit", function(event) {
                const password = passwordInput.value;
                if (password.length < 6) {
                    alert("Password must be at least 6 characters long!");
                    event.preventDefault();
                }
            });

            // Edit modal handlers
            const modalRoleSelect = document.getElementById('modal_role');
            const modalDeptSelectionType = document.getElementById('modal_department_selection_type');
            modalRoleSelect.addEventListener("change", function() {
                updateDepartmentSelectVisibility('modal_role', 'modal_department_selection');
                toggleDepartmentInput('modal_department_selection_type', 'modal_existing_department', 'modal_new_department', 'modal_department_id', 'modal_new_department_name');
            });

            modalDeptSelectionType.addEventListener("change", function() {
                toggleDepartmentInput('modal_department_selection_type', 'modal_existing_department', 'modal_new_department', 'modal_department_id', 'modal_new_department_name');
            });
        });

        // Redirect with delay if success
        <?php if (isset($success)): ?>
            setTimeout(() => {
                window.location.href = 'manage_users.php';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>