<?php
require_once 'config/config.php';
requireLogin();

// Admin cannot report items
if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit();
}

$error = '';
$success = '';
$item_type = isset($_GET['type']) && $_GET['type'] === 'found' ? 'found' : 'lost';

$conn = getDBConnection();

// Get categories
$categories_query = "SELECT * FROM categories ORDER BY category_name";
$categories = $conn->query($categories_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_type = sanitize($_POST['item_type']);
    $category_id = intval($_POST['category_id']);
    $item_name = sanitize($_POST['item_name']);
    $description = sanitize($_POST['description']);
    $location = sanitize($_POST['location']);
    $date = sanitize($_POST['date']);
    $color = sanitize($_POST['color']);
    $brand = sanitize($_POST['brand']);
    
    if (empty($item_name) || empty($description) || empty($category_id)) {
        $error = 'Please fill in all required fields';
    } else {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_result = uploadFile($_FILES['image'], 'items/');
            if ($upload_result['success']) {
                $image_path = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        }
        
        if (empty($error)) {
            $user_id = $_SESSION['user_id'];
            $item_type = $item_type === 'found' ? 'found' : 'lost';

            if ($item_type === 'lost') {
                $stmt = $conn->prepare("INSERT INTO items (user_id, category_id, item_name, item_type, description, location_lost, date_lost, image_path, color, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            } else {
                $stmt = $conn->prepare("INSERT INTO items (user_id, category_id, item_name, item_type, description, location_found, date_found, image_path, color, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            }

            $stmt->bind_param("iissssssss", $user_id, $category_id, $item_name, $item_type, $description, $location, $date, $image_path, $color, $brand);
            
            if ($stmt->execute()) {
                $item_id = $conn->insert_id;
                $success = ucfirst($item_type) . ' item reported successfully!';
                logActivity($user_id, 'report_' . $item_type, "Reported $item_type item: $item_name");
                checkForMatches($item_id, $item_name, $category_id, $user_id, $conn);
            } else {
                $error = 'Failed to report item. Please try again.';
            }
            
            $stmt->close();
        }
    }
}

$conn->close();

function checkForMatches($item_id, $item_name, $category_id, $user_id, $conn) {
    $conn = getDBConnection();
    $match_query = "SELECT item_id, user_id, item_name FROM items WHERE item_type = 'found' AND category_id = ? AND status = 'pending' AND user_id != ? LIMIT 5";
    $stmt = $conn->prepare($match_query);
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    if (!$matches) {
        return;
    }

    sendNotification($user_id, $item_id, 'match_found', 'Potential matches found for your lost item. Check the browse items page.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Item - iFindPeace</title>
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
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="items.php">Browse Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="report.php">Report Item</a>
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
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Report <?php echo ucfirst($item_type); ?> Item</h4>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="items.php" class="btn btn-primary">Browse Items</a>
                                <a href="my-items.php" class="btn btn-outline-primary ms-2">View My Items</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label class="form-label">Item Type</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="item_type" id="typeLost" value="lost" <?php echo $item_type === 'lost' ? 'checked' : ''; ?> required>
                                        <label class="btn btn-outline-danger" for="typeLost" style="padding: 1rem 2rem;">
                                            <i class="fas fa-search me-2"></i>Lost
                                        </label>
                                        <input type="radio" class="btn-check" name="item_type" id="typeFound" value="found" <?php echo $item_type === 'found' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success" for="typeFound" style="padding: 1rem 2rem;">
                                            <i class="fas fa-hand-holding-heart me-2"></i>Found
                                        </label>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category *</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $cat['category_id']; ?>">
                                                    <i class="fas <?php echo $cat['icon']; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Item Name *</label>
                                        <input type="text" name="item_name" class="form-control" required placeholder="e.g., iPhone 13, Black Wallet">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description *</label>
                                    <textarea name="description" class="form-control" rows="4" required placeholder="Provide detailed description of the item..."></textarea>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" id="locationLabel"><?php echo $item_type === 'lost' ? 'Where was it lost?' : 'Where was it found?'; ?> *</label>
                                        <input type="text" name="location" class="form-control" required placeholder="e.g., Library, 2nd Floor">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" id="dateLabel"><?php echo $item_type === 'lost' ? 'Date Lost' : 'Date Found'; ?> *</label>
                                        <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Color</label>
                                        <input type="text" name="color" class="form-control" placeholder="e.g., Black, Blue">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Brand</label>
                                        <input type="text" name="brand" class="form-control" placeholder="e.g., Apple, Nike">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Item Image</label>
                                    <div class="custom-file-upload">
                                        <input type="file" name="image" class="form-control" accept="image/*" id="itemImage">
                                        <label for="itemImage" class="file-upload-label">
                                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                            <span>Click to upload or drag and drop</span>
                                            <small class="d-block text-muted">Upload a clear photo of the item (Max 5MB)</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 mt-4">
                                    <button type="submit" class="btn btn-primary flex-grow-1" style="padding: 0.875rem 2rem;">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Report
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary" style="padding: 0.875rem 2rem;">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update labels based on item type selection
        document.getElementById('typeLost').addEventListener('change', function() {
            document.getElementById('locationLabel').textContent = 'Where was it lost? *';
            document.getElementById('dateLabel').textContent = 'Date Lost';
        });
        
        document.getElementById('typeFound').addEventListener('change', function() {
            document.getElementById('locationLabel').textContent = 'Where was it found? *';
            document.getElementById('dateLabel').textContent = 'Date Found';
        });

        // File upload preview
        document.getElementById('itemImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const label = document.querySelector('.file-upload-label');
                label.innerHTML = `<i class="fas fa-check-circle fa-2x mb-2 text-success"></i><span>${file.name}</span><small class="d-block text-muted">Selected</small>`;
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
