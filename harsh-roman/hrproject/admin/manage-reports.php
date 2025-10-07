<?php
session_start();
include_once('../includes/config.php');

// Security Check & Initialization
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!$db_conn) { die("Database connection failed: " . mysqli_connect_error()); }

$query_auth = "SELECT role FROM users WHERE id = ?";
$stmt_auth = mysqli_prepare($db_conn, $query_auth);
mysqli_stmt_bind_param($stmt_auth, "i", $admin_id);
mysqli_stmt_execute($stmt_auth);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_auth));
mysqli_stmt_close($stmt_auth);

if (!$user || $user['role'] !== 'admin') {
    header("Location: /hrproject/login.php");
    exit();
}

$report_data = [];
$report_title = '';
$report_type = $_GET['report_type'] ?? '';

// --- DATA FETCHING FOR FILTERS ---
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;

$students = [];
$student_result = mysqli_query($db_conn, "SELECT id, first_name, last_name FROM users WHERE role = 'student' ORDER BY first_name");
if ($student_result) while ($row = mysqli_fetch_assoc($student_result)) $students[] = $row;


// --- REPORT GENERATION LOGIC ---
if (!empty($report_type)) {
    $params = [];
    $types = '';
    
    switch ($report_type) {
        case 'student_list':
            $report_title = "Student List";
            $filter_class_id = (int)($_GET['filter_class_id'] ?? 0);
            $query = "SELECT u.first_name, u.last_name, u.username, u.email, c.name as class_name, c.section 
                      FROM users u 
                      LEFT JOIN classes c ON u.class_id = c.id
                      WHERE u.role = 'student'";
            if ($filter_class_id > 0) {
                $query .= " AND u.class_id = ?";
                $params[] = $filter_class_id;
                $types .= 'i';
                // Find class name for the title
                foreach($classes as $class) { if ($class['id'] == $filter_class_id) $report_title .= " for " . htmlspecialchars($class['name'] . ' - ' . $class['section']); }
            }
            $query .= " ORDER BY c.name, u.first_name";
            break;

        case 'attendance_report':
            $report_title = "Attendance Report";
            $filter_class_id = (int)($_GET['filter_class_id'] ?? 0);
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';

            if (empty($filter_class_id) || empty($start_date) || empty($end_date)) {
                $error = "Class, Start Date, and End Date are required for this report.";
            } else {
                $query = "SELECT att.attendance_date, att.status, u.first_name, u.last_name, s.name as subject_name 
                          FROM attendance att
                          JOIN users u ON att.student_id = u.id
                          JOIN subjects s ON att.subject_id = s.id
                          WHERE att.class_id = ? AND att.attendance_date BETWEEN ? AND ?
                          ORDER BY att.attendance_date, u.first_name";
                $params = [$filter_class_id, $start_date, $end_date];
                $types = 'iss';
                foreach($classes as $class) { if ($class['id'] == $filter_class_id) $report_title .= " for " . htmlspecialchars($class['name'] . ' - ' . $class['section']); }
                $report_title .= " (from $start_date to $end_date)";
            }
            break;

        case 'fee_report':
            $report_title = "Fee Payment Report";
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';
            if (empty($start_date) || empty($end_date)) {
                $error = "Start Date and End Date are required for this report.";
            } else {
                $query = "SELECT p.payment_date, p.amount, p.payment_for_month, u.first_name, u.last_name, c.name as class_name, c.section
                          FROM fee_payments p
                          JOIN users u ON p.student_id = u.id
                          LEFT JOIN classes c ON p.class_id = c.id
                          WHERE p.payment_date BETWEEN ? AND ?
                          ORDER BY p.payment_date DESC";
                $params = [$start_date, $end_date];
                $types = 'ss';
                $report_title .= " (from $start_date to $end_date)";
            }
            break;
    }

    if (isset($query) && empty($error)) {
        $stmt = mysqli_prepare($db_conn, $query);
        if ($stmt) {
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $report_data[] = $row;
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Failed to generate report: " . mysqli_error($db_conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        .sidebar-dark-primary .nav-sidebar .nav-link.active,
        .sidebar-dark-primary .nav-sidebar .nav-treeview>.nav-item>.nav-link.active {
            background-color: rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }
        .content-header { background-color: #f8f9fa; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: none; }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .card-header .btn, .main-sidebar, .main-header, .main-footer { display: none; }
            .content-wrapper { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav"><li class="nav-item"><a class="nav-link text-white" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li></ul>
        <ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link text-white" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link">
            <img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">SMS Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
                    <li class="nav-item"><a href="/hrproject/admin/dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Accounts<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-accounts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>All Accounts</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-book"></i><p>Manage Classes<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-classes.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Classes</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-calendar-alt"></i><p>Manage Class Routines<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-class-routines.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Routines</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-table"></i><p>Manage Timetable<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-timetable.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Timetable</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-book-open"></i><p>Manage Subjects<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-subjects.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Subjects</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Manage Examinations<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-examinations.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Exams</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-check-square"></i><p>Manage Attendance<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-attendance.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Attendance</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-calculator"></i><p>Manage Accountings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-accountings.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Fees</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-calendar-check"></i><p>Manage Events<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-events.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Events</p></a></li></ul></li>
                    <li class="nav-item has-treeview menu-open"><a href="#" class="nav-link active"><i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-reports.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Reports</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-bell"></i><p>Manage Notifications<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-notifications.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Notifications</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-settings.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Settings</p></a></li></ul></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">Generate Reports</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active">Reports</li></ol></div></div></div></div>
        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($error)): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="row">
                    <div class="col-md-4"><div class="card"><div class="card-header"><h3 class="card-title">Student List Report</h3></div><div class="card-body"><form method="GET"><input type="hidden" name="report_type" value="student_list"><div class="form-group"><label>Filter by Class (Optional)</label><select class="form-control" name="filter_class_id"><option value="">All Classes</option><?php foreach($classes as $class):?><option value="<?=$class['id'];?>" <?= (($_GET['filter_class_id'] ?? '') == $class['id']) ? 'selected' : '' ?>><?=htmlspecialchars($class['name'].' - '.$class['section']);?></option><?php endforeach;?></select></div><button type="submit" class="btn btn-primary">Generate</button></form></div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-header"><h3 class="card-title">Attendance Report</h3></div><div class="card-body"><form method="GET"><input type="hidden" name="report_type" value="attendance_report"><div class="form-group"><label>Class</label><select class="form-control" name="filter_class_id" required><option value="">Select Class</option><?php foreach($classes as $class):?><option value="<?=$class['id'];?>" <?= (($_GET['filter_class_id'] ?? '') == $class['id']) ? 'selected' : '' ?>><?=htmlspecialchars($class['name'].' - '.$class['section']);?></option><?php endforeach;?></select></div><div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" required></div><div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" required></div><button type="submit" class="btn btn-primary">Generate</button></form></div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-header"><h3 class="card-title">Fee Payment Report</h3></div><div class="card-body"><form method="GET"><input type="hidden" name="report_type" value="fee_report"><div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" required></div><div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" required></div><button type="submit" class="btn btn-primary">Generate</button></form></div></div></div>
                </div>

                <?php if (!empty($report_data)): ?>
                <div class="card print-area">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><?= htmlspecialchars($report_title); ?></h3><button onclick="window.print();" class="btn btn-secondary btn-sm float-right"><i class="fas fa-print"></i> Print</button></div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <?php if ($report_type === 'student_list'): ?>
                                <thead><tr><th>Student Name</th><th>Username</th><th>Email</th><th>Class</th></tr></thead>
                                <tbody><?php foreach($report_data as $row): ?><tr><td><?=htmlspecialchars($row['first_name'].' '.$row['last_name']);?></td><td><?=htmlspecialchars($row['username']);?></td><td><?=htmlspecialchars($row['email']);?></td><td><?=htmlspecialchars($row['class_name'].' - '.$row['section']);?></td></tr><?php endforeach; ?></tbody>
                            <?php elseif ($report_type === 'attendance_report'): ?>
                                <thead><tr><th>Date</th><th>Student Name</th><th>Subject</th><th>Status</th></tr></thead>
                                <tbody><?php foreach($report_data as $row): ?><tr><td><?=date('d M, Y', strtotime($row['attendance_date']));?></td><td><?=htmlspecialchars($row['first_name'].' '.$row['last_name']);?></td><td><?=htmlspecialchars($row['subject_name']);?></td><td><?=htmlspecialchars($row['status']);?></td></tr><?php endforeach; ?></tbody>
                            <?php elseif ($report_type === 'fee_report'): ?>
                                <thead><tr><th>Date</th><th>Student Name</th><th>Class</th><th>Amount</th><th>Description</th></tr></thead>
                                <tbody><?php foreach($report_data as $row): ?><tr><td><?=date('d M, Y', strtotime($row['payment_date']));?></td><td><?=htmlspecialchars($row['first_name'].' '.$row['last_name']);?></td><td><?=htmlspecialchars($row['class_name'] ? $row['class_name'].' - '.$row['section'] : 'N/A');?></td><td>₹<?=htmlspecialchars(number_format($row['amount'],2));?></td><td><?=htmlspecialchars($row['payment_for_month']);?></td></tr><?php endforeach; ?></tbody>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <?php elseif (!empty($report_type) && empty($error)): ?>
                <div class="alert alert-info">No records found for the selected criteria.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <footer class="main-footer"><strong>Copyright &copy; 2024-<?= date('Y'); ?> <a href="#">Your School</a>.</strong></footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>