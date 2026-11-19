<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ifindpeace');

// Site Configuration
define('SITE_NAME', 'iFindPeace');
define('SITE_TAGLINE', 'Peace Comes When Things Are Found');
define('BASE_URL', 'http://localhost/ifindpeace%20V2/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Sanitize Input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: " . BASE_URL . "dashboard.php");
        exit();
    }
}

// Log activity
function logActivity($user_id, $action, $description = '') {
    $conn = getDBConnection();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Send notification
function sendNotification($user_id, $item_id, $type, $message) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, item_id, notification_type, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $item_id, $type, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Get unread notification count
function getUnreadNotificationCount($user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $row['count'];
}

// Upload file
function uploadFile($file, $subdir = '') {
    $target_dir = UPLOAD_DIR . $subdir;
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, GIF, and PDF files are allowed.'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum limit of 5MB.'];
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $subdir . $new_filename];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file.'];
}

// Delete file
function deleteFile($filepath) {
    $full_path = UPLOAD_DIR . $filepath;
    if ($filepath && file_exists($full_path)) {
        unlink($full_path);
    }
}

function formatDisplayDate($value, $format = 'F d, Y') {
    if (empty($value)) {
        return 'Not specified';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'Not specified';
    }
    return date($format, $timestamp);
}

function reviewClaim($claim_id, $status, $notes, $actor_id, $actor_is_admin) {
    $allowed = ['pending', 'approved', 'rejected', 'completed'];
    if (!in_array($status, $allowed, true)) {
        return ['success' => false, 'message' => 'Invalid claim status'];
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT cl.claim_id, cl.item_id, cl.user_id AS claimant_id, i.item_name, i.user_id AS reporter_id
                            FROM claims cl
                            JOIN items i ON cl.item_id = i.item_id
                            WHERE cl.claim_id = ?");
    $stmt->bind_param("i", $claim_id);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$claim) {
        $conn->close();
        return ['success' => false, 'message' => 'Claim not found'];
    }

    if (!$actor_is_admin && (int) $claim['reporter_id'] !== (int) $actor_id) {
        $conn->close();
        return ['success' => false, 'message' => 'You cannot review this claim'];
    }

    $stmt = $conn->prepare("UPDATE claims SET claim_status = ?, admin_notes = ? WHERE claim_id = ?");
    $stmt->bind_param("ssi", $status, $notes, $claim_id);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $conn->close();
        return ['success' => false, 'message' => 'Failed to update claim'];
    }

    if ($status === 'approved' || $status === 'completed') {
        $item_status = 'claimed';
        $stmt = $conn->prepare("UPDATE items SET status = ? WHERE item_id = ?");
        $stmt->bind_param("si", $item_status, $claim['item_id']);
        $stmt->execute();
        $stmt->close();

        $pending = 'pending';
        $stmt = $conn->prepare("SELECT user_id FROM claims WHERE item_id = ? AND claim_id != ? AND claim_status = ?");
        $stmt->bind_param("iis", $claim['item_id'], $claim_id, $pending);
        $stmt->execute();
        $others = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if ($others) {
            $rejected = 'rejected';
            $stmt = $conn->prepare("UPDATE claims SET claim_status = ? WHERE item_id = ? AND claim_id != ? AND claim_status = ?");
            $stmt->bind_param("siis", $rejected, $claim['item_id'], $claim_id, $pending);
            $stmt->execute();
            $stmt->close();

            foreach ($others as $other) {
                sendNotification($other['user_id'], $claim['item_id'], 'claim_rejected', "Your claim for {$claim['item_name']} was closed because another claim was approved.");
            }
        }

        sendNotification($claim['claimant_id'], $claim['item_id'], 'claim_approved', "Your claim for {$claim['item_name']} has been approved!");
    } elseif ($status === 'rejected') {
        sendNotification($claim['claimant_id'], $claim['item_id'], 'claim_rejected', "Your claim for {$claim['item_name']} has been rejected.");
    } elseif ($status === 'completed') {
        // Mark item as returned when claim is completed
        $returned = 'returned';
        $stmt = $conn->prepare("UPDATE items SET status = ? WHERE item_id = ?");
        $stmt->bind_param("si", $returned, $claim['item_id']);
        $stmt->execute();
        $stmt->close();

        sendNotification($claim['claimant_id'], $claim['item_id'], 'item_returned', "Your item {$claim['item_name']} has been returned to you!");
        sendNotification($claim['reporter_id'], $claim['item_id'], 'item_returned', "Your found item {$claim['item_name']} has been returned to the claimant.");
    }

    logActivity($actor_id, 'update_claim_status', "Updated claim $claim_id status to $status");
    $conn->close();

    return ['success' => true, 'message' => 'Claim updated'];
}
?>
