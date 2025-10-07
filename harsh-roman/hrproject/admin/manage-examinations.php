<?php
session_start();
include_once('../includes/config.php');

// Initialize variables
$success = '';
$error = '';

// Check if user is logged in and is an admin
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}

// Verify database connection
if (!$db_conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Authenticate user as admin
$query = "SELECT role FROM users WHERE id = ?";
$stmt = mysqli_prepare($db_conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    die("Prepare failed: " . mysqli_error($db_conn));
}

if (!$user || $user['role'] !== 'admin') {
    header("Location: /hrproject/login.php");
    exit();
}

// ======================================================================
//  HANDLE FORM SUBMISSIONS (ADD/EDIT/DELETE EXAMS)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0);
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $total_marks = (int)($_POST['total_marks'] ?? 100);

        if (empty($name) || empty($class_id) || empty($start_date) || empty($end_date)) {
            $error = "Name, Class, Start Date, and End Date are required.";
        } else {
            if ($action === 'add') {
                $query = "INSERT INTO examinations (name, class_id, start_date, end_date, total_marks) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($db_conn, $query);
                mysqli_stmt_bind_param($stmt, "sissi", $name, $class_id, $start_date, $end_date, $total_marks);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Examination added successfully.";
                } else {
                    $error = "Failed to add examination: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } elseif ($action === 'edit') {
                $exam_id = (int)($_POST['exam_id'] ?? 0);
                if ($exam_id > 0) {
                    $query = "UPDATE examinations SET name = ?, class_id = ?, start_date = ?, end_date = ?, total_marks = ? WHERE id = ?";
                    $stmt = mysqli_prepare($db_conn, $query);
                    mysqli_stmt_bind_param($stmt, "sissii", $name, $class_id, $start_date, $end_date, $total_marks, $exam_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Examination updated successfully.";
                    } else {
                        $error = "Failed to update examination: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Invalid Examination ID.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        if ($exam_id > 0) {
            $query = "DELETE FROM examinations WHERE id = ?";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $exam_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Examination deleted successfully.";
            } else {
                $error = "Failed to delete examination: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Invalid Examination ID.";
        }
    }
}

// ======================================================================
//  FETCH DATA FOR PAGE DISPLAY
// ======================================================================

// Fetch classes for dropdowns
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;

// Fetch all examinations for the table
$examinations = [];
$query = "SELECT e.id, e.name, e.class_id, e.start_date, e.end_date, e.total_marks, c.name AS class_name, c.section
          FROM examinations e
          LEFT JOIN classes c ON e.class_id = c.id 
          ORDER BY e.start_date DESC";
$result = mysqli_query($db_conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $examinations[] = $row;
} else {
    $error = "Failed to fetch examinations: " . mysqli_error($db_conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Examinations - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        /* The .active class rule has been REMOVED from here */
        .content-header { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card-header { border-bottom: none; background-color: transparent; }
        .breadcrumb { background: transparent; }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
        .navbar-light .navbar-nav .nav-link:hover { color: white; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
            <li class="nav-item d-none d-sm-inline-block"><a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a class="nav-link" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
    
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
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-accounts.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i><p>Manage Accounts</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-classes.php" class="nav-link">
                             <i class="nav-icon fas fa-school"></i><p>Manage Classes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-class-routines.php" class="nav-link">
                             <i class="nav-icon fas fa-calendar-alt"></i><p>Manage Class Routines</p>
                        </a>
                    </li>
                     <li class="nav-item">
                        <a href="/hrproject/admin/manage-timetable.php" class="nav-link">
                             <i class="nav-icon fas fa-table"></i><p>Manage Timetable</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-subjects.php" class="nav-link">
                            <i class="nav-icon fas fa-book-open"></i><p>Manage Subjects</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview menu-open">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-file-alt"></i><p>Manage Examinations<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="/hrproject/admin/manage-examinations.php" class="nav-link active">
                                    <i class="far fa-circle nav-icon"></i><p>Exams</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-attendance.php" class="nav-link">
                            <i class="nav-icon fas fa-check-square"></i><p>Manage Attendance</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-accountings.php" class="nav-link">
                            <i class="nav-icon fas fa-calculator"></i><p>Manage Accountings</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-events.php" class="nav-link">
                             <i class="nav-icon fas fa-calendar-check"></i><p>Manage Events</p>
                        </a>
                    </li>
                
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-reports.php" class="nav-link">
                             <i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-notifications.php" class="nav-link">
                            <i class="nav-icon fas fa-bell"></i><p>Manage Notifications</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/hrproject/admin/manage-settings.php" class="nav-link">
                            <i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings</p>
                        </a>
                    </li>
                   
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Examinations</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/hrproject/admin/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Examinations</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-plus-circle text-primary"></i> Add New Examination</h3></div>
                    <div class="card-body">
                        <form method="POST" action="manage-examinations.php">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-6 form-group"><label>Exam Name</label><input type="text" class="form-control" name="name" placeholder="e.g., Mid-Term Exam" required></div>
                                <div class="col-md-6 form-group">
                                    <label>Class</label>
                                    <select class="form-control" name="class_id" required><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="col-md-4 form-group"><label>Start Date</label><input type="date" class="form-control" name="start_date" required></div>
                                <div class="col-md-4 form-group"><label>End Date</label><input type="date" class="form-control" name="end_date" required></div>
                                <div class="col-md-4 form-group"><label>Total Marks</label><input type="number" class="form-control" name="total_marks" value="100" required></div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Examination</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-list-ul text-primary"></i> All Examinations</h3></div>
                    <div class="card-body">
                        <table id="examsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Exam Name</th><th>Class</th><th>Start Date</th><th>End Date</th><th>Total Marks</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examinations as $exam): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($exam['name']); ?></td>
                                        <td><?= htmlspecialchars($exam['class_name'] . ' - ' . $exam['section']); ?></td>
                                        <td><?= date('d M, Y', strtotime($exam['start_date'])); ?></td>
                                        <td><?= date('d M, Y', strtotime($exam['end_date'])); ?></td>
                                        <td><?= htmlspecialchars($exam['total_marks']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editModal<?= $exam['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                                            <form method="POST" action="manage-examinations.php" style="display:inline-block; margin-left: 5px;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="exam_id" value="<?= $exam['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    
                                    <div class="modal fade" id="editModal<?= $exam['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">Edit Examination</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                                                <div class="modal-body">
                                                    <form method="POST" action="manage-examinations.php">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="exam_id" value="<?= $exam['id']; ?>">
                                                        <div class="form-group"><label>Exam Name</label><input type="text" class="form-control" name="name" value="<?= htmlspecialchars($exam['name']); ?>" required></div>
                                                        <div class="form-group">
                                                            <label>Class</label>
                                                            <select class="form-control" name="class_id" required><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>" <?= ($class['id'] == $exam['class_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select>
                                                        </div>
                                                        <div class="form-group"><label>Start Date</label><input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($exam['start_date']); ?>" required></div>
                                                        <div class="form-group"><label>End Date</label><input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($exam['end_date']); ?>" required></div>
                                                        <div class="form-group"><label>Total Marks</label><input type="number" class="form-control" name="total_marks" value="<?= htmlspecialchars($exam['total_marks']); ?>" required></div>
                                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Examination</button>
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

    <footer class="main-footer"><strong>Copyright &copy; 2024-2025 <a href="#">Your School</a>.</strong> All rights reserved.</footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#examsTable').DataTable({
        "paging": true, "lengthChange": true, "searching": true, "ordering": true,
        "info": true, "autoWidth": false, "responsive": true, "order": [[2, "desc"]]
    });
});
</script>
</body>
</html>