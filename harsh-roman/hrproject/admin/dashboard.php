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

// Fetch summary counts with error handling
function getCount($db_conn, $sql) {
    $result = mysqli_query($db_conn, $sql);
    if ($result === false) {
        error_log("Query failed: " . mysqli_error($db_conn));
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    return $row ? (int)$row['count'] : 0;
}

// Updated queries to match database schema
$user_count = getCount($db_conn, "SELECT COUNT(*) as count FROM users");
$class_count = getCount($db_conn, "SELECT COUNT(*) as count FROM classes");
$subject_count = getCount($db_conn, "SELECT COUNT(*) as count FROM subjects");
$routine_count = getCount($db_conn, "SELECT COUNT(*) as count FROM class_routines");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
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
        .info-box {
            min-height: 100px;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .info-box-icon {
            font-size: 2.5rem;
            color: #007bff;
            margin-bottom: 10px;
        }
        .info-box h4 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .info-box h3 {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .welcome-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .welcome-card .card-body {
            padding: 2rem;
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
                        <a href="/hrproject/admin/dashboard.php" class="nav-link active">
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
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>
                                Manage Class Routines
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-class-routines.php" class="nav-link">
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
                            <i class="nav-icon fas fa-folder-open"></i>
                            <p>
                                Manage Study Materials
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-study-materials.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Study Materials</p>
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
                        <h1 class="m-0">Admin Dashboard</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/hrproject/admin/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
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
                <!-- Welcome Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card welcome-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h2>Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</h2>
                                        <p class="lead">Here's what's happening with your school today. Check out key metrics and recent activities.</p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <i class="fas fa-chalkboard-teacher fa-3x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-users"></i></span>
                            <div class="info-box-content">
                                <h4>Total Users</h4>
                                <h3><?php echo htmlspecialchars($user_count); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-school"></i></span>
                            <div class="info-box-content">
                                <h4>Total Classes</h4>
                                <h3><?php echo htmlspecialchars($class_count); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-book"></i></span>
                            <div class="info-box-content">
                                <h4>Total Subjects</h4>
                                <h3><?php echo htmlspecialchars($subject_count); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                            <div class="info-box-content">
                                <h4>Total Routines</h4>
                                <h3><?php echo htmlspecialchars($routine_count); ?></h3>
                            </div>
                        </div>
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
<script>
    $(document).ready(function() {
        $('[data-widget="treeview"]').Treeview('init');
    });
</script>
</body>
</html>
