<?php
// Make sure to start the session on every page
session_start();

// Include database configuration
include('includes/config.php');

// Initialize error variable
$login_error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $login_error = 'Please fill in all fields.';
    } else {
        // This is a demo query. For production, use prepared statements to prevent SQL injection.
        $query = "SELECT * FROM users WHERE username = '$username' OR email = '$username'";
        $result = mysqli_query($db_conn, $query);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // This is a demo password check. For production, use password_verify().
            if ($password === $user['password']) {
                
                // *** IMPORTANT: Check if the user has the 'admin' role ***
                if ($user['role'] == 'admin') {
                    // Set session variables
                    $_SESSION['login'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect to the admin dashboard
                    header('Location: admin/dashboard.php');
                    exit();
                } else {
                    // If a non-admin (like a student) tries to log in
                    $login_error = 'Access Denied. Only administrators can log in here.';
                }
            } else {
                // If the password is wrong
                $login_error = 'Invalid username/email or password.';
            }
        } else {
            // If the user is not found
            $login_error = 'Invalid username/email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 my-5">
                <div class="card shadow">
                    <div class="card-header text-center bg-primary text-white">
                        <h3>Admin Panel Login</h3>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($login_error): ?>
                            <div class="alert alert-danger"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="login" value="1">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>