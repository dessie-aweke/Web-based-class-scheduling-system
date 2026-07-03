<?php
session_start();
include 'database/db_connection.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables for messages
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Get form data
        $role = $conn->real_escape_string($_POST['role']);
        $name = trim($conn->real_escape_string($_POST['name']));
        $user_id = strtoupper(trim($conn->real_escape_string($_POST['id'])));
        $feedback_message = trim($conn->real_escape_string($_POST['feedback_message']));

        // Validate form data
        if (empty($role) || empty($name) || empty($user_id) || empty($feedback_message)) {
            $error = "All fields are required.";
        } elseif (!in_array($role, ['Student', 'Instructor'])) {
            $error = "Invalid role selected.";
        } elseif (!preg_match('/^[A-Z]{2,3}\d+$/', $user_id)) {
            $error = "ID must start with 2 or 3 letters followed by numbers (e.g., AB123 or ABC1234).";
        } elseif (!preg_match('/^[A-Za-z\s-]{2,50}$/', $name)) {
            $error = "Name must be 2–50 characters, containing only letters, spaces, or hyphens.";
        } elseif (strlen($feedback_message) < 10 || strlen($feedback_message) > 500) {
            $error = "Feedback message must be 10–500 characters.";
        } elseif (preg_match('/^(.)\1{9,}$/', $feedback_message)) {
            $error = "Feedback message is too repetitive.";
        } else {
            // Insert feedback
            $stmt = $conn->prepare("INSERT INTO feedback (role, name, user_id, feedback_message) 
                                   VALUES (?, ?, ?, ?)");
            if ($stmt === false) {
                $error = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("ssss", $role, $name, $user_id, $feedback_message);
                if ($stmt->execute()) {
                    $success = "Feedback submitted successfully!";
                    $_POST = array(); // Clear form fields
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate CSRF token
                } else {
                    $error = "Error submitting feedback: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6; /* Blue */
            --secondary: #93c5fd; /* Light blue */
            --background: url('image/admin.jpg');
            --form-bg: rgba(255, 255, 255, 0.15);
            --text: #333;
            --white: #fff;
            --error: #ef4444; /* Red */
            --success: #22c55e; /* Green */
        }

        [data-theme="dark"] {
            --background: url('image/admin.jpg');
            --form-bg: rgba(31, 41, 55, 0.3); /* Darker form bg */
            --text: #e0e0e0;
            --white: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', Arial, sans-serif;
        }

        body {
            background: var(--background);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: var(--text);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            transition: background 0.3s;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://www.transparenttextures.com/patterns/white-diamond.png');
            opacity: 0.05;
            z-index: -1;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3); /* Semi-transparent overlay */
            z-index: -2;
        }

        header {
            background: linear-gradient(to right, #2dd4bf, #1e40af); /* Teal to dark blue */
            color: var(--white);
            padding: 1rem;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: opacity 0.3s;
        }

        header.scrolled {
            opacity: 0.95;
        }

        header img {
            max-width: 100px;
            transition: transform 0.3s ease;
        }

        header img:hover {
            transform: scale(1.1);
        }

        .hamburger {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--white);
            position: absolute;
            left: 1rem;
            top: 1.2rem;
        }

        nav {
            margin-top: 0.5rem;
        }

        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
        }

        nav ul li {
            margin: 0 1rem;
        }

        nav ul li a {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.2s;
        }

        nav ul li a:hover {
            background-color: var(--secondary);
            color: var(--text);
            transform: translateY(-2px);
        }

        .theme-toggle {
            position: absolute;
            right: 1rem;
            top: 1.2rem;
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.2rem;
            cursor: pointer;
        }

        h2 {
            text-align: center;
            color: var(--white);
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            margin: 2rem 0;
            font-size: 2.2em;
            font-weight: 700;
        }

        form {
            background: var(--form-bg);
            backdrop-filter: blur(15px);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--primary);
            width: 90%;
            max-width: 400px;
            margin: 2rem auto;
            animation: formFadeIn 0.8s ease-in-out;
        }

        @keyframes formFadeIn {
            0% { opacity: 0; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }

        .form-group {
            position: relative;
            margin: 1.2rem 0;
        }

        label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            color: var(--white);
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--form-bg);
            padding: 0 0.25rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        input:focus + label,
        input:not(:placeholder-shown) + label,
        select:focus + label,
        select:not(:placeholder-shown) + label,
        textarea:focus + label,
        textarea:not(:placeholder-shown) + label {
            top: -0.5rem;
            font-size: 0.75rem;
            color: var(--secondary);
        }

        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--primary);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 8px rgba(147, 197, 253, 0.5);
        }

        input.valid {
            border-color: var(--success);
            background: rgba(34, 197, 94, 0.1);
        }

        input.invalid, select.invalid, textarea.invalid {
            border-color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }

        select {
            appearance: none;
            background: rgba(255, 255, 255, 0.9) url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="%233b82f6"><polygon points="0,0 12,0 6,12"/></svg>') no-repeat right 0.75rem center;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .char-count {
            font-size: 0.8rem;
            color: var(--white);
            text-align: right;
            margin-top: -0.5rem;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: var(--white);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.3s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.5);
            animation: pulse 1.5s infinite;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @keyframes pulse {
            0% { transform: translateY(-2px); }
            50% { transform: translateY(0); }
            100% { transform: translateY(-2px); }
        }

        .toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            color: var(--white);
            display: flex;
            align-items: center;
            animation: slideIn 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
            z-index: 2000;
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--error);
        }

        .toast i {
            margin-right: 0.5rem;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(100%); }
        }

        .error-text {
            font-size: 0.8rem;
            color: var(--error);
            margin-top: -0.5rem;
            margin-bottom: 0.5rem;
            display: none;
        }

        .valid-icon, .invalid-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            display: none;
        }

        input.valid ~ .valid-icon {
            display: block;
            color: var(--success);
        }

        input.invalid ~ .invalid-icon {
            display: block;
            color: var(--error);
        }

        @media (max-width: 768px) {
            form {
                width: 90%;
                padding: 1.5rem;
            }

            .hamburger {
                display: block;
            }

            nav {
                display: none;
            }

            nav.active {
                display: block;
                position: absolute;
                top: 4rem;
                left: 0;
                width: 100%;
                background: linear-gradient(to right, #2dd4bf, #1e40af);
            }

            nav ul {
                flex-direction: column;
                align-items: center;
                padding: 1rem 0;
            }

            nav ul li {
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <header id="header">
        <i class="fas fa-bars hamburger" onclick="toggleNav()"></i>
        <img src="image/DMUlog.png" alt="Markos University Logo">
        <nav id="nav">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="public_schedule.php">View Schedule</a></li>
                <li><a href="login.php">Login</a></li>
              
                <li><a href="feedback.php">Give Feedback</a></li>
            </ul>
        </nav>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <form action="feedback.php" method="post" id="feedbackForm" novalidate>
        <h2>Feedback</h2>

        <!-- Display success or error message -->
        <?php if ($success): ?>
            <div class="toast success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="toast error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <!-- Role Selection -->
        <div class="form-group">
            <select name="role" id="role" required aria-describedby="role-error">
                <option value="">Select Role</option>
                <option value="Student" <?php echo isset($_POST['role']) && $_POST['role'] === 'Student' ? 'selected' : ''; ?>>Student</option>
                <option value="Instructor" <?php echo isset($_POST['role']) && $_POST['role'] === 'Instructor' ? 'selected' : ''; ?>>Instructor</option>
            </select>
            <label for="role">Your Role</label>
            <div class="error-text" id="role-error">Please select a role.</div>
        </div>

        <!-- Name -->
        <div class="form-group">
            <input type="text" name="name" id="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                   required pattern="[A-Za-z\s-]{2,50}" aria-describedby="name-error" placeholder=" ">
            <label for="name">Name</label>
            <i class="fas fa-check-circle valid-icon"></i>
            <i class="fas fa-times-circle invalid-icon"></i>
            <div class="error-text" id="name-error">Name must be 2–50 characters, letters, spaces, or hyphens only.</div>
        </div>

        <!-- ID -->
        <div class="form-group">
            <input type="text" name="id" id="id" value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>" 
                   required pattern="[A-Z]{2,3}\d+" aria-describedby="id-error" placeholder="e.g., AB123 or ABC1234">
            <label for="id">ID</label>
            <i class="fas fa-check-circle valid-icon"></i>
            <i class="fas fa-times-circle invalid-icon"></i>
            <div class="error-text" id="id-error">ID must start with 2 or 3 letters followed by numbers (e.g., AB123 or ABC1234).</div>
        </div>

        <!-- Feedback Message -->
        <div class="form-group">
            <textarea name="feedback_message" id="feedback_message" required aria-describedby="message-error" placeholder=" "><?php echo isset($_POST['feedback_message']) ? htmlspecialchars($_POST['feedback_message']) : ''; ?></textarea>
            <label for="feedback_message">Feedback Message</label>
            <div class="char-count" id="char-count">0/500</div>
            <div class="error-text" id="message-error">Feedback must be 10–500 characters and not repetitive.</div>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="submitBtn">Submit Feedback</button>
    </form>

    <script>
        // Theme toggle
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.querySelector('.theme-toggle i');
            if (body.getAttribute('data-theme') === 'dark') {
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

        // Hamburger menu toggle
        function toggleNav() {
            const nav = document.getElementById('nav');
            nav.classList.toggle('active');
        }

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            header.classList.toggle('scrolled', window.scrollY > 50);
        });

        // Form validation
        const form = document.getElementById('feedbackForm');
        const roleSelect = document.getElementById('role');
        const nameInput = document.getElementById('name');
        const idInput = document.getElementById('id');
        const messageInput = document.getElementById('feedback_message');
        const submitBtn = document.getElementById('submitBtn');
        const charCount = document.getElementById('char-count');

        function validateRole() {
            const errorText = document.getElementById('role-error');
            if (!roleSelect.value) {
                roleSelect.classList.add('invalid');
                errorText.style.display = 'block';
                return false;
            }
            roleSelect.classList.remove('invalid');
            errorText.style.display = 'none';
            return true;
        }

        function validateName() {
            const errorText = document.getElementById('name-error');
            const regex = /^[A-Za-z\s-]{2,50}$/;
            if (!regex.test(nameInput.value)) {
                nameInput.classList.add('invalid');
                nameInput.classList.remove('valid');
                errorText.style.display = 'block';
                return false;
            }
            nameInput.classList.remove('invalid');
            nameInput.classList.add('valid');
            errorText.style.display = 'none';
            return true;
        }

        function validateId() {
            const errorText = document.getElementById('id-error');
            const regex = /^[A-Z]{2,3}\d+$/;
            if (!regex.test(idInput.value)) {
                idInput.classList.add('invalid');
                idInput.classList.remove('valid');
                errorText.style.display = 'block';
                return false;
            }
            idInput.classList.remove('invalid');
            idInput.classList.add('valid');
            errorText.style.display = 'none';
            return true;
        }

        function validateMessage() {
            const errorText = document.getElementById('message-error');
            const length = messageInput.value.length;
            const isRepetitive = /^(.)\1{9,}$/.test(messageInput.value);
            charCount.textContent = `${length}/500`;
            if (length < 10 || length > 500 || isRepetitive) {
                messageInput.classList.add('invalid');
                errorText.style.display = 'block';
                return false;
            }
            messageInput.classList.remove('invalid');
            errorText.style.display = 'none';
            return true;
        }

        function validateForm() {
            const isValid = validateRole() && validateName() && validateId() && validateMessage();
            console.log('Form valid:', isValid, {
                role: validateRole(),
                name: validateName(),
                id: validateId(),
                message: validateMessage()
            });
            submitBtn.disabled = !isValid;
            return isValid;
        }

        roleSelect.addEventListener('change', () => {
            validateRole();
            validateForm();
        });

        nameInput.addEventListener('input', () => {
            validateName();
            validateForm();
        });

        idInput.addEventListener('input', () => {
            idInput.value = idInput.value.toUpperCase();
            validateId();
            validateForm();
        });

        messageInput.addEventListener('input', () => {
            validateMessage();
            validateForm();
        });

        form.addEventListener('submit', (e) => {
            console.log('Submit attempted');
            if (!validateForm()) {
                e.preventDefault();
            } else {
                submitBtn.disabled = true;
                console.log('Form submitted');
            }
        });

        // Initialize validation
        validateForm();
    </script>
</body>
</html>