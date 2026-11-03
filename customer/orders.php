<?php
session_start();

$conn = new mysqli("localhost", "root", "", "practicegoldshop");
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$is_logged_in = isset($_SESSION['user_id']);

// Check if viewing a specific order
$viewing_order_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$selected_order = null;

if ($viewing_order_id > 0) {
    // Fetch specific order details
    $order_query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("ii", $viewing_order_id, $user_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    
    if ($order_result->num_rows > 0) {
        $selected_order = $order_result->fetch_assoc();
         
        // Fetch order items
        $items_query = "
        SELECT oi.*, p.image 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param("i", $viewing_order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $selected_order['items'] = [];
        while ($item = $items_result->fetch_assoc()) {
            $selected_order['items'][] = $item;
        }
        $items_stmt->close();
        
        // Calculate totals
        $shipping_cost = 50.00;
        $tax_rate = 0.10;
        $subtotal = 0;
        
        foreach ($selected_order['items'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $selected_order['subtotal'] = $subtotal;
        $selected_order['tax_amount'] = $subtotal * $tax_rate;
        $selected_order['shipping_cost'] = $shipping_cost;
    }
    $order_stmt->close();
}

// Get filter from URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Fetch all orders for history view - UPDATED TO INCLUDE STATUS AND FILTER
$query = "
SELECT o.id as order_id, o.order_date, o.status, oi.product_name, oi.price, oi.quantity, p.image
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
JOIN products p ON oi.product_id = p.id
WHERE o.user_id = ?
";

// Add status filter if not 'all'
if ($status_filter !== 'all') {
    $query .= " AND o.status = ?";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);

if ($status_filter !== 'all') {
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

// --- Grouping Logic ---
$all_orders = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $order_id = $row['order_id'];
        $item_total = $row['price'] * $row['quantity'];

        if (!isset($all_orders[$order_id])) {
            $all_orders[$order_id] = [
                'id' => $order_id,
                'date' => $row['order_date'],
                'status' => $row['status'], // ADD STATUS
                'items' => [],
                'total' => 0
            ];
        }
        $all_orders[$order_id]['items'][] = $row;
        $all_orders[$order_id]['total'] += $item_total;
    }
}

$conn->close();

// Function to get status badge class and icon
function getStatusInfo($status) {
    $status_lower = strtolower($status);
    switch($status_lower) {
        case 'pending':
            return ['class' => 'status-pending', 'icon' => 'fa-clock', 'text' => 'Pending'];
        case 'processing':
            return ['class' => 'status-processing', 'icon' => 'fa-spinner', 'text' => 'Processing'];
        case 'delivered':
            return ['class' => 'status-delivered', 'icon' => 'fa-check-circle', 'text' => 'Delivered'];
        case 'cancelled':
            return ['class' => 'status-cancelled', 'icon' => 'fa-times-circle', 'text' => 'Cancelled'];
        default:
            return ['class' => 'status-pending', 'icon' => 'fa-info-circle', 'text' => ucfirst($status)];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - SweetHeart Gold Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&family=Qwigley&family=Playfair+Display:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Orders.css">
    <style>
        /* Status Filter Styles */
        .filter-container {
            margin: 2rem auto;
            max-width: 1200px;
            padding: 0 2rem;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid rgba(255, 215, 0, 0.3);
            background: transparent;
            color: #d4af37;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tab:hover {
            background: rgba(212, 175, 55, 0.1);
            border-color: #d4af37;
            transform: translateY(-2px);
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #d4af37 0%, #f9d77e 100%);
            color: #000;
            border-color: #d4af37;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .filter-tab i {
            font-size: 1rem;
        }

        /* Status Badge Styles */
        .order-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .order-status-badge i {
            font-size: 0.9rem;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-processing {
            background: rgba(33, 150, 243, 0.15);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .status-shipped {
            background: rgba(156, 39, 176, 0.15);
            color: #9c27b0;
            border: 1px solid rgba(156, 39, 176, 0.3);
        }

        .status-delivered {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        /* Receipt Status Badge */
        .receipt-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 15px;
        }

        .receipt-status-badge i {
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

<!-- Navigation Header -->
<header class="navigation-bar" id="navbar">
    <a href="index.php" class="sh-logo" aria-label="SweetHeart Gold Shop Home">SH</a>
    
    <!-- Desktop Navigation Links -->
    <nav>
        <ul class="nav-links">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="contact.php">CONTACT</a></li>
        </ul>
    </nav>
    
    <!-- Navigation Icons -->
    <div class="nav-icons">
        <!-- Shopping Cart -->
        <a href="cart.php" aria-label="Shopping Cart">
            <i class="fas fa-shopping-cart" id="cart"></i>
        </a>
        
        <!-- Profile Dropdown -->
        <div class="profile-container">
            <i class="fas fa-user" id="profile-icon" aria-label="User Profile" role="button" tabindex="0"></i>
            <div class="profile-dropdown" id="profile-dropdown" role="menu">
                <?php if ($is_logged_in): ?>
                    <div class="profile-info">
                        <div class="welcome-text">Welcome back!</div>
                        <div><?= htmlspecialchars($username) ?></div>
                    </div>
                    <div class="profile-links">
                        <a href="orders.php" role="menuitem">
                            <i class="fas fa-box"></i>
                            Order history
                        </a>
                        <a href="logout.php" role="menuitem">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                <?php else: ?>
                    <div class="login-prompt">
                        <div style="margin-bottom: 1rem; color: #ccc;">
                            Sign in to access your account
                        </div>
                        <div class="login-buttons">
                            <a href="login.php" class="login-btn">Login</a>
                            <a href="register.php" class="register-btn">Register</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Hamburger Menu Button (Mobile Only) -->
        <button class="hamburger-menu" id="hamburgerBtn" aria-label="Open Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>

<!-- Fullscreen Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay">
    <div class="sidebar-content">
        <!-- Close Button -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Menu Links -->
        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <li><a href="index.php">HOME</a></li>
                <li><a href="shop.php">SHOP</a></li>
                <li><a href="contact.php">CONTACT</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="orders.php">ORDER HISTORY</a></li>
                    <li><a href="logout.php">LOGOUT</a></li>
                <?php else: ?>
                    <li><a href="login.php">LOGIN</a></li>
                    <li><a href="register.php">REGISTER</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<?php if ($selected_order): ?>
    <!-- Receipt View -->
    <div class="receipt-container active">
        <div class="receipt-card">
            <div class="receipt-header">
                <div class="success-icon-container">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <h1 class="receipt-title">Order Receipt</h1>
                <p class="receipt-subtitle">Thank you for your purchase. Your identity has been verified.</p>
                
                <?php 
                $statusInfo = getStatusInfo($selected_order['status']);
                ?>
                <div class="receipt-status-badge <?= $statusInfo['class'] ?>">
                    <i class="fas <?= $statusInfo['icon'] ?>"></i>
                    <?= $statusInfo['text'] ?>
                </div>
            </div>

            <div class="receipt-body">
                <div class="order-details-box">
                    <div class="detail-row">
                        <span class="detail-label">ORDER ID:</span>
                        <span class="detail-value">#<?= str_pad($viewing_order_id, 6, '0', STR_PAD_LEFT) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">DATE:</span>
                        <span class="detail-value"><?= date('F j, Y - g:i A', strtotime($selected_order['order_date'])) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">STATUS:</span>
                        <span class="detail-value">
                            <span class="order-status-badge <?= $statusInfo['class'] ?>">
                                <i class="fas <?= $statusInfo['icon'] ?>"></i>
                                <?= $statusInfo['text'] ?>
                            </span>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">CUSTOMER:</span>
                        <span class="detail-value"><?= htmlspecialchars($selected_order['shipping_name']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">PHONE:</span>
                        <span class="detail-value"><?= htmlspecialchars($selected_order['shipping_phone']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">ADDRESS:</span>
                        <span class="detail-value"><?= htmlspecialchars($selected_order['shipping_address'] . ', ' . $selected_order['shipping_city']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">ID VERIFIED:</span>
                        <span class="detail-value" style="color: #28a745;">
                            <i class="fas fa-check-circle"></i> Yes - Document Uploaded
                        </span>
                    </div>

                    <?php if (!empty($selected_order['facebook_profile'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">FACEBOOK:</span>
                        <span class="detail-value">
                            <a href="<?= htmlspecialchars($selected_order['facebook_profile']) ?>" target="_blank">
                                <i class="fab fa-facebook"></i> View Profile
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label">PAYMENT:</span>
                        <span class="detail-value"><?= ucfirst($selected_order['payment_method']) ?></span>
                    </div>
                </div>

                <div class="items-section">
                    <h2 class="items-title">Items Ordered:</h2>
                    
                    <?php foreach ($selected_order['items'] as $item): 
                        $item_total = $item['quantity'] * $item['price'];
                    ?>
                    <div class="item-row">
                        <div class="item-info">
                            <span class="item-name"><?= htmlspecialchars($item['product_name']) ?></span>
                            <span class="item-details">Qty: <?= $item['quantity'] ?> &times; ₱<?= number_format($item['price'], 2) ?></span>
                        </div>
                        <span class="item-price">₱<?= number_format($item_total, 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="totals-section">
                    <div class="total-line">
                        <span>Subtotal:</span>
                        <span>₱<?= number_format($selected_order['subtotal'], 2) ?></span>
                    </div>
                    <div class="total-line">
                        <span>Shipping:</span>
                        <span>₱<?= number_format($selected_order['shipping_cost'], 2) ?></span>
                    </div>
                    <div class="total-line">
                        <span>Tax (10%):</span>
                        <span>₱<?= number_format($selected_order['tax_amount'], 2) ?></span>
                    </div>
                    <div class="grand-total-line">
                        <span>Grand Total:</span>
                        <span>₱<?= number_format($selected_order['total_price'], 2) ?></span>
                    </div>
                </div>

                <div class="receipt-actions">
                    <a href="shop.php" class="order-again-btn">
                        <i class="fas fa-redo-alt"></i> Order Again
                    </a>
                    <a href="orders.php" class="view-history-btn">
                        <i class="fas fa-history"></i> Back to Orders
                    </a>
                </div>

            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Order History View -->
    <div class="main-content">
        <h2 class="page-title fade-in">Your Order History</h2>

        <!-- Status Filter -->
        <div class="filter-container fade-in">
            <div class="filter-tabs">
                <a href="orders.php?status=all" class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> All Orders
                </a>
                <a href="orders.php?status=pending" class="filter-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
                <a href="orders.php?status=processing" class="filter-tab <?= $status_filter === 'processing' ? 'active' : '' ?>">
                    <i class="fas fa-spinner"></i> Processing
                </a>
                <a href="orders.php?status=delivered" class="filter-tab <?= $status_filter === 'delivered' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Delivered
                </a>
                <a href="orders.php?status=cancelled" class="filter-tab <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
                    <i class="fas fa-times-circle"></i> Cancelled
                </a>
            </div>
        </div>

        <?php if (!empty($all_orders)): ?>
            <div class="orders-timeline">
                <?php foreach ($all_orders as $order_data): 
                    $statusInfo = getStatusInfo($order_data['status']);
                ?>
                    <div class="order-card fade-in" onclick="window.location.href='orders.php?view=<?= $order_data['id'] ?>'">
                        <div class="order-card-header">
                            <div class="order-date-badge">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?= date('F j, Y', strtotime($order_data['date'])) ?></span>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div class="order-id-badge">
                                    ORDER #<?= str_pad($order_data['id'], 6, '0', STR_PAD_LEFT) ?>
                                </div>
                                <div class="order-status-badge <?= $statusInfo['class'] ?>">
                                    <i class="fas <?= $statusInfo['icon'] ?>"></i>
                                    <?= $statusInfo['text'] ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-items-grid">
                            <?php foreach ($order_data['items'] as $item): 
                                $imagePath = !empty($item['image']) 
                                    ? "../AdminDashboard/smpAdminwithBE/uploads/" . htmlspecialchars($item['image']) 
                                    : "../Assets/image/placeholder.jpg";
                                $fallbackPath = "../Assets/image/placeholder.jpg";
                            ?>
                            <div class="order-item-card">
                                <img src="<?= $imagePath ?>" 
                                     alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                     class="item-image"
                                     onerror="this.src='<?= $fallbackPath ?>';"
                                     loading="lazy">
                                <div class="item-info">
                                    <div class="item-name" title="<?= htmlspecialchars($item['product_name']) ?>">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </div>
                                    <div class="item-quantity">Qty: <?= $item['quantity'] ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-card-footer">
                            <a href="orders.php?view=<?= $order_data['id'] ?>" class="view-receipt-btn" onclick="event.stopPropagation()">
                                <i class="fas fa-receipt"></i>
                                View Receipt
                            </a>
                            <div class="order-total-display">
                                <span class="total-label">Total Amount</span>
                                <span class="total-amount">₱<?= number_format($order_data['total'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-orders fade-in">
                <i class="fas fa-shopping-bag"></i>
                <h3>No Orders Found</h3>
                <p>
                    <?php if ($status_filter !== 'all'): ?>
                        No orders with status "<?= ucfirst($status_filter) ?>" found.
                    <?php else: ?>
                        You haven't placed any orders yet. Start shopping to see your order history here!
                    <?php endif; ?>
                </p>
                
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Profile dropdown functionality
const profileIcon = document.getElementById('profile-icon');
const profileDropdown = document.getElementById('profile-dropdown');

if (profileIcon && profileDropdown) {
    profileIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileIcon.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
}

// Navbar scroll effect
const navbar = document.getElementById('navbar');
if (navbar) {
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Fade in animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animationDelay = Math.random() * 0.3 + 's';
            entry.target.classList.add('fade-in');
        }
    });
}, observerOptions);

document.querySelectorAll('.order-card, .no-orders').forEach(card => {
    observer.observe(card);
});

// ============================================
// FULLSCREEN SIDEBAR MENU FUNCTIONALITY
// ============================================

const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const sidebarClose = document.getElementById('sidebarClose');
const sidebarLinks = document.querySelectorAll('.sidebar-menu a');

// Open sidebar
if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', function() {
        sidebarOverlay.classList.add('active');
        document.body.classList.add('sidebar-open');
    });
}

// Close sidebar function
function closeSidebar() {
    sidebarOverlay.classList.remove('active');
    document.body.classList.remove('sidebar-open');
}

// Close sidebar when clicking X button
if (sidebarClose) {
    sidebarClose.addEventListener('click', closeSidebar);
}

// Close sidebar when clicking on a link
sidebarLinks.forEach(link => {
    link.addEventListener('click', closeSidebar);
});

// Close sidebar when clicking on the overlay (dark background)
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function(e) {
        if (e.target === sidebarOverlay) {
            closeSidebar();
        }
    });
}

// Close sidebar on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebarOverlay && sidebarOverlay.classList.contains('active')) {
        closeSidebar();
    }
});
</script>

</body>
</html>