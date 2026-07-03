<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Include database connection
include 'database/db_connection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST["username"]);
    $password = $_POST["password"];

    $query = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["role"] = $user["role"];

            switch ($user["role"]) {
                case "admin":
                    header("Location: admin_dashboard.php");
                    break;
                case "dept_head":
                    header("Location: dept_head_dashboard.php");
                    break;
                default:
                    echo "Unknown role!";
                    exit();
            }
            exit();
        } else {
            echo "Invalid password!";
        }
    } else {
        echo "Invalid username!";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #D1D5DB, #3B82F6, #A855F7);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://www.transparenttextures.com/patterns/diamond-upholstery.png');
            opacity: 0.1;
            z-index: -1;
        }
        header {
            background: linear-gradient(to right, #93C5FD, #6D28D9);
            color: white;
            padding: 15px 0;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between; /* Space logo and nav */
            padding: 15px 20px; /* Add padding for layout */
        }
        header img {
            max-width: 100px;
            transition: transform 0.3s ease;
            margin-left: 20px; /* Push logo to the left */
        }
        header img:hover {
            transform: rotate(10deg);
        }
        nav {
            flex-grow: 1;
            text-align: center; /* Center the nav items */
        }
        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: inline-flex; /* Keep nav inline */
        }
        nav ul li {
            display: inline;
            margin: 0 15px;
        }
        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.2s;
        }
        nav ul li a:hover {
            background-color: #A855F7;
            transform: translateY(-2px);
        }
        .login-container {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: stretch;
            margin: 40px auto;
            width: 90%;
            max-width: 800px;
            gap: 20px;
        }
        .login-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 2px solid transparent;
            border-image: linear-gradient(to right, #3B82F6, #A855F7) 1;
            text-align: center;
            color: #fff;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-panel img {
            max-width: 120px; /* Adjusted for the new admin logo size */
            margin: 0 auto 10px;
        }
        .login-panel p {
            margin: 0;
            font-size: 1.1em;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            flex: 1;
            animation: formFadeIn 0.8s ease-in-out;
        }
        @keyframes formFadeIn {
            0% {
                opacity: 0;
                transform: scale(0.95);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
        h2 {
            text-align: center;
            color: #fff;
            background: linear-gradient(to right, #3B82F6, #D1D5DB);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            font-size: 2.2em;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            color: #fff;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 2px solid #3B82F6;
            border-radius: 8px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #A855F7;
            box-shadow: 0 0 8px rgba(168, 85, 247, 0.5);
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #3B82F6, #A855F7);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.5);
        }
        .show-password {
            font-size: 0.9em;
            color: #fff;
            cursor: pointer;
            margin-top: -5px;
            text-align: right;
            transition: color 0.3s;
        }
        .show-password:hover {
            color: #A855F7;
        }
        .error {
            color: #EF4444;
            text-align: center;
            margin: 10px 0;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 5px;
            animation: fadeInMessage 0.5s ease;
        }
        @keyframes fadeInMessage {
            0% {
                opacity: 0;
                transform: translateY(-10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                gap: 15px;
            }
            .login-panel, form {
                width: 100%;
            }
            header {
                flex-direction: column;
                padding: 10px;
            }
            header img {
                margin-left: 0;
                margin-bottom: 10px;
            }
            nav ul {
                flex-direction: column;
                align-items: center;
            }
            nav ul li {
                margin: 5px 0;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const passwordInput = document.querySelector("input[name='password']");
            const toggle = document.createElement("div");
            toggle.className = "show-password";
            toggle.textContent = "Show Password";
            passwordInput.after(toggle);

            toggle.addEventListener("click", function() {
                if (passwordInput.type === "password") {
                    passwordInput.type = "text";
                    toggle.textContent = "Hide Password";
                } else {
                    passwordInput.type = "password";
                    toggle.textContent = "Show Password";
                }
            });
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
                <li><a href="login.php" target="_self">Login</a></li>
               
                <li><a href="feedback.php">Give Feedback</a></li>
            </ul>
        </nav>
    </header>

    <div class="login-container">
        <!-- Login Panel -->
        <div class="login-panel">
            <img src="image/admin.jpg" alt="Admin">
            <p>Welcome to the Login panel where you can Verify and authenticate the users.</p>
        </div>

        <!-- Login Form -->
        <form action="login.php" method="post">
            <h2>Login</h2>
            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                if (!$result || $result->num_rows == 0) {
                    echo '<p class="error">Invalid username!</p>';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    echo '<p class="error">Invalid password!</p>';
                }
            }
            ?>
            <label>Username:</label>
            <input type="text" name="username" required><br>

            <label>Password:</label>
            <input type="password" name="password" required><br>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>