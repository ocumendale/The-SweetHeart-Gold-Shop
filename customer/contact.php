<?php
include 'chatbot.php';
$conn = new mysqli("localhost", "root", "", "practicegoldshop");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - SweetHeart Gold Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&family=Qwigley&family=Playfair+Display:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Contact.css">
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

<div class="main-content">
    <h1 class="page-title">Get In Touch</h1>
    <p class="page-subtitle">We're here to help with all your gold jewelry needs</p>

    <div class="contact-grid">
        <div class="contact-card">
            <div class="info-row">
                <div class="info-icon">📍</div>
                <div class="info-content">
                    <h3>Visit Our Store</h3>
                    <p>1220 Buenavista St. Malinta Valenzuela City</p>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon">📞</div>
                <div class="info-content">
                    <h3>Call Us</h3>
                    <p>Phone: +63 931 219 1438</p>
                </div>
            </div>
        </div>

        <div class="contact-card">
            <div class="info-row">
                <div class="info-icon">⏰</div>
                <div class="info-content">
                    <h3>Business Hours</h3>
                    <p>Always Open!</p>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon">✉️</div>
                <div class="info-content">
                    <h3>Email Us</h3>
                    <p>info@goldshop.com<br>support@goldshop.com</p>
                </div>
            </div>
        </div>

        <div class="social-card">
            <h2>Connect With Us</h2>
            <p>Follow us on Facebook for the latest updates, new arrivals, and special promotions!</p>
            <a href="https://www.facebook.com/SweetheartGoldShopv2" target="_blank" class="facebook-btn">
                <span class="fb-icon">f</span>
                Visit Our Facebook Page
            </a>
        </div>
    </div>
</div>

<script>
// ============================================
// PROFILE DROPDOWN FUNCTIONALITY
// ============================================
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

// ============================================
// NAVBAR SCROLL EFFECT
// ============================================
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