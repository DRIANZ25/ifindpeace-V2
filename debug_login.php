<?php
require_once 'config/config.php';
$conn = getDBConnection();

$email = 'admin@ifindpeace.com';
$password = 'admin123';

// 1. Ensure the column is large enough (VARCHAR 255)
$conn->query("ALTER TABLE users MODIFY password VARCHAR(255)");

// 2. Generate a fresh hash
$hashed = password_hash($password, PASSWORD_DEFAULT);

// 3. Delete the old broken account
$conn->query("DELETE FROM users WHERE email = '$email'");

// 4. Insert the new admin with 'status' set to 'active'
$stmt = $conn->prepare("INSERT INTO users (student_id, full_name, email, password, user_type, status, honesty_pledge_signed) VALUES ('ADMIN-001', 'System Administrator', ?, ?, 'admin', 'active', 1)");
$stmt->bind_param("ss", $email, $hashed);
$stmt->execute();

// 5. Fetch the newly inserted row to verify
$result = $conn->query("SELECT password, status FROM users WHERE email = '$email'");
$row = $result->fetch_assoc();

echo "<h2>Debug Results:</h2>";
echo "<b>Email:</b> " . $email . "<br>";
echo "<b>Password we are trying:</b> " . $password . "<br>";
echo "<b>Hash in Database:</b> " . $row['password'] . "<br>";
echo "<b>Status in Database:</b> " . $row['status'] . "<br><br>";

if (password_verify($password, $row['password'])) {
    echo "<h3 style='color:green;'>SUCCESS! The password matches the hash.</h3>";
    echo "<p>You can now delete this debug file and log in.</p>";
} else {
    echo "<h3 style='color:red;'>FAILED! The hash does not match.</h3>";
}
$conn->close();
?>