<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if ($user['status'] !== 'active') {
            $error = 'Account is not active';
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            
            logActivity($user['user_id'], 'login', 'User logged in');
            
            if ($user['user_type'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'Invalid email';
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            background: linear-gradient(135deg, #800000 0%, #ff00ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 90%;
            border-radius: 15px;
            border: none;
        }
        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: #800000;
        }
        .brand-logo span {
            color: #ff69b4;
        }
    </style>
</head>
<body>
    <div class="card login-card shadow-lg">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="brand-logo mb-2">
                                <i class="fas fa-search-location me-2"></i>iFind<span>Peace</span>
                            </div>
                            <h5 class="fw-bold">Welcome Back</h5>
                            <small class="text-muted">Sign in to continue</small>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                                <small><?php echo $error; ?></small>
                                <button type="button" class="btn-close p-2" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <input type="email" name="email" class="form-control form-control-sm" required placeholder="Email Address">
                            </div>

                            <div class="mb-3">
                                <input type="password" name="password" class="form-control form-control-sm" required placeholder="Password">
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label small" for="remember">Remember me</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                Sign In
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <small class="text-muted">Don't have an account? <a href="register.php">Register</a></small>
                        </div>
                        
                        <div class="text-center mt-2">
                            <a href="index.php" class="small text-muted"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
                        </div>
                    </div>
                </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
