<?php include 'chatbot.php'; ?>
<?php

$conn = new mysqli("localhost", "root", "", "practicegoldshop");

// Handle search and filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$price_range = $_GET['price_range'] ?? '';

$sql = "SELECT * FROM products WHERE is_deleted = 0";
$params = [];
$types = "";

// Add category filter if selected
if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add search filter if provided
if (!empty($search)) {
    $sql .= " AND (product_name LIKE ? OR material LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

// Add price range filter if selected
if (!empty($price_range)) {
    if ($price_range == '20000+') {
        $sql .= " AND price >= ?";
        $params[] = 20000;
        $types .= "d";
    } else {
        $range = explode('-', $price_range);
        $sql .= " AND price >= ? AND price <= ?";
        $params[] = (float)$range[0];
        $params[] = (float)$range[1];
        $types .= "dd";
    }
}

$stmt = $conn->prepare($sql);

// Bind parameters if any exist
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - SweetHeart Gold Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/Shop.css">
</head>
<body>

<!-- Navigation Header -->
<header class="navigation-bar" id="navbar">
    <a href="index.php" class="sh-logo" aria-label="SweetHeart Gold Shop Home">SH</a>
    
    <!-- Desktop Navigation Links -->
    <nav>
        <ul class="nav-links">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php" aria-current="page">SHOP</a></li>
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
                        <p style="margin-bottom: 1rem; color: #ccc;">
                            Sign in to access your account
                        </p>
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

<!-- Main Content -->
<div class="shop-container">
    <h2 class="shop-title">Our Gold Collection</h2>
    <div class="luxury-divider"></div>
    <p class="shop-subtitle">Discover our exquisite jewelry pieces</p>

    <div class="filter-section">
        <form method="GET" action="shop.php" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search Products</label>
                    <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <option value="earring" <?= $category == 'earring' ? 'selected' : '' ?>>Earrings</option>
                        <option value="necklace" <?= $category == 'necklace' ? 'selected' : '' ?>>Necklaces</option>
                        <option value="bracelet" <?= $category == 'bracelet' ? 'selected' : '' ?>>Bracelets</option>
                        <option value="ring" <?= $category == 'ring' ? 'selected' : '' ?>>Rings</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Price Range</label>
                    <select name="price_range">
                        <option value="">All Prices</option>
                        <option value="0-5000" <?= $price_range == '0-5000' ? 'selected' : '' ?>>₱0 - ₱5,000</option>
                        <option value="5000-10000" <?= $price_range == '5000-10000' ? 'selected' : '' ?>>₱5,000 - ₱10,000</option>
                        <option value="10000-20000" <?= $price_range == '10000-20000' ? 'selected' : '' ?>>₱10,000 - ₱20,000</option>
                        <option value="20000+" <?= $price_range == '20000+' ? 'selected' : '' ?>>₱20,000+</option>
                    </select>
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit"><i class="fas fa-filter"></i> FILTER PRODUCTS</button>
                <?php if (!empty($search) || !empty($category) || !empty($price_range)): ?>
                    <a href="shop.php" class="clear-filter-btn"><i class="fas fa-times"></i> REMOVE ALL FILTERS</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="products-container">
        <?php if ($result->num_rows == 0): ?>
            <div class="no-products">
                <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; color: #d4af37;"></i>
                <p>No products found matching your criteria.</p>
                <p>Try adjusting your search or filter options.</p>
            </div>
        <?php else: ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <a href="details.php?id=<?= $row['id'] ?>" class="product-card">
                    <div class="card-image-container">
                        <div class="shimmer-effect"></div>
                        <?php 
                        $imagePath = !empty($row['image']) 
                            ? "../AdminDashboard/smpAdminwithBE/uploads/" . htmlspecialchars($row['image']) 
                            : "../Assets/image/placeholder.jpg";
                        ?>
                        <img src="<?= $imagePath ?>" 
                            alt="<?= htmlspecialchars($row['product_name']) ?>" 
                            onerror="this.src='../Assets/image/placeholder.jpg';"
                            loading="lazy">
                    </div>
                    <div class="card-content">
                        <div class="category-name"><?= htmlspecialchars(ucfirst($row['category'])) ?></div>
                        <h3><?= htmlspecialchars($row['product_name']) ?></h3>
                        <div class="price">₱<?= number_format($row['price'], 2) ?></div>
                        <?php if (!empty($row['material'])): ?>
                            <div class="material">Material: <?= htmlspecialchars($row['material']) ?></div>
                        <?php endif; ?>
                        <div class="stock-info">
                            <?php if ($row['stock'] > 0): ?>
                                <span class="in-stock">In Stock (<?= $row['stock'] ?> available)</span>
                            <?php else: ?>
                                <span class="out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <?php
                        $sizeInfo = '';
                        switch (strtolower($row['category'])) {
                            case 'ring':
                                if (!empty($row['ring_size'])) $sizeInfo = "Size: " . $row['ring_size'];
                                break;
                            case 'necklace':
                                if (!empty($row['necklace_length'])) $sizeInfo = "Length: " . $row['necklace_length'];
                                break;
                            case 'bracelet':
                                if (!empty($row['bracelet_length'])) $sizeInfo = "Length: " . $row['bracelet_length'];
                                break;
                            case 'earring':
                                if (!empty($row['closure_type'])) $sizeInfo = "Closure: " . $row['closure_type'];
                                break;
                        }
                        if ($sizeInfo): ?>
                            <div class="size-info"><?= htmlspecialchars($sizeInfo) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="luxury-accent"></div>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
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

document.querySelectorAll('.product-card').forEach(card => {
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
hamburgerBtn.addEventListener('click', function() {
    sidebarOverlay.classList.add('active');
    document.body.classList.add('sidebar-open');
});

// Close sidebar function
function closeSidebar() {
    sidebarOverlay.classList.remove('active');
    document.body.classList.remove('sidebar-open');
}

// Close sidebar when clicking X button
sidebarClose.addEventListener('click', closeSidebar);

// Close sidebar when clicking on a link
sidebarLinks.forEach(link => {
    link.addEventListener('click', closeSidebar);
});

// Close sidebar when clicking on the overlay (dark background)
sidebarOverlay.addEventListener('click', function(e) {
    if (e.target === sidebarOverlay) {
        closeSidebar();
    }
});

// Close sidebar on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebarOverlay.classList.contains('active')) {
        closeSidebar();
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>