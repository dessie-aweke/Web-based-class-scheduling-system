<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Class Scheduling</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom, #e6f0fa, #ffffff);
            min-height: 100vh;
            overflow-x: hidden;
        }
        /* Parallax Background */
        .parallax {
            background-image: url('image/yhalem.jpg');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.7;
        }
        /* Sticky Header */
        .header {
            background: linear-gradient(to right, #1e90ff, #9b59b6);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            text-align: center;
        }
        .logo {
            max-width: 120px;
            transition: transform 0.5s ease;
        }
        .logo:hover {
            transform: scale(1.1) rotate(360deg);
        }
        .nav ul {
            display: flex;
            justify-content: center;
            gap: 15px;
            list-style: none;
            padding: 10px 0;
        }
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 25px;
            transition: background-color 0.3s, transform 0.2s;
            font-size: 0.9em;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        .nav-link i {
            margin-right: 5px;
        }
        /* Hamburger Menu for Mobile */
        .hamburger {
            display: none;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            padding: 10px;
        }
        /* Main Content */
        .main {
            background: linear-gradient(to bottom, rgba(52, 152, 219, 0.8), rgba(142, 68, 173, 0.8));
            color: white;
            text-align: center;
            padding: 80px 20px;
            margin: 40px auto;
            width: 90%;
            max-width: 900px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .heading {
            font-size: 2.8em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            background: linear-gradient(to right, #ffffff, #f1c40f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        marquee {
            width: 100%;
        }
        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            text-align: center;
        }
        .social-links a {
            color: white;
            margin: 0 15px;
            font-size: 1.8em;
            transition: transform 0.3s, color 0.3s;
        }
        .social-links a:hover {
            color: #e74c3c;
            transform: rotate(360deg);
        }
        /* Responsive Design */
        @media (max-width: 768px) {
            .nav ul {
                display: none;
                flex-direction: column;
                background: #2c3e50;
                position: absolute;
                top: 60px;
                left: 0;
                width: 100%;
                padding: 10px 0;
            }
            .nav ul.active {
                display: flex;
            }
            .hamburger {
                display: block;
                position: absolute;
                top: 15px;
                right: 20px;
            }
            .main {
                width: 95%;
                padding: 40px 15px;
            }
            .heading {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="parallax"></div>
    <header class="header">
        <img class="logo" src="image/DMUlog.png" alt="Markos University Logo">
        <i class="fas fa-bars hamburger" onclick="toggleMenu()"></i>
        <nav class="nav">
            <ul>
                <li><a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a class="nav-link" href="key_features.php"><i class="fas fa-star"></i> Key Features</a></li>
                <li><a class="nav-link" href="contact_us.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                <li><a class="nav-link" href="public_schedule.php"><i class="fas fa-calendar-alt"></i> View Schedule</a></li>
                <li><a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a class="nav-link" href="feedback.php"><i class="fas fa-comment"></i> Give Feedback</a></li>
            </ul>
        </nav>
    </header>
    <main class="main">
        <h1 class="heading"><marquee>Welcome to Class Scheduling System</marquee></h1>
    </main>
    <footer class="footer">
        <p>© 2025 Markos University. All rights reserved. This site and its content are protected under international copyright laws.</p>
        <div class="social-links">
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
        </div>
    </footer>
    <script>
        // Hamburger Menu Toggle
        function toggleMenu() {
            const nav = document.querySelector('.nav ul');
            nav.classList.toggle('active');
        }
    </script>
</body>
</html>