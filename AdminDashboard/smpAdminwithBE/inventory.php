<?php
// inventory.php
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
        'category' => $_GET['category'] ?? '',
        'stock_level' => $_GET['stock_level'] ?? '',
        'min_price' => $_GET['min_price'] ?? 0,
        'max_price' => $_GET['max_price'] ?? 999999,
        'size' => $_GET['size'] ?? '',
        'sort_by' => $_GET['sort_by'] ?? 'id_desc'
    ];
    
    $products = getFilteredProducts($conn, $filters);
    
    // Calculate filtered stats
    $stats = calculateStats($products);
    
    echo json_encode([
        'products' => $products,
        'stats' => $stats,
        'count' => count($products)
    ]);
    exit;
}

// Function to build dynamic SQL query
function buildProductQuery($filters = []) {
    $sql = "SELECT id, product_name, price, category, material, stock, image,
               ring_size, necklace_length, bracelet_length, closure_type
        FROM products WHERE is_deleted = 0";
    $params = [];
    $types = '';
    
    // Text search filter
    if (!empty($filters['search'])) {
        $sql .= " AND (product_name LIKE ? OR material LIKE ? OR category LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= 'sss';
    }
    
    // Category filter
    if (!empty($filters['category'])) {
        $sql .= " AND category = ?";
        $params[] = $filters['category'];
        $types .= 's';
    }
    
    // Price range filter
    if (isset($filters['min_price']) && $filters['min_price'] > 0) {
        $sql .= " AND price >= ?";
        $params[] = $filters['min_price'];
        $types .= 'd';
    }
    if (isset($filters['max_price']) && $filters['max_price'] < 999999) {
        $sql .= " AND price <= ?";
        $params[] = $filters['max_price'];
        $types .= 'd';
    }
    
    // Size filter (handles all size types)
    if (!empty($filters['size'])) {
        $sql .= " AND (ring_size = ? OR necklace_length = ? OR bracelet_length = ? OR closure_type = ?)";
        $params = array_merge($params, [$filters['size'], $filters['size'], $filters['size'], $filters['size']]);
        $types .= 'ssss';
    }
    
    // Stock level filter
    if (!empty($filters['stock_level'])) {
        switch ($filters['stock_level']) {
            case 'high':
                $sql .= " AND stock >= 15";
                break;
            case 'medium':
                $sql .= " AND stock BETWEEN 6 AND 14";
                break;
            case 'low':
                $sql .= " AND stock <= 5";
                break;
            case 'in_stock':
                $sql .= " AND stock > 0";
                break;
            case 'out_of_stock':
                $sql .= " AND stock = 0";
                break;
        }
    }
    
    // Sorting
    switch ($filters['sort_by'] ?? 'id_desc') {
        case 'price_asc':
            $sql .= " ORDER BY price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY price DESC";
            break;
        case 'name_asc':
            $sql .= " ORDER BY product_name ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY product_name DESC";
            break;
        case 'stock_desc':
            $sql .= " ORDER BY stock DESC";
            break;
        case 'stock_asc':
            $sql .= " ORDER BY stock ASC";
            break;
        default:
            $sql .= " ORDER BY id DESC";
    }
    
    return ['sql' => $sql, 'params' => $params, 'types' => $types];
}

// Function to get filtered products
function getFilteredProducts($conn, $filters) {
    $query = buildProductQuery($filters);
    
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
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    return $products;
}

// Function to calculate statistics
function calculateStats($products) {
    $total_products = count($products);
    $total_value = 0;
    $low_stock_count = 0;
    $categories = [];
    
    foreach ($products as $product) {
        $total_value += ($product['price'] * $product['stock']);
        if ($product['stock'] <= 5) {
            $low_stock_count++;
        }
        if (!in_array($product['category'], $categories)) {
            $categories[] = $product['category'];
        }
    }
    
    return [
        'total_products' => $total_products,
        'total_value' => $total_value,
        'low_stock_count' => $low_stock_count,
        'categories_count' => count($categories)
    ];
}

// Get unique sizes for filter dropdown
function getUniqueSizes($conn, $category = null) {
    $sizes = [];
    
    // Get sizes from all relevant columns
    $columns = [
        'ring_size' => 'ring',
        'necklace_length' => 'necklace', 
        'bracelet_length' => 'bracelet'
    ];
    
    foreach ($columns as $column => $cat) {
        if ($category && $category !== $cat) continue;
        
        $sql = "SELECT DISTINCT $column FROM products 
                WHERE $column IS NOT NULL 
                AND $column != '' 
                AND $column != 'N/A'";
        
        if ($category) {
            $sql .= " AND category = '$category'";
        }
        
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row[$column])) {
                    $sizes[] = $row[$column];
                }
            }
        }
    }
    
    return array_unique($sizes);
}

// Fetch all products for initial display
$sql = "SELECT * FROM products WHERE is_deleted = 0 ORDER BY id DESC";
$result = $conn->query($sql);

// Calculate inventory statistics
$total_products = 0;
$total_value = 0;
$low_stock_count = 0;
$categories = [];

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $total_products++;
        $total_value += ($row['price'] * $row['stock']);
        if ($row['stock'] < 6) {
            $low_stock_count++;
        }
        if (!in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
    }
    $result->data_seek(0);
}

// Get unique sizes for the size filter
$all_sizes = getUniqueSizes($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Inventory.css">
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
                <a href="inventory.php" class="nav-link active">
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
                <h1>PRODUCTS</h1>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>
          
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📦 Total Products</div>
                <div class="stat-value" id="totalProductsCount"><?= $total_products ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">💰 Inventory Value</div>
                <div class="stat-value" id="totalValueCount">₱<?= number_format($total_value, 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">⚠️ Low Stock Items</div>
                <div class="stat-value" id="lowStockCount"><?= $low_stock_count ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🏷️ Categories</div>
                <div class="stat-value"><?= count($categories) ?></div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card no-print">
            <div class="filter-section">
                <div class="search-add-row">
                    <input type="text" class="form-control search-input" id="searchInput"
                        placeholder="Search products, materials, categories...">
                    
                    <a href="addproduct.php" style="text-decoration: none;">
                        <button class="btn-add-product">
                            <img src="../icons/plus.png" alt="Add" style="width:24px; height:24px; margin-right:8px;">
                            Add Product
                        </button>
                    </a>
                </div>

                <!-- Filter Controls -->
                <div class="row g-3 mt-3">
                    <div class="col-md-2">
                        <label class="filter-label">Category</label>
                        <select class="form-select filter-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= strtolower($category) ?>"><?= ucfirst($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">Stock Level</label>
                        <select class="form-select filter-select" id="stockFilter">
                            <option value="">All Stock Levels</option>
                            <option value="high">High Stock (15+)</option>
                            <option value="medium">Medium Stock (6-14)</option>
                            <option value="low">Low Stock (≤5)</option>
                            <option value="in_stock">In Stock Only</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">Price Range</label>
                        <div class="price-range-inputs">
                            <input type="number" class="form-control filter-input" 
                                   id="minPrice" placeholder="Min ₱">
                            <span class="price-separator">-</span>
                            <input type="number" class="form-control filter-input" 
                                   id="maxPrice" placeholder="Max ₱">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">Size</label>
                        <select class="form-select filter-select" id="sizeFilter">
                            <option value="">All Sizes</option>
                            <?php foreach ($all_sizes as $size): ?>
                                <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">Sort By</label>
                        <select class="form-select filter-select" id="sortFilter">
                            <option value="id_desc">Newest First</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="name_asc">Name: A to Z</option>
                            <option value="name_desc">Name: Z to A</option>
                            <option value="stock_desc">Stock: High to Low</option>
                            <option value="stock_asc">Stock: Low to High</option>
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

        <!-- Products Table -->
        <div class="card">
            <div class="table-header">
                <h3 id="resultsCount">Showing <?= $total_products ?> products</h3>
                <div class="quick-filters">
                    <button class="btn-quick-filter" onclick="quickFilter('low')">Low Stock Alert</button>
                    <button class="btn-quick-filter" onclick="quickFilter('in_stock')">In Stock Only</button>
                </div>
            </div>

            <table id="productsTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Material</th>
                        <th>Size/Details</th>
                        <th style="width: 120px;">Stock</th>
                        <th style="width: 130px;">Price</th>
                        <th style="width: 180px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
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
                                    <?php 
                                    $stock = $row['stock'];
                                    if ($stock >= 15) {
                                        $badgeClass = 'stock-high';
                                    } elseif ($stock >= 6) {
                                        $badgeClass = 'stock-medium';
                                    } else {
                                        $badgeClass = 'stock-low';
                                    }
                                    ?>
                                    <span class="stock-badge <?= $badgeClass ?>"><?= $stock ?> pcs</span>
                                </td>
                                <td class="price">₱<?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="editproduct.php?id=<?= $row['id'] ?>" class="btn-table-edit">Edit</a>
                                        <a href="deleteproduct.php?id=<?= $row['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to move this product to Recycle Bin?');"
                                           class="btn-table-delete">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <h4>No products found</h4>
                                <p>Start by <a href="addproduct.php">adding your first product</a>!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
class ProductInventoryManager {
    constructor() {
        this.initializeEventListeners();
        this.originalStats = {
            totalProducts: <?= $total_products ?>,
            totalValue: <?= $total_value ?>,
            lowStockCount: <?= $low_stock_count ?>
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

        document.getElementById('categoryFilter').addEventListener('change', () => {
            this.updateSizeOptions();
        });
    }

    showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    updateSizeOptions() {
        const category = document.getElementById('categoryFilter').value;
        const sizeFilter = document.getElementById('sizeFilter');
        
        sizeFilter.innerHTML = '<option value="">All Sizes</option>';
        
        if (!category) {
            <?php foreach ($all_sizes as $size): ?>
                sizeFilter.innerHTML += '<option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>';
            <?php endforeach; ?>
            return;
        }

        const categoryToSizes = {
            'ring': ['5', '6', '7', '8', '9', '10', '11', '12'],
            'necklace': ['16"', '18"', '20"', '22"', '24"'],
            'bracelet': ['7"', '7.5"', '8"', '8.5"'],
            'earring': ['Push Back', 'Screw Back', 'Lever Back', 'Hook']
        };

        if (categoryToSizes[category]) {
            categoryToSizes[category].forEach(size => {
                sizeFilter.innerHTML += `<option value="${size}">${size}</option>`;
            });
        }
    }

    getFilterValues() {
        return {
            search: document.getElementById('searchInput').value.trim(),
            category: document.getElementById('categoryFilter').value,
            stock_level: document.getElementById('stockFilter').value,
            min_price: document.getElementById('minPrice').value || 0,
            max_price: document.getElementById('maxPrice').value || 999999,
            size: document.getElementById('sizeFilter').value,
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

        fetch(`inventory.php?${params.toString()}`)
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
        const tbody = document.getElementById('productsTableBody');
        const resultsCount = document.getElementById('resultsCount');
        
        resultsCount.textContent = `Showing ${data.count} products`;
        
        if (data.products.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <h4>No products found</h4>
                        <p>Try adjusting your filters or search terms.</p>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        data.products.forEach(product => {
            let badgeClass = 'stock-low';
            if (product.stock >= 15) {
                badgeClass = 'stock-high';
            } else if (product.stock >= 6) {
                badgeClass = 'stock-medium';
            }

            let details = '';
            switch (product.category.toLowerCase()) {
                case 'ring':
                    details = `Size: ${product.ring_size || 'N/A'}`;
                    break;
                case 'necklace':
                    details = `Length: ${product.necklace_length || 'N/A'}`;
                    break;
                case 'earring':
                    details = `Closure: ${product.closure_type || 'N/A'}`;
                    break;
                case 'bracelet':
                    details = `Length: ${product.bracelet_length || 'N/A'}`;
                    break;
                default:
                    details = '-';
            }

            const imageUrl = product.image ? `uploads/${product.image}` : '../productPic/default.jpg';

            html += `
                <tr>
                    <td>
                        <img src="${imageUrl}" alt="${product.product_name}" class="product-image-thumb">
                    </td>
                    <td><strong>${product.product_name}</strong></td>
                    <td><span class="category-badge">${product.category}</span></td>
                    <td>${product.material || 'N/A'}</td>
                    <td>${details}</td>
                    <td>
                        <span class="stock-badge ${badgeClass}">${product.stock} pcs</span>
                    </td>
                    <td class="price">₱${parseFloat(product.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>
                        <div class="action-buttons">
                            <a href="editproduct.php?id=${product.id}" class="btn-table-edit">Edit</a>
                            <a href="deleteproduct.php?id=${product.id}" 
                               onclick="return confirm('Are you sure you want to move this product to Recycle Bin?');"
                               class="btn-table-delete">Delete</a>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    updateStats(stats) {
        document.getElementById('totalProductsCount').textContent = stats.total_products;
        document.getElementById('totalValueCount').textContent = 
            '₱' + stats.total_value.toLocaleString('en-US', {maximumFractionDigits: 0});
        document.getElementById('lowStockCount').textContent = stats.low_stock_count;
    }

    updateActiveFilters(filters) {
        const activeFiltersDiv = document.getElementById('activeFilters');
        const badges = [];

        if (filters.search) badges.push(`Search: "${filters.search}"`);
        if (filters.category) badges.push(`Category: ${filters.category}`);
        if (filters.stock_level) badges.push(`Stock: ${this.getStockLevelText(filters.stock_level)}`);
        if (filters.min_price > 0) badges.push(`Min: ₱${filters.min_price}`);
        if (filters.max_price < 999999) badges.push(`Max: ₱${filters.max_price}`);
        if (filters.size) badges.push(`Size: ${filters.size}`);
        if (filters.sort_by && filters.sort_by !== 'id_desc') {
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

    getStockLevelText(level) {
        const texts = {
            'high': 'High Stock (15+)',
            'medium': 'Medium Stock (6-14)',
            'low': 'Low Stock (≤5)',
            'in_stock': 'In Stock Only',
            'out_of_stock': 'Out of Stock'
        };
        return texts[level] || level;
    }

    getSortText(sort) {
        const texts = {
            'price_asc': 'Price: Low to High',
            'price_desc': 'Price: High to Low',
            'name_asc': 'Name: A to Z',
            'name_desc': 'Name: Z to A',
            'stock_desc': 'Stock: High to Low',
            'stock_asc': 'Stock: Low to High'
        };
        return texts[sort] || sort;
    }

    clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('categoryFilter').value = '';
        document.getElementById('stockFilter').value = '';
        document.getElementById('sortFilter').value = 'id_desc';
        document.getElementById('minPrice').value = '';
        document.getElementById('maxPrice').value = '';
        document.getElementById('sizeFilter').value = '';
        
        this.updateSizeOptions();
        this.applyFilters();
    }

    quickFilter(type) {
        document.getElementById('searchInput').value = '';
        document.getElementById('categoryFilter').value = '';
        document.getElementById('sortFilter').value = 'id_desc';
        document.getElementById('minPrice').value = '';
        document.getElementById('maxPrice').value = '';
        document.getElementById('sizeFilter').value = '';
        
        document.getElementById('stockFilter').value = type;
        
        this.applyFilters();
    }
}

function quickFilter(type) {
    window.inventoryManager.quickFilter(type);
}

document.addEventListener('DOMContentLoaded', function() {
    window.inventoryManager = new ProductInventoryManager();
});
    </script>
</body>
</html>