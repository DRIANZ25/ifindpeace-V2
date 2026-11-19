<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['count' => 0]);
    exit();
}

echo json_encode(['count' => (int) getUnreadNotificationCount($_SESSION['user_id'])]);
