<?php
// admin/customerDetails.php
session_start();
include 'db.php';

// Get customer ID from URL
$customer_id = $_GET['id'] ?? '';

if (empty($customer_id)) {
    header('Location: customers.php');
    exit;
}

// Determine if it's a guest customer or registered user
$is_guest = strpos($customer_id, 'guest_') === 0;

if ($is_guest) {
    // Extract order ID from guest customer ID
    $order_id = str_replace('guest_', '', $customer_id);
    
    // Get guest customer info from orders - FIXED VERSION
    $sql = "SELECT 
                CONCAT('guest_', MIN(o.id)) as customer_id,
                MAX(o.shipping_name) as customer_name,
                MAX(o.shipping_phone) as contact_number,
                MAX(o.shipping_address) as address,
                MIN(o.order_date) as registration_date,
                CASE 
                    WHEN MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active'
                    ELSE 'Inactive'
                END as status
            FROM orders o
            WHERE CONCAT('guest_', o.id) = ?
            GROUP BY o.shipping_name, o.shipping_phone
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $customer_id);
} else {
    // Get registered user info - FIXED VERSION
    $sql = "SELECT 
                u.id as customer_id,
                u.username as customer_name,
                MAX(o.shipping_phone) as contact_number,
                MAX(o.shipping_address) as address,
                MIN(o.order_date) as registration_date,
                CASE 
                    WHEN MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active'
                    ELSE 'Inactive'
                END as status
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.id = ?
            GROUP BY u.id, u.username
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $customer_id);
}

$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Get customer statistics
if ($is_guest) {
    $stats_sql = "SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COALESCE(SUM(o.total_price), 0) as total_spent,
                    COALESCE(COUNT(DISTINCT oi.product_id), 0) as cart_items
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.shipping_name = ? AND o.shipping_phone = ?";
    
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param('ss', $customer['customer_name'], $customer['contact_number']);
} else {
    $stats_sql = "SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COALESCE(SUM(o.total_price), 0) as total_spent,
                    COALESCE(COUNT(DISTINCT oi.product_id), 0) as cart_items
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.user_id = ?";
    
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param('s', $customer_id);
}

$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent orders
if ($is_guest) {
    $orders_sql = "SELECT 
                    o.id as order_id,
                    o.order_date as date,
                    o.total_price as amount,
                    o.status
                FROM orders o
                WHERE o.shipping_name = ? AND o.shipping_phone = ?
                ORDER BY o.order_date DESC
                LIMIT 10";
    
    $stmt = $conn->prepare($orders_sql);
    $stmt->bind_param('ss', $customer['customer_name'], $customer['contact_number']);
} else {
    $orders_sql = "SELECT 
                    o.id as order_id,
                    o.order_date as date,
                    o.total_price as amount,
                    o.status
                FROM orders o
                WHERE o.user_id = ?
                ORDER BY o.order_date DESC
                LIMIT 10";
    
    $stmt = $conn->prepare($orders_sql);
    $stmt->bind_param('s', $customer_id);
}

$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Details - <?= htmlspecialchars($customer['customer_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/CustomerDetails.css">
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
                    Dashboard
                </a>
            </li>
            <li class="mb-4">
                <a href="inventory.php" class="nav-link text-white">
                    <img src="../icons/inventory.png" alt="Inventory" class="nav-icon" />
                    Inventory
                </a>
            </li>
            <li class="mb-4">
                <a href="orders.php" class="nav-link text-white">
                    <img src="../icons/orders.png" alt="Orders" class="nav-icon" />
                    Orders
                </a>
            </li>
            <li class="mb-4">
                <a href="customers.php" class="nav-link active">
                    <img src="../icons/customers.png" alt="Customers" class="nav-icon" />
                    Customers
                </a>
            </li>
            <li class="mb-4">
                <a href="sales_report.php" class="nav-link text-white">
                    <img src="../icons/salesreport.png" alt="Sales Report" class="nav-icon" />
                    Sales Report
                </a>
            </li>
            <li class="mb-4">
                <a href="activitylog.php" class="nav-link text-white">
                    <img src="../icons/restore.png" alt="Activity Log and Recycle Bin" class="nav-icon" />
                    Log and Bin
                </a>
            </li>
        </ul>
    </div>

    <!-- Content Area -->
    <div class="content">
        <!-- Header Section -->
        <div class="header-section">
            <div>
                <a href="customers.php" class="back-button">
                    ← Back to Customers
                </a>
            </div>
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Customer Info Card -->
        <div class="customer-card">
            <div class="customer-header">
                <div class="customer-main-info">
                    <div class="customer-avatar-large">
                        <img src="../icons/newcustomer.png" alt="Customer Avatar">
                    </div>
                    <div class="customer-name-section">
                        <h2><?= htmlspecialchars($customer['customer_name']) ?></h2>
                        <div class="customer-id-badge">Customer ID: #<?= htmlspecialchars($customer['customer_id']) ?></div>
                    </div>
                </div>
                <div>
                    <span class="status-badge-large status-<?= strtolower($customer['status']) ?>">
                        <?= $customer['status'] ?>
                    </span>
                </div>
            </div>

            <div class="customer-details-grid">
                <div class="detail-item">
                    <label>Address</label>
                    <div class="value"><?= htmlspecialchars($customer['address'] ?? 'N/A') ?></div>
                </div>
                <div class="detail-item">
                    <label>Contact Number</label>
                    <div class="value"><?= htmlspecialchars($customer['contact_number'] ?? 'N/A') ?></div>
                </div>
                <div class="detail-item">
                    <label>Registration Date</label>
                    <div class="value"><?= $customer['registration_date'] ? date('M d, Y', strtotime($customer['registration_date'])) : 'N/A' ?></div>
                </div>
                <div class="detail-item">
                    <label>Customer Type</label>
                    <div class="value"><?= $is_guest ? 'Guest Customer' : 'Registered User' ?></div>
                </div>
            </div>

           
        </div>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">🧮 Total Orders</div>
                <div class="stat-number"><?= $stats['total_orders'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">💰 Total Spent</div>
                <div class="stat-number">₱<?= number_format($stats['total_spent'], 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🛒 Cart Items</div>
                <div class="stat-number"><?= str_pad($stats['cart_items'], 2, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <!-- Recent Orders Section -->
        <div class="orders-section">
            <h3 class="section-title">📋 Recent Orders</h3>
            <?php if (empty($orders)): ?>
                <div style="text-align: center; padding: 40px; color: #8b7355;">
                    <h4>No orders found</h4>
                    <p>This customer hasn't placed any orders yet.</p>
                </div>
            <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong class="order-id">#ORD-<?= str_pad($order['order_id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($order['date'])) ?></td>
                        <td><strong style="color: #d6a76d;">₱<?= number_format($order['amount'], 2) ?></strong></td>
                        <td>
                            <span class="order-status status-<?= strtolower($order['status']) ?>">
                                <?= strtoupper($order['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCustomer() {
            // Implement edit functionality
            alert('Edit customer functionality to be implemented');
        }

        function suspendAccount() {
            if (confirm('Are you sure you want to suspend this account?')) {
                // Implement suspend functionality
                alert('Suspend account functionality to be implemented');
            }
        }
    </script>
</body>
</html>