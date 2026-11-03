<?php include 'chatbot.php'; ?>
<?php

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = $_SESSION['cart'];
$total = 0;

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';

// Check for notification messages
$notification = $_SESSION['cart_notification'] ?? null;
unset($_SESSION['cart_notification']); // Clear notification after reading
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Sweetheart Gold Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&family=Qwigley&family=Playfair+Display:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Cart.css">
    <style>
        /* Toast Notification Styles */
        .toast-notification {
            position: fixed;
            top: 100px;
            right: 20px;
            background-color:#83786d;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 300px;
            max-width: 400px;
            z-index: 10000;
            animation: slideIn 0.4s ease-out, fadeOut 0.4s ease-in 4.6s;
            opacity: 0;
        }

        .toast-notification.show {
            opacity: 1;
        }

        .toast-notification.error {
             background-color:#83786d;
        }

        .toast-notification.success {
             background-color:#83786d;
        }

        .toast-notification.warning {
             background-color:#83786d;
        }

        .toast-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .toast-message {
            font-size: 0.875rem;
            opacity: 0.95;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            opacity: 0.8;
            transition: opacity 0.2s;
            flex-shrink: 0;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        /* Cart limit info banner */
        .cart-limit-info {
             background-color:#83786d;
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: fadeIn 0.5s ease-out;
        }

        .cart-limit-info i {
            font-size: 1.5rem;
        }

        .cart-limit-info .limit-text {
            flex: 1;
        }

        .cart-limit-info .limit-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .cart-limit-info .limit-count {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .toast-notification {
                right: 10px;
                left: 10px;
                min-width: auto;
                max-width: none;
            }
        }
    </style>
</head>
<body>
<!-- Toast Notification Container -->
<?php if ($notification): ?>
<div class="toast-notification <?= htmlspecialchars($notification['type']) ?>" id="toastNotification">
    <div class="toast-icon">
        <?php 
        switch($notification['type']) {
            case 'error':
                echo '<i class="fas fa-exclamation-circle"></i>';
                break;
            case 'success':
                echo '<i class="fas fa-check-circle"></i>';
                break;
            case 'warning':
                echo '<i class="fas fa-exclamation-triangle"></i>';
                break;
            default:
                echo '<i class="fas fa-info-circle"></i>';
        }
        ?>
    </div>
    <div class="toast-content">
        <div class="toast-title"><?= htmlspecialchars($notification['title']) ?></div>
        <div class="toast-message"><?= htmlspecialchars($notification['message']) ?></div>
    </div>
    <button class="toast-close" onclick="closeToast()">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

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

    <div class="cart-container">
        <div class="cart-header fade-in">
            <h1 class="cart-title">Shopping Cart</h1>
            <p class="cart-subtitle">Review your selected items before checkout</p>
        </div>

        <?php if (!empty($cart)): ?>
            <!-- Cart Limit Info Banner -->
            <div class="cart-limit-info fade-in">
                <i class="fas fa-info-circle"></i>
                <div class="limit-text">
                    <div class="limit-title">Cart Limit Policy</div>
                    <div class="limit-count">
                        You have <?= count($cart) ?> of 3 maximum items in your cart
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($cart)): ?>
            <div class="empty-cart fade-in">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Your cart is empty</h3>
                <p>Discover our exquisite collection of gold jewelry</p>
                <a href="shop.php" class="continue-shopping-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-items fade-in">
                <?php foreach ($cart as $item_id => $item):
                    $subtotal = $item['qty'] * $item['price'];
                    $total += $subtotal;

                    // Fix image path - use admin uploads folder or placeholder
                    $imagePath = !empty($item['image']) 
                        ? "../AdminDashboard/smpAdminwithBE/uploads/" . htmlspecialchars($item['image']) 
                        : "../Assets/image/placeholder.jpg";
                ?>
                <div class="cart-item">
                    <div class="product-info">
                        <img src="<?= $imagePath ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>" 
                             class="product-image"
                             onerror="this.src='../Assets/image/placeholder.jpg';">
                        <div class="product-details">
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <div class="product-price">₱<?= number_format($item['price'], 2) ?></div>
                            <?php if (isset($item['karat'])): ?>
                                <div class="product-karat">Karat: <?= htmlspecialchars($item['karat']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="quantity-controls">
                        <form action="update_cart.php" method="post" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($item_id) ?>">
                            <button type="submit" name="action" value="decrease" class="qty-btn">−</button>
                            <input type="number" name="quantity" value="<?= $item['qty'] ?>" min="1" class="quantity-input" onchange="this.form.submit()">
                            <button type="submit" name="action" value="increase" class="qty-btn">+</button>
                        </form>
                    </div>

                    <div class="item-total">₱<?= number_format($subtotal, 2) ?></div>

                    <div>
                        <form action="remove_from_cart.php" method="post">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($item_id) ?>">
                            <button type="submit" class="remove-btn" title="Remove item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary fade-in">
                <div class="cart-total">
                    Total: ₱<?= number_format($total, 2) ?>
                </div>
                <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
// Toast notification functionality
function closeToast() {
    const toast = document.getElementById('toastNotification');
    if (toast) {
        toast.style.animation = 'fadeOut 0.4s ease-in';
        setTimeout(() => {
            toast.remove();
        }, 400);
    }
}

// Auto-show and auto-hide toast
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('toastNotification');
    if (toast) {
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            closeToast();
        }, 5000);
    }
});

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

// Fade in animation on load
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        setTimeout(() => {
            element.style.animationDelay = (index * 0.1) + 's';
            element.classList.add('fade-in');
        }, index * 100);
    });
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