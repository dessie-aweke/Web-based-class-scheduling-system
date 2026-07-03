<?php
// Start session
session_start();

// Check if user is logged in and is a department head
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "dept_head") {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'database/db_connection.php';

// Get department info for the logged-in dept head
$user_id = $_SESSION["user_id"];
$query = "SELECT department_id, department FROM users WHERE user_id = '$user_id'";
$result = $conn->query($query);
$dept_info = $result->fetch_assoc();
$department_id = $dept_info['department_id'];
$department = $dept_info['department'];

// Check if department is assigned
if (!$department_id || !$department) {
    $error = "No department assigned to this Department Head. Please contact the admin to assign a department.";
}

// Fetch feedback related to schedules/system for this department
$feedback_query = "SELECT f.*, u.username 
                   FROM feedback f 
                   JOIN users u ON f.user_id = u.user_id 
                   WHERE u.department = '$department' 
                   ORDER BY f.submitted_at DESC 
                   LIMIT 5";
$feedback_result = $conn->query($feedback_query);
$feedbacks = $feedback_result ? $feedback_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Head Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #28a745;
            --secondary: #17a2b8;
            --background: #f4f4f4;
            --card-bg: rgba(255, 255, 255, 0.9);
            --text: #333;
            --sidebar-bg: #2c3e50;
            --gradient: linear-gradient(135deg, #28a745, #17a2b8);
            --error: #dc3545;
            --overlay: rgba(0, 0, 0, 0.5);
        }

        [data-theme="dark"] {
            --background: #1a1a1a;
            --card-bg: rgba(44, 44, 44, 0.9);
            --text: #e0e0e0;
            --sidebar-bg: #1f2a44;
            --overlay: rgba(0, 0, 0, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', Arial, sans-serif;
        }

        body {
            background: var(--overlay) url('image/admin.jpg') no-repeat center center fixed;
            background-size: cover;
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
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(5px);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .error {
            color: var(--error);
            padding: 1rem;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .welcome-card {
            background: var(--gradient);
            color: white;
            text-align: center;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .welcome-card p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        .cta-btn {
            background: white;
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s, color 0.3s;
        }

        .cta-btn:hover {
            background: var(--primary);
            color: white;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: var(--card-bg);
            text-align: center;
            padding: 1.5rem;
            border-radius: 12px;
            transition: transform 0.3s, background 0.3s;
        }

        .action-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .action-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .action-card:hover {
            background: var(--gradient);
            color: white;
        }

        .action-card:hover i {
            color: white;
        }

        .overview-card h3, .feedback-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .overview-item {
            background: var(--gradient);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .feedback-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .feedback-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .feedback-item:last-child {
            border-bottom: none;
        }

        .feedback-item p {
            margin: 0.5rem 0;
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

            .welcome-card h2 {
                font-size: 1.8rem;
            }

            .welcome-card p {
                font-size: 1rem;
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

            body {
                background: none;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <h1>Department Head Dashboard</h1>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <nav class="sidebar" id="sidebar">
        <?php if (!isset($error)): ?>
            <a href="submit_dept_info.php"><i class="fas fa-building"></i> Submit Dept Info</a>
            <a href="submit_course_instructor.php"><i class="fas fa-chalkboard-teacher"></i> Manage Courses & Instructors</a>
            <a href="manage_schedule.php"><i class="fas fa-calendar"></i> Manage Schedules</a>
            <a href="manage_feedback.php"><i class="fas fa-comment"></i> View Feedback</a>
        <?php endif; ?>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <main class="main-content" id="main-content">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <!-- Welcome Card -->
            <div class="card welcome-card">
                <h2>Welcome to <?php echo htmlspecialchars($department); ?> Head</h2>
                <p>Streamline your department's operations with ease and efficiency.</p>
               
            </div>

            

           
        <?php endif; ?>
    </main>

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

        // Load theme from localStorage
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

        // Cache dashboard data
        const dashboardData = {
            department: <?php echo json_encode($department); ?>,
            feedbackCount: <?php echo json_encode(count($feedbacks)); ?>
        };
        localStorage.setItem('dashboardData', JSON.stringify(dashboardData));

        // Load cached data if offline
        if (!navigator.onLine) {
            const cachedData = JSON.parse(localStorage.getItem('dashboardData') || '{}');
            if (cachedData.department) {
                document.querySelector('.welcome-card h2').textContent = 
                    `Welcome to ${cachedData.department} Management`;
                document.querySelector('.overview-item p').textContent = 
                    `${cachedData.feedbackCount} Recent`;
            }
        }
    </script>
</body>
</html>