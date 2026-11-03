<?php
include 'db.php'; // adjust if needed

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
  exit;
}

// Get dashboard data
$totalOrders = $conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()['total'];
$totalSales = $conn->query("SELECT SUM(total_price) AS total FROM orders WHERE status='Delivered'")->fetch_assoc()['total'] ?? 0;
$todaySales = $conn->query("SELECT SUM(total_price) AS total FROM orders WHERE DATE(order_date) = CURDATE() AND status='Delivered'")->fetch_assoc()['total'] ?? 0;
$monthlyOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE())")->fetch_assoc()['total'];
$totalCustomers = $conn->query("SELECT COUNT(DISTINCT user_id) AS total FROM orders")->fetch_assoc()['total'];
$lowStock = $conn->query("SELECT COUNT(*) AS total FROM products WHERE stock < 5")->fetch_assoc()['total'];

// Get recent orders (simplified - assuming no order_items table)
$recentOrders = $conn->query("
  SELECT o.id, o.total_price, o.status, o.order_date, 
         o.shipping_name AS customer_name
  FROM orders o
  ORDER BY o.order_date DESC
  LIMIT 4
");

// Get sales data for chart (last 7 days)
$salesData = $conn->query("
  SELECT DATE(order_date) as date, SUM(total_price) as total
  FROM orders
  WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
  AND status = 'Delivered'
  GROUP BY DATE(order_date)
  ORDER BY date ASC
");

$chartLabels = [];
$chartData = [];
while($row = $salesData->fetch_assoc()) {
  $chartLabels[] = date('M d', strtotime($row['date']));
  $chartData[] = $row['total'];
}
// Get top products (without order_items, showing all products by price)
$topProducts = $conn->query("
  SELECT id, product_name, price, category, image
  FROM products
  WHERE is_deleted = 0
  ORDER BY price DESC
  LIMIT 4
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="CSS/Dashboard.css">
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
                <a href="dashboard.php" class="nav-link active">
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
                <a href="customers.php" class="nav-link text-white">
                    <img src="../icons/customers.png" alt="Customers" class="nav-icon" />
                    Customers
                </a>
            </li>
            <li class="mb-4">
                <a href="sales_report.php" class="nav-link text-white">
                    <img src="../icons/salesreport.png" alt="salesreport" class="nav-icon" />
                    Sales Report
                </a>
            </li>
            <li class="mb-4">
                <a href="activitylog.php" class="nav-link text-white">
                    <img src="../icons/restore.png" alt="Activity Log and Recycle Bin" class="nav-icon" />Log and Bin
                </a>
            </li>
        </ul>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <div class="header">
            <div>
                <h1>DASHBOARD</h1>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">💰 Today's Sales</div>
                <div class="stat-value">₱<?= number_format($todaySales, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📦 Monthly Orders</div>
                <div class="stat-value"><?= $monthlyOrders ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">⚠️ Low Stock Items</div>
                <div class="stat-value"><?= $lowStock ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">💵 Total Revenue</div>
                <div class="stat-value">₱<?= number_format($totalSales, 2) ?></div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Recent Orders -->
            <div class="col-md-8">
                <div class="card">
                    <h3>Recent Orders</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($order = $recentOrders->fetch_assoc()): ?>
                            <tr>
                                <td class="order-id">#ORD-<?= str_pad($order['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                                <td>Various Items</td>
                                <td class="status-cell" data-status="<?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></td>
                                <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                <td class="price">₱<?= number_format($order['total_price'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Products -->
            <div class="col-md-4">
                <div class="card">
                    <h3>Top Products</h3>
                    <div class="product-list">
                        <?php while($product = $topProducts->fetch_assoc()): ?>
                        <div class="product-item">
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                <span class="product-badge"><?= strtoupper(htmlspecialchars($product['category'])) ?></span>
                            </div>
                            <div class="product-price">₱<?= number_format($product['price'], 2) ?></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Sales Chart -->
            <div class="col-md-8">
                <div class="card">
                    <h3>Sales Overview (Last 7 Days)</h3>
                    <div class="chart-container">
                        <canvas id="salesChart" height="90"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-md-4">
                <div class="card">
                    <h3>Recent Activities</h3>
                    <div class="activity-list">
                        <div class="activity-item">
                            <img src="../icons/newOrder.png" alt="icon" width="32" height="32">
                            <div class="activity-content">
                                <div class="activity-title"><?= $totalOrders ?> total orders</div>
                                <div class="activity-subtitle">All time</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <img src="../icons/jewelry.png" alt="icon" width="32" height="32">
                            <div class="activity-content">
                                <div class="activity-title">Stock management</div>
                                <div class="activity-subtitle">Monitor inventory</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <img src="../icons/newcustomer.png" alt="icon" width="32" height="32">
                            <div class="activity-content">
                                <div class="activity-title"><?= $totalCustomers ?> customers</div>
                                <div class="activity-subtitle">Total registered</div>
                            </div>
                        </div>
                        <?php if($lowStock > 0): ?>
                        <div class="activity-item">
                            <img src="../icons/lowstock.png" alt="icon" width="32" height="32">
                            <div class="activity-content">
                                <div class="activity-title" style="color: #dc3545;"><?= $lowStock ?> items low stock</div>
                                <div class="activity-subtitle">Needs attention</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales Chart - matching Sales Report style
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: 'rgba(212, 165, 116, 0.8)',
                    borderColor: '#d4a574',
                    borderWidth: 0,
                    borderRadius: 6,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Sales Performance Over Time',
                        color: '#5a4a3a',
                        font: { size: 16, family: "'Poppins', sans-serif", weight: '600' },
                        padding: 20
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Order Status Badges
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".status-cell").forEach(function (cell) {
                let status = cell.getAttribute('data-status');
                let badge = document.createElement("span");
                badge.classList.add("badge", "bg-success");

                if (status === "complete" || status === "completed" || status === "delivered") {
                    badge.style.backgroundColor = "#86D391";
                    badge.textContent = "COMPLETE";
                } else if (status === "canceled" || status === "cancelled" || status === "cancel") {
                    badge.style.backgroundColor = "#FF7477";
                    badge.textContent = "CANCELED";
                } else if (status === "pending") {
                    badge.style.backgroundColor = "#F7BA69";
                    badge.textContent = "PENDING";
                } else if (status === "processing") {
                    badge.style.backgroundColor = "#6CB4EE";
                    badge.textContent = "PROCESSING";
                } else {
                    badge.textContent = cell.textContent.toUpperCase();
                }

                cell.textContent = "";
                cell.appendChild(badge);
            });
        });
    </script>
</body>
</html>