<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
    exit;
}

// Handle AJAX requests for filtering
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filter') {
    header('Content-Type: application/json');
    
    $filters = [
        'search' => $_GET['search'] ?? '',
        'action' => $_GET['action'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'sort_by' => $_GET['sort_by'] ?? 'created_at_desc'
    ];
    
    $logs = getFilteredLogs($conn, $filters);
    
    echo json_encode([
        'logs' => $logs,
        'count' => count($logs)
    ]);
    exit;
}

// Function to build dynamic SQL query for logs
function buildLogQuery($filters = []) {
    $sql = "SELECT * FROM activity_log WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!empty($filters['search'])) {
        $sql .= " AND (action LIKE ? OR description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm]);
        $types .= 'ss';
    }
    
    if (!empty($filters['action'])) {
        $sql .= " AND action = ?";
        $params[] = $filters['action'];
        $types .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(timestamp) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(timestamp) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    switch ($filters['sort_by'] ?? 'created_at_desc') {
        case 'created_at_asc':
            $sql .= " ORDER BY timestamp ASC";
            break;
        case 'action_asc':
            $sql .= " ORDER BY action ASC";
            break;
        case 'action_desc':
            $sql .= " ORDER BY action DESC";
            break;
        default:
            $sql .= " ORDER BY timestamp DESC";
    }
    
    return ['sql' => $sql, 'params' => $params, 'types' => $types];
}

// Function to get filtered logs
function getFilteredLogs($conn, $filters) {
    $query = buildLogQuery($filters);
    
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
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return $logs;
}

// Fetch all logs for initial display
$sql = "SELECT * FROM activity_log ORDER BY timestamp DESC";
$result = $conn->query($sql);

// Calculate statistics
$total_logs = 0;
$actions = [];
$today_logs = 0;
$today = date('Y-m-d');

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $total_logs++;
        if (!in_array($row['action'], $actions)) {
            $actions[] = $row['action'];
        }
        if (date('Y-m-d', strtotime($row['timestamp'])) == $today) {
            $today_logs++;
        }
    }
    $result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Log - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Activitylog.css">
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
                <a href="activitylog.php" class="nav-link active">
                    <img src="../icons/restore.png" alt="Activity Log and Recycle Bin" class="nav-icon" />
                    Log and Bin
                </a>
            </li>
        </ul>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <div class="header">
            <div>
                <h1>ACTIVITY LOG</h1>
            </div>
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">🧮 Total Activities</div>
                <div class="stat-value" id="totalActivitiesCount"><?= $total_logs ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📅 Today's Activities</div>
                <div class="stat-value" id="todayActivitiesCount"><?= $today_logs ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📊 Action Types</div>
                <div class="stat-value"><?= count($actions) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ System Status</div>
                <div class="stat-value" style="font-size: 28px;">Active</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card">
            <div class="filter-section">
                <div class="d-flex align-items-center mb-3">
                    <input type="text" class="form-control search-input" id="searchInput"
                        placeholder="Search activities..." style="max-width: 500px;">
                    
                    <a href="recyclebin.php" style="text-decoration: none; margin-left: auto;">
                        <button class="recycle-btn">
                            <img src="../icons/trash.png" alt="Recycle" style="width:24px; height:24px; margin-right:8px;">
                            Recycle Bin
                        </button>
                    </a>
                </div>

                <!-- Enhanced Filter Row -->
                <form class="filter-form">
                    <div class="form-group">
                        <label>Action Type</label>
                        <select class="form-select" id="actionFilter">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= htmlspecialchars($action) ?>"><?= ucfirst(str_replace('_', ' ', $action)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" class="form-control" id="dateFrom">
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" id="dateTo">
                    </div>

                    <div class="form-group">
                        <label>Sort By</label>
                        <select class="form-select" id="sortFilter">
                            <option value="created_at_desc">Newest First</option>
                            <option value="created_at_asc">Oldest First</option>
                            <option value="action_asc">Action: A to Z</option>
                            <option value="action_desc">Action: Z to A</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" id="applyFilters">Apply</button>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="reset-btn" id="clearFilters">Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Table -->
        <div class="card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 id="resultsCount">Showing <?= $total_logs ?> activities</h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 20%;">Date & Time</th>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 15%;">Action</th>
                        <th style="width: 55%;">Description</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M d, Y h:i A', strtotime($row['timestamp'])) ?></td>
                                <td class="order-id">#<?= $row['id'] ?></td>
                                <td>
                                    <?php
                                    $actionClass = 'action-' . str_replace(' ', '_', strtolower($row['action']));
                                    $actionText = ucfirst(str_replace('_', ' ', $row['action']));
                                    ?>
                                    <span class="action-badge <?= $actionClass ?>"><?= $actionText ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">
                                <div class="alert alert-info">
                                    <h4>No activities found</h4>
                                    <p>No activity logs available at the moment.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class ActivityLogManager {
            constructor() {
                this.initializeEventListeners();
                this.originalStats = {
                    totalActivities: <?= $total_logs ?>,
                    todayActivities: <?= $today_logs ?>
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
                    action: document.getElementById('actionFilter').value,
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
                    if (filters[key]) {
                        params.append(key, filters[key]);
                    }
                });

                fetch(`activitylog.php?${params.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        this.displayResults(data);
                        this.hideLoading();
                    })
                    .catch(error => {
                        console.error('Error applying filters:', error);
                        this.hideLoading();
                        alert('Error applying filters. Please try again.');
                    });
            }

            displayResults(data) {
                const tableBody = document.getElementById('tableBody');
                const resultsCount = document.getElementById('resultsCount');
                
                resultsCount.textContent = `Showing ${data.count} activities`;
                
                if (data.logs.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center">
                                <div class="alert alert-warning">
                                    <h4>No activities found</h4>
                                    <p>Try adjusting your filters or search terms.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                let html = '';
                data.logs.forEach(log => {
                    const actionClass = 'action-' + log.action.replace(/ /g, '_').toLowerCase();
                    const actionText = log.action.charAt(0).toUpperCase() + log.action.slice(1).replace(/_/g, ' ');
                    const formattedDate = new Date(log.timestamp).toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });

                    html += `
                        <tr>
                            <td>${formattedDate}</td>
                            <td class="order-id">#${log.id}</td>
                            <td>
                                <span class="action-badge ${actionClass}">${actionText}</span>
                            </td>
                            <td>${log.description}</td>
                        </tr>
                    `;
                });

                tableBody.innerHTML = html;
            }

            clearFilters() {
                document.getElementById('searchInput').value = '';
                document.getElementById('actionFilter').value = '';
                document.getElementById('sortFilter').value = 'created_at_desc';
                document.getElementById('dateFrom').value = '';
                document.getElementById('dateTo').value = '';
                
                window.location.reload();
            }

            quickFilter(actionType) {
                document.getElementById('searchInput').value = '';
                document.getElementById('sortFilter').value = 'created_at_desc';
                document.getElementById('dateFrom').value = '';
                document.getElementById('dateTo').value = '';
                
                document.getElementById('actionFilter').value = actionType;
                
                this.applyFilters();
            }
        }

        function quickFilter(type) {
            window.activityLogManager.quickFilter(type);
        }

        document.addEventListener('DOMContentLoaded', function() {
            window.activityLogManager = new ActivityLogManager();
        });
    </script>
</body>
</html>