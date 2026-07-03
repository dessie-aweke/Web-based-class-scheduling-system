<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in and has the admin role
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'database/db_connection.php';

// Fetch existing department heads for the dropdown
$heads_result = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'dept_head' AND (department_id IS NULL OR department_id NOT IN (SELECT dept_id FROM departments WHERE head_id IS NOT NULL))");
$department_heads = $heads_result ? $heads_result->fetch_all(MYSQLI_ASSOC) : [];

// Handle department submission
$success = '';
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit_dept'])) {
        $dept_id = $conn->real_escape_string($_POST['dept_id']);
        $dept_name = $conn->real_escape_string($_POST['dept_name']);
        $dept_code = $conn->real_escape_string($_POST['dept_code']);
        $head_id = $conn->real_escape_string($_POST['head_id']);
        
        // Fetch the current head_id to update the users table
        $current_head_query = $conn->query("SELECT head_id FROM departments WHERE dept_id = '$dept_id'");
        $current_head_id = $current_head_query->fetch_assoc()['head_id'];

        $sql = "UPDATE departments SET dept_name='$dept_name', dept_code='$dept_code', head_id='$head_id' WHERE dept_id='$dept_id'";
        if ($conn->query($sql)) {
            // Update department name in users table for associated dept_head
            $sql_update_user = "UPDATE users SET department='$dept_name', department_id='$dept_id' WHERE user_id='$head_id'";
            $conn->query($sql_update_user);
            // Clear department_id for the previous head if changed
            if ($current_head_id && $current_head_id != $head_id) {
                $conn->query("UPDATE users SET department_id = NULL WHERE user_id = '$current_head_id'");
            }
            $success = "Department updated successfully!";
        } else {
            $error = "Error updating department: An unexpected issue occurred.";
        }
    } elseif (isset($_POST['delete_dept'])) {
        $dept_id = $conn->real_escape_string($_POST['dept_id']);
        
        // Step 1: Clear department_id for ALL users associated with this department
        $conn->query("UPDATE users SET department_id = NULL, department = NULL WHERE department_id = '$dept_id'");
        
        // Step 2: Delete related sections and batches
        $conn->query("DELETE FROM sections WHERE batch_id IN (SELECT id FROM batches WHERE dept_id='$dept_id')");
        $conn->query("DELETE FROM batches WHERE dept_id='$dept_id'");
        
        // Step 3: Delete the department
        $sql = "DELETE FROM departments WHERE dept_id='$dept_id'";
        if ($conn->query($sql)) {
            $success = "Department deleted successfully!";
        } else {
            $error = "Cannot delete this department because it is linked to existing batches. Please delete all associated batches first before trying again.";
        }
    }
}

// Fetch all departments for display with their head names
$departments_result = $conn->query("SELECT d.*, u.full_name AS head_name FROM departments d LEFT JOIN users u ON d.head_id = u.user_id");
$departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments</title>
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
        <h1>Manage Departments</h1>
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
            <h2>Manage Departments</h2>
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Departments Table -->
            <div class="card table-container">
                <h3>Existing Departments</h3>
                <?php if (!empty($departments)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Department Code</th>
                                <th>Department Head</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['dept_code']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['head_name'] ?: 'Not Assigned'); ?></td>
                                    <td>
                                        <button class="action-btn edit-btn" onclick="openEditModal(
                                            '<?php echo $dept['dept_id']; ?>',
                                            '<?php echo htmlspecialchars($dept['dept_name']); ?>',
                                            '<?php echo htmlspecialchars($dept['dept_code']); ?>',
                                            '<?php echo $dept['head_id'] ?: ''; ?>'
                                        )"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="action-btn delete-btn" onclick="openDeleteModal('<?php echo $dept['dept_id']; ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No departments registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit Department Modal -->
    <div id="editDeptModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">×</span>
            <h3>Edit Department</h3>
            <form method="post" id="editDeptForm">
                <input type="hidden" name="dept_id" id="modal_dept_id">
                <div class="form-group">
                    <input type="text" name="dept_name" id="modal_dept_name" required placeholder=" ">
                    <label for="modal_dept_name">Department Name</label>
                </div>
                <div class="form-group">
                    <input type="text" name="dept_code" id="modal_dept_code" required placeholder=" ">
                    <label for="modal_dept_code">Department Code</label>
                </div>
                <div class="form-group">
                    <select name="head_id" id="modal_head_id" required>
                        <option value="">-- Select Department Head --</option>
                        <?php foreach ($department_heads as $head): ?>
                            <option value="<?php echo $head['user_id']; ?>">
                                <?php echo htmlspecialchars($head['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="modal_head_id">Assign Department Head</label>
                </div>
                <button type="submit" name="edit_dept"><i class="fas fa-edit"></i> Update Department</button>
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Department Modal -->
    <div id="deleteDeptModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteModal()">×</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this department? This will also delete all associated batches and sections.</p>
            <form method="post" id="deleteDeptForm">
                <input type="hidden" name="dept_id" id="delete_dept_id">
                <button type="submit" name="delete_dept" class="delete-btn">Delete</button>
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

        // Cache departments data
        const departmentsData = <?php echo json_encode($departments); ?>;
        localStorage.setItem('departmentsData', JSON.stringify(departmentsData));

        // Load cached data if offline
        if (!navigator.onLine) {
            const cachedDepartments = JSON.parse(localStorage.getItem('departmentsData') || '[]');
            if (cachedDepartments.length) {
                const deptTableBody = document.querySelector('.table-container tbody');
                if (deptTableBody) {
                    deptTableBody.innerHTML = '';
                    cachedDepartments.forEach(dept => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${dept.dept_name}</td>
                            <td>${dept.dept_code}</td>
                            <td>${dept.head_name || 'Not Assigned'}</td>
                            <td>
                                <button class="action-btn edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                                <button class="action-btn delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                        deptTableBody.appendChild(row);
                    });
                }
            }
        }

        // Edit Department Modal
        function openEditModal(deptId, deptName, deptCode, headId) {
            document.getElementById('modal_dept_id').value = deptId;
            document.getElementById('modal_dept_name').value = deptName;
            document.getElementById('modal_dept_code').value = deptCode;
            document.getElementById('modal_head_id').value = headId;
            document.getElementById('editDeptModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editDeptModal').style.display = 'none';
        }

        // Delete Department Modal
        function openDeleteModal(deptId) {
            document.getElementById('delete_dept_id').value = deptId;
            document.getElementById('deleteDeptModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteDeptModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editDeptModal');
            const deleteModal = document.getElementById('deleteDeptModal');
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };

        // Redirect with delay if success
        <?php if ($success): ?>
            setTimeout(() => {
                window.location.href = 'register_department.php';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>