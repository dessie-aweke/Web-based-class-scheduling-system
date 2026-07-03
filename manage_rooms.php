<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'database/db_connection.php';

// Determine active tab
$active_tab = $_GET['tab'] ?? 'rooms';

// Function to validate input data
function validateInput($data, $field, $conn, $context = [], $is_edit = false) {
    $errors = [];

    switch ($field) {
        case 'building_name':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Building name is required.";
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-]{3,50}$/', $data)) {
                $errors[] = "Building name must be 3–50 characters, letters, numbers, spaces, or hyphens.";
            } else {
                // Check uniqueness (case-insensitive)
                $stmt = $conn->prepare("SELECT id FROM buildings WHERE LOWER(TRIM(building_name)) = ?");
                $lower_data = strtolower($data);
                $stmt->bind_param("s", $lower_data);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if ($is_edit && isset($context['building_id'])) {
                        $row = $result->fetch_assoc();
                        if ($row['id'] != $context['building_id']) {
                            $errors[] = "This building name is already stored in the system.";
                        }
                    } else {
                        $errors[] = "This building name is already stored in the system.";
                    }
                }
                $stmt->close();
            }
            break;

        case 'location':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Location is required.";
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-,]{3,100}$/', $data)) {
                $errors[] = "Location must be 3–100 characters, letters, numbers, spaces, hyphens, or commas.";
            }
            break;

        case 'room_name':
            $data = trim($data);
            if (empty($data)) {
                $errors[] = "Room name is required.";
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-]{3,50}$/', $data)) {
                $errors[] = "Room name must be 3–50 characters, letters, numbers, spaces, or hyphens.";
            } else {
                // Check uniqueness within the same building
                $building_id = $context['building_id'] ?? null;
                if ($building_id) {
                    $stmt = $conn->prepare("SELECT id FROM rooms WHERE LOWER(TRIM(room_name)) = ? AND building_id = ?");
                    $lower_data = strtolower($data);
                    $stmt->bind_param("si", $lower_data, $building_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        if ($is_edit && isset($context['room_id'])) {
                            $row = $result->fetch_assoc();
                            if ($row['id'] != $context['room_id']) {
                                $errors[] = "This room name is already stored in the selected building.";
                            }
                        } else {
                            $errors[] = "This room name is already stored in the selected building.";
                        }
                    }
                    $stmt->close();
                }
            }
            break;

        case 'building_id':
            if (empty($data)) {
                $errors[] = "Building selection is required.";
            } else {
                $stmt = $conn->prepare("SELECT id FROM buildings WHERE id = ?");
                $stmt->bind_param("i", $data);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    $errors[] = "Invalid building selected.";
                }
                $stmt->close();
            }
            break;

        case 'type':
            if (!in_array($data, ['Lab', 'Regular'])) {
                $errors[] = "Room type must be either Lab or Regular.";
            }
            break;

        case 'capacity':
            $data = (int)$data;
            if ($data <= 0) {
                $errors[] = "Capacity must be a positive number.";
            } elseif ($data > 1000) {
                $errors[] = "Capacity must not exceed 1000.";
            }
            break;
    }

    return $errors;
}

// Handle building operations
$building_success = '';
$building_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && $active_tab === 'buildings') {
    if (isset($_POST['add_building'])) {
        $building_name = $_POST['building_name'];
        $location = $_POST['location'];

        // Collect validation errors
        $validation_errors = [];
        $validation_errors = array_merge($validation_errors, validateInput($building_name, 'building_name', $conn));
        $validation_errors = array_merge($validation_errors, validateInput($location, 'location', $conn));

        if (empty($validation_errors)) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_name, location) VALUES (?, ?)");
            $stmt->bind_param("ss", $building_name, $location);
            if ($stmt->execute()) {
                $building_success = "Building added successfully!";
            } else {
                $building_error = "Error adding building: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $building_error = implode("<br>", $validation_errors);
        }
    } elseif (isset($_POST['edit_building'])) {
        $building_id = $_POST['building_id'];
        $building_name = $_POST['building_name'];
        $location = $_POST['location'];

        // Collect validation errors
        $validation_errors = [];
        $validation_errors = array_merge($validation_errors, validateInput($building_name, 'building_name', $conn, ['building_id' => $building_id], true));
        $validation_errors = array_merge($validation_errors, validateInput($location, 'location', $conn));

        if (empty($validation_errors)) {
            $stmt = $conn->prepare("UPDATE buildings SET building_name = ?, location = ? WHERE id = ?");
            $stmt->bind_param("ssi", $building_name, $location, $building_id);
            if ($stmt->execute()) {
                $building_success = "Building updated successfully!";
            } else {
                $building_error = "Error updating building: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $building_error = implode("<br>", $validation_errors);
        }
    } elseif (isset($_POST['delete_building'])) {
        $building_id = $_POST['building_id'];
        $stmt = $conn->prepare("SELECT * FROM rooms WHERE building_id = ?");
        $stmt->bind_param("i", $building_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $building_error = "Cannot delete building: It is linked to one or more rooms.";
        } else {
            $stmt = $conn->prepare("DELETE FROM buildings WHERE id = ?");
            $stmt->bind_param("i", $building_id);
            if ($stmt->execute()) {
                $building_success = "Building deleted successfully!";
            } else {
                $building_error = "Error deleting building: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Handle room operations
$room_success = '';
$room_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && $active_tab === 'rooms') {
    if (isset($_POST['add_room'])) {
        $room_name = $_POST['room_name'];
        $building_id = $_POST['building_id'];
        $type = $_POST['type'];
        $capacity = (int)$_POST['capacity'];

        // Collect validation errors
        $validation_errors = [];
        $validation_errors = array_merge($validation_errors, validateInput($room_name, 'room_name', $conn, ['building_id' => $building_id]));
        $validation_errors = array_merge($validation_errors, validateInput($building_id, 'building_id', $conn));
        $validation_errors = array_merge($validation_errors, validateInput($type, 'type', $conn));
        $validation_errors = array_merge($validation_errors, validateInput($capacity, 'capacity', $conn));

        if (empty($validation_errors)) {
            $stmt = $conn->prepare("INSERT INTO rooms (room_name, building_id, type, capacity) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sisi", $room_name, $building_id, $type, $capacity);
            if ($stmt->execute()) {
                $room_success = "Room added successfully!";
            } else {
                $room_error = "Error adding room: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $room_error = implode("<br>", $validation_errors);
        }
    } elseif (isset($_POST['edit_room'])) {
        $room_id = $_POST['room_id'];
        $room_name = $_POST['room_name'];
        $building_id = $_POST['building_id'];
        $type = $_POST['type'];
        $capacity = (int)$_POST['capacity'];

        // Collect validation errors
        $validation_errors = [];
        $validation_errors = array_merge($validation_errors, validateInput($room_name, 'room_name', $conn, ['building_id' => $building_id, 'room_id' => $room_id], true));
        $validation_errors = array_merge($validation_errors, validateInput($building_id, 'building_id', $conn));
        $validation_errors = array_merge($validation_errors, validateInput($type, 'type', $conn));
        $validation_errors = array_merge($validation_errors, validateInput($capacity, 'capacity', $conn));

        if (empty($validation_errors)) {
            $stmt = $conn->prepare("UPDATE rooms SET room_name = ?, building_id = ?, type = ?, capacity = ? WHERE id = ?");
            $stmt->bind_param("sisii", $room_name, $building_id, $type, $capacity, $room_id);
            if ($stmt->execute()) {
                $room_success = "Room updated successfully!";
            } else {
                $room_error = "Error updating room: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $room_error = implode("<br>", $validation_errors);
        }
    } elseif (isset($_POST['delete_room'])) {
        $room_id = $_POST['room_id'];
        $stmt = $conn->prepare("SELECT * FROM section_schedules WHERE room_id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $room_error = "Cannot delete room: It is currently scheduled in one or more classes.";
        } else {
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            if ($stmt->execute()) {
                $room_success = "Room deleted successfully!";
            } else {
                $room_error = "Error deleting room: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Fetch buildings and rooms
$buildings = $conn->query("SELECT * FROM buildings")->fetch_all(MYSQLI_ASSOC) ?? [];
$type_filter = $_GET['type'] ?? 'all';
$where_clause = $type_filter === 'all' ? '' : "WHERE r.type = '" . $conn->real_escape_string($type_filter) . "'";
$rooms_query = "SELECT r.*, b.building_name FROM rooms r JOIN buildings b ON r.building_id = b.id $where_clause";
$rooms = $conn->query($rooms_query)->fetch_all(MYSQLI_ASSOC) ?? [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buildings & Rooms</title>
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

        .sidebar a.active {
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

        .tab-buttons {
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .tab-buttons button {
            padding: 0.75rem 1.5rem;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }

        .tab-buttons button.active {
            background: linear-gradient(135deg, #2E7D32, #138496);
        }

        .tab-buttons button:hover {
            background: linear-gradient(135deg, #2E7D32, #138496);
        }

        .action-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            position: relative;
            margin: 1rem 0;
        }

        input[type="text"],
        input[type="number"],
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

        .hidden {
            display: none;
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

            .tab-buttons {
                flex-direction: column;
                align-items: flex-start;
            }

            .tab-buttons button {
                width: 100%;
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .action-section {
                flex-direction: column;
            }

            .modal-content {
                max-height: 70vh;
            }
        }

        @media print {
            .header, .sidebar, .theme-toggle, .hamburger, .tab-buttons, .action-section {
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

            .table-container:not(.hidden) {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Header with sticky navigation and theme toggle -->
    <header class="header">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <img src="image/markoslogo.jpg" alt="Markos University Logo" class="logo" onerror="this.src='https://via.placeholder.com/40';">
        <h1>Manage Buildings & Rooms</h1>
        <button class="theme-toggle" aria-label="Toggle theme" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <!-- Collapsible sidebar -->
    <nav class="sidebar" id="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage_rooms.php" class="active"><i class="fas fa-building"></i> Manage Buildings & Rooms</a>
        <a href="register_department.php"><i class="fas fa-building"></i> Manage Departments</a>
        <a href="manage_feedback.php"><i class="fas fa-comment"></i> Manage Feedback</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <!-- Main content area -->
    <main class="main-content" id="main-content">
        <div class="card">
            <h2>Manage Buildings & Rooms</h2>
            <?php if ($building_success || $room_success): ?>
                <div class="success"><?php echo htmlspecialchars($building_success ?: $room_success); ?></div>
            <?php endif; ?>
            <?php if ($building_error || $room_error): ?>
                <div class="error"><?php echo htmlspecialchars($building_error ?: $room_error); ?></div>
            <?php endif; ?>

            <!-- Tab Buttons -->
            <div class="tab-buttons">
                <button class="<?php echo $active_tab === 'buildings' ? 'active' : ''; ?>" onclick="switchTab('buildings')">Manage Buildings</button>
                <button class="<?php echo $active_tab === 'rooms' ? 'active' : ''; ?>" onclick="switchTab('rooms')">Manage Rooms</button>
            </div>

            <!-- Manage Buildings Section -->
            <div id="buildings-section" class="<?php echo $active_tab === 'buildings' ? '' : 'hidden'; ?>">
                <div class="action-section">
                    <button onclick="openAddBuildingModal()" aria-label="Add new building"><i class="fas fa-plus"></i> Add New Building</button>
                    <button onclick="toggleTable('building-table')" id="view-building-btn"><i class="fas fa-table"></i> View All Buildings</button>
                </div>
                <div id="building-table" class="table-container hidden">
                    <div class="action-section">
                        <button onclick="toggleTable('building-table')" aria-label="Hide building table"><i class="fas fa-eye-slash"></i> Hide Table</button>
                    </div>
                    <h3>Existing Buildings</h3>
                    <?php if (!empty($buildings)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Building Name</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($buildings as $building): ?>
                                    <tr>
                                        <td><?php echo $building['id']; ?></td>
                                        <td><?php echo htmlspecialchars($building['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($building['location']); ?></td>
                                        <td>
                                            <button class="action-btn edit-btn" onclick="openEditBuildingModal(
                                                '<?php echo $building['id']; ?>',
                                                '<?php echo htmlspecialchars($building['building_name']); ?>',
                                                '<?php echo htmlspecialchars($building['location']); ?>'
                                            )"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="action-btn delete-btn" onclick="openDeleteBuildingModal('<?php echo $building['id']; ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No buildings registered yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Manage Rooms Section -->
            <div id="rooms-section" class="<?php echo $active_tab === 'rooms' ? '' : 'hidden'; ?>">
                <div class="action-section">
                    <button onclick="openAddRoomModal()" aria-label="Add new room"><i class="fas fa-plus"></i> Add New Room</button>
                    <button onclick="toggleTable('room-table')" id="view-room-btn"><i class="fas fa-table"></i> View All Rooms</button>
                </div>
                <div id="room-table" class="table-container hidden">
                    <div class="action-section">
                        <button onclick="toggleTable('room-table')" aria-label="Hide room table"><i class="fas fa-eye-slash"></i> Hide Table</button>
                        <form method="get" style="display: inline;">
                            <input type="hidden" name="tab" value="rooms">
                            <div class="form-group">
                                <select name="type" onchange="this.form.submit()" id="type-filter">
                                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Lab" <?php echo $type_filter === 'Lab' ? 'selected' : ''; ?>>Lab</option>
                                    <option value="Regular" <?php echo $type_filter === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                </select>
                                <label for="type-filter">Filter by Type</label>
                            </div>
                        </form>
                    </div>
                    <h3>Existing Rooms</h3>
                    <?php if (!empty($rooms)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Building</th>
                                    <th>Room Name</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?php echo $room['id']; ?></td>
                                        <td><?php echo htmlspecialchars($room['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($room['room_name']); ?></td>
                                        <td><?php echo htmlspecialchars($room['type']); ?></td>
                                        <td><?php echo htmlspecialchars($room['capacity']); ?></td>
                                        <td>
                                            <button class="action-btn edit-btn" onclick="openEditRoomModal(
                                                '<?php echo $room['id']; ?>',
                                                '<?php echo $room['building_id']; ?>',
                                                '<?php echo htmlspecialchars($room['room_name']); ?>',
                                                '<?php echo $room['type']; ?>',
                                                '<?php echo $room['capacity']; ?>'
                                            )"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="action-btn delete-btn" onclick="openDeleteRoomModal('<?php echo $room['id']; ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No rooms found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Building Modal -->
    <div id="addBuildingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddBuildingModal()">×</span>
            <h3>Add New Building</h3>
            <form method="post" id="addBuildingForm">
                <div class="form-group">
                    <input type="text" name="building_name" id="add_building_name" required placeholder=" ">
                    <label for="add_building_name">Building Name</label>
                </div>
                <div class="form-group">
                    <input type="text" name="location" id="add_location" required placeholder=" ">
                    <label for="add_location">Location</label>
                </div>
                <button type="submit" name="add_building"><i class="fas fa-plus"></i> Add Building</button>
                <button type="button" class="cancel-btn" onclick="closeAddBuildingModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Building Modal -->
    <div id="editBuildingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditBuildingModal()">×</span>
            <h3>Edit Building</h3>
            <form method="post" id="editBuildingForm">
                <input type="hidden" name="building_id" id="modal_building_id">
                <div class="form-group">
                    <input type="text" name="building_name" id="modal_building_name" required placeholder=" ">
                    <label for="modal_building_name">Building Name</label>
                </div>
                <div class="form-group">
                    <input type="text" name="location" id="modal_location" required placeholder=" ">
                    <label for="modal_location">Location</label>
                </div>
                <button type="submit" name="edit_building"><i class="fas fa-edit"></i> Update Building</button>
                <button type="button" class="cancel-btn" onclick="closeEditBuildingModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Building Modal -->
    <div id="deleteBuildingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteBuildingModal()">×</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this building?</p>
            <form method="post" id="deleteBuildingForm">
                <input type="hidden" name="building_id" id="delete_building_id">
                <button type="submit" name="delete_building" class="delete-btn">Delete</button>
                <button type="button" class="cancel-btn" onclick="closeDeleteBuildingModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div id="addRoomModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddRoomModal()">×</span>
            <h3>Add New Room</h3>
            <form method="post" id="addRoomForm">
                <div class="form-group">
                    <select name="building_id" id="add_building_id" required>
                        <option value="">-- Select Building --</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo $building['id']; ?>">
                                <?php echo htmlspecialchars($building['building_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="add_building_id">Building</label>
                </div>
                <div class="form-group">
                    <input type="text" name="room_name" id="add_room_name" required placeholder=" ">
                    <label for="add_room_name">Room Name</label>
                </div>
                <div class="form-group">
                    <select name="type" id="add_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Lab">Lab</option>
                        <option value="Regular">Regular</option>
                    </select>
                    <label for="add_type">Type</label>
                </div>
                <div class="form-group">
                    <input type="number" name="capacity" id="add_capacity" min="1" required placeholder=" ">
                    <label for="add_capacity">Capacity (Number of Students)</label>
                </div>
                <button type="submit" name="add_room"><i class="fas fa-plus"></i> Add Room</button>
                <button type="button" class="cancel-btn" onclick="closeAddRoomModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div id="editRoomModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditRoomModal()">×</span>
            <h3>Edit Room</h3>
            <form method="post" id="editRoomForm">
                <input type="hidden" name="room_id" id="modal_room_id">
                <div class="form-group">
                    <select name="building_id" id="modal_building_id" required>
                        <option value="">-- Select Building --</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo $building['id']; ?>">
                                <?php echo htmlspecialchars($building['building_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="modal_building_id">Building</label>
                </div>
                <div class="form-group">
                    <input type="text" name="room_name" id="modal_room_name" required placeholder=" ">
                    <label for="modal_room_name">Room Name</label>
                </div>
                <div class="form-group">
                    <select name="type" id="modal_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Lab">Lab</option>
                        <option value="Regular">Regular</option>
                    </select>
                    <label for="modal_type">Type</label>
                </div>
                <div class="form-group">
                    <input type="number" name="capacity" id="modal_capacity" min="1" required placeholder=" ">
                    <label for="modal_capacity">Capacity (Number of Students)</label>
                </div>
                <button type="submit" name="edit_room"><i class="fas fa-edit"></i> Update Room</button>
                <button type="button" class="cancel-btn" onclick="closeEditRoomModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Room Modal -->
    <div id="deleteRoomModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteRoomModal()">×</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this room?</p>
            <form method="post" id="deleteRoomForm">
                <input type="hidden" name="room_id" id="delete_room_id">
                <button type="submit" name="delete_room" class="delete-btn">Delete</button>
                <button type="button" class="cancel-btn" onclick="closeDeleteRoomModal()">Cancel</button>
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
            document.querySelectorAll('button:not(.theme-toggle)').forEach(btn => btn.disabled = false);
        });

        window.addEventListener('offline', () => {
            document.getElementById('offline-message').style.display = 'block';
            document.querySelectorAll('.action-btn, button[onclick*="openAdd"], button[onclick*="openEdit"], button[onclick*="openDelete"]').forEach(btn => btn.disabled = true);
        });

        // Cache buildings and rooms data
        const buildingsData = <?php echo json_encode($buildings); ?>;
        const roomsData = <?php echo json_encode($rooms); ?>;
        localStorage.setItem('buildingsData', JSON.stringify(buildingsData));
        localStorage.setItem('roomsData', JSON.stringify(roomsData));

        // Load cached data if offline and table is visible
        if (!navigator.onLine) {
            const cachedBuildings = JSON.parse(localStorage.getItem('buildingsData') || '[]');
            const cachedRooms = JSON.parse(localStorage.getItem('roomsData') || '[]');
            const activeTab = '<?php echo $active_tab; ?>';

            if (activeTab === 'buildings' && cachedBuildings.length && !document.getElementById('building-table').classList.contains('hidden')) {
                const buildingTableBody = document.querySelector('#building-table tbody');
                if (buildingTableBody) {
                    buildingTableBody.innerHTML = '';
                    cachedBuildings.forEach(building => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${building.id}</td>
                            <td>${building.building_name}</td>
                            <td>${building.location}</td>
                            <td>
                                <button class="action-btn edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                                <button class="action-btn delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                        buildingTableBody.appendChild(row);
                    });
                }
            } else if (activeTab === 'rooms' && cachedRooms.length && !document.getElementById('room-table').classList.contains('hidden')) {
                const roomTableBody = document.querySelector('#room-table tbody');
                if (roomTableBody) {
                    roomTableBody.innerHTML = '';
                    cachedRooms.forEach(room => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${room.id}</td>
                            <td>${room.building_name}</td>
                            <td>${room.room_name}</td>
                            <td>${room.type}</td>
                            <td>${room.capacity}</td>
                            <td>
                                <button class="action-btn edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                                <button class="action-btn delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                        roomTableBody.appendChild(row);
                    });
                }
            }
        }

        // Tab switching
        function switchTab(tab) {
            const buildingsSection = document.getElementById('buildings-section');
            const roomsSection = document.getElementById('rooms-section');
            const buildingBtn = document.querySelector('.tab-buttons button:nth-child(1)');
            const roomBtn = document.querySelector('.tab-buttons button:nth-child(2)');

            if (tab === 'buildings') {
                buildingsSection.classList.remove('hidden');
                roomsSection.classList.add('hidden');
                buildingBtn.classList.add('active');
                roomBtn.classList.remove('active');
            } else {
                buildingsSection.classList.add('hidden');
                roomsSection.classList.remove('hidden');
                buildingBtn.classList.remove('active');
                roomBtn.classList.add('active');
            }

            // Update URL without reloading
            history.pushState(null, '', `?tab=${tab}`);
        }

        // Toggle table visibility
        function toggleTable(tableId) {
            const table = document.getElementById(tableId);
            const viewBtn = tableId === 'building-table' ? document.getElementById('view-building-btn') : document.getElementById('view-room-btn');
            const isHidden = table.classList.contains('hidden');
            
            table.classList.toggle('hidden');
            viewBtn.innerHTML = isHidden ? 
                `<i class="fas fa-eye-slash"></i> Hide Table` : 
                `<i class="fas fa-table"></i> View All ${tableId.includes('building') ? 'Buildings' : 'Rooms'}`;
            
            // Load cached data if offline and table is shown
            if (!navigator.onLine && isHidden) {
                const cachedData = tableId === 'building-table' ? JSON.parse(localStorage.getItem('buildingsData') || '[]') : JSON.parse(localStorage.getItem('roomsData') || '[]');
                const tbody = table.querySelector('tbody');
                if (tbody && cachedData.length) {
                    tbody.innerHTML = '';
                    cachedData.forEach(item => {
                        const row = document.createElement('tr');
                        if (tableId === 'building-table') {
                            row.innerHTML = `
                                <td>${item.id}</td>
                                <td>${item.building_name}</td>
                                <td>${item.location}</td>
                                <td>
                                    <button class="action-btn edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                                    <button class="action-btn delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                                </td>
                            `;
                        } else {
                            row.innerHTML = `
                                <td>${item.id}</td>
                                <td>${item.building_name}</td>
                                <td>${item.room_name}</td>
                                <td>${item.type}</td>
                                <td>${item.capacity}</td>
                                <td>
                                    <button class="action-btn edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                                    <button class="action-btn delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                                </td>
                            `;
                        }
                        tbody.appendChild(row);
                    });
                }
            }
        }

        // Add Building Modal
        function openAddBuildingModal() {
            if (!navigator.onLine) {
                alert('Cannot add building in offline mode.');
                return;
            }
            document.getElementById('addBuildingModal').style.display = 'flex';
        }

        function closeAddBuildingModal() {
            document.getElementById('addBuildingModal').style.display = 'none';
            document.getElementById('addBuildingForm').reset();
        }

        // Edit Building Modal
        function openEditBuildingModal(id, building_name, location) {
            if (!navigator.onLine) {
                alert('Cannot edit building in offline mode.');
                return;
            }
            document.getElementById('modal_building_id').value = id;
            document.getElementById('modal_building_name').value = building_name;
            document.getElementById('modal_location').value = location;
            document.getElementById('editBuildingModal').style.display = 'flex';
        }

        function closeEditBuildingModal() {
            document.getElementById('editBuildingModal').style.display = 'none';
        }

        // Delete Building Modal
        function openDeleteBuildingModal(id) {
            if (!navigator.onLine) {
                alert('Cannot delete building in offline mode.');
                return;
            }
            document.getElementById('delete_building_id').value = id;
            document.getElementById('deleteBuildingModal').style.display = 'flex';
        }

        function closeDeleteBuildingModal() {
            document.getElementById('deleteBuildingModal').style.display = 'none';
        }

        // Add Room Modal
        function openAddRoomModal() {
            if (!navigator.onLine) {
                alert('Cannot add room in offline mode.');
                return;
            }
            document.getElementById('addRoomModal').style.display = 'flex';
        }

        function closeAddRoomModal() {
            document.getElementById('addRoomModal').style.display = 'none';
            document.getElementById('addRoomForm').reset();
        }

        // Edit Room Modal
        function openEditRoomModal(id, building_id, room_name, type, capacity) {
            if (!navigator.onLine) {
                alert('Cannot edit room in offline mode.');
                return;
            }
            document.getElementById('modal_room_id').value = id;
            document.getElementById('modal_building_id').value = building_id;
            document.getElementById('modal_room_name').value = room_name;
            document.getElementById('modal_type').value = type;
            document.getElementById('modal_capacity').value = capacity;
            document.getElementById('editRoomModal').style.display = 'flex';
        }

        function closeEditRoomModal() {
            document.getElementById('editRoomModal').style.display = 'none';
        }

        // Delete Room Modal
        function openDeleteRoomModal(id) {
            if (!navigator.onLine) {
                alert('Cannot delete room in offline mode.');
                return;
            }
            document.getElementById('delete_room_id').value = id;
            document.getElementById('deleteRoomModal').style.display = 'flex';
        }

        function closeDeleteRoomModal() {
            document.getElementById('deleteRoomModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = [
                'addBuildingModal',
                'editBuildingModal',
                'deleteBuildingModal',
                'addRoomModal',
                'editRoomModal',
                'deleteRoomModal'
            ];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                    if (modalId.includes('add')) {
                        document.getElementById(modalId.replace('Modal', 'Form')).reset();
                    }
                }
            });
        };

        // Form validation
        document.addEventListener('DOMContentLoaded', () => {
            ['addBuildingForm', 'editBuildingForm', 'addRoomForm', 'editRoomForm'].forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', (e) => {
                        if (formId.includes('Room')) {
                            const capacity = form.querySelector('[name="capacity"]').value;
                            if (capacity <= 0) {
                                e.preventDefault();
                                alert('Capacity must be a positive number.');
                            } else if (capacity > 1000) {
                                e.preventDefault();
                                alert('Capacity must not exceed 1000.');
                            }
                        }
                    });
                }
            });
        });

        // Redirect with delay if success
        <?php if ($building_success || $room_success): ?>
            setTimeout(() => {
                window.location.href = 'manage_rooms.php?tab=<?php echo $active_tab; ?>';
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>