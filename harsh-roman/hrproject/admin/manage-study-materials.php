<?php
session_start();
include_once('../includes/config.php');

// Security Check & Variable Initialization
if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
    header("Location: /hrproject/login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Verify database connection and user role
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

// Define upload directory
define('UPLOAD_PATH', '../uploads/materials/');

// Ensure upload directory exists
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Handle Delete Action
    if ($action === 'delete') {
        $material_id = (int)($_POST['material_id'] ?? 0);
        if ($material_id > 0) {
            // First, get the file path to delete the file from the server
            $stmt = mysqli_prepare($db_conn, "SELECT file_path FROM study_materials WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $material_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $material = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($material && file_exists($material['file_path'])) {
                unlink($material['file_path']); // Delete the actual file
            }

            // Then, delete the record from the database
            $stmt = mysqli_prepare($db_conn, "DELETE FROM study_materials WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $material_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Study material deleted successfully.";
            } else {
                $error = "Failed to delete database record.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Handle Add Action
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);

        if (empty($title) || empty($class_id) || !isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "Title, Class, and a valid file are required.";
        } else {
            $file_name = time() . '_' . basename($_FILES['material_file']['name']);
            $target_path = UPLOAD_PATH . $file_name;

            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $target_path)) {
                $query = "INSERT INTO study_materials (title, class_id, subject_id, file_name, file_path, uploaded_by_id) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($db_conn, $query);
                if ($stmt) {
                    $bind_subject_id = $subject_id > 0 ? $subject_id : null;
                    mysqli_stmt_bind_param($stmt, "siissi", $title, $class_id, $bind_subject_id, $file_name, $target_path, $admin_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Study material uploaded successfully.";
                    } else {
                        $error = "Database error: " . mysqli_stmt_error($stmt);
                        unlink($target_path); // Clean up uploaded file if DB insert fails
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Database prepare error: " . mysqli_error($db_conn);
                    unlink($target_path);
                }
            } else {
                $error = "Failed to upload file.";
            }
        }
    }
}

// Fetch data for page display with robust error checking
$classes = [];
$class_result = mysqli_query($db_conn, "SELECT id, name, section FROM classes ORDER BY name, section");
if ($class_result) {
    $classes = $class_result->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Could not fetch classes. Please ensure the 'classes' table exists.";
}

$subjects = [];
$subject_result = mysqli_query($db_conn, "SELECT id, name FROM subjects ORDER BY name");
if ($subject_result) {
    $subjects = $subject_result->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Could not fetch subjects. Please ensure the 'subjects' table exists.";
}

$materials = [];
$materials_result = mysqli_query($db_conn, 
    "SELECT sm.id, sm.title, sm.file_name, sm.file_path, sm.created_at, c.name as class_name, c.section, s.name as subject_name 
     FROM study_materials sm
     JOIN classes c ON sm.class_id = c.id
     LEFT JOIN subjects s ON sm.subject_id = s.id
     ORDER BY sm.created_at DESC"
);
if ($materials_result) {
    $materials = $materials_result->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Could not fetch study materials. Please ensure the 'study_materials' table exists and all columns are correct.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Study Materials - SMS</title>
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
    <nav class="main-header navbar navbar-expand navbar-light"><ul class="navbar-nav"><li class="nav-item"><a class="nav-link text-white" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li><li class="nav-item d-none d-sm-inline-block"><a href="/hrproject/admin/dashboard.php" class="nav-link">Home</a></li></ul><ul class="navbar-nav ml-auto"><li class="nav-item"><a class="nav-link text-white" href="/hrproject/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/hrproject/admin/dashboard.php" class="brand-link"><img src="/hrproject/dist/img/AdminLTELogo.png" alt="SMS Logo" class="brand-image img-circle elevation-3"><span class="brand-text font-weight-light">SMS Admin</span></a>
        <div class="sidebar"><nav class="mt-2"><ul class="nav nav-pills nav-sidebar flex-column nav-legacy" data-widget="treeview" role="menu" data-accordion="true">
            <!-- Sidebar is consistent with your dashboard -->
            <li class="nav-item"><a href="/hrproject/admin/dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Accounts<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-accounts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>All Accounts</p></a></li></ul></li>
            <li class="nav-item has-treeview"><a href="#" class="nav-link"><i class="nav-icon fas fa-book"></i><p>Manage Classes<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-classes.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Classes</p></a></li></ul></li>
            <li class="nav-item has-treeview menu-open"><a href="#" class="nav-link active"><i class="nav-icon fas fa-folder-open"></i><p>Manage Study Materials<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a href="/hrproject/admin/manage-study-materials.php" class="nav-link active"><i class="far fa-circle nav-icon"></i><p>Study Materials</p></a></li></ul></li>
            <!-- Add other sidebar items here to match dashboard -->
        </ul></nav></div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0 font-weight-bold">Manage Study Materials</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active">Study Materials</li></ol></div></div></div></div>
        
        <section class="content">
            <div class="container-fluid">
                <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Upload New Material</h3></div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        <div class="card-body row">
                            <div class="form-group col-md-4"><label>Title</label><input type="text" name="title" class="form-control" required></div>
                            <div class="form-group col-md-4"><label>Class</label><select name="class_id" class="form-control" required><option value="">Select Class</option><?php foreach($classes as $c): ?><option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['name'].' - '.$c['section']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group col-md-4"><label>Subject (Optional)</label><select name="subject_id" class="form-control"><option value="">Select Subject</option><?php foreach($subjects as $s): ?><option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group col-md-12"><label>File</label><div class="custom-file"><input type="file" class="custom-file-input" id="material_file" name="material_file" required><label class="custom-file-label" for="material_file">Choose file...</label></div></div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn btn-primary">Upload</button></div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Uploaded Materials</h3></div>
                    <div class="card-body">
                        <table id="materialsTable" class="table table-bordered table-striped">
                            <thead><tr><th>Title</th><th>Class</th><th>Subject</th><th>File</th><th>Uploaded On</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach($materials as $material): ?>
                                <tr>
                                    <td><?= htmlspecialchars($material['title']); ?></td>
                                    <td><?= htmlspecialchars($material['class_name'].' - '.$material['section']); ?></td>
                                    <td><?= htmlspecialchars($material['subject_name'] ?? 'N/A'); ?></td>
                                    <td><a href="<?= htmlspecialchars($material['file_path']); ?>" target="_blank"><?= htmlspecialchars($material['file_name']); ?></a></td>
                                    <td><?= date('d M, Y', strtotime($material['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this file?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="material_id" value="<?= $material['id']; ?>">
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
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
    $('#materialsTable').DataTable({"order": [[4, "desc"]]});
    // Script to show file name in bootstrap custom file input
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
});
</script>
</body>
</html>

