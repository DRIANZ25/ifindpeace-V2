<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if (isset($_GET['read'])) {
    $message_id = intval($_GET['read']);
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read = TRUE WHERE message_id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $stmt->close();
    header("Location: messages.php");
    exit();
}

$messages = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - iFindPeace Admin</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="msu-nav-text ms-3 d-none d-lg-flex align-items-center">
                    <img src="../assets/images/msu-logo.jpg" alt="MSU Logo" class="msu-logo-icon me-2">
                    <div>
                        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">MINDANAO STATE UNIVERSITY</small>
                        <br>
                        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">GENERAL SANTOS</small>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold">Contact Messages</h2>
                    <p class="text-muted">Messages sent from the contact page</p>
                </div>
            </div>

            <?php if ($messages): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Received</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($message['name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($message['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($message['message'])); ?></td>
                                    <td><?php echo formatDisplayDate($message['created_at'], 'M d, Y g:i A'); ?></td>
                                    <td>
                                        <?php if ($message['is_read']): ?>
                                            <span class="badge bg-secondary">Read</span>
                                        <?php else: ?>
                                            <a href="messages.php?read=<?php echo (int) $message['message_id']; ?>" class="btn btn-sm btn-primary">Mark read</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open fa-4x"></i>
                    <h4>No messages yet</h4>
                    <p>Contact form submissions will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
