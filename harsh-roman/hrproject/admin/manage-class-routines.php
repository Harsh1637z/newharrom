<?php
session_start();
include_once('../includes/config.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}

// Verify database connection
if (!$db_conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$query = "SELECT role FROM users WHERE id = ?";
$stmt = mysqli_prepare($db_conn, $query);
if ($stmt === false) {
    die("Prepare failed: " . mysqli_error($db_conn));
}
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user || $user['role'] !== 'admin') {
    header("Location: /hrproject/login.php");
    exit();
}

// Fetch classes for dropdown
$classes = [];
$query = "SELECT id, name, section FROM classes ORDER BY name, section";
$result = mysqli_query($db_conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $classes[] = $row;
    }
    mysqli_free_result($result);
}

// Fetch teachers for dropdown
$teachers = [];
$query = "SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username";
$result = mysqli_query($db_conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
    mysqli_free_result($result);
}

// Handle form submissions for adding/editing/deleting routines
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $class_id = (int)($_POST['class_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $day_of_week = trim($_POST['day_of_week'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');

        // Validate time inputs
        $start = strtotime($start_time);
        $end = strtotime($end_time);
        if ($start && $end && $end <= $start) {
            $error = "End time must be after start time.";
        } elseif ($action === 'add') {
            // Validate input
            if ($class_id <= 0 || $teacher_id <= 0 || empty($day_of_week) || empty($start_time) || empty($end_time)) {
                $error = "All fields are required.";
            } else {
                $query = "INSERT INTO class_routines (class_id, teacher_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($db_conn, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "iisss", $class_id, $teacher_id, $day_of_week, $start_time, $end_time);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Routine added successfully.";
                    } else {
                        $error = "Failed to add routine: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Prepare failed: " . mysqli_error($db_conn);
                }
            }
        } elseif ($action === 'edit') {
            $routine_id = (int)($_POST['routine_id'] ?? 0);
            if ($routine_id <= 0) {
                $error = "Invalid routine ID.";
            } elseif ($class_id <= 0 || $teacher_id <= 0 || empty($day_of_week) || empty($start_time) || empty($end_time)) {
                $error = "All fields are required.";
            } else {
                $query = "UPDATE class_routines SET class_id = ?, teacher_id = ?, day_of_week = ?, start_time = ?, end_time = ? WHERE id = ?";
                $stmt = mysqli_prepare($db_conn, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "iisssi", $class_id, $teacher_id, $day_of_week, $start_time, $end_time, $routine_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Routine updated successfully.";
                    } else {
                        $error = "Failed to update routine: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Prepare failed: " . mysqli_error($db_conn);
                }
            }
        } elseif ($action === 'delete') {
            $routine_id = (int)($_POST['routine_id'] ?? 0);
            if ($routine_id <= 0) {
                $error = "Invalid routine ID.";
            } else {
                $query = "DELETE FROM class_routines WHERE id = ?";
                $stmt = mysqli_prepare($db_conn, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $routine_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Routine deleted successfully.";
                    } else {
                        $error = "Failed to delete routine: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Prepare failed: " . mysqli_error($db_conn);
                }
            }
        }
    }
}

// Fetch all routines with class and teacher details
$query = "SELECT cr.id, cr.class_id, c.name AS class_name, c.section, cr.teacher_id, u.username AS teacher_name, cr.day_of_week, cr.start_time, cr.end_time, cr.created_at 
          FROM class_routines cr 
          LEFT JOIN classes c ON cr.class_id = c.id 
          LEFT JOIN users u ON cr.teacher_id = u.id 
          ORDER BY cr.day_of_week, cr.start_time";
$result = mysqli_query($db_conn, $query);
$routines = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $routines[] = $row;
    }
    mysqli_free_result($result);
} else {
    $error = "Failed to fetch routines: " . mysqli_error($db_conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class Routines - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <style>
        .main-header {
            background: linear-gradient(135deg, #3923a7, #5a4fc4);
            color: white;
        }
        .brand-link {
            background: linear-gradient(135deg, #3923a7, #5a4fc4);
        }
        .sidebar-dark-primary .nav-sidebar .nav-link.active {
            background: rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }
        .content-header {
            background-color: #e9ecef;
        }
        .date-time-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        .table {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .breadcrumb {
            background: transparent;
        }
        .content-header h1 {
            color: #2c3e50;
            font-weight: 700;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light" style="background: linear-gradient(135deg, #3923a7, #5a4fc4); color: #fff;">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="#" class="nav-link">Contact</a>
            </li>
        </ul>
        <div class="input-group input-group-sm mx-3" style="width: 150px;">
            <input type="text" class="form-control" placeholder="Search">
            <div class="input-group-append">
                <button class="btn btn-default" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="dropdown">
                    <i class="fas fa-bell"></i> <span class="badge badge-warning navbar-badge">15</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/hrproject/admin/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link">
            <img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">SMS Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
                    <li class="nav-item">
                        <a href="/hrproject/admin/dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>
                                Manage Accounts
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-accounts.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>All Accounts</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-book"></i>
                            <p>
                                Manage Classes
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-classes.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Classes</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview menu-open">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>
                                Manage Class Routines
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-class-routines.php" class="nav-link active">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Routines</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                      <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-table"></i>
                            <p>
                                Manage Timetable
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-timetable.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Timetable</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-book-open"></i>
                            <p>
                                Manage Subjects
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-subjects.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Subjects</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>
                                Manage Examinations
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-examinations.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Exams</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-check-square"></i>
                            <p>
                                Manage Attendance
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-attendance.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Attendance</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calculator"></i>
                            <p>
                                Manage Accountings
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-accountings.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Fees</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>
                                Manage Events
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-events.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Events</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                
                 
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>
                                Manage Reports
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-reports.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reports</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-bell"></i>
                            <p>
                                Manage Notifications
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-notifications.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Notifications</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-sliders-h"></i>
                            <p>
                                Manage Settings
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-settings.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Settings</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                   
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Manage Class Routines</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/hrproject/admin/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Manage Class Routines</li>
                        </ol>
                    </div>
                    <div class="col-12 date-time-info">
                        <small>Current Date and Time: <?php echo date('l, F d, Y, h:i A T'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Routine</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label for="class_id">Class</label>
                                <select class="form-control" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name'] . ' - ' . $class['section']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="teacher_id">Teacher</label>
                                <select class="form-control" id="teacher_id" name="teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="day_of_week">Day of Week</label>
                                <select class="form-control" id="day_of_week" name="day_of_week" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="start_time">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="form-group">
                                <label for="end_time">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Routine</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Routines</h3>
                    </div>
                    <div class="card-body">
                        <table id="routinesTable" class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Teacher</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routines as $routine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($routine['id']); ?></td>
                                        <td><?php echo htmlspecialchars($routine['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($routine['section']); ?></td>
                                        <td><?php echo htmlspecialchars($routine['teacher_name']); ?></td>
                                        <td><?php echo htmlspecialchars($routine['day_of_week']); ?></td>
                                        <td><?php echo htmlspecialchars($routine['start_time'] . ' - ' . $routine['end_time']); ?></td>
                                        <td><?php echo htmlspecialchars($routine['created_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editModal<?php echo $routine['id']; ?>">Edit</button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="routine_id" value="<?php echo $routine['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this routine?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <div class="modal fade" id="editModal<?php echo $routine['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Routine</h5>
                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="routine_id" value="<?php echo $routine['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="class_id<?php echo $routine['id']; ?>">Class</label>
                                                            <select class="form-control" id="class_id<?php echo $routine['id']; ?>" name="class_id" required>
                                                                <option value="">Select Class</option>
                                                                <?php foreach ($classes as $class): ?>
                                                                    <option value="<?php echo $class['id']; ?>" <?php echo $class['id'] == $routine['class_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['section']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="teacher_id<?php echo $routine['id']; ?>">Teacher</label>
                                                            <select class="form-control" id="teacher_id<?php echo $routine['id']; ?>" name="teacher_id" required>
                                                                <option value="">Select Teacher</option>
                                                                <?php foreach ($teachers as $teacher): ?>
                                                                    <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher['id'] == $routine['teacher_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($teacher['username']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="day_of_week<?php echo $routine['id']; ?>">Day of Week</label>
                                                            <select class="form-control" id="day_of_week<?php echo $routine['id']; ?>" name="day_of_week" required>
                                                                <option value="">Select Day</option>
                                                                <option value="Monday" <?php echo $routine['day_of_week'] == 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                                                <option value="Tuesday" <?php echo $routine['day_of_week'] == 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                                                <option value="Wednesday" <?php echo $routine['day_of_week'] == 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                                                <option value="Thursday" <?php echo $routine['day_of_week'] == 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                                                <option value="Friday" <?php echo $routine['day_of_week'] == 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                                                <option value="Saturday" <?php echo $routine['day_of_week'] == 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                                                <option value="Sunday" <?php echo $routine['day_of_week'] == 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="start_time<?php echo $routine['id']; ?>">Start Time</label>
                                                            <input type="time" class="form-control" id="start_time<?php echo $routine['id']; ?>" name="start_time" value="<?php echo htmlspecialchars($routine['start_time']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="end_time<?php echo $routine['id']; ?>">End Time</label>
                                                            <input type="time" class="form-control" id="end_time<?php echo $routine['id']; ?>" name="end_time" value="<?php echo htmlspecialchars($routine['end_time']); ?>" required>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Update Routine</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 3.0.5
        </div>
        <strong>Copyright &copy; 2014-2025 <a href="#">AdminLTE.io</a>. All rights reserved.</strong>
    </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('[data-widget="treeview"]').Treeview('init');
        $('#routinesTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "order": [[4, "asc"], [5, "asc"]]
        });
    });
</script>
</body>
</html>