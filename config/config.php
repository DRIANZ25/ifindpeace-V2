<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. DATABASE CONNECTION (Fixed for Railway)
function getDBConnection() {
    // Try all methods to get the environment variables from Railway
    $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT');
    $dbname = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE');
    $username = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME');
    $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

    // Fallback for local XAMPP development (forces TCP connection, not a socket)
    if (!$host) {
        $host = '127.0.0.1'; 
        $port = 3306;
        $dbname = 'ifindpeace';
        $username = 'root';
        $password = '';
    }

    $port = (int)$port;

    // Suppress warnings so we can handle errors cleanly
    mysqli_report(MYSQLI_REPORT_OFF);
    
    $conn = new mysqli($host, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        die("Database Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// 2. AUTHENTICATION HELPERS
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// 3. SECURITY & UTILITIES
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 4. FILE UPLOAD HELPER
function uploadFile($file, $subfolder = '') {
    $target_dir = "uploads/" . $subfolder;
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $filename = uniqid() . '_' . basename($file["name"]);
    $target_file = $target_dir . $filename;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }

    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'Sorry, your file is too large.'];
    }

    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return ['success' => false, 'message' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.'];
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $subfolder . $filename];
    } else {
        return ['success' => false, 'message' => 'Sorry, there was an error uploading your file.'];
    }
}

function deleteFile($filepath) {
    if (!empty($filepath) && file_exists("uploads/" . $filepath)) {
        unlink("uploads/" . $filepath);
    }
}

// 5. NOTIFICATIONS & ACTIVITY LOGGING
function getUnreadNotificationCount($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    $conn->close();
    return $count;
}

function sendNotification($user_id, $item_id, $type, $message) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, item_id, notification_type, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $item_id, $type, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function logActivity($user_id, $action, $description) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// 6. FORMATTING
function formatDisplayDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}
?>