<?php
session_start();
include_once('../includes/config.php');

// Initialize variables
$success_msg = '';
$error = '';

// Check if user is logged in and is an admin
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];

// Verify database connection
if (!$db_conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$query = "SELECT role, id AS user_id_for_edit FROM users WHERE id = ?";
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


// --- HANDLE AJAX REQUEST TO GET STUDENTS ---
if (isset($_POST['get_students'])) {
    $class_id = (int)$_POST['class_id'];
    $subject_id = (int)$_POST['subject_id'];
    $date = $_POST['date'];

    $existing_attendance = [];
    $stmt = mysqli_prepare($db_conn, "SELECT student_id, status FROM attendance WHERE class_id = ? AND subject_id = ? AND attendance_date = ?");
    if($stmt === false) { die("AJAX Error (prepare attendance): " . mysqli_error($db_conn)); }
    mysqli_stmt_bind_param($stmt, "iis", $class_id, $subject_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $existing_attendance[$row['student_id']] = $row['status'];
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db_conn, "SELECT id, first_name, last_name FROM users WHERE role = 'student' AND class_id = ? ORDER BY first_name ASC");
    if($stmt === false) { die("AJAX Error (prepare students): " . mysqli_error($db_conn)); }
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $students_result = mysqli_stmt_get_result($stmt);
    
    $html = '<hr><label>Student List</label><div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>#</th><th>Student Name</th><th>Status</th></tr></thead><tbody>';
    if ($students_result && mysqli_num_rows($students_result) > 0) {
        $count = 1;
        while ($student = mysqli_fetch_assoc($students_result)) {
            $status = $existing_attendance[$student['id']] ?? 'Present';
            $html .= '<tr><td>' . $count++ . '</td><td>' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</td><td><select class="form-control" name="status[' . $student['id'] . ']"><option value="Present"' . ($status == 'Present' ? ' selected' : '') . '>Present</option><option value="Absent"' . ($status == 'Absent' ? ' selected' : '') . '>Absent</option><option value="Late"' . ($status == 'Late' ? ' selected' : '') . '>Late</option><option value="Excused"' . ($status == 'Excused' ? ' selected' : '') . '>Excused</option></select></td></tr>';
        }
    } else { $html .= '<tr><td colspan="3" class="text-center">No students found in this class.</td></tr>'; }
    $html .= '</tbody></table></div>';
    echo $html;
    exit;
}

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Save Attendance
    if (isset($_POST['submit_attendance'])) {
        $class_id = (int)$_POST['class_id'];
        $subject_id = (int)$_POST['subject_id'];
        $attendance_date = $_POST['attendance_date'];
        $statuses = $_POST['status'] ?? [];

        if (empty($class_id) || empty($subject_id) || empty($attendance_date) || empty($statuses)) {
            $error = 'Please select class, subject, date, and ensure students are loaded.';
        } else {
            $query = "INSERT INTO attendance (student_id, class_id, subject_id, attendance_date, status, taken_by_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), taken_by_id = VALUES(taken_by_id)";
            $stmt = mysqli_prepare($db_conn, $query);
            $total_count = 0;
            foreach ($statuses as $student_id => $status) {
                mysqli_stmt_bind_param($stmt, "iiissi", $student_id, $class_id, $subject_id, $attendance_date, $status, $admin_id);
                mysqli_stmt_execute($stmt);
                $total_count++;
            }
            mysqli_stmt_close($stmt);
            $success_msg = "$total_count attendance records have been successfully saved/updated.";
        }
    }

    // Handle Delete Attendance Record
    if (isset($_POST['delete_attendance'])) {
        $attendance_id = (int)$_POST['attendance_id'];
        $stmt = mysqli_prepare($db_conn, "DELETE FROM attendance WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $attendance_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Attendance record deleted successfully.";
        } else {
            $error = "Failed to delete record.";
        }
        mysqli_stmt_close($stmt);
    }
}

// --- FETCH DATA FOR PAGE DISPLAY ---

// Fetch data for dropdowns
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;
$subjects = [];
$subject_result = mysqli_query($db_conn, "SELECT id, name FROM subjects ORDER BY name");
if ($subject_result) while ($row = mysqli_fetch_assoc($subject_result)) $subjects[] = $row;

// Fetch data for the attendance log
$attendance_log = [];
$filter_class_id = $_GET['filter_class_id'] ?? null;
$filter_date = $_GET['filter_date'] ?? null;

$log_query = "SELECT att.id, att.attendance_date, att.status, 
              u.first_name, u.last_name, 
              c.name as class_name, c.section, 
              s.name as subject_name
              FROM attendance att
              JOIN users u ON att.student_id = u.id
              JOIN classes c ON att.class_id = c.id
              JOIN subjects s ON att.subject_id = s.id
              WHERE 1=1";
$params = [];
$types = '';

if (!empty($filter_class_id)) {
    $log_query .= " AND att.class_id = ?";
    $params[] = $filter_class_id;
    $types .= 'i';
}
if (!empty($filter_date)) {
    $log_query .= " AND att.attendance_date = ?";
    $params[] = $filter_date;
    $types .= 's';
}
$log_query .= " ORDER BY att.attendance_date DESC, c.name, s.name, u.first_name";

$stmt_log = mysqli_prepare($db_conn, $log_query);

if ($stmt_log === false) {
    $error = "Database query error for attendance log: " . mysqli_error($db_conn);
} else {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_log, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_log);
    $result_log = mysqli_stmt_get_result($stmt_log);
    if ($result_log) {
        while ($row = mysqli_fetch_assoc($result_log)) {
            $attendance_log[] = $row;
        }
    }
    mysqli_stmt_close($stmt_log);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - SMS Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        .sidebar-dark-primary .nav-sidebar .nav-link.active { background: rgba(255, 193, 7, 0.3); color: #ffc107; }
        .content-header { background-color: #f8f9fa; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: none; }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light"><ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li><li class="nav-item d-none d-sm-inline-block"><a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a></li></ul><ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></nav>
    
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link">
            <img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3">
            <span class="brand-text font-weight-light">SMS Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
                    <li class="nav-item">
                        <a href="/hrproject/admin/dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-users"></i><p>Manage Accounts<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-accounts.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>All Accounts</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-book"></i><p>Manage Classes<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-classes.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Classes</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-alt"></i><p>Manage Class Routines<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-class-routines.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Routines</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-table"></i><p>Manage Timetable<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-timetable.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Timetable</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-book-open"></i><p>Manage Subjects<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-subjects.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Subjects</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i><p>Manage Examinations<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-examinations.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Exams</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview menu-open">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-check-square"></i><p>Manage Attendance<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-attendance.php" class="nav-link active">
                                    <i class="far fa-circle nav-icon"></i><p>Attendance</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calculator"></i><p>Manage Accountings<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-accountings.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Fees</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-calendar-check"></i><p>Manage Events<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-events.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Events</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-reports.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Reports</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-bell"></i><p>Manage Notifications<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-notifications.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Notifications</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-settings.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i><p>Settings</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                  
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Attendance</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/hrproject/admin/dashboard.php">Home</a></li><li class="breadcrumb-item active">Attendance</li></ol></div></div></div></div>
        <section class="content">
            <div class="container-fluid">
                <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($success_msg); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="card"><div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-edit text-primary"></i> Record Attendance</h3></div>
                    <div class="card-body">
                        <form id="attendance-form" method="POST" action="">
                            <div class="row"><div class="col-md-4 form-group"><label for="class_id">Class</label><select class="form-control" id="class_id" name="class_id" required><option value="">-- Select a Class --</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select></div><div class="col-md-4 form-group"><label for="subject_id">Subject</label><select class="form-control" id="subject_id" name="subject_id" required><option value="">-- Select a Subject --</option><?php foreach ($subjects as $subject): ?><option value="<?= $subject['id']; ?>"><?= htmlspecialchars($subject['name']); ?></option><?php endforeach; ?></select></div><div class="col-md-4 form-group"><label for="attendance_date">Date</label><input type="date" class="form-control" id="attendance_date" name="attendance_date" value="<?= date('Y-m-d'); ?>" required></div></div>
                            <div id="students_list"></div>
                            <button type="submit" name="submit_attendance" class="btn btn-primary mt-3"><i class="fas fa-save"></i> Submit Attendance</button>
                        </form>
                    </div>
                </div>

                <div class="card"><div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-history text-primary"></i> Attendance Log</h3></div>
                    <div class="card-body">
                        <form method="GET" action="" class="mb-3">
                            <div class="row"><div class="col-md-5 form-group"><label>Filter by Class</label><select class="form-control" name="filter_class_id"><option value="">All Classes</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>" <?= ($filter_class_id == $class['id'] ? 'selected' : ''); ?>><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select></div><div class="col-md-5 form-group"><label>Filter by Date</label><input type="date" class="form-control" name="filter_date" value="<?= htmlspecialchars($filter_date ?? ''); ?>"></div><div class="col-md-2 form-group d-flex align-items-end"><button type="submit" class="btn btn-secondary btn-block">Filter</button></div></div>
                        </form>
                        <table id="attendanceLogTable" class="table table-bordered table-striped">
                            <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($attendance_log as $log): ?>
                                <tr>
                                    <td><?= date('d M, Y', strtotime($log['attendance_date'])); ?></td>
                                    <td><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                    <td><?= htmlspecialchars($log['class_name'] . ' - ' . $log['section']); ?></td>
                                    <td><?= htmlspecialchars($log['subject_name']); ?></td>
                                    <td><?= htmlspecialchars($log['status']); ?></td>
                                    <td><form method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');"><input type="hidden" name="attendance_id" value="<?= $log['id']; ?>"><button type="submit" name="delete_attendance" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button></form></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer"><strong>Copyright &copy; 2024-<?= date('Y'); ?> <a href="#">Your School</a>.</strong></footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable for the log
    $('#attendanceLogTable').DataTable({
        "order": [[ 0, "desc" ]] // Sort by date descending by default
    });

    // AJAX function to fetch students
    function fetchStudents() {
        var class_id = $('#class_id').val();
        var subject_id = $('#subject_id').val();
        var date = $('#attendance_date').val();
        if (class_id && subject_id && date) {
            $('#students_list').html('<p class="text-center my-3">Loading students...</p>');
            $.ajax({
                url: 'manage-attendance.php', type: 'POST',
                data: { get_students: 1, class_id: class_id, subject_id: subject_id, date: date },
                success: function(data) { $('#students_list').html(data); },
                error: function() { $('#students_list').html('<p class="text-danger">Failed to load students.</p>'); }
            });
        } else {
            $('#students_list').empty();
        }
    }
    $('#class_id, #subject_id, #attendance_date').on('change', fetchStudents);
});
</script>
</body>
</html>