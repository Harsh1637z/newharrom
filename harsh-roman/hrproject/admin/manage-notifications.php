<?php
session_start();
include_once('../includes/config.php');

// Initialize variables
$success = '';
$error = '';

// Security Check: Ensure user is a logged-in admin
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}

// Verify database connection
if (!$db_conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$query_auth = "SELECT role FROM users WHERE id = ?";
$stmt_auth = mysqli_prepare($db_conn, $query_auth);
if ($stmt_auth === false) {
    die("Fatal Error: Could not prepare user role check. " . mysqli_error($db_conn));
}
mysqli_stmt_bind_param($stmt_auth, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_auth);
$result_auth = mysqli_stmt_get_result($stmt_auth);
$current_user = mysqli_fetch_assoc($result_auth);
mysqli_stmt_close($stmt_auth);

if (!$current_user || $current_user['role'] !== 'admin') {
    header("Location: /hrproject/login.php");
    exit();
}

// Handle Delete Action via POST for security
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_inquiry') {
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    if ($inquiry_id > 0) {
        $stmt = mysqli_prepare($db_conn, "DELETE FROM inquiries WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $inquiry_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Inquiry deleted successfully.";
            } else {
                $error = "Failed to delete inquiry.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database error: Could not prepare statement.";
        }
    } else {
        $error = "Invalid Inquiry ID.";
    }
}

// Fetch all inquiries from the database
$inquiries = [];
$result = mysqli_query($db_conn, "SELECT id, full_name, email, phone_number, subject, message, submitted_at FROM inquiries ORDER BY submitted_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $inquiries[] = $row;
    }
    mysqli_free_result($result);
} else {
    $error = "Could not fetch inquiries from the database.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notifications - SMS</title>
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
        .navbar-light .navbar-nav .nav-link:hover { color: white; }
        .content-header { background-color: #f8f9fa; }
        .card { border-radius: .75rem; box-shadow: 0 2px 10px rgba(0,0,0,0.075); border: none; }
        .message-content { white-space: pre-wrap; word-break: break-word; max-width: 400px; }
        .action-buttons form { display: inline-block; margin-left: 5px; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
            <li class="nav-item d-none d-sm-inline-block"><a href="dashboard.php" class="nav-link">Home</a></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="dashboard.php" class="brand-link"><img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3" style="opacity:.8"><span class="brand-text font-weight-light">SMS Admin</span></a>
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
                    <li class="nav-item has-treeview menu-open"><a href="#" class="nav-link active"><i class="nav-icon fas fa-bell"></i><p>Manage Notifications<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-notifications.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Notifications</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-reports.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Reports</p></a></li></ul></li>
                    <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-settings.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Settings</p></a></li></ul></li>
                </ul>
            </nav>
        </div>
    </aside>
    <div class="content-wrapper">
        <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Notifications</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active">Notifications</li></ol></div></div></div></div>
        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($success)): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
                <?php if (!empty($error)): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="card">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-envelope-open-text text-primary"></i> Website Inquiries</h3></div>
                    <div class="card-body">
                        <table id="inquiriesTable" class="table table-bordered table-striped">
                            <thead><tr><th>Date</th><th>From</th><th>Contact</th><th>Subject</th><th>Message</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($inquiries as $inquiry): ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?= date('d M, Y h:i A', strtotime($inquiry['submitted_at'])); ?></td>
                                        <td><?= htmlspecialchars($inquiry['full_name']); ?></td>
                                        <td style="white-space: nowrap;"><?= htmlspecialchars($inquiry['email']); ?><br><?= htmlspecialchars($inquiry['phone_number']); ?></td>
                                        <td><?= htmlspecialchars($inquiry['subject']); ?></td>
                                        <td class="message-content"><?= htmlspecialchars($inquiry['message']); ?></td>
                                        <td class="action-buttons">
                                            <?php
                                                $reply_subject = "RE: " . $inquiry['subject'];
                                                $reply_body = "Dear " . htmlspecialchars($inquiry['full_name']) . ",\n\n" .
                                                              "Thank you for contacting us regarding your inquiry.\n\n" .
                                                              "--- Your Message ---\n" .
                                                              htmlspecialchars($inquiry['message']) . "\n" .
                                                              "----------------------\n\n" .
                                                              "[Please type your reply here]\n\n" .
                                                              "Best regards,\n" .
                                                              "SMS Administration";
                                            ?>
                                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= htmlspecialchars($inquiry['email']); ?>&su=<?= urlencode($reply_subject); ?>&body=<?= urlencode($reply_body); ?>" target="_blank" class="btn btn-sm btn-primary" title="Reply via Gmail">
                                                <i class="fas fa-reply"></i> Reply
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this inquiry?');">
                                                <input type="hidden" name="action" value="delete_inquiry">
                                                <input type="hidden" name="inquiry_id" value="<?= $inquiry['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete Inquiry"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
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
        $('#inquiriesTable').DataTable({
            "order": [[0, "desc"]], // Sort by the first column (Date) in descending order
            "responsive": true
        });
    });
</script>
</body>
</html>

