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

// Handle form submissions for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');

        if (empty($title) || empty($start_date)) {
            $error = "Event Title and Start Date are required.";
        } else {
            if ($action === 'add') {
                $query = "INSERT INTO events (title, description, start_date, end_date) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($db_conn, $query);
                mysqli_stmt_bind_param($stmt, "ssss", $title, $description, $start_date, $end_date);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Event added successfully.";
                } else {
                    $error = "Failed to add event: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } elseif ($action === 'edit') {
                $event_id = (int)($_POST['event_id'] ?? 0);
                if ($event_id > 0) {
                    $query = "UPDATE events SET title = ?, description = ?, start_date = ?, end_date = ? WHERE id = ?";
                    $stmt = mysqli_prepare($db_conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssssi", $title, $description, $start_date, $end_date, $event_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Event updated successfully.";
                    } else {
                        $error = "Failed to update event: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Invalid Event ID.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $query = "DELETE FROM events WHERE id = ?";
            $stmt = mysqli_prepare($db_conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $event_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Event deleted successfully.";
            } else {
                $error = "Failed to delete event.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Invalid Event ID.";
        }
    } elseif ($action === 'send_notification') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $class_ids = $_POST['class_ids'] ?? [];

        if (empty($event_id) || empty($class_ids)) {
            $error = "An event and at least one class must be selected to send notifications.";
        } else {
            // 1. Fetch Event Details
            $stmt_event = mysqli_prepare($db_conn, "SELECT title, description, start_date, end_date FROM events WHERE id = ?");
            mysqli_stmt_bind_param($stmt_event, "i", $event_id);
            mysqli_stmt_execute($stmt_event);
            $event_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_event));
            mysqli_stmt_close($stmt_event);

            if ($event_details) {
                // 2. Fetch student emails
                $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
                $types = str_repeat('i', count($class_ids));
                $stmt_students = mysqli_prepare($db_conn, "SELECT email FROM users WHERE role = 'student' AND class_id IN ($placeholders)");
                mysqli_stmt_bind_param($stmt_students, $types, ...$class_ids);
                mysqli_stmt_execute($stmt_students);
                $students_result = mysqli_stmt_get_result($stmt_students);
                
                $recipient_emails = [];
                while ($student = mysqli_fetch_assoc($students_result)) {
                    if (!empty($student['email'])) {
                        $recipient_emails[] = $student['email'];
                    }
                }
                mysqli_stmt_close($stmt_students);

                if (!empty($recipient_emails)) {
                    // 3. Compose and Send Email
                    $subject = "Upcoming Event: " . $event_details['title'];
                    $start = date('d M, Y \a\t h:i A', strtotime($event_details['start_date']));
                    $end = !empty($event_details['end_date']) ? ' to ' . date('d M, Y \a\t h:i A', strtotime($event_details['end_date'])) : '';
                    
                    $body = "Hello Student,\n\n";
                    $body .= "This is a notification for an upcoming event:\n\n";
                    $body .= "Event: " . $event_details['title'] . "\n";
                    $body .= "Date: " . $start . $end . "\n\n";
                    $body .= "Description:\n" . $event_details['description'] . "\n\n";
                    $body .= "Regards,\nSchool Administration";
                    
                    $headers = "From: no-reply@yourschool.com" . "\r\n" .
                               "Bcc: " . implode(",", $recipient_emails);

                    // Note: PHP's mail() function is unreliable. For production, use a library like PHPMailer.
                    if (mail("", $subject, $body, $headers)) {
                        $success = "Event notifications sent to " . count($recipient_emails) . " student(s).";
                    } else {
                        $error = "Failed to send emails. Please check server mail configuration.";
                    }
                } else {
                    $error = "No students with valid email addresses found in the selected class(es).";
                }
            } else {
                $error = "Could not find the selected event.";
            }
        }
    }
}

// Fetch all events for the table
$events = [];
$result = mysqli_query($db_conn, "SELECT id, title, description, start_date, end_date FROM events ORDER BY start_date DESC");
if ($result) {
    $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $error = "Could not fetch events. Please ensure the 'events' table exists.";
}

// Fetch classes for the notification modal
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        .sidebar-dark-primary .nav-sidebar .nav-link.active,
        .sidebar-dark-primary .nav-sidebar .nav-treeview>.nav-item>.nav-link.active {
            background-color: rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
        .content-header { background-color: #f8f9fa; }
        .card { border-radius: .75rem; box-shadow: 0 2px 10px rgba(0,0,0,0.075); border: none; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav"><li class="nav-item"><a class="nav-link text-white" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li></ul>
        <ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link text-white" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link"><img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3"><span class="brand-text font-weight-light">SMS Admin</span></a>
        <div class="sidebar"><nav class="mt-2"><ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
            <li class="nav-item"><a href="/hrproject/admin/dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Accounts<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-accounts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>All Accounts</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-book"></i><p>Manage Classes<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-classes.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Classes</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-calendar-alt"></i><p>Manage Class Routines<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-class-routines.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Routines</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-table"></i><p>Manage Timetable<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-timetable.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Timetable</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-book-open"></i><p>Manage Subjects<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-subjects.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Subjects</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-file-alt"></i><p>Manage Examinations<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-examinations.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Exams</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-check-square"></i><p>Manage Attendance<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-attendance.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Attendance</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-calculator"></i><p>Manage Accountings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-accountings.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Fees</p></a></li></ul></li>
            <li class="nav-item has-treeview menu-open"><a href="#" class="nav-link active"><i class="nav-icon fas fa-calendar-check"></i><p>Manage Events<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-events.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Events</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-reports.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Reports</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-bell"></i><p>Manage Notifications<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-notifications.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Notifications</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-settings.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Settings</p></a></li></ul></li>
        </ul></nav></div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Events</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active">Events</li></ol></div></div></div></div>
        
        <section class="content">
            <div class="container-fluid">
                <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Add New Event</h3></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Event Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Start Date & Time</label>
                                    <input type="datetime-local" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>End Date & Time (Optional)</label>
                                    <input type="datetime-local" name="end_date" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description (Optional)</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn btn-primary">Add Event</button></div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Upcoming & Past Events</h3></div>
                    <div class="card-body">
                        <table id="eventsTable" class="table table-bordered table-striped">
                            <thead><tr><th>Title</th><th>Starts</th><th>Ends</th><th>Description</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['title']); ?></td>
                                    <td><?= date('d M, Y h:i A', strtotime($event['start_date'])); ?></td>
                                    <td><?= !empty($event['end_date']) ? date('d M, Y h:i A', strtotime($event['end_date'])) : 'N/A'; ?></td>
                                    <td><?= nl2br(htmlspecialchars($event['description'])); ?></td>
                                    <td>
                                        <button class="btn btn-xs btn-info" data-toggle="modal" data-target="#editModal<?= $event['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="btn btn-xs btn-warning notify-btn" data-toggle="modal" data-target="#notifyModal" data-event-id="<?= $event['id']; ?>" data-event-title="<?= htmlspecialchars($event['title']); ?>"><i class="fas fa-bullhorn"></i> Notify</button>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editModal<?= $event['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">Edit Event</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                                                    <div class="form-group"><label>Event Title</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($event['title']); ?>" required></div>
                                                    <div class="row">
                                                        <div class="col-md-6 form-group"><label>Start Date & Time</label><input type="datetime-local" name="start_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>" required></div>
                                                        <div class="col-md-6 form-group"><label>End Date & Time (Optional)</label><input type="datetime-local" name="end_date" class="form-control" value="<?= !empty($event['end_date']) ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ''; ?>"></div>
                                                    </div>
                                                    <div class="form-group"><label>Description (Optional)</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($event['description']); ?></textarea></div>
                                                </div>
                                                <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
                                            </form>
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

    <div class="modal fade" id="notifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                 <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Send Event Notification</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_notification">
                        <input type="hidden" name="event_id" id="notify_event_id">
                        <p>Send a notification email for the event: <strong id="notify_event_title"></strong></p>
                        <div class="form-group">
                            <label>Select Classes to Notify</label>
                            <select name="class_ids[]" class="form-control" multiple required size="5">
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to select multiple classes.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane"></i> Send Emails</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="main-footer"><strong>Copyright &copy; <?= date('Y'); ?> <a href="#">Your School</a>.</strong> All rights reserved.</footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#eventsTable').DataTable({ "order": [[1, "desc"]] });

    // Handle Notify Modal population
    $('.notify-btn').on('click', function() {
        var eventId = $(this).data('event-id');
        var eventTitle = $(this).data('event-title');
        $('#notify_event_id').val(eventId);
        $('#notify_event_title').text(eventTitle);
    });
});
</script>
</body>
</html>