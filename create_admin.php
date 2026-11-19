<?php
require_once 'config/config.php';
$conn = getDBConnection();

$email = 'admin@ifindpeace.com';
$password = 'admin123';
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Try to update existing user
$stmt = $conn->prepare("UPDATE users SET password = ?, user_type = 'admin' WHERE email = ?");
$stmt->bind_param("ss", $hashed, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Success: Existing account updated to Admin with new password!";
} else {
    // If no rows were updated, insert a brand new admin
    $stmt = $conn->prepare("INSERT INTO users (student_id, full_name, email, password, user_type, honesty_pledge_signed) VALUES ('ADMIN-001', 'System Administrator', ?, ?, 'admin', 1)");
    $stmt->bind_param("ss", $email, $hashed);
    if ($stmt->execute()) {
        echo "Success: Brand new Admin account created!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
$conn->close();
?>