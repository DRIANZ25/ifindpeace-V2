<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-search-location me-2"></i>iFind<span>Peace</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="items.php">Browse Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light ms-2" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            <i class="fas fa-search-location fa-4x text-primary mb-3"></i>
                            <h1 class="fw-bold">About iFindPeace</h1>
                            <p class="text-muted fs-5">Peace Comes When Things Are Found</p>
                        </div>

                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">Our Mission</h3>
                            <p class="text-muted">
                                iFindPeace is a centralized web-based lost and found platform designed to help students of Mindanao State University - General Santos efficiently report, search for, and recover lost belongings. Our goal is to foster a culture of honesty and accountability within the campus community.
                            </p>
                        </div>

                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">What We Do</h3>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Provide an accessible platform for reporting lost and found items
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Enable automated matching and notification system for lost items
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Implement a verified claiming process with ID verification
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Reduce reliance on social media for lost and found posts
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Minimize unclaimed items through notification reminders
                                </li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">Our Values</h3>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-handshake fa-3x text-primary mb-2"></i>
                                        <h5>Honesty</h5>
                                        <p class="small text-muted">Building trust through transparent reporting</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-shield-alt fa-3x text-success mb-2"></i>
                                        <h5>Accountability</h5>
                                        <p class="small text-muted">Taking responsibility for our actions</p>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-users fa-3x text-warning mb-2"></i>
                                        <h5>Community</h5>
                                        <p class="small text-muted">Working together for a better campus</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">Target Beneficiaries</h3>
                            <p class="text-muted">
                                iFindPeace is specifically designed for students of Mindanao State University - General Santos City. By providing a dedicated platform, we aim to create a more efficient and organized system for managing lost and found items on campus.
                            </p>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Did you know?</strong> Items reported on social media often get buried by unrelated posts. iFindPeace provides a dedicated space where lost and found items are easily searchable and visible to the entire campus community.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
