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

define('UPLOAD_PATH_LOGO', '../uploads/logo/');
if (!is_dir(UPLOAD_PATH_LOGO)) {
    mkdir(UPLOAD_PATH_LOGO, 0777, true);
}

// Handle form submission to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings_to_update = [
        'school_name' => $_POST['school_name'] ?? '',
        'school_email' => $_POST['school_email'] ?? '',
        'school_phone' => $_POST['school_phone'] ?? '',
        'school_address' => $_POST['school_address'] ?? ''
    ];

    // Handle logo upload
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $file_name = 'logo_' . time() . '_' . basename($_FILES['school_logo']['name']);
        $target_path = UPLOAD_PATH_LOGO . $file_name;
        if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $target_path)) {
            $settings_to_update['school_logo'] = $target_path;
        } else {
            $error = "Failed to upload new logo.";
        }
    }

    $query = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
    $stmt = mysqli_prepare($db_conn, $query);

    if ($stmt) {
        foreach ($settings_to_update as $key => $value) {
            mysqli_stmt_bind_param($stmt, "ss", $value, $key);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $success = "Settings updated successfully.";
    } else {
        $error = "Failed to prepare database statement.";
    }
}

// Fetch current settings
$settings = [];
$result = mysqli_query($db_conn, "SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} else {
    $error = "Could not fetch settings. Please ensure the 'settings' table exists.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Settings - SMS</title>
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
        .navbar-light .navbar-nav .nav-link { color: rgba(255,255,255,.8); }
        .content-header { background-color: #f8f9fa; }
        .card { border-radius: .75rem; box-shadow: 0 2px 10px rgba(0,0,0,0.075); border: none; }
        .current-logo { max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 5px; border-radius: 5px; }
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
            <!-- Other sidebar items... -->
            <li class="nav-item has-treeview menu-open">
                <a href="#" class="nav-link active">
                    <i class="nav-icon fas fa-sliders-h"></i><p>Manage Settings<i class="right fas fa-angle-left"></i></p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item"><a href="/hrproject/admin/manage-settings.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Settings</p></a></li>
                </ul>
            </li>
        </ul></nav></div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">System Settings</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active">Settings</li></ol></div></div></div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">General Settings</h3></div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="card-body">
                            <div class="form-group">
                                <label>School Name</label>
                                <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($settings['school_name'] ?? ''); ?>" required>
                            </div>
                             <div class="form-group">
                                <label>School Email</label>
                                <input type="email" name="school_email" class="form-control" value="<?= htmlspecialchars($settings['school_email'] ?? ''); ?>" required>
                            </div>
                             <div class="form-group">
                                <label>School Phone</label>
                                <input type="text" name="school_phone" class="form-control" value="<?= htmlspecialchars($settings['school_phone'] ?? ''); ?>">
                            </div>
                             <div class="form-group">
                                <label>School Address</label>
                                <textarea name="school_address" class="form-control" rows="3"><?= htmlspecialchars($settings['school_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>School Logo</label>
                                <?php if (!empty($settings['school_logo']) && file_exists($settings['school_logo'])): ?>
                                    <div class="mb-3">
                                        <img src="<?= htmlspecialchars($settings['school_logo']); ?>" alt="Current Logo" class="current-logo">
                                    </div>
                                <?php endif; ?>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="school_logo" name="school_logo">
                                    <label class="custom-file-label" for="school_logo">Choose new logo (optional)</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer"><strong>Copyright &copy; <?= date('Y'); ?> <a href="#">Your School</a>.</strong> All rights reserved.</footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
});
</script>
</body>
</html>
