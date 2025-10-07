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
//  DASHBOARD DATA LOGIC (Added from dashboard.php)
// ======================================================================

// ======================================================================
//  SUBJECT MANAGEMENT LOGIC
// ======================================================================

// Handle form submissions for ADD/EDIT/DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    if ($action === 'add') {
        if (empty($name)) {
            $error = "Subject name is required.";
        } else {
            $query = "INSERT INTO subjects (name, class_id, teacher_id) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "sii", $name, $class_id, $teacher_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Subject added successfully.";
            } else {
                $error = "Failed to add subject: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'edit') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        if ($subject_id <= 0 || empty($name)) {
            $error = "Invalid subject ID or name.";
        } else {
            $query = "UPDATE subjects SET name = ?, class_id = ?, teacher_id = ? WHERE id = ?";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "siii", $name, $class_id, $teacher_id, $subject_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Subject updated successfully.";
            } else {
                $error = "Failed to update subject: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'delete') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        if ($subject_id <= 0) {
            $error = "Invalid subject ID.";
        } else {
            $query = "DELETE FROM subjects WHERE id = ?";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $subject_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Subject deleted successfully.";
            } else {
                $error = "Failed to delete subject: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch data for dropdowns
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;

$teachers = [];
$teacher_result = mysqli_query($db_conn, "SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username");
if ($teacher_result) while ($row = mysqli_fetch_assoc($teacher_result)) $teachers[] = $row;

// Fetch all subjects for the table
$subjects = [];
$query = "SELECT s.id, s.name, s.class_id, s.teacher_id, s.created_at, c.name AS class_name, c.section, u.username AS teacher_name FROM subjects s LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN users u ON s.teacher_id = u.id ORDER BY s.created_at DESC";
$result = mysqli_query($db_conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $subjects[] = $row;
} else {
    $error = "Failed to fetch subjects: " . mysqli_error($db_conn);
}
$subject_count = count($subjects);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        /* Active link highlight has been removed for uniform color */
        .content-header { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card-header { border-bottom: none; background-color: transparent; }
        .breadcrumb { background: transparent; }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
        .navbar-light .navbar-nav .nav-link:hover { color: white; }
        .info-box { min-height: 100px; background-color: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 15px; text-align: center; transition: transform 0.3s ease, box-shadow 0.3s ease; border: 1px solid rgba(0,0,0,0.05); }
        .info-box:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .info-box-icon { font-size: 2.5rem; color: #007bff; margin-bottom: 10px; }
        .info-box h4 { margin-bottom: 5px; color: #2c3e50; }
        .info-box h3 { font-size: 2rem; font-weight: bold; color: #007bff; }
        .welcome-card { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li><li class="nav-item d-none d-sm-inline-block"><a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a></li></ul>
        <ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
    </nav>
    
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link">
            <img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3">
            <span class="brand-text font-weight-light">SMS Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
                    <li class="nav-item"><a href="/hrproject/admin/dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-accounts.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Accounts</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-classes.php" class="nav-link"><i class="nav-icon fas fa-school"></i><p>Manage Classes</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-class-routines.php" class="nav-link"><i class="nav-icon fas fa-calendar-alt"></i><p>Manage Class Routines</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-timetable.php" class="nav-link"><i class="nav-icon fas fa-table"></i><p>Manage Timetable</p></a></li>
                    <li class="nav-item has-treeview menu-open">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-book-open"></i><p>Manage Subjects<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="/hrproject/admin/manage-subjects.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Subjects</p></a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-examinations.php" class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Manage Examinations</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-attendance.php" class="nav-link"><i class="nav-icon fas fa-check-square"></i><p>Manage Attendance</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-accountings.php" class="nav-link"><i class="nav-icon fas fa-calculator"></i><p>Manage Accountings</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-events.php" class="nav-link"><i class="nav-icon fas fa-calendar-check"></i><p>Manage Events</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-reports.php" class="nav-link"><i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-notifications.php" class="nav-link"><i class="nav-icon fas fa-bell"></i><p>Manage Notifications</p></a></li>
                    <li class="nav-item"><a href="/hrproject/admin/manage-settings.php" class="nav-link"><i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Subjects</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/hrproject/admin/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Manage Subjects</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
           

                <div class="card mt-4">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-plus-circle text-primary"></i> Add New Subject</h3></div>
                    <div class="card-body">
                        <form method="POST" action="manage-subjects.php">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-4 form-group"><label>Subject Name</label><input type="text" class="form-control" name="name" required></div>
                                <div class="col-md-4 form-group">
                                    <label>Associated Class (Optional)</label>
                                    <select class="form-control" name="class_id"><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Assigned Teacher (Optional)</label>
                                    <select class="form-control" name="teacher_id"><option value="">Select Teacher</option><?php foreach ($teachers as $teacher): ?><option value="<?= $teacher['id']; ?>"><?= htmlspecialchars($teacher['username']); ?></option><?php endforeach; ?></select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Subject</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-list-ul text-primary"></i> All Subjects</h3></div>
                    <div class="card-body">
                        <table id="subjectsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr><th>ID</th><th>Name</th><th>Associated Class</th><th>Assigned Teacher</th><th>Created At</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['id']); ?></td>
                                        <td><?= htmlspecialchars($subject['name']); ?></td>
                                        <td><?= $subject['class_name'] ? htmlspecialchars($subject['class_name'] . ' - ' . $subject['section']) : '<span class="text-muted">N/A</span>'; ?></td>
                                        <td><?= $subject['teacher_name'] ? htmlspecialchars($subject['teacher_name']) : '<span class="text-muted">N/A</span>'; ?></td>
                                        <td><?= htmlspecialchars($subject['created_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editModal<?= $subject['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                                            <form method="POST" action="manage-subjects.php" style="display:inline-block; margin-left: 5px;"><input type="hidden" name="action" value="delete"><input type="hidden" name="subject_id" value="<?= $subject['id']; ?>"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');"><i class="fas fa-trash"></i> Delete</button></form>
                                        </td>
                                    </tr>
                                    <div class="modal fade" id="editModal<?= $subject['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">Edit Subject</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                                                <div class="modal-body">
                                                    <form method="POST" action="manage-subjects.php">
                                                        <input type="hidden" name="action" value="edit"><input type="hidden" name="subject_id" value="<?= $subject['id']; ?>">
                                                        <div class="form-group"><label>Subject Name</label><input type="text" class="form-control" name="name" value="<?= htmlspecialchars($subject['name']); ?>" required></div>
                                                        <div class="form-group">
                                                            <label>Associated Class</label>
                                                            <select class="form-control" name="class_id"><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>" <?= ($class['id'] == $subject['class_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Assigned Teacher</label>
                                                            <select class="form-control" name="teacher_id"><option value="">Select Teacher</option><?php foreach ($teachers as $teacher): ?><option value="<?= $teacher['id']; ?>" <?= ($teacher['id'] == $subject['teacher_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($teacher['username']); ?></option><?php endforeach; ?></select>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Subject</button>
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
    $('#subjectsTable').DataTable({
        "paging": true, "lengthChange": true, "searching": true, "ordering": true,
        "info": true, "autoWidth": false, "responsive": true, "order": [[4, "desc"]]
    });
});
</script>
</body>
</html>