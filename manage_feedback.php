<?php
session_start();

// Role-Based Access Control (RBAC)
// Allow Admins and Department Heads to view, but only Admins can delete
$view_allowed_roles = ['admin', 'dept_head'];
$delete_allowed_roles = ['admin'];

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || !in_array($_SESSION["role"], $view_allowed_roles)) {
    // Redirect unauthorized users to login page
    header("Location: login.php");
    exit();
}

include 'database/db_connection.php';

// Initialize variables for messages
$success = '';
$error = '';

// Handle feedback deletion (only for Admins)
if (isset($_POST['delete_feedback']) && in_array($_SESSION["role"], $delete_allowed_roles)) {
    $feedback_id = isset($_POST['feedback_id']) ? (int)$_POST['feedback_id'] : 0;

    // Validate feedback_id
    if ($feedback_id <= 0) {
        $error = "Invalid feedback ID.";
    } else {
        // Use prepared statement to delete feedback
        $stmt = $conn->prepare("DELETE FROM feedback WHERE feedback_id = ?");
        $stmt->bind_param("i", $feedback_id);
        if ($stmt->execute()) {
            $success = "Feedback deleted successfully!";
        } else {
            $error = "Error deleting feedback: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all feedback entries (only from Students and Instructors)
$query = "SELECT feedback_id, role, name, user_id, feedback_message, submitted_at 
          FROM feedback 
          WHERE role IN ('Student', 'Instructor') 
          ORDER BY submitted_at DESC";
$result = $conn->query($query);

// Debugging: Check if the query failed
if ($result === false) {
    $error = "Query failed: " . $conn->error;
    $feedback_entries = [];
} else {
    $feedback_entries = $result->fetch_all(MYSQLI_ASSOC);
    if (empty($feedback_entries)) {
        $error = "No feedback entries found. Check if the role values match 'Student' or 'Instructor' exactly.";
    }
}

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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f4f4f4, #d9e2ec);
            min-height: 100vh;
            color: #333;
        }

        /* Header */
        header {
            background: linear-gradient(90deg, #2c3e50, #4a6a8a);
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        header h1 {
            font-size: 2.2em;
            margin-bottom: 5px;
        }

        header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        /* Navigation */
        nav {
            background-color: #4a6a8a;
            padding: 10px 0;
            display: flex;
            justify-content: center;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            font-size: 1.1em;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.2s;
        }

        nav ul li a:hover {
            background-color: #2c3e50;
            transform: scale(1.05);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Feedback Table */
        .feedback-table {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #2c3e50;
            color: white;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        tr:hover {
            background: #f1f1f1;
        }

        .delete-btn {
            padding: 8px 15px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .delete-btn:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        .delete-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        /* Messages */
        .success {
            color: green;
            text-align: center;
            margin: 10px 0;
        }

        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
        }

        /* Logout Button */
        .logout {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.2s;
        }

        .logout:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }

            table {
                font-size: 0.9em;
            }

            th, td {
                padding: 8px;
            }

            .logout {
                position: static;
                margin: 20px auto;
                display: block;
                width: fit-content;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Manage Feedback</h1>
        <p>Welcome, <?php echo ucfirst($_SESSION["role"]); ?>!</p>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </header>

    <nav>
        <ul>
            <li><a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'dept_head_dashboard.php'; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage_feedback.php"><i class="fas fa-comment"></i> Manage Feedback</a></li>
        </ul>
    </nav>

    <div class="container">
        <!-- Messages -->
        <?php if ($success): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <!-- Feedback Table -->
        <div class="feedback-table">
            <h2>Feedback Entries</h2>
            <?php if (!empty($feedback_entries)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Feedback Message</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback_entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['role']); ?></td>
                                <td><?php echo htmlspecialchars($entry['name']); ?></td>
                                <td><?php echo htmlspecialchars($entry['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($entry['feedback_message']); ?></td>
                                <td><?php echo $entry['submitted_at']; ?></td>
                                <td>
                                    <?php if (in_array($_SESSION["role"], $delete_allowed_roles)): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="feedback_id" value="<?php echo $entry['feedback_id']; ?>">
                                            <button type="submit" name="delete_feedback" class="delete-btn"
                                                    onclick="return confirm('Are you sure you want to delete this feedback?');">
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="delete-btn" disabled title="Only Admins can delete feedback">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No feedback entries found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>