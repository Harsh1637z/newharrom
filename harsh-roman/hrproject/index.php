<?php
// Start the session to handle form messages
session_start();

$form_message = '';
$form_message_type = '';

// Check if there's a message from a previous submission after redirect
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    $form_message_type = $_SESSION['form_message_type'];
    // Clear the message so it doesn't show again on refresh
    unset($_SESSION['form_message'], $_SESSION['form_message_type']);
}

// Handle the form submission when it's posted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    // This assumes your config file is in an 'includes' folder
    include_once('includes/config.php'); 
    
    // Check DB connection after include
    if (!$db_conn) {
        $_SESSION['form_message'] = "Database connection error. Please try again later.";
        $_SESSION['form_message_type'] = 'danger';
        header('Location: index.php#inquiry-form');
        exit();
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($full_name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['form_message'] = "Please fill in all required fields.";
        $_SESSION['form_message_type'] = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['form_message'] = "Please provide a valid email address.";
        $_SESSION['form_message_type'] = 'danger';
    } else {
        // Insert into database using prepared statements for security
        $query = "INSERT INTO inquiries (full_name, email, phone_number, subject, message) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($db_conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $phone, $subject, $message);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['form_message'] = "Thank you for your inquiry! We will get back to you shortly.";
                $_SESSION['form_message_type'] = 'success';
            } else {
                $_SESSION['form_message'] = "Sorry, there was an error sending your message. Please try again later.";
                $_SESSION['form_message_type'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['form_message'] = "A database error occurred.";
            $_SESSION['form_message_type'] = 'danger';
        }
    }
    // Redirect back to the form section to prevent re-submission on refresh
    header('Location: index.php#inquiry-form');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to SMS School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; color: #444; }
        .navbar { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar-brand { color: #3923a7 !important; }
        .hero { background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.pexels.com/photos/8613089/pexels-photo-8613089.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1') no-repeat center center; background-size: cover; color: white; padding: 150px 0; text-align: center; }
        .hero h1 { font-size: 3.5rem; font-weight: 700; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
        .section { padding: 80px 0; }
        .section-title { text-align: center; margin-bottom: 50px; font-weight: 700; color: #3923a7; }
        .teacher-card { text-align: center; }
        .teacher-card img { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 5px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.15); margin-bottom: 1rem; }
        .review-card { background-color: #f8f9fa; border-left: 5px solid #3923a7; }
        .stars { color: #ffc107; }
        .gallery-item { overflow: hidden; border-radius: .5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .gallery-item img { width: 100%; height: 250px; object-fit: cover; transition: transform 0.3s ease; }
        .gallery-item:hover img { transform: scale(1.05); }
        .footer { background-color: #2c3e50; color: white; padding: 40px 0; }
        .footer a { color: #ffc107; text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #fff; }
        .footer .social-icons a { font-size: 1.5rem; margin: 0 10px; }
        .btn-primary { background-color: #5a4fc4; border-color: #5a4fc4; }
        .btn-primary:hover { background-color: #3923a7; border-color: #3923a7; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="fas fa-school me-2"></i> SMS School</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#teachers">Teachers</a></li>
                    <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="#reviews">Reviews</a></li>
                    <li class="nav-item"><a class="nav-link" href="#inquiry-form">Contact</a></li>
                </ul>
                <a href="/hrproject/admin/dashboard.php" class="btn btn-primary ms-lg-3">Admin Login</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <h1 class="display-4">Nurturing Minds, Shaping Futures</h1>
            <p class="lead my-4">Providing quality education to build a brighter tomorrow for every child.</p>
            <a href="#inquiry-form" class="btn btn-primary btn-lg">Admissions Open</a>
        </div>
    </header>

    <main>
        <section id="teachers" class="section">
            <div class="container">
                <h2 class="section-title">Meet Our Educators</h2>
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="teacher-card">
                            <img src="https://images.pexels.com/photos/5212345/pexels-photo-5212345.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Principal">
                            <h5 class="mt-3 mb-1 fw-bold">Dr. Anjali Sharma</h5>
                            <p class="text-muted">Principal, PhD in Education</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="teacher-card">
                            <img src="https://images.pexels.com/photos/5212338/pexels-photo-5212338.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Science Teacher">
                            <h5 class="mt-3 mb-1 fw-bold">Ravi Singh</h5>
                            <p class="text-muted">Head of Science, M.Sc.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="teacher-card">
                            <img src="https://images.pexels.com/photos/4100670/pexels-photo-4100670.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Arts Teacher">
                            <h5 class="mt-3 mb-1 fw-bold">Priya Patel</h5>
                            <p class="text-muted">Arts & Crafts Teacher, B.A.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="teacher-card">
                            <img src="https://images.pexels.com/photos/6963009/pexels-photo-6963009.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Sports Coach">
                            <h5 class="mt-3 mb-1 fw-bold">Sameer Khan</h5>
                            <p class="text-muted">Sports Coach, B.P.Ed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <section id="gallery" class="section bg-light">
            <div class="container">
                <h2 class="section-title">Campus Life</h2>
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3"><div class="gallery-item"><img src="https://images.pexels.com/photos/207692/pexels-photo-207692.jpeg?auto=compress&cs=tinysrgb&w=600" alt="School Library"></div></div>
                    <div class="col-md-6 col-lg-3"><div class="gallery-item"><img src="https://images.pexels.com/photos/3861457/pexels-photo-3861457.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Science Lab"></div></div>
                    <div class="col-md-6 col-lg-3"><div class="gallery-item"><img src="https://images.pexels.com/photos/159711/books-bookstore-book-reading-159711.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Student Reading"></div></div>
                    <div class="col-md-6 col-lg-3"><div class="gallery-item"><img src="https://images.pexels.com/photos/60909/referee-football-judge-flute-60909.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Sports Day"></div></div>
                </div>
            </div>
        </section>

        <section id="reviews" class="section">
            <div class="container">
                <h2 class="section-title">What Parents Say</h2>
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="review-card p-4 h-100 rounded shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Sunita Sharma</h6>
                                <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                            <p class="mb-0">"A fantastic school with dedicated teachers. My son has shown incredible growth since joining. The focus on both academics and extracurriculars is brilliant."</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="review-card p-4 h-100 rounded shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Rajesh Verma</h6>
                                <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
                            </div>
                            <p class="mb-0">"The teachers are very supportive and the campus is safe and clean. We are very happy with our choice to enroll our daughter here."</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="review-card p-4 h-100 rounded shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Meena Iyer</h6>
                                <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                            <p class="mb-0">"Excellent infrastructure and a very positive learning environment. The school administration is always responsive and helpful. Highly recommended."</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="inquiry-form" class="section bg-light">
            <div class="container">
                <h2 class="section-title">Contact & Inquiry Form</h2>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php if ($form_message): ?>
                            <div class="alert alert-<?= $form_message_type; ?> alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($form_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <div class="card p-4 shadow-sm">
                            <form action="index.php#inquiry-form" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control" id="full_name" name="full_name" required></div>
                                    <div class="col-md-6 mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" name="email" required></div>
                                    <div class="col-md-6 mb-3"><label for="phone_number" class="form-label">Phone Number (Optional)</label><input type="tel" class="form-control" id="phone_number" name="phone_number"></div>
                                    <div class="col-md-6 mb-3"><label for="subject" class="form-label">Subject</label><input type="text" class="form-control" id="subject" name="subject" required></div>
                                    <div class="col-12 mb-3"><label for="message" class="form-label">Message</label><textarea class="form-control" id="message" name="message" rows="5" required></textarea></div>
                                </div>
                                <button type="submit" name="submit_inquiry" class="btn btn-primary">Submit Inquiry</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container text-center">
            <div class="row">
                <div class="col-md-4 mb-3"><h5>Contact Us</h5><p>123 School Lane, Education City,<br>Ahmedabad, Gujarat 380001</p></div>
                <div class="col-md-4 mb-3"><h5>Quick Links</h5><p><a href="#">Admissions</a> | <a href="#">Careers</a> | <a href="#">Sitemap</a></p></div>
                <div class="col-md-4 mb-3 social-icons"><h5>Follow Us</h5><a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a><a href="https://www.instagram.com/harsh_20.x?igsh=OGtuanBkbHB0Zzdp" aria-label="Instagram"><i class="fab fa-instagram"></i></a></div>
            </div>
            <hr>
            <p>&copy; <?php echo date('Y'); ?> SMS School. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>