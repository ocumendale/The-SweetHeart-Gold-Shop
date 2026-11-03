<?php
// admin/orders.php
session_start();
include 'db.php';// adjust path to your db.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
  exit;
}

// Add this function to get status options based on payment method
function getStatusOptions($payment_method) {
    if ($payment_method === 'Store Pickup') {
        return [
            'Pending' => 'Pending',
            'Processing' => 'Processing',
            'Ready for Pickup' => 'Ready for Pickup',
            'Picked Up' => 'Picked Up',
            'Cancelled' => 'Cancelled'
        ];
    } else {
        return [
            'Pending' => 'Pending',
            'Processing' => 'Processing',
            'Shipped' => 'Shipped',
            'Delivered' => 'Delivered',
            'Cancelled' => 'Cancelled'
        ];
    }
}

// Handle AJAX requests for filtering
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filter') {
    header('Content-Type: application/json');
    
    $filters = [
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? '',
        'payment_method' => $_GET['payment_method'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'sort_by' => $_GET['sort_by'] ?? 'order_date_desc'
    ];
    
    $orders = getFilteredOrders($conn, $filters);
    $stats = calculateOrderStats($orders);
    
    echo json_encode([
        'orders' => $orders,
        'stats' => $stats,
        'count' => count($orders)
    ]);
    exit;
}

// Function to build dynamic SQL query for orders
function buildOrderQuery($filters = []) {
    $sql = "SELECT o.id, o.user_id, IFNULL(u.username, 'Guest') AS username, o.total_price, o.payment_method, o.shipping_name,
            o.shipping_address, o.shipping_city, o.shipping_phone, o.status, o.order_date
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
    $params = [];
    $types = '';
    
    // Text search filter
    if (!empty($filters['search'])) {
        $sql .= " AND (o.id LIKE ? OR u.username LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= 'ssss';
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        $sql .= " AND o.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    // Payment method filter
    if (!empty($filters['payment_method'])) {
        $sql .= " AND o.payment_method = ?";
        $params[] = $filters['payment_method'];
        $types .= 's';
    }
    
    // Date range filter
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(o.order_date) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(o.order_date) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    // Sorting
    switch ($filters['sort_by'] ?? 'order_date_desc') {
        case 'order_date_asc':
            $sql .= " ORDER BY o.order_date ASC";
            break;
        case 'total_asc':
            $sql .= " ORDER BY o.total_price ASC";
            break;
        case 'total_desc':
            $sql .= " ORDER BY o.total_price DESC";
            break;
        case 'status':
            $sql .= " ORDER BY o.status ASC";
            break;
        default:
            $sql .= " ORDER BY o.order_date DESC";
    }
    
    return ['sql' => $sql, 'params' => $params, 'types' => $types];
}

// Function to get filtered orders
function getFilteredOrders($conn, $filters) {
    $query = buildOrderQuery($filters);
    
    if (!empty($query['params'])) {
        $stmt = $conn->prepare($query['sql']);
        if (!empty($query['types'])) {
            $stmt->bind_param($query['types'], ...$query['params']);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query['sql']);
    }
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    return $orders;
}

// Function to calculate order statistics
function calculateOrderStats($orders) {
    $total_orders = count($orders);
    $total_revenue = 0;
    $pending_count = 0;
    $processing_count = 0;
    $delivered_count = 0;
    $picked_up_count = 0;
    $shipped_count = 0;
    $ready_for_pickup_count = 0;
    
    foreach ($orders as $order) {
        $total_revenue += $order['total_price'];
        switch($order['status']) {
            case 'Pending':
                $pending_count++;
                break;
            case 'Processing':
                $processing_count++;
                break;
            case 'Delivered':
                $delivered_count++;
                break;
            case 'Picked Up':
                $picked_up_count++;
                break;
            case 'Shipped':
                $shipped_count++;
                break;
            case 'Ready for Pickup':
                $ready_for_pickup_count++;
                break;
        }
    }
    
    return [
        'total_orders' => $total_orders,
        'total_revenue' => $total_revenue,
        'pending_count' => $pending_count,
        'processing_count' => $processing_count,
        'delivered_count' => $delivered_count,
        'picked_up_count' => $picked_up_count,
        'shipped_count' => $shipped_count,
        'ready_for_pickup_count' => $ready_for_pickup_count
    ];
}

// Fetch all orders for initial display
$sql = "SELECT o.id, o.user_id, IFNULL(u.username, 'Guest') AS username, o.total_price, o.payment_method, o.shipping_name,
        o.shipping_address, o.shipping_city, o.shipping_phone, o.status, o.order_date
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.order_date DESC";
$result = $conn->query($sql);
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate statistics
$total_orders = count($orders);
$total_revenue = array_sum(array_column($orders, 'total_price'));
$pending_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Pending'));
$processing_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Processing'));
$delivered_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$picked_up_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Picked Up'));
$shipped_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Shipped'));
$ready_for_pickup_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Ready for Pickup'));

// Get unique payment methods
$payment_methods = array_unique(array_column($orders, 'payment_method'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders Management - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Orders.css">

    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
    emailjs.init("j16wRtf0Lbq-vxO9r");
    </script>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-warning" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

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
                <a href="orders.php" class="nav-link active">
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
                <h1>ORDERS</h1>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📋 Total Orders</div>
                <div class="stat-value" id="totalOrdersCount"><?= $total_orders ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">💰 Total Revenue</div>
                <div class="stat-value" id="totalRevenueCount">₱<?= number_format($total_revenue, 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">⏳ Pending Orders</div>
                <div class="stat-value" id="pendingOrdersCount"><?= $pending_orders ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ Delivered</div>
                <div class="stat-value" id="deliveredOrdersCount"><?= $delivered_orders ?></div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card no-print">
            <div class="filter-section">
                <div class="search-add-row">
                    <input type="text" class="form-control search-input" id="searchInput"
                        placeholder="Search by order ID, customer name, phone...">
                    
                    <div class="quick-filter-buttons">
                        <button class="btn-quick-filter" onclick="quickFilter('Pending')">
                            Pending Orders
                        </button>
                        <button class="btn-quick-filter" onclick="quickFilter('Processing')">
                            Processing
                        </button>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="row g-3 mt-3">
                    <div class="col-md-2">
                        <label class="filter-label">Status</label>
                        <select class="form-select filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Ready for Pickup">Ready for Pickup</option>
                            <option value="Picked Up">Picked Up</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">Payment Method</label>
                        <select class="form-select filter-select" id="paymentFilter">
                            <option value="">All Methods</option>
                            <?php foreach ($payment_methods as $method): ?>
                                <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="filter-label">Date Range</label>
                        <div class="price-range-inputs">
                            <input type="date" class="form-control filter-input" id="dateFrom">
                            <span class="price-separator">to</span>
                            <input type="date" class="form-control filter-input" id="dateTo">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">Sort By</label>
                        <select class="form-select filter-select" id="sortFilter">
                            <option value="order_date_desc">Newest First</option>
                            <option value="order_date_asc">Oldest First</option>
                            <option value="total_desc">Amount: High to Low</option>
                            <option value="total_asc">Amount: Low to High</option>
                            <option value="status">Status</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">&nbsp;</label>
                        <div class="filter-buttons">
                            <button class="btn-apply" id="applyFilters">Apply</button>
                            <button class="btn-clear" id="clearFilters">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <div id="activeFilters" class="mt-3"></div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="table-header">
                <h3 id="resultsCount">Showing <?= $total_orders ?> orders</h3>
                <!-- <button class="btn-export" onclick="exportOrders()">
                    📊 Export Orders
                </button> -->
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;">Order ID</th>
                        <th>Customer</th>
                        <th>Shipping Info</th>
                        <th style="width: 120px;">Amount</th>
                        <th style="width: 130px;">Payment</th>
                        <th style="width: 150px;">Date</th>
                        <th style="width: 130px;">Status</th>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php foreach ($orders as $o): ?>
                    <tr data-order-id="<?= $o['id'] ?>">
                        <td>
                            <span class="order-id">#<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </td>
                        <td>
                            <div class="customer-info">
                                <div class="customer-avatar">
                                    <?= strtoupper(substr($o['username'], 0, 1)) ?>
                                </div>
                                <div class="customer-details">
                                    <div class="customer-name"><?= htmlspecialchars($o['username']) ?></div>
                                    <small class="customer-id">ID: <?= htmlspecialchars($o['user_id'] ?: 'Guest') ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="shipping-info">
                                <strong><?= htmlspecialchars($o['shipping_name']) ?></strong><br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($o['shipping_city']) ?><br>
                                    <?= htmlspecialchars($o['shipping_phone']) ?>
                                </small>
                        </div>
                        </td>
                        <td>
                            <strong class="price">₱<?= number_format($o['total_price'], 2) ?></strong>
                        </td>
                        <td>
                            <span class="payment-badge"><?= htmlspecialchars($o['payment_method']) ?></span>
                        </td>
                        <td>
                            <div class="date-info">
                                <div><?= date('M d, Y', strtotime($o['order_date'])) ?></div>
                                <small class="text-muted"><?= date('h:i A', strtotime($o['order_date'])) ?></small>
                            </div>
                        </td>
                        <td>
                            <select class="status-select status-<?= strtolower(str_replace(' ', '_', $o['status'])) ?>" 
                                    data-order-id="<?= $o['id'] ?>"
                                    data-payment-method="<?= htmlspecialchars($o['payment_method']) ?>"
                                    data-previous-status="<?= htmlspecialchars($o['status']) ?>"
                                    onchange="updateOrderStatus(this)">
                                <?php 
                                // Get the appropriate status options for this order's payment method
                                $status_options = getStatusOptions($o['payment_method']);
                                foreach ($status_options as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" 
                                            <?= $o['status'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-center">
                            <a href="view_order.php?id=<?= $o['id'] ?>" class="btn-table-view">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
class OrdersManager {
    constructor() {
        this.initializeEventListeners();
        this.originalStats = {
            totalOrders: <?= $total_orders ?>,
            totalRevenue: <?= $total_revenue ?>,
            pendingOrders: <?= $pending_orders ?>,
            deliveredOrders: <?= $delivered_orders ?>
        };
    }

    initializeEventListeners() {
        document.getElementById('applyFilters').addEventListener('click', () => {
            this.applyFilters();
        });

        document.getElementById('clearFilters').addEventListener('click', () => {
            this.clearFilters();
        });

        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.applyFilters();
            }
        });
    }

    showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    getFilterValues() {
        return {
            search: document.getElementById('searchInput').value.trim(),
            status: document.getElementById('statusFilter').value,
            payment_method: document.getElementById('paymentFilter').value,
            date_from: document.getElementById('dateFrom').value,
            date_to: document.getElementById('dateTo').value,
            sort_by: document.getElementById('sortFilter').value
        };
    }

    applyFilters() {
        const filters = this.getFilterValues();
        
        this.showLoading();
        
        const params = new URLSearchParams();
        params.append('ajax', 'filter');
        
        Object.keys(filters).forEach(key => {
            if (filters[key] && filters[key] !== '') {
                params.append(key, filters[key]);
            }
        });

        fetch(`orders.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                this.displayResults(data);
                this.updateStats(data.stats);
                this.updateActiveFilters(filters);
                this.hideLoading();
            })
            .catch(error => {
                console.error('Error applying filters:', error);
                this.hideLoading();
                alert('Error applying filters. Please try again.');
            });
    }

    displayResults(data) {
        const tbody = document.getElementById('ordersTableBody');
        const resultsCount = document.getElementById('resultsCount');
        
        resultsCount.textContent = `Showing ${data.count} orders`;
        
        if (data.orders.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <h4>No orders found</h4>
                        <p>Try adjusting your filters or search terms.</p>
                    </td>
                </tr>
            `;
            return;
        }

        // Function to get status options based on payment method
        const getStatusOptions = (paymentMethod) => {
            if (paymentMethod === 'Store Pickup') {
                return [
                    { value: 'Pending', label: 'Pending' },
                    { value: 'Processing', label: 'Processing' },
                    { value: 'Ready for Pickup', label: 'Ready for Pickup' },
                    { value: 'Picked Up', label: 'Picked Up' },
                    { value: 'Cancelled', label: 'Cancelled' }
                ];
            } else {
                return [
                    { value: 'Pending', label: 'Pending' },
                    { value: 'Processing', label: 'Processing' },
                    { value: 'Shipped', label: 'Shipped' },
                    { value: 'Delivered', label: 'Delivered' },
                    { value: 'Cancelled', label: 'Cancelled' }
                ];
            }
        };

        let html = '';
        data.orders.forEach(order => {
            const orderDate = new Date(order.order_date);
            const dateStr = orderDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeStr = orderDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            const statusOptions = getStatusOptions(order.payment_method);
            
            let statusOptionsHtml = '';
            statusOptions.forEach(option => {
                statusOptionsHtml += `<option value="${option.value}" ${order.status === option.value ? 'selected' : ''}>${option.label}</option>`;
            });
            
            html += `
                <tr data-order-id="${order.id}">
                    <td>
                        <span class="order-id">#${String(order.id).padStart(5, '0')}</span>
                    </td>
                    <td>
                        <div class="customer-info">
                            <div class="customer-avatar">
                                ${order.username.charAt(0).toUpperCase()}
                            </div>
                            <div class="customer-details">
                                <div class="customer-name">${order.username}</div>
                                <small class="customer-id">ID: ${order.user_id || 'Guest'}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="shipping-info">
                            <strong>${order.shipping_name}</strong><br>
                            <small class="text-muted">
                                ${order.shipping_city}<br>
                                ${order.shipping_phone}
                            </small>
                        </div>
                    </td>
                    <td>
                        <strong class="price">₱${parseFloat(order.total_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                    </td>
                    <td>
                        <span class="payment-badge">${order.payment_method}</span>
                    </td>
                    <td>
                        <div class="date-info">
                            <div>${dateStr}</div>
                            <small class="text-muted">${timeStr}</small>
                        </div>
                    </td>
                    <td>
                        <select class="status-select status-${order.status.toLowerCase().replace(/ /g, '_')}" 
                                data-order-id="${order.id}"
                                data-payment-method="${order.payment_method}"
                                data-previous-status="${order.status}"
                                onchange="updateOrderStatus(this)">
                            ${statusOptionsHtml}
                        </select>
                    </td>
                    <td class="text-center">
                        <a href="view_order.php?id=${order.id}" class="btn-table-view">View</a>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    updateStats(stats) {
        document.getElementById('totalOrdersCount').textContent = stats.total_orders;
        document.getElementById('totalRevenueCount').textContent = 
            '₱' + stats.total_revenue.toLocaleString('en-US', {maximumFractionDigits: 0});
        document.getElementById('pendingOrdersCount').textContent = stats.pending_count;
        document.getElementById('deliveredOrdersCount').textContent = stats.delivered_count;
    }

    updateActiveFilters(filters) {
        const activeFiltersDiv = document.getElementById('activeFilters');
        const badges = [];

        if (filters.search) badges.push(`Search: "${filters.search}"`);
        if (filters.status) badges.push(`Status: ${filters.status}`);
        if (filters.payment_method) badges.push(`Payment: ${filters.payment_method}`);
        if (filters.date_from) badges.push(`From: ${filters.date_from}`);
        if (filters.date_to) badges.push(`To: ${filters.date_to}`);
        if (filters.sort_by && filters.sort_by !== 'order_date_desc') {
            badges.push(`Sort: ${this.getSortText(filters.sort_by)}`);
        }

        if (badges.length > 0) {
            activeFiltersDiv.innerHTML = 
                '<div class="active-filters-container"><strong>Active Filters:</strong> ' +
                badges.map(badge => `<span class="filter-badge">${badge}</span>`).join(' ') +
                '</div>';
        } else {
            activeFiltersDiv.innerHTML = '';
        }
    }

    getSortText(sort) {
        const texts = {
            'order_date_asc': 'Oldest First',
            'total_desc': 'Amount: High to Low',
            'total_asc': 'Amount: Low to High',
            'status': 'Status'
        };
        return texts[sort] || sort;
    }

    clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('paymentFilter').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        document.getElementById('sortFilter').value = 'order_date_desc';
        
        this.applyFilters();
    }

    quickFilter(status) {
        document.getElementById('searchInput').value = '';
        document.getElementById('paymentFilter').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        document.getElementById('sortFilter').value = 'order_date_desc';
        
        document.getElementById('statusFilter').value = status;
        
        this.applyFilters();
    }
}

function quickFilter(status) {
    window.ordersManager.quickFilter(status);
}

function updateOrderStatus(selectElement) {
    const orderId = selectElement.dataset.orderId;
    const newStatus = selectElement.value;
    const paymentMethod = selectElement.dataset.paymentMethod;
    const previousStatus = selectElement.getAttribute('data-previous-status') ||
                         selectElement.options[selectElement.selectedIndex].value;

    const row = selectElement.closest('tr');

    // Extract customer data from DOM
    const customerEmailElement = row.querySelector('.customer-name');
    const rawEmail = customerEmailElement ? customerEmailElement.innerText.trim() : '';
    
    // Extract just the email using regex (handles cases with extra text)
    const emailMatch = rawEmail.match(/[\w.-]+@[\w.-]+\.\w+/);
    const customerEmail = emailMatch ? emailMatch[0] : rawEmail;
    
    // Get customer name from shipping info
    const customerName = row.querySelector('.shipping-info strong')?.innerText.trim() || 'Customer';
    const orderAmount = row.querySelector('.price')?.innerText.trim() || '';

    // Validate email before proceeding
    if (!customerEmail || !customerEmail.includes('@')) {
        alert('Error: Invalid customer email address. Email found: ' + customerEmail);
        return;
    }

    if (confirm(`Are you sure you want to change this order status to "${newStatus}"?`)) {
        document.getElementById('loadingOverlay').style.display = 'flex';

        selectElement.setAttribute('data-previous-status', previousStatus);
        selectElement.className = `status-select status-${newStatus.toLowerCase().replace(/ /g, '_')}`;

        // Update order status in database
        fetch('update_order_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                selectElement.setAttribute('data-previous-status', newStatus);

                // Fetch order items to get product names
                fetch(`get_order_items.php?order_id=${orderId}`)
                    .then(response => response.json())
                    .then(itemsData => {
                        // Create a formatted list of product names
                        const productNames = itemsData.items.map(item => 
                            `${item.product_name} (Qty: ${item.quantity})`
                        ).join(', ');

                        console.log('Order products:', productNames);

                        // Determine which email template to use
                        // Use template_yf0odyr ONLY if payment is Store Pickup AND status is Ready for Pickup
                        // Otherwise use template_2ahychk
                        let emailTemplate = 'template_2ahychk'; // Default template
                        
                        if (paymentMethod === 'Store Pickup' && newStatus === 'Ready for Pickup') {
                            emailTemplate = 'template_yf0odyr';
                        }

                        console.log('Using email template:', emailTemplate);
                        console.log('Payment method:', paymentMethod);
                        console.log('New status:', newStatus);

                        // Send email notification
                        console.log('Sending email to:', customerEmail);

                        emailjs.send("service_y594p76", emailTemplate, {
                            to_email: customerEmail,
                            to_name: customerName,
                            customer_name: customerName,
                            order_id: orderId,
                            order_name: productNames,  
                            order_status: newStatus,
                            order_amount: orderAmount,
                            payment_method: paymentMethod
                        })
                        .then(function(response) {
                            console.log('Email sent successfully!', response.status, response.text);
                            document.getElementById('loadingOverlay').style.display = 'none';
                            alert('Order status updated successfully! Notification email sent.');
                            window.ordersManager.applyFilters();
                        })
                        .catch(function(error) {
                            console.error('Email sending failed:', error);
                            document.getElementById('loadingOverlay').style.display = 'none';
                            alert('Order status updated, but email notification failed to send.');
                            window.ordersManager.applyFilters();
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching order items:', error);
                        document.getElementById('loadingOverlay').style.display = 'none';
                        alert('Order status updated, but failed to fetch order items for email.');
                        window.ordersManager.applyFilters();
                    });
            } else {
                document.getElementById('loadingOverlay').style.display = 'none';
                selectElement.value = previousStatus;
                selectElement.className = `status-select status-${previousStatus.toLowerCase().replace(/ /g, '_')}`;
                alert('Error updating order status: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            console.error('Error:', error);
            selectElement.value = previousStatus;
            selectElement.className = `status-select status-${previousStatus.toLowerCase().replace(/ /g, '_')}`;
            alert('Error updating order status. Please try again.');
        });
    } else {
        // Revert to previous status if user cancels
        const currentValue = selectElement.getAttribute('data-previous-status');
        if (currentValue) {
            selectElement.value = currentValue;
        }
    }
}

function exportOrders() {
    alert('Export functionality would be implemented here. This would generate a CSV or Excel file of the current orders.');
}

document.addEventListener('DOMContentLoaded', function() {
    window.ordersManager = new OrdersManager();
});
    </script>
</body>
</html>