<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = sanitize($_POST['student_id']);
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $course = sanitize($_POST['course']);
    $year_level = sanitize($_POST['year_level']);
    $pledge = isset($_POST['honesty_pledge']);
    
    // Validation
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$pledge) {
        $error = 'You must sign the honesty pledge to register';
    } else {
        $conn = getDBConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $email_taken = $result->num_rows > 0;
        $stmt->close();
        
        if ($email_taken) {
            $error = 'Email already registered';
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $id_taken = $result->num_rows > 0;
            $stmt->close();
            
            if ($id_taken) {
                $error = 'Student ID already registered';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $pledge_signed = 1;
                $pledge_date = date('Y-m-d H:i:s');
                
                $stmt = $conn->prepare("INSERT INTO users (student_id, full_name, email, password, phone, course, year_level, honesty_pledge_signed, pledge_signed_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssis", $student_id, $full_name, $email, $hashed_password, $phone, $course, $year_level, $pledge_signed, $pledge_date);
                
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    $success = 'Registration successful! You can now login.';
                    $stmt->close();
                    logActivity($new_user_id, 'register', 'New user registered');
                } else {
                    $error = 'Registration failed. Please try again.';
                    $stmt->close();
                }
            }
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - iFindPeace</title>
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
        .register-card {
            max-width: 450px;
            width: 90%;
            border-radius: 15px;
            border: none;
            max-height: 90vh;
            overflow-y: auto;
        }
        .brand-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #800000;
        }
        .brand-logo span {
            color: #ff69b4;
        }
        .pledge-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="card register-card shadow-lg">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <div class="brand-logo mb-2">
                                <i class="fas fa-search-location me-2"></i>iFind<span>Peace</span>
                            </div>
                            <h5 class="fw-bold">Create Account</h5>
                            <small class="text-muted">Join the community</small>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                                <small><?php echo $error; ?></small>
                                <button type="button" class="btn-close p-2" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                                <small><?php echo $success; ?></small>
                                <button type="button" class="btn-close p-2" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary btn-sm">Proceed to Login</a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" name="student_id" class="form-control form-control-sm" required placeholder="Student ID">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="full_name" class="form-control form-control-sm" required placeholder="Full Name">
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <input type="email" name="email" class="form-control form-control-sm" required placeholder="Email Address">
                                </div>

                                <div class="row g-2 mt-2">
                                    <div class="col-6">
                                        <input type="password" name="password" class="form-control form-control-sm" required placeholder="Password (min 6)">
                                    </div>
                                    <div class="col-6">
                                        <input type="password" name="confirm_password" class="form-control form-control-sm" required placeholder="Confirm">
                                    </div>
                                </div>

                                <div class="row g-2 mt-2">
                                    <div class="col-6">
                                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="Phone (optional)">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="course" class="form-control form-control-sm" placeholder="Course (optional)">
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <select name="year_level" class="form-select form-select-sm">
                                        <option value="">Year Level (optional)</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                        <option value="5th Year">5th Year</option>
                                    </select>
                                </div>

                                <div class="pledge-box mt-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="honesty_pledge" id="honestyPledge" required>
                                        <label class="form-check-label fw-bold" for="honestyPledge">
                                            <i class="fas fa-handshake text-primary me-1"></i>I agree to be honest
                                        </label>
                                    </div>
                                    <small class="text-muted">I will report truthfully, not claim others' items, and follow platform rules.</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 btn-sm mt-3">
                                    Create Account
                                </button>
                            </form>

                            <div class="text-center mt-3">
                                <small class="text-muted">Already have an account? <a href="login.php">Login</a></small>
                            </div>
                            
                            <div class="text-center mt-2">
                                <a href="index.php" class="small text-muted"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
