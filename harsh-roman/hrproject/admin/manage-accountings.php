<?php
session_start();
include_once('../includes/config.php');

// Security Check: Ensure user is a logged-in admin
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];
$query_auth = "SELECT role FROM users WHERE id = ?";
$stmt_auth = mysqli_prepare($db_conn, $query_auth);
mysqli_stmt_bind_param($stmt_auth, "i", $admin_id);
mysqli_stmt_execute($stmt_auth);
$result_auth = mysqli_stmt_get_result($stmt_auth);
$current_user = mysqli_fetch_assoc($result_auth);
mysqli_stmt_close($stmt_auth);
if (!$current_user || $current_user['role'] !== 'admin') {
    header("Location: /hrproject/login.php");
    exit();
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ACTION: Generate Monthly Fees
    if ($action === 'generate_fees') {
        $class_id = (int)$_POST['class_id'];
        $amount = (float)$_POST['amount'];
        $fee_month_year = $_POST['fee_month']; // Format: YYYY-MM
        
        if (empty($class_id) || empty($amount) || empty($fee_month_year)) {
            $error = "Please select a class, provide an amount, and choose a month.";
        } else {
            $month_name = date("F Y", strtotime($fee_month_year . "-01"));
            $due_date = date("Y-m-t", strtotime($fee_month_year . "-01")); // Last day of the month

            // Get all students from the selected class
            $stmt_students = mysqli_prepare($db_conn, "SELECT id FROM users WHERE role='student' AND class_id = ?");
            mysqli_stmt_bind_param($stmt_students, "i", $class_id);
            mysqli_stmt_execute($stmt_students);
            $students_result = mysqli_stmt_get_result($stmt_students);

            $generated_count = 0;
            $skipped_count = 0;
            
            $insert_query = "INSERT INTO student_fees (student_id, class_id, amount_due, fee_month, due_date, status) VALUES (?, ?, ?, ?, ?, 'Unpaid')
                             ON DUPLICATE KEY UPDATE student_id=student_id"; // ON DUPLICATE does nothing, just prevents error
            $stmt_insert = mysqli_prepare($db_conn, $insert_query);

            while ($student = mysqli_fetch_assoc($students_result)) {
                mysqli_stmt_bind_param($stmt_insert, "iidss", $student['id'], $class_id, $amount, $month_name, $due_date);
                mysqli_stmt_execute($stmt_insert);
                if (mysqli_stmt_affected_rows($stmt_insert) > 0) {
                    $generated_count++;
                } else {
                    $skipped_count++;
                }
            }
            $success = "$generated_count fee records generated for $month_name. $skipped_count records already existed and were skipped.";
            mysqli_stmt_close($stmt_students);
            mysqli_stmt_close($stmt_insert);
        }
    }
    
    // ACTION: Record a Payment
    if ($action === 'record_payment') {
        $fee_id = (int)$_POST['fee_id'];
        $amount_paid = (float)$_POST['amount_paid'];
        $payment_date = $_POST['payment_date'];

        if (empty($fee_id) || empty($amount_paid) || empty($payment_date)) {
            $error = "Missing payment information.";
        } else {
            // Get fee details to insert into payments table
            // **FIX:** Also fetch `fee_month`
            $stmt_fee = mysqli_prepare($db_conn, "SELECT student_id, class_id, fee_month FROM student_fees WHERE id = ?");
            mysqli_stmt_bind_param($stmt_fee, "i", $fee_id);
            mysqli_stmt_execute($stmt_fee);
            $fee_details = mysqli_stmt_get_result($stmt_fee)->fetch_assoc();
            mysqli_stmt_close($stmt_fee);

            if ($fee_details) {
                // Use a transaction
                mysqli_begin_transaction($db_conn);

                try {
                    // 1. Insert into fee_payments
                    // **FIX:** Add `payment_for_month` to the query
                    $stmt_payment = mysqli_prepare($db_conn, "INSERT INTO fee_payments (fee_id, student_id, class_id, amount, payment_date, payment_for_month, recorded_by_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    // **FIX:** Check if prepare was successful before binding
                    if ($stmt_payment) {
                        // **FIX:** Add `fee_month` to bind_param and update types string
                        mysqli_stmt_bind_param($stmt_payment, "iiidssi", $fee_id, $fee_details['student_id'], $fee_details['class_id'], $amount_paid, $payment_date, $fee_details['fee_month'], $admin_id);
                        mysqli_stmt_execute($stmt_payment);
                        $payment_id = mysqli_insert_id($db_conn);
                        mysqli_stmt_close($stmt_payment);

                        // 2. Update student_fees status
                        $stmt_update = mysqli_prepare($db_conn, "UPDATE student_fees SET status = 'Paid', payment_id = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_update, "ii", $payment_id, $fee_id);
                        mysqli_stmt_execute($stmt_update);
                        mysqli_stmt_close($stmt_update);

                        mysqli_commit($db_conn);
                        $success = "Payment recorded successfully.";
                    } else {
                        throw new Exception("Failed to prepare the payment insert statement: " . mysqli_error($db_conn));
                    }
                } catch (Exception $exception) {
                    mysqli_rollback($db_conn);
                    $error = "Transaction failed: " . $exception->getMessage();
                }
            } else {
                $error = "Invalid Fee ID.";
            }
        }
    }
}

// Fetch classes for dropdowns
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;

// Fetch all due fee records for the main table
$fees_due = [];
$query = "SELECT sf.id, sf.amount_due, sf.fee_month, sf.status,
                 u.first_name, u.last_name,
                 c.name as class_name, c.section
          FROM student_fees sf
          JOIN users u ON sf.student_id = u.id
          LEFT JOIN classes c ON sf.class_id = c.id
          ORDER BY sf.created_at DESC, sf.id DESC";
$result = mysqli_query($db_conn, $query);
if ($result) while ($row = mysqli_fetch_assoc($result)) $fees_due[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accountings - SMS</title>
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
        .badge-unpaid { background-color: #dc3545; color: white; }
        .badge-paid { background-color: #28a745; color: white; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-light"><ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li><li class="nav-item d-none d-sm-inline-block"><a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a></li></ul><ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></nav>
    
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
            <li class="nav-item has-treeview menu-open"><a href="#" class="nav-link active"><i class="nav-icon fas fa-calculator"></i><p>Manage Accountings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-accountings.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Fees</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-calendar-check"></i><p>Manage Events<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-events.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Events</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-chart-pie"></i><p>Manage Reports<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-reports.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Reports</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-bell"></i><p>Manage Notifications<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-notifications.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Notifications</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-settings.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Settings</p></a></li></ul></li>
        </ul></nav></div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Accountings</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active">Accountings</li></ol></div></div></div></div>
        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($success)): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
                <?php if (!empty($error)): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="card"><div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-file-invoice-dollar text-primary"></i> Generate Monthly Fees</h3></div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="generate_fees">
                            <div class="row">
                                <div class="col-md-4 form-group"><label>For Class</label><select class="form-control" name="class_id" required><option value="">-- Select Class --</option><?php foreach ($classes as $class): ?><option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-4 form-group"><label>For Month</label><input type="month" class="form-control" name="fee_month" value="<?= date('Y-m'); ?>" required></div>
                                <div class="col-md-4 form-group"><label>Amount (₹)</label><input type="number" step="0.01" class="form-control" name="amount" placeholder="e.g., 5000.00" required></div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-cogs"></i> Generate Fees</button>
                        </form>
                    </div>
                </div>

                <div class="card"><div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-list-alt text-primary"></i> Fee Status Log</h3></div>
                    <div class="card-body">
                        <table id="feesTable" class="table table-bordered table-striped">
                            <thead><tr><th>Student</th><th>Class</th><th>Fee for Month</th><th>Amount Due</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($fees_due as $fee): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fee['first_name'] . ' ' . $fee['last_name']); ?></td>
                                        <td><?= htmlspecialchars($fee['class_name'] ? $fee['class_name'] . ' - ' . $fee['section'] : 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($fee['fee_month']); ?></td>
                                        <td>₹<?= htmlspecialchars(number_format($fee['amount_due'], 2)); ?></td>
                                        <td>
                                            <?php if ($fee['status'] == 'Paid'): ?>
                                                <span class="badge badge-paid">Paid</span>
                                            <?php else: ?>
                                                <span class="badge badge-unpaid">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fee['status'] == 'Unpaid'): ?>
                                                <button class="btn btn-xs btn-success pay-btn" 
                                                        data-toggle="modal" 
                                                        data-target="#paymentModal"
                                                        data-fee-id="<?= $fee['id']; ?>"
                                                        data-student-name="<?= htmlspecialchars($fee['first_name'] . ' ' . $fee['last_name']); ?>"
                                                        data-fee-month="<?= htmlspecialchars($fee['fee_month']); ?>"
                                                        data-amount-due="<?= $fee['amount_due']; ?>">
                                                    <i class="fas fa-money-bill-wave"></i> Pay
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
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

    <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" id="modal_fee_id" name="fee_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">Record Payment</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Student:</strong> <span id="modal_student_name"></span></p>
                        <p><strong>Fee for:</strong> <span id="modal_fee_month"></span></p>
                        <div class="form-group">
                            <label for="modal_amount_paid">Amount Paid (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="modal_amount_paid" name="amount_paid" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_payment_date">Payment Date</label>
                            <input type="date" class="form-control" id="modal_payment_date" name="payment_date" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
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
    $('[data-widget="treeview"]').Treeview('init');
    $('#feesTable').DataTable({"order": []}); // Default order

    // Populate modal with data from the clicked button
    $('.pay-btn').on('click', function() {
        var feeId = $(this).data('fee-id');
        var studentName = $(this).data('student-name');
        var feeMonth = $(this).data('fee-month');
        var amountDue = $(this).data('amount-due');
        
        $('#modal_fee_id').val(feeId);
        $('#modal_student_name').text(studentName);
        $('#modal_fee_month').text(feeMonth);
        $('#modal_amount_paid').val(amountDue);
        $('#paymentModalLabel').text('Record Payment for ' + studentName);
    });
});
</script>
</body>
</html>