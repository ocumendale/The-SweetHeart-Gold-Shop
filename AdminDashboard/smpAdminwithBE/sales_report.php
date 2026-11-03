<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
    exit;
}

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$params = [];
$types = '';

// Main query for orders - Filter by Delivered status
$query = "SELECT o.id, o.user_id, o.shipping_name, o.shipping_address, o.shipping_city, 
          o.shipping_phone, o.total_price, o.payment_method, o.status, o.order_date 
          FROM orders o WHERE o.status = 'Delivered'";

// Apply date filters - Use DATE() function for consistency
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(o.order_date) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $types = "ss";
} elseif (!empty($start_date)) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params = [$start_date];
    $types = "s";
} elseif (!empty($end_date)) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params = [$end_date];
    $types = "s";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$total_orders = 0;
$total_revenue = 0;
$completed_revenue = 0;
$pending_revenue = 0;
$cancelled_revenue = 0;
$payment_methods = [];
$products_sold = [];
$customers_count = [];
$sales_by_date = [];

$orders_data = [];
while ($row = $result->fetch_assoc()) {
    $orders_data[] = $row;
    $total_orders++;
    $total_revenue += $row['total_price'];
    
    // Status-based revenue
    if ($row['status'] == 'Delivered') {
        $completed_revenue += $row['total_price'];
    } elseif ($row['status'] == 'Pending' || $row['status'] == 'Processing') {
        $pending_revenue += $row['total_price'];
    } elseif ($row['status'] == 'Cancelled') {
        $cancelled_revenue += $row['total_price'];
    }
    
    // Payment methods
    $pm = $row['payment_method'] ?? 'Unknown';
    $payment_methods[$pm] = ($payment_methods[$pm] ?? 0) + 1;
    
    // Customer tracking
    $customers_count[$row['shipping_name']] = ($customers_count[$row['shipping_name']] ?? 0) + $row['total_price'];
    
    // Sales by date
    $date = date('Y-m-d', strtotime($row['order_date']));
    $sales_by_date[$date] = ($sales_by_date[$date] ?? 0) + $row['total_price'];
}

// Get best-selling products (only from Delivered orders)
$product_query = "SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.price * oi.quantity) as total_sales
                  FROM order_items oi 
                  INNER JOIN orders o ON oi.order_id = o.id 
                  WHERE o.status = 'Delivered'";

// Apply the SAME date filters as the main query
if (!empty($start_date) && !empty($end_date)) {
    $product_query .= " AND DATE(o.order_date) BETWEEN ? AND ?";
} elseif (!empty($start_date)) {
    $product_query .= " AND DATE(o.order_date) >= ?";
} elseif (!empty($end_date)) {
    $product_query .= " AND DATE(o.order_date) <= ?";
}

$product_query .= " GROUP BY oi.product_name ORDER BY total_qty DESC LIMIT 1";

$prod_stmt = $conn->prepare($product_query);
if (!empty($params)) {
    $prod_stmt->bind_param($types, ...$params);
}
$prod_stmt->execute();
$prod_result = $prod_stmt->get_result();
$best_product = $prod_result->fetch_assoc();
$prod_stmt->close();

// Most used payment method
$most_payment = 'N/A';
if (!empty($payment_methods)) {
    $most_payment = array_keys($payment_methods, max($payment_methods))[0];
}

// Top customer
$top_customer = 'N/A';
$top_customer_amount = 0;
if (!empty($customers_count)) {
    arsort($customers_count);
    $top_customer = array_key_first($customers_count);
    $top_customer_amount = $customers_count[$top_customer];
}

// Average order value
$avg_order = $total_orders > 0 ? $total_revenue / $total_orders : 0;

ksort($sales_by_date);

// Chart data
$dates = array_keys($sales_by_date);
$sales = array_values($sales_by_date);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Report - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Salesreport.css">
    
    <style>
        /* Hide print section on screen */
        .print-section {
            position: absolute;
            left: -9999px;
            top: -9999px;
            width: 0;
            height: 0;
            overflow: hidden;
        }
        
        /* Print-specific styles */
        @media print {
            /* Remove browser headers and footers */
            @page {
                margin: 0.5cm;
            }
            
            body {
                margin: 0;
            }
            
            /* Hide everything except print section */
            body * {
                visibility: hidden;
            }
            
            /* Show only print section */
            .print-section {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                padding: 20px;
                display: block !important;
                visibility: visible;
            }
            
            .print-section * {
                visibility: visible;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Print header styling */
            .print-header {
                text-align: center;
                border-bottom: 3px solid #000;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .print-header h1 {
                font-size: 28px;
                font-weight: bold;
                margin: 0;
                font-family: 'Playfair Display', serif;
            }
            
            .print-header .subtitle {
                font-size: 18px;
                margin: 5px 0;
                font-weight: 600;
            }
            
            .print-meta {
                margin-top: 15px;
                font-size: 12px;
                line-height: 1.6;
            }
            
            /* Print table styling */
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 11px;
            }
            
            .print-table th {
                background-color: #f0f0f0;
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
                font-weight: bold;
            }
            
            .print-table td {
                border: 1px solid #666;
                padding: 6px 8px;
            }
            
            .print-table .text-right {
                text-align: right;
            }
            
            .print-table .text-center {
                text-align: center;
            }
            
            .print-summary {
                margin-top: 30px;
                border-top: 2px solid #000;
                padding-top: 15px;
            }
            
            .print-summary-row {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                font-size: 13px;
            }
            
            .print-summary-row.total {
                font-weight: bold;
                font-size: 16px;
                border-top: 2px solid #000;
                padding-top: 10px;
                margin-top: 10px;
            }
            
            .print-footer {
                margin-top: 40px;
                padding-top: 15px;
                border-top: 1px solid #666;
                font-size: 10px;
                text-align: center;
            }
            
            /* Force page breaks */
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>

    <!-- Print Section (Hidden on screen, visible when printing) -->
    <div class="print-section">
        <div class="print-header">
            <h1>SWEETHEART GOLD SHOP</h1>
            <div class="subtitle">SALES REPORT</div>
            <div class="print-meta">
                <div><strong>Report Period:</strong> 
                    <?php 
                    if (!empty($start_date) && !empty($end_date)) {
                        echo date('F d, Y', strtotime($start_date)) . " to " . date('F d, Y', strtotime($end_date));
                    } elseif (!empty($start_date)) {
                        echo "From " . date('F d, Y', strtotime($start_date));
                    } elseif (!empty($end_date)) {
                        echo "Until " . date('F d, Y', strtotime($end_date));
                    } else {
                        echo "All Time";
                    }
                    ?>
                </div>
                <div><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
                <div><strong>Generated By:</strong> Admin</div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="print-summary">
            <h3 style="font-size: 16px; margin-bottom: 15px; font-weight: bold;">SALES SUMMARY</h3>
            <div class="print-summary-row">
                <span>Total Orders:</span>
                <span><strong><?php echo $total_orders; ?></strong></span>
            </div>
            <div class="print-summary-row">
                <span>Total Revenue:</span>
                <span><strong>₱<?php echo number_format($total_revenue, 2); ?></strong></span>
            </div>
            <div class="print-summary-row">
                <span>Average Order Value:</span>
                <span><strong>₱<?php echo number_format($avg_order, 2); ?></strong></span>
            </div>
        </div>

        <!-- Order Details Table -->
        <h3 style="font-size: 16px; margin: 30px 0 15px 0; font-weight: bold;">ORDER DETAILS</h3>
        <?php if ($total_orders > 0): ?>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width: 8%;">Order ID</th>
                    <th style="width: 10%;">Date</th>
                    <th style="width: 15%;">Customer</th>
                    <th style="width: 25%;">Product</th>
                    <th style="width: 7%;" class="text-center">Qty</th>
                    <th style="width: 12%;" class="text-right">Unit Price</th>
                    <th style="width: 12%;" class="text-right">Subtotal</th>
                    <th style="width: 11%;" class="text-center">Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders_data as $order): 
                    // Get order items
                    $items_query = "SELECT product_name, quantity, price FROM order_items WHERE order_id = ?";
                    $items_stmt = $conn->prepare($items_query);
                    $items_stmt->bind_param("i", $order['id']);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();
                    
                    $first_item = true;
                    $rowspan = $items_result->num_rows > 0 ? $items_result->num_rows : 1;
                    
                    if ($items_result->num_rows > 0):
                        while ($item = $items_result->fetch_assoc()):
                ?>
                <tr>
                    <?php if ($first_item): ?>
                    <td rowspan="<?php echo $rowspan; ?>">#<?php echo $order['id']; ?></td>
                    <td rowspan="<?php echo $rowspan; ?>"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                    <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                    <?php endif; ?>
                    
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-right">₱<?php echo number_format($item['price'], 2); ?></td>
                    
                    <?php if ($first_item): ?>
                    <td rowspan="<?php echo $rowspan; ?>" class="text-right"><strong>₱<?php echo number_format($order['total_price'], 2); ?></strong></td>
                    <td rowspan="<?php echo $rowspan; ?>" class="text-center"><?php echo htmlspecialchars($order['payment_method']); ?></td>
                    <?php $first_item = false; endif; ?>
                </tr>
                <?php 
                        endwhile;
                    else:
                ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                    <td><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                    <td colspan="3"><em>No items</em></td>
                    <td class="text-right"><strong>₱<?php echo number_format($order['total_price'], 2); ?></strong></td>
                    <td class="text-center"><?php echo htmlspecialchars($order['payment_method']); ?></td>
                </tr>
                <?php 
                    endif;
                    $items_stmt->close();
                endforeach; 
                ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f0f0f0; font-weight: bold;">
                    <td colspan="6" class="text-right" style="padding: 10px;"><strong>TOTAL REVENUE:</strong></td>
                    <td class="text-right" style="padding: 10px; font-size: 14px;"><strong>₱<?php echo number_format($total_revenue, 2); ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <p style="text-align: center; padding: 20px; font-style: italic;">No delivered orders found for the selected period.</p>
        <?php endif; ?>

        <!-- Print Footer -->
        <div class="print-footer">
            <p><strong>SweetHeart Gold Shop</strong> | Official Sales Report</p>
            <p>This is a system-generated report. All information is accurate as of <?php echo date('F d, Y h:i:s A'); ?></p>
            <p style="margin-top: 10px; font-style: italic;">Confidential - For Internal Use Only</p>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="d-flex flex-column flex-shrink-0 p-3 sidebar no-print">
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
                <a href="customers.php" class="nav-link text-white">
                    <img src="../icons/customers.png" alt="Customers" class="nav-icon" />
                    Customers
                </a>
            </li>
            <li class="mb-4">
                <a href="sales_report.php" class="nav-link active">
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
    <div class="content-area no-print">
        <div class="header">
            <div>
                <h1>SALES REPORT</h1>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">🧮 Total Orders</div>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">💰 Total Revenue</div>
                    <div class="stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">🪙 Completed (Delivered)</div>
                    <div class="stat-value">₱<?php echo number_format($completed_revenue, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">📈 Average Order</div>
                    <div class="stat-value">₱<?php echo number_format($avg_order, 2); ?></div>
                </div>
            </div>

            <div class="card">
                <div class="filter-section">
                    <form method="GET">
                        <div class="form-group">
                            <label>Start Date:</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date:</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <button type="submit">Apply</button>
                        <a href="sales_report.php" style="text-decoration: none;">
                            <button type="button" class="reset-btn">Clear</button>
                        </a>
                        <button type="button" class="print-btn" onclick="window.print()">🖨️ Print Report</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h3>Sales Overview</h3>
                <div class="chart-container">
                    <canvas id="salesChart" height="90"></canvas>
                </div>
                
                <!-- Daily Breakdown Table -->
                <div class="daily-breakdown">
                    <h4>Daily Sales Breakdown</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Number of Orders</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!empty($sales_by_date)):
                                // Count orders per day
                                $daily_orders = [];
                                foreach ($orders_data as $order) {
                                    $date = date('Y-m-d', strtotime($order['order_date']));
                                    $daily_orders[$date] = ($daily_orders[$date] ?? 0) + 1;
                                }
                                
                                // Sort by date
                                ksort($sales_by_date);
                                
                                foreach ($sales_by_date as $date => $sales_amount): 
                            ?>
                            <tr>
                                <td><?php echo date('F d, Y (D)', strtotime($date)); ?></td>
                                <td><?php echo $daily_orders[$date] ?? 0; ?> orders</td>
                                <td class="price">₱<?php echo number_format($sales_amount, 2); ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="3" class="text-center">No sales data available for the selected period.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background-color: #f9f5ed; border-top: 3px solid #d4a574;">
                                <td style="text-align: right; padding: 15px;">TOTAL:</td>
                                <td><?php echo $total_orders; ?> orders</td>
                                <td class="price" style="font-size: 18px;">₱<?php echo number_format($total_revenue, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3>📋 Detailed Sales Transactions - <?php echo $total_orders; ?> Orders</h3>
                <?php if ($total_orders > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Customer Name</th>
                            <th>Product Name</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders_data as $order): 
                            // Get order items
                            $items_query = "SELECT product_name, quantity, price FROM order_items WHERE order_id = ?";
                            $items_stmt = $conn->prepare($items_query);
                            $items_stmt->bind_param("i", $order['id']);
                            $items_stmt->execute();
                            $items_result = $items_stmt->get_result();
                            
                            $first_item = true;
                            $rowspan = $items_result->num_rows > 0 ? $items_result->num_rows : 1;
                            
                            if ($items_result->num_rows > 0):
                                while ($item = $items_result->fetch_assoc()):
                        ?>
                        <tr>
                            <?php if ($first_item): ?>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>" class="order-id">#<?php echo $order['id']; ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                            <?php endif; ?>
                            
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                            
                            <?php if ($first_item): ?>
                            <td rowspan="<?php echo $rowspan; ?>" class="price">₱<?php echo number_format($order['total_price'], 2); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($order['payment_method']); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>">
                                <span class="badge bg-success"><?php echo $order['status']; ?></span>
                            </td>
                            <?php $first_item = false; endif; ?>
                        </tr>
                        <?php 
                                endwhile;
                            else:
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td class="order-id">#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                            <td colspan="3"><em>No items</em></td>
                            <td class="price">₱<?php echo number_format($order['total_price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                            <td>
                                <span class="badge bg-success"><?php echo $order['status']; ?></span>
                            </td>
                        </tr>
                        <?php 
                            endif;
                            $items_stmt->close();
                        endforeach; 
                        ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-info text-center" style="margin: 20px;">
                    <h4>No Delivered Orders Found</h4>
                    <p>There are no delivered orders for the selected date range.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Analytics Section -->
            <div class="card analytics-section">
                <h3>📈 Sales Analytics</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">🏆 Best-Selling Product</div>
                        <div class="summary-value" style="font-size: 18px;">
                            <?php echo $best_product ? htmlspecialchars($best_product['product_name']) . " (" . $best_product['total_qty'] . " sold)" : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">💳 Most Used Payment Method</div>
                        <div class="summary-value" style="font-size: 18px;"><?php echo htmlspecialchars($most_payment); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">👤 Top Customer</div>
                        <div class="summary-value" style="font-size: 18px;">
                            <?php echo htmlspecialchars($top_customer) . " (₱" . number_format($top_customer_amount, 2) . ")"; ?>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">📅 Reporting Period</div>
                        <div class="summary-value" style="font-size: 18px;">
                            <?php 
                            if (!empty($start_date) && !empty($end_date)) {
                                echo date('M d', strtotime($start_date)) . " - " . date('M d, Y', strtotime($end_date));
                            } else {
                                echo "All Time";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: <?php echo json_encode($sales); ?>,
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
    </script>
</body>
</html>