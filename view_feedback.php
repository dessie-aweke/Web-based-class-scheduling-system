<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in and is an admin or department head
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["admin", "department_head"])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'database/db_connection.php';

// Handle feedback deletion
if (isset($_POST['delete_feedback'])) {
    $feedback_id = $conn->real_escape_string($_POST['feedback_id']);
    $sql = "DELETE FROM feedback WHERE feedback_id = '$feedback_id'";
    if ($conn->query($sql) === TRUE) {
        $success = "Feedback deleted successfully!";
    } else {
        $error = "Error deleting feedback: " . $conn->error;
    }
}

// Handle marking feedback as read
if (isset($_POST['mark_read'])) {
    $feedback_id = $conn->real_escape_string($_POST['feedback_id']);
    $sql = "UPDATE feedback SET is_read = TRUE WHERE feedback_id = '$feedback_id'";
    if ($conn->query($sql) === TRUE) {
        $success = "Feedback marked as read!";
    } else {
        $error = "Error updating feedback: " . $conn->error;
    }
}

// Filter and sort feedback
$role_filter = isset($_GET['role']) ? $conn->real_escape_string($_GET['role']) : 'all';
$where_clause = $role_filter === 'all' ? '' : "WHERE role = '$role_filter'";
$sql = "SELECT feedback_id, role, name, user_id, feedback_message, 
               COALESCE(submitted_at, 'N/A') AS submitted_at, 
               COALESCE(is_read, FALSE) AS is_read 
        FROM feedback 
        $where_clause 
        ORDER BY COALESCE(submitted_at, NOW()) DESC";
$feedback_result = $conn->query($sql);
$feedbacks = $feedback_result ? $feedback_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --secondary: #93c5fd;
            --background: url('image/admin.jpg');
            --card-bg: rgba(255, 255, 255, 0.9);
            --text: #333;
            --sidebar-bg: #1e3a8a;
            --gradient: linear-gradient(135deg, #3b82f6, #93c5fd);
            --error: #ef4444;
            --warning: #f59e0b;
            --success: #22c55e;
        }
        [data-theme="dark"] {
            --background: url('image/admin.jpg');
            --card-bg: rgba(31, 41, 55, 0.9);
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
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: var(--text);
            min-height: 100vh;
            position: relative;
            transition: background 0.3s, color 0.3s;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: -1;
        }
        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://www.transparenttextures.com/patterns/white-diamond.png');
            opacity: 0.05;
            z-index: -2;
        }
        .header {
            background: linear-gradient(to right, #2dd4bf, #1e40af);
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
            color: var(--success);
            background: rgba(34, 197, 94, 0.1);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .error {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .form-group {
            position: relative;
            margin: 1rem 0;
        }
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--primary);
            border-radius: 5px;
            background: var(--card-bg);
            color: var(--text);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 5px rgba(147, 197, 253, 0.3);
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
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
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
        .delete-btn {
            background: linear-gradient(45deg, #ef4444, #dc2626);
        }
        .delete-btn:hover {
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }
        .read-btn {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }
        .read-btn:hover {
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
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
        tr.read {
            background: rgba(0, 0, 0, 0.05);
        }
        tr:hover {
            background: rgba(59, 130, 246, 0.1);
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
            max-width: 400px;
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
        <h1>Manage Feedback</h1>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <nav class="sidebar" id="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_rooms.php"><i class="fas fa-building"></i> Manage Buildings & Rooms</a>
        <a href="set_academic_dates.php"><i class="fas fa-calendar-alt"></i> Academic Dates</a>
        <a href="register_department.php"><i class="fas fa-building"></i> Manage Departments</a>
        <a href="manage_feedback.php"><i class="fas fa-comment"></i> Manage Feedback</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <main class="main-content" id="main-content">
        <div class="card">
            <h2>Manage Feedback</h2>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>Filter Feedback</h3>
                <form method="get" id="filterForm">
                    <div class="form-group">
                        <select name="role" id="role_filter">
                            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="Student" <?php echo $role_filter === 'Student' ? 'selected' : ''; ?>>Student</option>
                            <option value="Instructor" <?php echo $role_filter === 'Instructor' ? 'selected' : ''; ?>>Instructor</option>
                        </select>
                        <label for="role_filter">Filter by Role</label>
                    </div>
                    <button type="submit" data-tooltip="Apply Filter"><i class="fas fa-filter"></i> Filter</button>
                </form>
            </div>

            <div class="card table-container">
                <h3>Feedback List</h3>
                <?php if (!empty($feedbacks)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Feedback</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbacks as $feedback): ?>
                                <tr <?php echo $feedback['is_read'] ? 'class="read"' : ''; ?>>
                                    <td><?php echo htmlspecialchars($feedback['name']); ?></td>
                                    <td><?php echo htmlspecialchars($feedback['role']); ?></td>
                                    <td><?php echo htmlspecialchars($feedback['feedback_message']); ?></td>
                                    <td><?php echo htmlspecialchars($feedback['submitted_at']); ?></td>
                                    <td><?php echo $feedback['is_read'] ? 'Read' : 'Unread'; ?></td>
                                    <td>
                                        <button class="delete-btn" data-tooltip="Delete Feedback" onclick="openDeleteModal(<?php echo $feedback['feedback_id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <?php if (!$feedback['is_read']): ?>
                                            <button class="read-btn" data-tooltip="Mark as Read" onclick="openReadModal(<?php echo $feedback['feedback_id']; ?>)">
                                                <i class="fas fa-check"></i> Mark as Read
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No feedback available.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="modal" id="deleteFeedbackModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteModal()">×</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this feedback?</p>
            <form method="post" id="deleteFeedbackForm">
                <input type="hidden" name="feedback_id" id="delete_feedback_id">
                <button type="submit" name="delete_feedback" class="delete-btn" data-tooltip="Confirm Delete">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="cancel-btn" data-tooltip="Cancel" onclick="closeDeleteModal()">Cancel</button>
            </form>
        </div>
    </div>

    <div class="modal" id="markReadModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeReadModal()">×</span>
            <h3>Mark as Read</h3>
            <p>Mark this feedback as read?</p>
            <form method="post" id="markReadForm">
                <input type="hidden" name="feedback_id" id="read_feedback_id">
                <button type="submit" name="mark_read" class="read-btn" data-tooltip="Confirm Mark as Read">
                    <i class="fas fa-check"></i> Mark as Read
                </button>
                <button type="button" class="cancel-btn" data-tooltip="Cancel" onclick="closeReadModal()">Cancel</button>
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

        const feedbackData = <?php echo json_encode($feedbacks); ?>;
        localStorage.setItem('feedbackData', JSON.stringify(feedbackData));

        if (!navigator.onLine) {
            const cachedFeedbacks = JSON.parse(localStorage.getItem('feedbackData') || '[]');
            if (cachedFeedbacks.length) {
                const feedbackTableBody = document.querySelector('.table-container tbody');
                if (feedbackTableBody) {
                    feedbackTableBody.innerHTML = '';
                    cachedFeedbacks.forEach(f => {
                        const row = document.createElement('tr');
                        row.className = f.is_read ? 'read' : '';
                        row.innerHTML = `
                            <td>${f.name}</td>
                            <td>${f.role}</td>
                            <td>${f.feedback_message}</td>
                            <td>${f.submitted_at}</td>
                            <td>${f.is_read ? 'Read' : 'Unread'}</td>
                            <td>
                                <button class="delete-btn" disabled data-tooltip="Delete Feedback">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                ${!f.is_read ? `
                                    <button class="read-btn" disabled data-tooltip="Mark as Read">
                                        <i class="fas fa-check"></i> Mark as Read
                                    </button>
                                ` : ''}
                            </td>
                        `;
                        feedbackTableBody.appendChild(row);
                    });
                }
            }
        }

        function openDeleteModal(feedbackId) {
            document.getElementById('delete_feedback_id').value = feedbackId;
            document.getElementById('deleteFeedbackModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteFeedbackModal').style.display = 'none';
        }

        function openReadModal(feedbackId) {
            document.getElementById('read_feedback_id').value = feedbackId;
            document.getElementById('markReadModal').style.display = 'flex';
        }

        function closeReadModal() {
            document.getElementById('markReadModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteFeedbackModal');
            const readModal = document.getElementById('markReadModal');
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            if (event.target === readModal) {
                closeReadModal();
            }
        };

        <?php if (isset($success)): ?>
            setTimeout(() => {
                window.location.href = 'view_feedback.php<?php echo $role_filter !== 'all' ? "?role=$role_filter" : ''; ?>';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>