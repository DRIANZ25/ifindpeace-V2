<?php
require_once 'config/config.php';

if (!isset($_GET['id'])) {
    header("Location: items.php");
    exit();
}

$item_id = intval($_GET['id']);
$conn = getDBConnection();

// Increment view count
$conn->query("UPDATE items SET view_count = view_count + 1 WHERE item_id = $item_id");

// Get item details
$query = "SELECT i.*, c.category_name, u.full_name, u.student_id, u.email 
          FROM items i 
          JOIN categories c ON i.category_id = c.category_id 
          JOIN users u ON i.user_id = u.user_id 
          WHERE i.item_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: items.php");
    exit();
}

// Check if user has already claimed this item
$already_claimed = false;
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $claim_check = $conn->prepare("SELECT * FROM claims WHERE item_id = ? AND user_id = ?");
    $claim_check->bind_param("ii", $item_id, $user_id);
    $claim_check->execute();
    if ($claim_check->get_result()->num_rows > 0) {
        $already_claimed = true;
    }
    $claim_check->close();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_item'])) {
    requireLogin();
    
    if ($item['item_type'] !== 'found') {
        $error = 'Only found items can be claimed';
    } elseif ($item['status'] !== 'pending') {
        $error = 'This item is no longer available to claim';
    } elseif ($already_claimed) {
        $error = 'You have already claimed this item';
    } elseif ($item['user_id'] == $_SESSION['user_id']) {
        $error = 'You cannot claim your own item';
    } else {
        $security_question = sanitize($_POST['security_question']);
        $security_answer = sanitize($_POST['security_answer']);
        
        // Handle ID proof upload
        $id_proof_path = '';
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === 0) {
            $upload_result = uploadFile($_FILES['id_proof'], 'id_proofs/');
            if ($upload_result['success']) {
                $id_proof_path = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        } else {
            $error = 'Please upload your ID proof';
        }
        
        if (empty($error)) {
            $user_id = $_SESSION['user_id'];
            $deadline = date('Y-m-d', strtotime('+7 days'));
            
            $claim_stmt = $conn->prepare("INSERT INTO claims (item_id, user_id, id_proof_path, security_question, security_answer, deadline) VALUES (?, ?, ?, ?, ?, ?)");
            $claim_stmt->bind_param("iissss", $item_id, $user_id, $id_proof_path, $security_question, $security_answer, $deadline);
            
            if ($claim_stmt->execute()) {
                $claim_stmt->close();
                logActivity($user_id, 'claim_item', "Claimed item: {$item['item_name']}");
                sendNotification($item['user_id'], $item_id, 'admin_message', "Someone claimed your found item: {$item['item_name']}. Open the item page to review the claim.");
                header("Location: item-details.php?id=" . $item_id . "&claimed=1");
                exit();
            } else {
                $error = 'Failed to submit claim. Please try again.';
                $claim_stmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_claim'])) {
    requireLogin();
    $review = reviewClaim(
        intval($_POST['claim_id']),
        sanitize($_POST['status']),
        sanitize($_POST['admin_notes'] ?? ''),
        $_SESSION['user_id'],
        isAdmin()
    );
    if ($review['success']) {
        header("Location: item-details.php?id=" . $item_id . "&updated=1");
        exit();
    }
    $error = $review['message'];
}

if (isset($_GET['claimed'])) {
    $success = 'Claim submitted successfully. The item reporter will review it.';
}
if (isset($_GET['updated'])) {
    $success = 'Claim updated successfully.';
}

$stmt->close();

$incoming_claims = [];
$is_owner = isLoggedIn() && (int) $item['user_id'] === (int) $_SESSION['user_id'];
if ($is_owner) {
    $incoming_stmt = $conn->prepare("SELECT cl.*, u.full_name, u.student_id AS claimant_student_id
                                    FROM claims cl
                                    JOIN users u ON cl.user_id = u.user_id
                                    WHERE cl.item_id = ?
                                    ORDER BY cl.claim_date DESC");
    $incoming_stmt->bind_param("i", $item_id);
    $incoming_stmt->execute();
    $incoming_claims = $incoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $incoming_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['item_name']); ?> - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="report.php">Report Item</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php 
                                $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                                if ($unread_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu notification-dropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><a class="dropdown-item" href="notifications.php">View All</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="my-items.php"><i class="fas fa-box me-2"></i>My Items</a></li>
                                <li><hr class="dropdown-divider"></li>
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
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <?php if ($item['image_path']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>" style="max-height: 500px; object-fit: contain;">
                    <?php else: ?>
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                            <i class="fas fa-image fa-5x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?> fs-6">
                                <?php echo strtoupper($item['item_type']); ?>
                            </span>
                            <span class="badge bg-secondary">
                                <i class="fas fa-eye me-1"></i><?php echo $item['view_count']; ?> views
                            </span>
                        </div>
                        
                        <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($item['item_name']); ?></h2>
                        
                        <div class="mb-3">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                            </span>
                            <?php if ($item['color']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($item['color']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($item['brand']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-trademark me-1"></i><?php echo htmlspecialchars($item['brand']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="fw-bold">Description</h5>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="fw-bold">Details</h5>
                            <table class="table">
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?php echo ucfirst($item['item_type']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo $item['item_type'] == 'lost' ? 'Lost' : 'Found'; ?> Location:</strong></td>
                                    <td><?php echo htmlspecialchars($item['item_type'] == 'lost' ? ($item['location_lost'] ?: 'Not specified') : ($item['location_found'] ?: 'Not specified')); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo $item['item_type'] == 'lost' ? 'Lost' : 'Found'; ?> Date:</strong></td>
                                    <td><?php echo formatDisplayDate($item['item_type'] == 'lost' ? $item['date_lost'] : $item['date_found']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Posted By:</strong></td>
                                    <td><?php echo htmlspecialchars($item['full_name']); ?> (<?php echo htmlspecialchars($item['student_id']); ?>)</td>
                                </tr>
                                <tr>
                                    <td><strong>Posted On:</strong></td>
                                    <td><?php echo date('F d, Y g:i A', strtotime($item['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php if ($is_owner && $incoming_claims): ?>
                            <div class="mb-4">
                                <h5 class="fw-bold">Claims on this item</h5>
                                <?php foreach ($incoming_claims as $incoming): ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($incoming['full_name']); ?></strong>
                                            <span class="badge bg-secondary"><?php echo ucfirst($incoming['claim_status']); ?></span>
                                        </div>
                                        <p class="small text-muted mb-1"><?php echo htmlspecialchars($incoming['claimant_student_id']); ?> · Deadline <?php echo formatDisplayDate($incoming['deadline'], 'M d, Y'); ?></p>
                                        <p class="mb-1"><strong>Question:</strong> <?php echo htmlspecialchars($incoming['security_question']); ?></p>
                                        <p class="mb-2"><strong>Answer:</strong> <?php echo htmlspecialchars($incoming['security_answer']); ?></p>
                                        <?php if ($incoming['id_proof_path']): ?>
                                            <a href="uploads/<?php echo htmlspecialchars($incoming['id_proof_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-2">View ID proof</a>
                                        <?php endif; ?>
                                        <form method="POST" class="row g-2">
                                            <input type="hidden" name="review_claim" value="1">
                                            <input type="hidden" name="claim_id" value="<?php echo (int) $incoming['claim_id']; ?>">
                                            <div class="col-md-4">
                                                <select name="status" class="form-select form-select-sm">
                                                    <?php foreach (['pending', 'approved', 'rejected', 'completed'] as $status_option): ?>
                                                        <option value="<?php echo $status_option; ?>" <?php echo $incoming['claim_status'] === $status_option ? 'selected' : ''; ?>><?php echo ucfirst($status_option); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" name="admin_notes" class="form-control form-control-sm" placeholder="Notes" value="<?php echo htmlspecialchars($incoming['admin_notes'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="btn btn-primary btn-sm w-100">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($item['item_type'] === 'found' && $item['status'] === 'pending' && isLoggedIn() && !isAdmin() && !$already_claimed && $item['user_id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#claimModal">
                                <i class="fas fa-hand-paper me-2"></i>Claim This Item
                            </button>
                        <?php elseif ($already_claimed): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>You have already claimed this item
                            </div>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Claim
                            </a>
                        <?php endif; ?>
                        
                        <a href="items.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Browse
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Claim Modal -->
    <?php if ($item['item_type'] === 'found' && $item['status'] === 'pending' && isLoggedIn() && !isAdmin() && !$already_claimed && $item['user_id'] != $_SESSION['user_id']): ?>
        <div class="modal fade" id="claimModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Claim This Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">ID Proof *</label>
                                    <input type="file" name="id_proof" class="form-control" accept="image/*,.pdf" required>
                                    <small class="text-muted">Upload your student ID or any valid ID (Max 5MB)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Security Question *</label>
                                    <input type="text" name="security_question" class="form-control" required placeholder="e.g., What color is the item?">
                                    <small class="text-muted">A question only the rightful owner can answer</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Your Answer *</label>
                                    <input type="text" name="security_answer" class="form-control" required placeholder="Your answer to the security question">
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Your claim will be reviewed by the item reporter. You have 7 days to complete the verification process.
                                </div>
                                
                                <button type="submit" name="claim_item" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Claim
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
