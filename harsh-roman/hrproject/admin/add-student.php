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

$error = '';

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $class_id = (int)($_POST['class_id'] ?? 0);
    $role = 'student'; // Role is fixed for this page

    if (empty($username) || empty($password) || empty($class_id) || empty($email)) {
        $error = "Username, Password, Email, and Class are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "The email address is not valid.";
    } else {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, password, first_name, last_name, email, role, class_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($db_conn, $query);
        
        if($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $username, $password_hashed, $first_name, $last_name, $email, $role, $class_id);
            if (mysqli_stmt_execute($stmt)) {
                // Set a success message in the session and redirect back to the list
                $_SESSION['success_message'] = "Student '$first_name $last_name' added successfully!";
                header("Location: manage-accounts.php");
                exit();
            } else {
                $error = "Failed to add student: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database error: could not prepare statement.";
        }
    }
}

// Fetch classes for the dropdown menu
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) while ($row = mysqli_fetch_assoc($class_result)) $classes[] = $row;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - SMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .main-header, .brand-link { background: linear-gradient(135deg, #3923a7, #5a4fc4); color: white !important; }
        .content-header { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-light"><ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li><li class="nav-item d-none d-sm-inline-block"><a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a></li></ul><ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></nav>
    
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link"><img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3"><span class="brand-text font-weight-light">SMS Admin</span></a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
                    <li class="nav-item"><a href="/hrproject/admin/dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                    <li class="nav-item has-treeview menu-open">
                        <a href="#" class="nav-link active"><i class="nav-icon fas fa-users"></i><p>Manage Accounts<i class="right fas fa-angle-left"></i></p></a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="/hrproject/admin/manage-accounts.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>All Accounts</p></a></li>
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
                    <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Add New Student</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/hrproject/admin/dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="manage-accounts.php">Manage Accounts</a></li>
                            <li class="breadcrumb-item active">Add Student</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-user-plus text-primary"></i> Student Details</h3></div>
                    <div class="card-body">
                        <form method="POST" action="add-student.php">
                            <div class="row">
                                <div class="col-md-6 form-group"><label>First Name</label><input type="text" class="form-control" name="first_name" placeholder="Enter first name"></div>
                                <div class="col-md-6 form-group"><label>Last Name</label><input type="text" class="form-control" name="last_name" placeholder="Enter last name"></div>
                                <div class="col-md-6 form-group"><label>Username</label><input type="text" class="form-control" name="username" placeholder="Unique username" required></div>
                                <div class="col-md-6 form-group"><label>Email</label><input type="email" class="form-control" name="email" placeholder="Unique email address" required></div>
                                <div class="col-md-6 form-group"><label>Password</label><input type="password" class="form-control" name="password" placeholder="Create a password" required></div>
                                <div class="col-md-6 form-group">
                                    <label>Class</label>
                                    <select class="form-control" name="class_id" required>
                                        <option value="">-- Assign a Class --</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?= $class['id']; ?>"><?= htmlspecialchars($class['name'] . ' - ' . $class['section']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Student</button>
                            <a href="manage-accounts.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer"><strong>Copyright &copy; 2024-<?php echo date('Y'); ?> <a href="#">Your School</a>.</strong> All rights reserved.</footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>