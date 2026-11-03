<?php
// admin/customers.php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
    exit;
}

// Handle AJAX requests for filtering
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filter') {
    header('Content-Type: application/json');
    
    $filters = [
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? '',
        'time_period' => $_GET['time_period'] ?? ''
    ];
    
    $customers = getFilteredCustomers($conn, $filters);
    $stats = calculateCustomerStats($conn, $filters);
    
    echo json_encode([
        'customers' => $customers,
        'stats' => $stats,
        'count' => count($customers)
    ]);
    exit;
}

function getFilteredCustomers($conn, $filters = []) {
    // Base query to get customers who have placed orders
    $sql = "SELECT 
                COALESCE(MAX(u.id), CONCAT('guest_', MIN(o.id))) as customer_id,
                COALESCE(MAX(u.username), MAX(o.shipping_name)) as customer_name,
                MAX(o.shipping_phone) as email,
                MAX(o.shipping_phone) as phone,
                MIN(o.order_date) as first_order_date,
                MAX(o.order_date) as last_order_date,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_price) as total_spent,
                CASE 
                    WHEN MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active'
                    ELSE 'Inactive'
                END as status
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Search filter - apply before GROUP BY
    if (!empty($filters['search'])) {
        $sql .= " AND (u.username LIKE ? 
                  OR o.shipping_name LIKE ?
                  OR o.shipping_phone LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= 'sss';
    }
    
    // Group by user_id for registered users, or by phone+name for guests
    $sql .= " GROUP BY COALESCE(o.user_id, o.shipping_phone, o.shipping_name)";
    
    // Status filter (applied after grouping)
    $havingClauses = [];
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'Active') {
            $havingClauses[] = "MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } else if ($filters['status'] === 'Inactive') {
            $havingClauses[] = "MAX(o.order_date) < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    // Time period filter
    if (!empty($filters['time_period'])) {
        switch($filters['time_period']) {
            case 'today':
                $havingClauses[] = "MAX(o.order_date) >= CURDATE()";
                break;
            case 'week':
                $havingClauses[] = "MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $havingClauses[] = "MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $havingClauses[] = "MAX(o.order_date) >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
                break;
        }
    }
    
    if (!empty($havingClauses)) {
        $sql .= " HAVING " . implode(' AND ', $havingClauses);
    }
    
    $sql .= " ORDER BY total_spent DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    return $customers;
}

// Function to calculate customer statistics
function calculateCustomerStats($conn, $filters = []) {
    $customers = getFilteredCustomers($conn, $filters);
    
    $total_customers = count($customers);
    $active_count = 0;
    $new_this_month = 0;
    
    $currentMonth = date('Y-m');
    
    foreach ($customers as $customer) {
        if ($customer['status'] === 'Active') {
            $active_count++;
        }
        
        if (date('Y-m', strtotime($customer['first_order_date'])) === $currentMonth) {
            $new_this_month++;
        }
    }
    
    // Get pending chats count (placeholder - implement based on your chat system)
    $pending_chats = 5;
    
    return [
        'total_customers' => $total_customers,
        'active_count' => $active_count,
        'new_this_month' => $new_this_month,
        'pending_chats' => $pending_chats
    ];
}

// Initial data load
$customers = getFilteredCustomers($conn);
$stats = calculateCustomerStats($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customers Management - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Customers.css">
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
                <h1>CUSTOMERS</h1>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">👥 Total Customers</div>
                <div class="stat-value" id="totalCustomers"><?= $stats['total_customers'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ Active</div>
                <div class="stat-value" id="activeCustomers"><?= $stats['active_count'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🆕 New This Month</div>
                <div class="stat-value" id="newThisMonth"><?= $stats['new_this_month'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">💬 Pending Chats</div>
                <div class="stat-value" id="pendingChats"><?= $stats['pending_chats'] ?></div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card">
            <div class="filter-section">
                <div class="search-chat-row">
                    <input type="text" class="search-input" id="searchInput" 
                           placeholder="Search by name, phone or customer ID...">
                    
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>

                    <select class="filter-select" id="timeFilter">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>

                    <a href="admin_livechat.php" style="text-decoration: none; margin-left: auto;">
                        <button class="btn-chat">
                            <span>💬 Customer Support Chat</span>
                            <div class="chat-notification"></div>
                        </button>
                    </a>
                </div>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="card">
            <h3>Customer List (<?= count($customers) ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Registered</th>
                        <th style="width: 100px; text-align: center;">Orders</th>
                        <th style="width: 150px;">Total Spent</th>
                        <th style="width: 120px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <?php foreach ($customers as $customer): ?>
                    <tr class="customer-row" onclick="window.location.href='customerDetails.php?id=<?= urlencode($customer['customer_id']) ?>'">
                        <td>
                            <div class="customer-info">
                                <div class="customer-avatar">
                                    <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                                </div>
                                <div class="customer-details">
                                    <div class="customer-name"><?= htmlspecialchars($customer['customer_name']) ?></div>
                                    <div class="customer-id">#<?= htmlspecialchars($customer['customer_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="contact-info">
                            <?= htmlspecialchars($customer['email']) ?>
                        </td>
                        <td class="date-info">
                            <?= date('M d, Y', strtotime($customer['first_order_date'])) ?>
                        </td>
                        <td class="order-count">
                            <?= $customer['total_orders'] ?>
                        </td>
                        <td class="price">
                            ₱<?= number_format($customer['total_spent'], 2) ?>
                        </td>
                        <td class="text-center">
                            <span class="status-badge status-<?= strtolower($customer['status']) ?>">
                                <?= $customer['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
class CustomersManager {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        document.getElementById('searchInput').addEventListener('input', 
            this.debounce(() => this.applyFilters(), 500)
        );

        document.getElementById('statusFilter').addEventListener('change', () => {
            this.applyFilters();
        });

        document.getElementById('timeFilter').addEventListener('change', () => {
            this.applyFilters();
        });
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
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
            time_period: document.getElementById('timeFilter').value
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

        fetch(`customers.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                this.displayResults(data);
                this.updateStats(data.stats);
                this.hideLoading();
            })
            .catch(error => {
                console.error('Error applying filters:', error);
                this.hideLoading();
                alert('Error applying filters. Please try again.');
            });
    }

    displayResults(data) {
        const tbody = document.getElementById('customersTableBody');
        
        if (data.customers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <h4>No customers found</h4>
                        <p>Try adjusting your filters or search terms.</p>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        data.customers.forEach(customer => {
            const regDate = new Date(customer.first_order_date);
            const dateStr = regDate.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
            
            html += `
                <tr class="customer-row" onclick="window.location.href='customerDetails.php?id=${encodeURIComponent(customer.customer_id)}'">
                    <td>
                        <div class="customer-info">
                            <div class="customer-avatar">
                                ${customer.customer_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="customer-details">
                                <div class="customer-name">${this.escapeHtml(customer.customer_name)}</div>
                                <div class="customer-id">#${this.escapeHtml(customer.customer_id)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="contact-info">
                        ${this.escapeHtml(customer.email)}
                    </td>
                    <td class="date-info">
                        ${dateStr}
                    </td>
                    <td class="order-count">
                        ${customer.total_orders}
                    </td>
                    <td class="price">
                        ₱${parseFloat(customer.total_spent).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}
                    </td>
                    <td class="text-center">
                        <span class="status-badge status-${customer.status.toLowerCase()}">
                            ${customer.status}
                        </span>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    updateStats(stats) {
        document.getElementById('totalCustomers').textContent = stats.total_customers;
        document.getElementById('activeCustomers').textContent = stats.active_count;
        document.getElementById('newThisMonth').textContent = stats.new_this_month;
        document.getElementById('pendingChats').textContent = stats.pending_chats;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    window.customersManager = new CustomersManager();
});
    </script>
</body>
</html>