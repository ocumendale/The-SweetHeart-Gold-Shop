<?php
include 'chatbot.php';
$conn = new mysqli("localhost", "root", "", "practicegoldshop");

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? 0;
// Using product_name and material columns for consistency with shop.php
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    http_response_code(404);
    die("Product not found.");
}

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';

// Main product image path
$productImagePath = !empty($product['image'])
    ? "../AdminDashboard/smpAdminwithBE/uploads/" . htmlspecialchars($product['image'])
    : "../Assets/image/placeholder.jpg";

$stmt->close(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($product['product_name'] ?? 'Product') ?> - Sweetheart Gold Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&family=Qwigley&family=Playfair+Display:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Details.css"> 
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

<div class="details-container">
    <div class="image-section">
        <img src="<?= $productImagePath ?>" 
             class="details-img" 
             alt="<?= htmlspecialchars($product['product_name'] ?? 'Product') ?>">
    </div>

    <div class="details-info">
        <span class="product-badge"><?= htmlspecialchars(ucfirst($product['category'] ?? 'Product')) ?></span>
        
        <h1><?= htmlspecialchars($product['product_name'] ?? 'No Name') ?></h1>
        
        <div class="product-meta">
            <div class="meta-item">
                <span class="meta-label">Material</span>
                <span class="meta-value"><?= htmlspecialchars($product['material'] ?? 'N/A') ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Category</span>
                <span class="meta-value"><?= htmlspecialchars(ucfirst($product['category'] ?? 'N/A')) ?></span>
            </div>
        </div>
        
        <p class="desc"><?= htmlspecialchars($product['description'] ?? 'No description available.') ?></p>
        
        <div class="price-section">
            <span class="price-label">Price</span>
            <p class="price">₱<?= number_format($product['price'] ?? 0, 2) ?></p>
        </div>

        <form action="add_to_cart.php" method="post" id="productForm">
            <input type="hidden" name="id" value="<?= $product['id'] ?? 0 ?>">
            <input type="hidden" name="name" value="<?= htmlspecialchars($product['product_name'] ?? '') ?>">
            <input type="hidden" name="price" value="<?= $product['price'] ?? 0 ?>">
            <input type="hidden" name="image" value="<?= htmlspecialchars($product['image'] ?? 'default.png') ?>">
            <input type="hidden" name="karat" value="<?= htmlspecialchars($product['material'] ?? '') ?>">

            <div class="qty-input">
                <label for="qty">Quantity:</label>
                <input type="number" id="qty" name="qty" value="1" min="1" required>
            </div>

            <div class="button-group">
                <button type="submit" class="add-to-cart-btn" name="action" value="add">
                    <i class="fas fa-shopping-bag"></i>
                    Add to Cart
                </button>
                
                <button type="button" class="proceed-checkout-btn" id="proceedCheckoutBtn">
                    <i class="fas fa-credit-card"></i>
                    Proceed to Checkout
                </button>
            </div>
        </form>
    </div>
</div>


<div class="related-products">
    <h2 class="related-products-title">You May Also Like</h2>
    <div class="luxury-divider"></div>
    <p class="related-products-subtitle">Discover more exquisite pieces from our collection</p>
    
    <div class="products-container">
        <?php
        $relatedStmt = $conn->prepare("SELECT * FROM products WHERE category = ? AND id != ? LIMIT 4");
        $category = $product['category'] ?? '';
        $prod_id = $product['id'] ?? 0;
        $relatedStmt->bind_param("si", $category, $prod_id);
        $relatedStmt->execute();
        $relatedResult = $relatedStmt->get_result();

        while ($related = $relatedResult->fetch_assoc()):
            $relatedImagePath = !empty($related['image'])
                ? "../AdminDashboard/smpAdminwithBE/uploads/" . htmlspecialchars($related['image'])
                : "../Assets/image/placeholder.jpg";
        ?>
            <a href="details.php?id=<?= $related['id'] ?>" class="product-card">
                <div class="card-image-container">
                    <img src="<?= $relatedImagePath ?>" 
                alt="<?= htmlspecialchars($related['product_name'] ?? 'Product') ?>">
                </div>
                <div class="card-content">
                    <div class="category-name"><?= htmlspecialchars(ucfirst($related['category'] ?? '')) ?></div>
                    <h3><?= htmlspecialchars($related['product_name'] ?? 'No Name') ?></h3>
                    <div class="price">₱<?= number_format($related['price'] ?? 0, 2) ?></div>
                    <?php if (!empty($related['description'])): ?>
                        <div class="description">
                            <?= htmlspecialchars(substr($related['description'], 0, 100)) ?>
                            <?= strlen($related['description']) > 100 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        <?php endwhile; 
        
        $relatedStmt->close();
        ?>
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

// ============================================
// SMOOTH SCROLLING FOR ANCHOR LINKS
// ============================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#') {
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

// ============================================
// FADE IN ANIMATION FOR RELATED PRODUCTS
// ============================================
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
// PRODUCT FORM FUNCTIONALITY
// ============================================
const productForm = document.getElementById('productForm');
const qtyInput = document.getElementById('qty');
const proceedBtn = document.getElementById('proceedCheckoutBtn');

// Function to handle quantity validation before any submission
const validateQuantity = (e) => {
    if (!qtyInput.value || parseInt(qtyInput.value) < 1) {
        e.preventDefault();
        alert('Please enter a valid quantity');
        qtyInput.focus();
        return false;
    }
    return true;
}

// Add to Cart submission feedback (optional, for 'action=add')
if (productForm) {
    productForm.addEventListener('submit', function(e) {
        if (!validateQuantity(e)) return;
        
        // Add visual feedback only for 'add to cart' action
        if (e.submitter && e.submitter.value === 'add') {
            const submitBtn = e.submitter;
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }, 1000);
        }
    });
}

// Direct Checkout Logic
if (proceedBtn && productForm && qtyInput) {
    proceedBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Validate quantity
        if (!qtyInput.value || parseInt(qtyInput.value) < 1) {
            alert('Please enter a valid quantity');
            qtyInput.focus();
            return;
        }
        
        // Create a new form for direct checkout
        const checkoutForm = document.createElement('form');
        checkoutForm.method = 'POST';
        checkoutForm.action = 'checkout.php';
        
        // Get all hidden inputs from the product form
        const hiddenInputs = productForm.querySelectorAll('input[type="hidden"]');
        hiddenInputs.forEach(input => {
            const newInput = document.createElement('input');
            newInput.type = 'hidden';
            newInput.name = input.name;
            newInput.value = input.value;
            checkoutForm.appendChild(newInput);
        });
        
        // Add quantity
        const qtyInput2 = document.createElement('input');
        qtyInput2.type = 'hidden';
        qtyInput2.name = 'qty';
        qtyInput2.value = qtyInput.value;
        checkoutForm.appendChild(qtyInput2);
        
        // Add direct checkout flag
        const directCheckout = document.createElement('input');
        directCheckout.type = 'hidden';
        directCheckout.name = 'direct_checkout';
        directCheckout.value = '1';
        checkoutForm.appendChild(directCheckout);
        
        // Append and submit
        document.body.appendChild(checkoutForm);
        checkoutForm.submit();
    });
}

// ============================================
// QUANTITY INPUT CONSTRAINTS
// ============================================
if (qtyInput) {
    qtyInput.addEventListener('input', function() {
        let value = parseInt(this.value);
        if (isNaN(value) || value < 1) {
            this.value = 1;
        }
    });

    qtyInput.addEventListener('blur', function() {
        if (this.value === '') {
            this.value = 1;
        }
    });
}

// ============================================
// IMAGE LAZY LOADING (OPTIONAL ENHANCEMENT)
// ============================================
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.style.opacity = '1';
                imageObserver.unobserve(img);
            }
        });
    });

    document.querySelectorAll('.details-img, .product-img').forEach(img => {
        img.style.opacity = '0';
        img.style.transition = 'opacity 0.3s ease';
        imageObserver.observe(img);
    });
}
</script>

</body>
</html>