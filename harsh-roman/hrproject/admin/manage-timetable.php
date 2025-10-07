<?php
session_start();
include_once('../includes/config.php');

// --- START: Admin Authentication ---
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}
if (!$db_conn) { die("Database connection failed: " . mysqli_connect_error()); }

$query = "SELECT role FROM users WHERE id = ?";
$stmt = mysqli_prepare($db_conn, $query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user || $user['role'] !== 'admin') {
    header("Location: /hrproject/login.php");
    exit();
}
// --- END: Admin Authentication ---


// --- START: Form Submission Logic (Add, Edit, Delete) ---
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $timetable_id = (int)($_POST['timetable_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $day = trim($_POST['day'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');

    if (in_array($action, ['add', 'edit'])) {
        if ($class_id <= 0 || $subject_id <= 0 || empty($day) || empty($start_time) || empty($end_time)) {
            $error = "Class, Subject, Day, and Times are required.";
        }
    }

    if (empty($error)) {
        if ($action === 'add') {
            $query = "INSERT INTO class_routines (class_id, teacher_id, subject_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "iiisss", $class_id, $teacher_id, $subject_id, $day, $start_time, $end_time);
            if (mysqli_stmt_execute($stmt)) $success = "Timetable entry added."; else $error = "Failed to add entry.";
        } elseif ($action === 'edit' && $timetable_id > 0) {
            $query = "UPDATE class_routines SET class_id = ?, teacher_id = ?, subject_id = ?, day_of_week = ?, start_time = ?, end_time = ? WHERE id = ?";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "iiisssi", $class_id, $teacher_id, $subject_id, $day, $start_time, $end_time, $timetable_id);
            if (mysqli_stmt_execute($stmt)) $success = "Timetable entry updated."; else $error = "Failed to update entry.";
        } elseif ($action === 'delete' && $timetable_id > 0) {
            $query = "DELETE FROM class_routines WHERE id = ?";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $timetable_id);
            if (mysqli_stmt_execute($stmt)) $success = "Timetable entry deleted."; else $error = "Failed to delete entry.";
        }
    }
}
// --- END: Form Submission Logic ---

// --- START: Fetch Data for Display ---
$classes = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section")->fetch_all(MYSQLI_ASSOC);
$teachers = mysqli_query($db_conn, "SELECT id, username FROM users WHERE role = 'teacher' ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$subjects = mysqli_query($db_conn, "SELECT id, name FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$timetable_grid = [];
$query = "SELECT cr.id, cr.class_id, cr.teacher_id, cr.subject_id, cr.day_of_week, cr.start_time, cr.end_time, c.name as class_name, s.name as subject_name, u.username as teacher_name 
          FROM class_routines cr 
          JOIN classes c ON cr.class_id = c.id
          JOIN subjects s ON cr.subject_id = s.id
          LEFT JOIN users u ON cr.teacher_id = u.id";
$result = mysqli_query($db_conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['day_of_week'] . '|' . $row['start_time'] . '|' . $row['class_id'];
        $timetable_grid[$key] = $row;
    }
}

$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
$time_slots = [
    "08:00:00" => "09:00:00",
    "09:00:00" => "10:00:00",
    "10:00:00" => "11:00:00",
    "11:00:00" => "12:00:00",
    "12:00:00" => "13:00:00"
];
// --- END: Fetch Data for Display ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
        .navbar-light .navbar-nav .nav-link:hover { color: white; }
        .timetable-table { table-layout: fixed; }
        .timetable-table th, .timetable-table td { text-align: center; vertical-align: middle; border: 1px solid #dee2e6; }
        .timetable-table .time-slot { font-weight: bold; width: 120px; }
        .timetable-entry { padding: 8px; border-radius: 8px; background-color: #e9ecef; position: relative; }
        .add-btn { cursor: pointer; color: #007bff; }
        .entry-actions { position: absolute; top: 5px; right: 5px; }
        .entry-actions .btn { padding: 0.1rem 0.3rem; font-size: 0.7rem; }

        /* --- NEW: Styles for Printing --- */
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .main-sidebar, .main-header, .entry-actions, .add-btn, .nav-pills, .card-header .card-title, .content-header, .alert {
                display: none !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
            }
            .timetable-entry {
                box-shadow: none;
                border: 1px solid #ccc;
            }
            .tab-pane.active {
                display: block !important;
                opacity: 1 !important;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li></ul>
        <ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
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
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Visual Timetable</h1></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $success ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $error ?></div><?php endif; ?>
                
                <div class="card print-area">
                    <div class="card-header">
                        <h3 class="card-title">Click a class and a time slot to add or edit an entry.</h3>
                        <div class="card-tools">
                            <button onclick="window.print();" class="btn btn-primary"><i class="fas fa-print"></i> Print Timetable</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="nav nav-pills p-3">
                            <?php foreach($classes as $index => $class): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $index === 0 ? 'active' : '' ?>" href="#class_<?= $class['id'] ?>" data-toggle="tab">
                                        <?= htmlspecialchars($class['name'] . ' - ' . $class['section']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tab-content">
                            <?php foreach($classes as $index => $class): ?>
                                <div class="tab-pane <?= $index === 0 ? 'active' : '' ?>" id="class_<?= $class['id'] ?>">
                                    <h4 class="text-center mt-3 d-none print-header">Timetable for <?= htmlspecialchars($class['name'] . ' - ' . $class['section']) ?></h4>
                                    <table class="table table-bordered timetable-table">
                                        <thead>
                                            <tr>
                                                <th class="time-slot">Time</th>
                                                <?php foreach ($days as $day): ?>
                                                    <th><?= $day ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($time_slots as $start_time => $end_time): ?>
                                                <tr>
                                                    <td class="time-slot"><?= date('g:i A', strtotime($start_time)) ?></td>
                                                    <?php foreach ($days as $day): ?>
                                                        <td>
                                                            <?php
                                                                $key = $day . '|' . $start_time . '|' . $class['id'];
                                                                if (isset($timetable_grid[$key])):
                                                                    $entry = $timetable_grid[$key];
                                                            ?>
                                                                <div class="timetable-entry">
                                                                    <strong><?= htmlspecialchars($entry['subject_name']) ?></strong>
                                                                    <small><?= htmlspecialchars($entry['teacher_name'] ?? 'N/A') ?></small>
                                                                    <div class="entry-actions">
                                                                        <button class="btn btn-sm btn-info edit-btn" data-entry='<?= htmlspecialchars(json_encode($entry), ENT_QUOTES, 'UTF-8') ?>' data-toggle="modal" data-target="#timetableModal"><i class="fas fa-edit"></i></button>
                                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                                                            <input type="hidden" name="action" value="delete"><input type="hidden" name="timetable_id" value="<?= $entry['id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <i class="fas fa-plus-circle fa-2x add-btn" data-class-id="<?= $class['id'] ?>" data-day="<?= $day ?>" data-start-time="<?= $start_time ?>" data-end-time="<?= $end_time ?>" data-toggle="modal" data-target="#timetableModal"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="timetableModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="timetableForm" method="POST">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Add/Edit Entry</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="modalAction"><input type="hidden" name="timetable_id" id="modalTimetableId">
                <div class="form-group"><label>Class</label><select class="form-control" name="class_id" id="modalClassId" required>
                    <?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="form-group"><label>Subject</label><select class="form-control" name="subject_id" id="modalSubjectId" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $subject): ?><option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="form-group"><label>Teacher</label><select class="form-control" name="teacher_id" id="modalTeacherId">
                    <option value="0">N/A</option>
                    <?php foreach ($teachers as $teacher): ?><option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['username']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="row">
                    <div class="col-md-4 form-group"><label>Day</label><input type="text" class="form-control" name="day" id="modalDay" readonly></div>
                    <div class="col-md-4 form-group"><label>Start Time</label><input type="time" class="form-control" name="start_time" id="modalStartTime" readonly></div>
                    <div class="col-md-4 form-group"><label>End Time</label><input type="time" class="form-control" name="end_time" id="modalEndTime" readonly></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" id="modalSubmitBtn">Save</button></div>
        </form>
    </div></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    $('.add-btn').on('click', function() {
        $('#timetableForm')[0].reset();
        $('#modalTitle').text('Add Timetable Entry');
        $('#modalAction').val('add');
        $('#modalTimetableId').val('');
        $('#modalSubmitBtn').text('Add Entry');
        $('#modalClassId').val($(this).data('class-id'));
        $('#modalDay').val($(this).data('day'));
        $('#modalStartTime').val($(this).data('start-time'));
        $('#modalEndTime').val($(this).data('end-time'));
        $('#modalClassId').prop('disabled', true);
    });
    $('.edit-btn').on('click', function() {
        $('#timetableForm')[0].reset();
        var entry = $(this).data('entry');
        $('#modalTitle').text('Edit Timetable Entry');
        $('#modalAction').val('edit');
        $('#modalTimetableId').val(entry.id);
        $('#modalSubmitBtn').text('Update Entry');
        $('#modalClassId').val(entry.class_id);
        $('#modalSubjectId').val(entry.subject_id);
        $('#modalTeacherId').val(entry.teacher_id);
        $('#modalDay').val(entry.day_of_week);
        $('#modalStartTime').val(entry.start_time);
        $('#modalEndTime').val(entry.end_time);
        $('#modalClassId').prop('disabled', false);
    });
    $('#timetableForm').on('submit', function() {
        $('#modalClassId').prop('disabled', false);
    });
});
</script>
</body>
</html>