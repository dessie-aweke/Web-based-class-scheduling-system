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
    <title>Key Features - Class Scheduling</title>
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
        .hamburger {
            display: none;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            padding: 10px;
        }
        .main {
            width: 90%;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }
        .section-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        }
        .section-heading {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 15px;
            position: relative;
        }
        .section-heading::after {
            content: '';
            width: 50px;
            height: 3px;
            background: #3498db;
            position: absolute;
            bottom: -5px;
            left: 0;
        }
        .key-features ul {
            list-style: none;
            padding: 0;
        }
        .feature-item {
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .feature-title {
            padding: 15px;
            cursor: pointer;
            font-weight: 600;
            color: #2c3e50;
            transition: background 0.3s;
            display: flex;
            align-items: center;
        }
        .feature-title:hover {
            background: #e0f7fa;
        }
        .feature-title i {
            margin-right: 10px;
            color: #3498db;
        }
        .feature-content {
            max-height: 0;
            overflow: hidden;
            padding: 0 15px;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: #ffffff;
        }
        .feature-content p {
            padding: 15px 0;
            color: #34495e;
        }
        .feature-item.active .feature-content {
            max-height: 200px;
            padding: 15px;
        }
        .section-links {
            margin-top: 20px;
            text-align: center;
        }
        .section-links a {
            color: #3498db;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 600;
            transition: color 0.3s;
        }
        .section-links a:hover {
            color: #e74c3c;
        }
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
                padding: 15px;
            }
            .section-heading {
                font-size: 1.5em;
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
        <div class="section-card">
            <h2 class="section-heading">Key Features</h2>
            <ul class="key-features">
                <li class="feature-item">
                    <div class="feature-title"><i class="fas fa-sync"></i> Real-time Updates</div>
                    <div class="feature-content"><p>Access the latest schedule changes instantly for students and faculty.</p></div>
                </li>
                <li class="feature-item">
                    <div class="feature-title"><i class="fas fa-shield-alt"></i> Conflict Detection</div>
                    <div class="feature-content"><p>Automatic detection and resolution of scheduling conflicts.</p></div>
                </li>
                <li class="feature-item">
                    <div class="feature-title"><i class="fas fa-users"></i> User-Friendly Interface</div>
                    <div class="feature-content"><p>Navigate effortlessly with an intuitive design.</p></div>
                </li>
                <li class="feature-item">
                    <div class="feature-title"><i class="fas fa-tools"></i> Administrative Tools</div>
                    <div class="feature-content"><p>Plan schedules efficiently with powerful admin tools.</p></div>
                </li>
            </ul>
            <div class="section-links">
                <a href="contact_us.php">Contact Us</a> | <a href="public_schedule.php">View Schedules</a> | <a href="index.php">Home</a>
            </div>
        </div>
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
        // Accordion for Key Features
        document.querySelectorAll('.feature-title').forEach(item => {
            item.addEventListener('click', () => {
                const parent = item.parentElement;
                const isActive = parent.classList.contains('active');
                document.querySelectorAll('.feature-item').forEach(el => el.classList.remove('active'));
                if (!isActive) {
                    parent.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>