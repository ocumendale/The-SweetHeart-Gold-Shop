<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
  exit;
}
// ✅ Function to log activity (no product_id)
function log_activity($conn, $action, $description) {
    $stmt = $conn->prepare("INSERT INTO activity_log (action, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $action, $description);
    $stmt->execute();
    $stmt->close();
}

// ✅ Restore product if restore button is clicked
if (isset($_GET['restore_id'])) {
    $id = intval($_GET['restore_id']);
    
    // Get product name for logging
    $product_stmt = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
    $product_stmt->bind_param("i", $id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    $product_name = $product_result->fetch_assoc()['product_name'] ?? 'Unknown Product';
    
    $sql = "UPDATE products SET is_deleted = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        log_activity($conn, "restore", "Restored product: $product_name (ID: $id)");
        header("Location: recyclebin.php?msg=restored");
        exit();
    }
}

// ✅ Permanently delete product
if (isset($_GET['permanent_delete_id'])) {
    $id = intval($_GET['permanent_delete_id']);
    
    // Get product name for logging
    $product_stmt = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
    $product_stmt->bind_param("i", $id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    $product_name = $product_result->fetch_assoc()['product_name'] ?? 'Unknown Product';
    
    $sql = "DELETE FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        log_activity($conn, "permanent_delete", "Permanently deleted product: $product_name (ID: $id)");
        header("Location: recyclebin.php?msg=deleted");
        exit();
    }
}

// ✅ Fetch only deleted products
$sql = "SELECT * FROM products WHERE is_deleted = 1 ORDER BY id DESC";
$result = $conn->query($sql);

// Calculate recycle bin statistics
$total_deleted = 0;
$categories = [];

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $total_deleted++;
        if (!in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
    }
    $result->data_seek(0);
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recycle Bin - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="CSS/Recyclebin.css">
  </head>
  <body>

    <!-- Sidebar -->
    <div class="d-flex flex-column flex-shrink-0 p-3 sidebar">
    
    <br><br><br>
    <div class="logo">
        <div class="sweetheart">SweetHeart</div>
        <div class="goldshop">Gold Shop</div>
    </div>

    <br><br><br>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item mb-4">
          <a href="dashboard.php" class="nav-link text-white">
            <img src="../icons/home.png" alt="Dashboard" class="nav-icon" />
            Dashboard</a>
        </li>
        <li class="mb-4">
            <a href="inventory.php" class="nav-link text-white">
                <img src="../icons/inventory.png" alt="Inventory" class="nav-icon" />
                Inventory</a>
        </li>
        <li class="mb-4">
            <a href="orders.php" class="nav-link text-white">
                <img src="../icons/orders.png" alt="Orders" class="nav-icon" />
                Orders
            </a>
        </li>
        <li class="mb-4">
            <a href="customers.php" class="nav-link text-white">
                <img src="../icons/customers.png" alt="Customers" class="nav-icon" />
                Customers</a>
        </li>
        <li class="mb-4">
            <a href="sales_report.php" class="nav-link text-white">
                <img src="../icons/salesreport.png" alt="salesreport" class="nav-icon" />
                Sales Report</a>
        </li>
        <li class="mb-4">
            <a href="activitylog.php" class="nav-link active">
            <img src="../icons/restore.png" alt="Activity Log and Recycle Bin" class="nav-icon" />Log and Bin
          </a>
        </li>
      </ul>
      
    </div>

    <!-- Content Area -->
    <div class="content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>RECYCLE BIN</h1>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>

        <!-- Messages -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-custom">
                <?php if ($_GET['msg'] == 'restored'): ?>
                    <strong>Success!</strong> Product has been restored to inventory.
                <?php elseif ($_GET['msg'] == 'deleted'): ?>
                    <strong>Deleted!</strong> Product has been permanently deleted.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">🗑️ Deleted Products</div>
                <div class="stat-value"><?= $total_deleted ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">📦 Categories Affected</div>
                <div class="stat-value"><?= count($categories) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">💾 Storage Space</div>
                <div class="stat-value" style="font-size: 24px;">Recoverable</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">⚙️ Actions Available</div>
                <div class="stat-value" style="font-size: 24px;">Restore/Delete</div>
            </div>
        </div>

        <!-- Action Buttons Card -->
        <div class="card">
            <div class="action-buttons-section">
                
                <a href="activitylog.php" style="text-decoration: none;">
                    <button class="action-btn btn-activity">
                        <img src="../icons/restore.png" alt="Activity" style="width:24px; height:24px; margin-right:8px;">
                        Activity Log
                    </button>
                </a>
            </div>
        </div>

        <!-- Products Table Card -->
        <div class="card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>🗑️ Deleted Products - Showing <?= $total_deleted ?> items</h3>
                <?php if ($total_deleted > 0): ?>
                    <small class="text-muted">These items can be restored or permanently deleted</small>
                <?php endif; ?>
            </div>

            <div class="recycle-table">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Material</th>
                            <th>Size/Details</th>
                            <th style="width: 100px;">Stock</th>
                            <th style="width: 130px;">Price</th>
                            <th style="width: 220px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($row['image']) ?>" 
                                                 alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                 class="product-image-thumb">
                                        <?php else: ?>
                                            <img src="../productPic/default.jpg" alt="No image" class="product-image-thumb">
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['product_name']) ?></strong></td>
                                    <td><span class="category-badge"><?= htmlspecialchars($row['category']) ?></span></td>
                                    <td><?= htmlspecialchars($row['material'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        switch (strtolower($row['category'])) {
                                            case 'ring':
                                                echo "Size: " . ($row['ring_size'] ?? 'N/A');
                                                break;
                                            case 'necklace':
                                                echo "Length: " . ($row['necklace_length'] ?? 'N/A');
                                                break;
                                            case 'earring':
                                                echo "Closure: " . ($row['closure_type'] ?? 'N/A');
                                                break;
                                            case 'bracelet':
                                                echo "Length: " . ($row['bracelet_length'] ?? 'N/A');
                                                break;
                                            default:
                                                echo "-";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="deleted-badge-table"><?= $row['stock'] ?> pcs</span>
                                    </td>
                                    <td class="price-cell">₱<?= number_format($row['price'], 2) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="recyclebin.php?restore_id=<?= $row['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to restore this product?');"
                                               class="btn-table-restore">Restore</a>
                                            <a href="recyclebin.php?permanent_delete_id=<?= $row['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to permanently delete this product? This action cannot be undone!');"
                                               class="btn-table-delete-forever">Delete Forever</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="empty-state">
                                        <h4>Recycle Bin is Empty</h4>
                                        <p>No deleted products found. All products are currently active in your inventory.</p>
                                        <a href="inventory.php" class="btn btn-primary">Back to Inventory</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>