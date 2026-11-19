<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    
    // Destroy session
    session_unset();
    session_destroy();
}

header("Location: index.php");
exit();
?>
