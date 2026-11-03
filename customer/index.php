<?php 
include 'chatbot.php';

// Database Connection
$conn = new mysqli("localhost", "root", "", "practicegoldshop");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Session Variables
$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';

// Fetch Top Sellers
$categories = ['ring', 'necklace', 'bracelet', 'earring'];
$top_sellers = [];

foreach ($categories as $cat) {
    $query = "
        SELECT p.*, COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.category = ?  AND (p.is_deleted = 0 OR p.is_deleted IS NULL)
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $cat);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $top_sellers[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SweetHeart Gold Shop - Pure gold, pure beauty made just for you. Shop our exclusive collection of rings, necklaces, bracelets, and earrings.">
    <title>SweetHeart Gold Shop - Luxury Gold Jewelry</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&family=Qwigley&family=Playfair+Display:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Index.css">
    
    <style>
        /* Privacy Policy Footer & Modal Styles */
        .privacy-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: #d4af37;
        padding: 1rem;
        text-align: center;
        border-top: 2px solid #d4af37;
        font-family: 'Inter', sans-serif;
        z-index: 9999;
    }

        .privacy-footer p {
            margin: 0;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
        }

        .privacy-footer a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border-bottom: 1px solid transparent;
        }

        .privacy-footer a:hover {
            color: #fff;
            border-bottom: 1px solid #d4af37;
        }

        /* Privacy Modal */
        .privacy-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .privacy-modal-content {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            margin: 3% auto;
            padding: 0;
            border: 2px solid #d4af37;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 50px rgba(212, 175, 55, 0.3);
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .privacy-modal-header {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            padding: 1.5rem 2rem;
            border-radius: 13px 13px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .privacy-modal-header h2 {
            margin: 0;
            color: #1a1a1a;
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .privacy-close {
            color: #1a1a1a;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            background: none;
            border: none;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .privacy-close:hover {
            background: rgba(0, 0, 0, 0.2);
            transform: rotate(90deg);
        }

        .privacy-modal-body {
            padding: 2rem;
            color: #e0e0e0;
            line-height: 1.8;
        }

        .privacy-modal-body h3 {
            color: #d4af37;
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
            border-bottom: 1px solid #d4af37;
            padding-bottom: 0.5rem;
        }

        .privacy-modal-body p {
            margin-bottom: 1rem;
            font-family: 'Inter', sans-serif;
        }

        .privacy-modal-body ul {
            margin: 1rem 0;
            padding-left: 2rem;
        }

        .privacy-modal-body li {
            margin-bottom: 0.5rem;
            font-family: 'Inter', sans-serif;
        }

        .privacy-modal-body strong {
            color: #d4af37;
        }

        .privacy-modal-body a {
            color: #d4af37;
            text-decoration: none;
            border-bottom: 1px solid #d4af37;
            transition: all 0.3s ease;
        }

        .privacy-modal-body a:hover {
            color: #fff;
        }

        /* Scrollbar styling for modal */
        .privacy-modal-content::-webkit-scrollbar {
            width: 10px;
        }

        .privacy-modal-content::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        .privacy-modal-content::-webkit-scrollbar-thumb {
            background: #d4af37;
            border-radius: 5px;
        }

        .privacy-modal-content::-webkit-scrollbar-thumb:hover {
            background: #f4d03f;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .privacy-modal-content {
                width: 95%;
                margin: 5% auto;
                max-height: 90vh;
            }

            .privacy-modal-header h2 {
                font-size: 1.4rem;
            }

            .privacy-modal-body {
                padding: 1.5rem;
            }

            .privacy-modal-body h3 {
                font-size: 1.2rem;
            }
        }

        /* Prevent body scroll when modal is open */
        body.modal-open {
            overflow: hidden;
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

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero-section" id="home" aria-labelledby="hero-title">
            <div class="hero-content">
                <h1 class="hero-title" id="hero-title">SweetHeart Gold Shop</h1>
                <p class="hero-subtitle">Pure gold, pure beauty made just for you.</p>
                <a href="shop.php" class="shop-now-btn">Shop Now</a>
            </div>
        </section>

        <!-- Products Section -->
        <section class="products-section" id="products" aria-labelledby="products-title">
            <h2 class="section-title" id="products-title">Our Gold Collection</h2>
            <div class="luxury-divider" role="presentation"></div>
            <p class="section-subtitle">Discover our top sellers from each category</p>

            <!-- Top Sellers Grid -->
            <div class="top-sellers-container">
                <?php if (empty($top_sellers)): ?>
                    <div style="text-align: center; grid-column: 1 / -1;">
                        <p>No products available at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_sellers as $product): ?>
                        <article class="category-card">
                            <a href="details.php?id=<?= $product['id'] ?>" aria-label="View <?= htmlspecialchars($product['product_name']) ?>">
                                <div class="card-image-container">
                                    <div class="shimmer-effect" aria-hidden="true"></div>
                                    <span class="category-badge">Top Seller</span>
                                    <?php 
                                    $imagePath = !empty($product['image']) 
                                        ? "../AdminDashboard/smpAdminwithBE/uploads/" . htmlspecialchars($product['image']) 
                                        : "../Assets/image/placeholder.jpg";
                                    ?>
                                    <img src="<?= $imagePath ?>" 
                                        class="details-img" 
                                        alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                        onerror="this.src='../Assets/image/placeholder.jpg';"
                                        loading="lazy"> 
                                </div>
                                <div class="card-content">
                                    <span class="category-name"><?= htmlspecialchars(ucfirst($product['category'])) ?></span>
                                    <h3><?= htmlspecialchars($product['product_name']) ?></h3> 
                                    <p class="price">₱<?= number_format($product['price'], 2) ?></p>
                                    <?php if (!empty($product['description'])): ?>
                                        <p class="description">
                                            <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>
                                            <?= strlen($product['description']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="luxury-accent" aria-hidden="true"></div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- View All Button -->
            <div class="view-all-container">
                <a href="shop.php" class="view-all-btn">
                    <span>View All Products</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    </main>

    <!-- Privacy Policy Footer -->
    <footer class="privacy-footer">
        <p>
            © 2025 SweetHeart Gold Shop. All rights reserved. | 
            <a href="#" id="openPrivacyModal">Privacy Policy</a>
        </p>
    </footer>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="privacy-modal">
        <div class="privacy-modal-content">
            <div class="privacy-modal-header">
                <h2>Privacy Policy</h2>
                <button class="privacy-close" id="closePrivacyModal" aria-label="Close">&times;</button>
            </div>
            <div class="privacy-modal-body">
                <p>We, <strong>SweetHeart Gold Shop</strong>, value your privacy. This Privacy Policy explains how we collect, use, and protect your information when you use our website.</p>

                <h3>Information We Collect</h3>
                <ul>
                    <li>Account details such as your username and password</li>
                    <li>Order and transaction details</li>
                    <li>Basic contact information if provided (e.g., email or phone number)</li>
                </ul>

                <h3>How We Use Your Information</h3>
                <ul>
                    <li>Create and manage your account</li>
                    <li>Process and track orders</li>
                    <li>Maintain your login session securely</li>
                    <li>Improve the user experience of our website</li>
                </ul>

                <h3>Session Data</h3>
                <p>We use sessions to keep you logged in while using our site. Sessions temporarily store a unique ID for your account and are deleted automatically once you log out or close the browser.</p>

                <h3>Data Protection</h3>
                <p>All collected information is stored securely and used only for its intended purpose. We do not share your personal information with third parties without your consent, except as required by law.</p>

                <h3>Your Rights</h3>
                <ul>
                    <li>Access and correct your information</li>
                    <li>Request deletion of your account or data</li>
                    <li>Contact us for any concerns regarding your data</li>
                </ul>

                <h3>Contact Us</h3>
                <p>If you have questions about this Privacy Policy, you may contact us via our Facebook Page:<br>
                📧 <a href="https://www.facebook.com/SweetheartGoldShopv2" target="_blank" rel="noopener">SweetHeart Gold Shop Facebook Page</a></p>
            </div>
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

// Privacy Policy Modal
const privacyModal = document.getElementById('privacyModal');
const openPrivacyBtn = document.getElementById('openPrivacyModal');
const closePrivacyBtn = document.getElementById('closePrivacyModal');

// Open modal
openPrivacyBtn.addEventListener('click', function(e) {
    e.preventDefault();
    privacyModal.style.display = 'block';
    document.body.classList.add('modal-open');
});

// Close modal
closePrivacyBtn.addEventListener('click', function() {
    privacyModal.style.display = 'none';
    document.body.classList.remove('modal-open');
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === privacyModal) {
        privacyModal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && privacyModal.style.display === 'block') {
        privacyModal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
});

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
        const href = this.getAttribute('href');
        if (href !== '#' && href.length > 1) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
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

document.querySelectorAll('.category-card').forEach(card => {
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
<?php $conn->close(); ?>