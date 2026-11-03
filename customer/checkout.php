<?php
ob_start(); // Start output buffering
include 'chatbot.php';
include '../AdminDashboard/smpAdminwithBE/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear buffer
    header("Location: login.php?redirect=checkout");
    exit();
}

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
$user_id = $_SESSION['user_id'];

// Initialize variables
$cartItems = [];
$cart = [];
$total = 0;

$tax_rate = 0.10;
$base_shipping_cost = 50.00;

// Initialize shipping cost FIRST
$shipping_cost = $base_shipping_cost; // Default shipping cost

$is_direct_checkout = false;

// HANDLE DIRECT CHECKOUT FROM PRODUCT DETAILS PAGE
if (isset($_POST['direct_checkout']) && $_POST['direct_checkout'] == '1') {
    $is_direct_checkout = true;
    
    $product = [
        'id' => intval($_POST['id'] ?? 0),
        'name' => $_POST['name'] ?? '',
        'price' => floatval($_POST['price'] ?? 0),
        'image' => $_POST['image'] ?? 'default.png',
        'karat' => $_POST['karat'] ?? '',
        'qty' => intval($_POST['qty'] ?? 1)
    ];
    
    if ($product['id'] > 0 && $product['qty'] > 0) {
        $cartItems[] = $product;
        $cart[] = $product;
        $total = $product['price'] * $product['qty'];
        $empty_cart = false;
    } else {
        $_SESSION['error'] = "Invalid product data";
        header("Location: shop.php");
        exit();
    }
} else {
    // REGULAR CHECKOUT FROM CART
    $cart = $_SESSION['cart'] ?? [];
    
    if (empty($cart)) {
        $empty_cart = true;
    } else {
        $empty_cart = false;
        foreach ($cart as $item) {
            $cartItems[] = $item;
            $total += $item['qty'] * $item['price'];
        }
    }
}

// Fetch user's latest order info for auto-fill
$default_form_data = [
    'shipping_name' => '',
    'shipping_phone' => '',
    'shipping_address' => '',
    'shipping_city' => '',
    'facebook_profile' => '',
    'payment_method' => ''
];

$latest_order_query = $conn->prepare("SELECT shipping_name, shipping_phone, shipping_address, shipping_city, facebook_profile, payment_method 
                                      FROM orders 
                                      WHERE user_id = ? 
                                      ORDER BY order_date DESC 
                                      LIMIT 1");
$latest_order_query->bind_param("i", $user_id);
$latest_order_query->execute();
$latest_order_result = $latest_order_query->get_result();

if ($latest_order_result->num_rows > 0) {
    $latest_order = $latest_order_result->fetch_assoc();
    $default_form_data = [
        'shipping_name' => $latest_order['shipping_name'] ?? '',
        'shipping_phone' => $latest_order['shipping_phone'] ?? '',
        'shipping_address' => $latest_order['shipping_address'] ?? '',
        'shipping_city' => $latest_order['shipping_city'] ?? '',
        'facebook_profile' => $latest_order['facebook_profile'] ?? '',
        'payment_method' => $latest_order['payment_method'] ?? ''
    ];
}
$latest_order_query->close();

// Initialize form data from session or use latest order data
$form_data = $_SESSION['checkout_form_data'] ?? $default_form_data;

// Check if Store Pickup is selected and adjust shipping cost
if (isset($form_data['payment_method']) && $form_data['payment_method'] === 'Store Pickup') {
    $shipping_cost = 0.00;
} else {
    $shipping_cost = $base_shipping_cost;
}

// Calculate totals with correct shipping cost
$tax_amount = $total * $tax_rate;
$grand_total = $total + $shipping_cost + $tax_amount;

// Initialize variables
$order_success = false;
$order_id = null;
$order_data = null;
$error_message = '';

// Handle checkout submission
if (isset($_POST['place_order']) && !empty($cartItems)) {
    // Store form data in session for persistence
    $_SESSION['checkout_form_data'] = [
        'shipping_name' => trim($_POST['shipping_name']),
        'shipping_phone' => trim($_POST['shipping_phone']),
        'shipping_address' => trim($_POST['shipping_address']),
        'shipping_city' => trim($_POST['shipping_city']),
        'facebook_profile' => trim($_POST['facebook_profile']),
        'payment_method' => trim($_POST['payment_method'])
    ];
    
    $form_data = $_SESSION['checkout_form_data'];
    
    // Sanitize form data
    $shipping_name = $conn->real_escape_string($form_data['shipping_name']);
    $shipping_phone = $conn->real_escape_string($form_data['shipping_phone']);
    $shipping_address = $conn->real_escape_string($form_data['shipping_address']);
    $shipping_city = $conn->real_escape_string($form_data['shipping_city']);
    $facebook_profile = $conn->real_escape_string($form_data['facebook_profile']);
    $payment_method = $conn->real_escape_string($form_data['payment_method']);

    // 🔒 RE-CALCULATE SHIPPING & TOTALS (SECURITY)
    $is_pickup = ($payment_method === 'Store Pickup');
    $shipping_cost = $is_pickup ? 0.00 : $base_shipping_cost;

    $tax_amount = $total * $tax_rate;
    $grand_total = $total + $shipping_cost + $tax_amount;

    // Handle ID upload
    $id_image_path = null;
    $id_upload_error = false;
    
    if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/customer_ids/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $error_message = "Cannot create upload directory. Please contact administrator.";
            }
        }
        
        if (empty($error_message)) {
            $file_extension = strtolower(pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Check file size (5MB max)
                if ($_FILES['id_image']['size'] > 5 * 1024 * 1024) {
                    $error_message = "File size must be less than 5MB.";
                } else {
                    $new_filename = 'ID_' . $user_id . '_' . time() . '.' . $file_extension;
                    $target_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['id_image']['tmp_name'], $target_path)) {
                        $id_image_path = $target_path;
                    } else {
                        $error_message = "Error uploading ID file. Please try again.";
                    }
                }
            } else {
                $error_message = "Invalid ID file type. Only JPG, PNG, and PDF are allowed.";
            }
        }
    } else {
        // Check what the actual error is
        $upload_error = $_FILES['id_image']['error'] ?? 'No file uploaded';
        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            $error_message = "ID verification document is required.";
        } else {
            $error_message = "Error uploading file. Please try again. (Error code: " . $upload_error . ")";
        }
    }

    // Only proceed if no errors so far
    if (empty($error_message)) {
        // Check if any product in cart is out of stock
        $out_of_stock_items = [];

        foreach ($cartItems as $item) {
            $product_id = $item['id'];
            $quantity_requested = $item['qty'];

            $check_stock = $conn->prepare("SELECT stock, product_name FROM products WHERE id = ?");
            $check_stock->bind_param("i", $product_id);
            $check_stock->execute();
            $result = $check_stock->get_result();
            $product = $result->fetch_assoc();
            $check_stock->close();

            if (!$product) {
                $out_of_stock_items[] = "Unknown product (ID: $product_id)";
            } elseif ($product['stock'] <= 0) {
                $out_of_stock_items[] = $product['product_name'] . " (Out of stock)";
            } elseif ($quantity_requested > $product['stock']) {
                $out_of_stock_items[] = $product['product_name'] . " (Only {$product['stock']} left)";
            }
        }

        // Stop checkout if there are stock issues
        if (!empty($out_of_stock_items)) {
            $error_message = "Checkout failed. Some items are out of stock:<br>" . implode("<br>", $out_of_stock_items);
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Insert order
                $stmt = $conn->prepare("INSERT INTO orders 
                    (user_id, total_price, shipping_name, shipping_address, shipping_city, shipping_phone, id_image_path, facebook_profile, payment_method, order_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("idsssssss", $user_id, $grand_total, $shipping_name, $shipping_address, $shipping_city, $shipping_phone, $id_image_path, $facebook_profile, $payment_method);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert order: " . $stmt->error);
                }
                
                $order_id = $stmt->insert_id;
                $stmt->close();

                // Insert order items + update stock
                $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)");
                $stmt_update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

                foreach ($cartItems as $item) {
                    $product_id = $item['id'];
                    $product_name = $item['name'];
                    $quantity = $item['qty'];
                    $price = $item['price'];

                    // Insert order item
                    $stmt_item->bind_param("iisdi", $order_id, $product_id, $product_name, $price, $quantity);
                    if (!$stmt_item->execute()) {
                        throw new Exception("Failed to insert order item: " . $stmt_item->error);
                    }

                    // Update stock
                    $stmt_update->bind_param("ii", $quantity, $product_id);
                    if (!$stmt_update->execute()) {
                        throw new Exception("Failed to update stock: " . $stmt_update->error);
                    }
                }

                $stmt_item->close();
                $stmt_update->close();

                // Commit all changes if successful
                $conn->commit();

                // Clear cart AND form data on success (only if not direct checkout)
                if (!$is_direct_checkout) {
                    unset($_SESSION['cart']);
                }
                unset($_SESSION['checkout_form_data']);

                // Prepare order data for receipt
                $order_data = [
                    'items' => $cartItems,
                    'shipping_name' => $shipping_name,
                    'shipping_phone' => $shipping_phone,
                    'shipping_address' => $shipping_address . ', ' . $shipping_city,
                    'id_verified' => true,
                    'facebook_profile' => $facebook_profile,
                    'payment_method' => $payment_method,
                    'total' => $total,
                    'shipping_cost' => $shipping_cost,
                    'tax_amount' => $tax_amount,
                    'grand_total' => $grand_total,
                    'order_date' => date('Y-m-d H:i:s')
                ];

                $order_success = true;
                $success_message = "✅ Order placed successfully!";

            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error_message = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SweetHeart Gold Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&family=Qwigley&family=Playfair+Display:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Checkout.css">
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

    <div class="loading-overlay" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-icon">
                <i class="fas fa-gem gem-icon"></i>
                <div class="loading-ring"></div>
            </div>
            
            <div class="loading-text">
                <h2 class="loading-title">Processing Your Order</h2>
                <p class="loading-subtitle">Verifying your information securely...</p>
            </div>
            
            <div class="loading-dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    </div>

    <div class="checkout-container" id="checkoutContent" style="<?php echo $order_success ? 'display: none;' : 'display: block;'; ?>">
        <div class="checkout-header fade-in">
            <h1 class="checkout-title">Checkout</h1>
            <div class="luxury-divider"></div>
            <p class="checkout-subtitle">Complete your luxury purchase</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <?php if ($empty_cart): ?>
            <div class="empty-cart fade-in">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your Cart is Empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="back-link">
                    <i class="fas fa-shopping-bag"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="checkout-content fade-in">
                <div class="checkout-form">
                    <form action="" method="post" id="checkoutForm" enctype="multipart/form-data">
                        <?php if ($is_direct_checkout): ?>
                            <input type="hidden" name="direct_checkout" value="1">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($_POST['id'] ?? '') ?>">
                            <input type="hidden" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            <input type="hidden" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                            <input type="hidden" name="image" value="<?= htmlspecialchars($_POST['image'] ?? '') ?>">
                            <input type="hidden" name="karat" value="<?= htmlspecialchars($_POST['karat'] ?? '') ?>">
                            <input type="hidden" name="qty" value="<?= htmlspecialchars($_POST['qty'] ?? '') ?>">
                        <?php endif; ?>
                        
                        <div class="section-title">
                            <i class="fas fa-shipping-fast"></i>
                            Shipping Information
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_name">Full Name</label>
                                <input type="text" name="shipping_name" id="shipping_name" 
                                       value="<?= htmlspecialchars($form_data['shipping_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="shipping_phone">Phone Number</label>
                                <input type="tel" name="shipping_phone" id="shipping_phone" 
                                       value="<?= htmlspecialchars($form_data['shipping_phone']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="shipping_address">Street Address / House No.</label>
                            <textarea name="shipping_address" id="shipping_address" required 
                                      placeholder="Enter your complete street address and house number"><?= htmlspecialchars($form_data['shipping_address']) ?></textarea>
                        </div>
            
                        <div class="form-group">
                            <label for="shipping_city">City / Province</label>
                            <input type="text" name="shipping_city" id="shipping_city" 
                                   value="<?= htmlspecialchars($form_data['shipping_city']) ?>" required>
                        </div>

                        <div class="section-title" style="margin-top: 2rem;">
                            <i class="fas fa-shield-alt"></i>
                            Identity Verification - Fraud Protection
                        </div>

                        <div class="verification-notice">
                            <i class="fas fa-info-circle"></i>
                            <strong>Why we need this:</strong> To protect both you and our business from fraud, we require identity verification for all luxury jewelry purchases. Your information is stored securely and used only for order verification.
                        </div>

                        <div class="form-group">
                            <label for="id_image">
                                <i class="fas fa-id-card"></i> Upload Government-Issued ID
                                <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                            </label>
                            
                            <div class="id-upload-container" id="uploadContainer">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                
                                <div class="upload-text">
                                    <strong>Upload Your Valid ID</strong>
                                    Click below to select your ID photo
                                </div>
                                
                                <input type="file" name="id_image" id="id_image" accept="image/jpeg,image/jpg,image/png,application/pdf" required>
                                <label for="id_image" class="upload-btn">
                                    <i class="fas fa-file-upload"></i> Choose File
                                </label>

                                <div class="upload-requirements">
                                    <strong style="color: var(--color-gold); display: block; margin-bottom: 0.5rem;">Accepted Documents:</strong>
                                    <ul>
                                        <li><i class="fas fa-check-circle"></i> Driver's License</li>
                                        <li><i class="fas fa-check-circle"></i> National ID / Postal ID</li>
                                        <li><i class="fas fa-check-circle"></i> Passport</li>
                                        <li><i class="fas fa-check-circle"></i> Any Government-Issued ID</li>
                                    </ul>
                                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.1);">
                                        <strong>Requirements:</strong> JPG, PNG, or PDF &bullet; Max 5MB &bullet; Clear and readable
                                    </div>
                                </div>

                                <div class="security-badge">
                                    <i class="fas fa-lock"></i>
                                    Your data is encrypted and secure
                                </div>
                            </div>

                            <div class="file-preview" id="filePreview">
                                <div class="preview-content">
                                    <div class="preview-info">
                                        <div class="preview-icon">
                                            <i class="fas fa-file-image"></i>
                                        </div>
                                        <div class="preview-details">
                                            <div class="preview-name" id="fileName"></div>
                                            <div class="preview-size" id="fileSize"></div>
                                        </div>
                                    </div>
                                    <button type="button" class="remove-file" onclick="removeFile()">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="facebook_profile">
                                <i class="fab fa-facebook"></i> Facebook Profile Link
                                <span style="color: #dc3545; margin-left: 0.25rem;">*</span>
                            </label>
                            <input type="url" name="facebook_profile" id="facebook_profile" 
                                   value="<?= htmlspecialchars($form_data['facebook_profile']) ?>" 
                                   required placeholder="https://facebook.com/yourprofile">
                            <small style="color: #999; display: block; margin-top: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Additional verification to ensure secure transactions
                            </small>
                        </div>

                        <div class="section-title" style="margin-top: 2rem;">
                            <i class="fas fa-credit-card"></i>
                            Payment Method
                        </div>

                        <div class="payment-methods">
                            <div class="payment-option <?= $form_data['payment_method'] === 'Cash on Delivery' ? 'selected' : '' ?>" 
                                 onclick="selectPayment('cash')">
                                <input type="radio" name="payment_method" value="Cash on Delivery" id="cash" 
                                       <?= $form_data['payment_method'] === 'Cash on Delivery' ? 'checked' : '' ?> required>
                                <i class="fas fa-money-bills"></i>
                                <div>Cash on Delivery</div>
                            </div>

                            <div class="payment-option <?= $form_data['payment_method'] === 'Store Pickup' ? 'selected' : '' ?>" 
                                onclick="selectPayment('pickup')">
                                <input type="radio" name="payment_method" value="Store Pickup" id="pickup" 
                                    <?= $form_data['payment_method'] === 'Store Pickup' ? 'checked' : '' ?> required>
                                <i class="fas fa-store"></i>
                                <div>Store Pickup</div>
                            </div>
        
                            <div class="payment-option <?= $form_data['payment_method'] === 'paypal' ? 'selected' : '' ?>" 
                                 onclick="selectPayment('paypal')">
                                <input type="radio" name="payment_method" value="paypal" id="paypal" 
                                       <?= $form_data['payment_method'] === 'paypal' ? 'checked' : '' ?> required>
                                <i class="fab fa-paypal"></i>
                                <div>PayPal</div>
                            </div>
                            <div class="payment-option" id="paypalOption" style="display:none;" onclick="selectPayment('Paypal')">
                                <input type="radio" name="payment_method" value="Paypal" id="Paypal" required>
                                <i class="fab fa-paypal"></i>
                                <div>Paypal</div>
                            </div>
                        </div>

                        <!-- Terms Agreement Section - RIGHT BEFORE CHECKOUT BUTTON -->
                        <div class="terms-agreement-section">
                            <div class="terms-checkbox-container">
                                <input type="checkbox" name="terms_agreement" id="termsAgreement" required>
                                <label for="termsAgreement" class="terms-label">
                                    I have read and agree to the 
                                    <a href="#" id="openTermsModal">Terms and Conditions</a> 
                                    of SweetHeart Gold Shop
                                </label>
                            </div>
                            <div class="terms-error" id="termsError">
                                <i class="fas fa-exclamation-circle"></i>
                                You must agree to the Terms and Conditions to proceed
                            </div>
                        </div>

                        
                    </form>
                </div>

                <?php
                // Recompute shipping and totals based on selected payment method
                $is_pickup = isset($form_data['payment_method']) && $form_data['payment_method'] === 'Store Pickup';
                $shipping_cost = $is_pickup ? 0.00 : $base_shipping_cost;
                $tax_amount = $total * $tax_rate;
                $grand_total = $total + $shipping_cost + $tax_amount;
                ?>

            <div class="invoice-container" id="invoiceContainer">
                <!-- Invoice Header -->
                <div class="invoice-header">
                    <div class="invoice-logo">SH</div>
                    <h2 class="invoice-title">INVOICE</h2>
                    <p class="invoice-subtitle">SweetHeart Gold Shop</p>
                    <div class="invoice-date">Date: <?= date('F d, Y') ?></div>
                </div>
                
                <!-- Invoice Information -->
                <div class="invoice-info-section">
                    <div class="invoice-info-block">
                        <h3>Bill To:</h3>
                        <p class="customer-name"><?= htmlspecialchars($username) ?></p>
                        <p class="customer-address"><?= htmlspecialchars($form_data['shipping_address']) ?>, <?= htmlspecialchars($form_data['shipping_city']) ?></p>
                    </div>
                    <div class="invoice-info-block">
                        <h3>Order Info:</h3>
                        <p>Payment Method: <?= htmlspecialchars($form_data['payment_method']) ?: 'N/A' ?></p>
                        <p class="invoice-status">Status: <span class="status-pending">Pending Payment</span></p>
                    </div>
                </div>

                <!-- Invoice Items Table -->
                <div class="invoice-items-section">
                    <div class="invoice-table-header">
                        <div class="col-product">Product</div>
                        <div class="col-qty">Qty</div>
                        <div class="col-price">Price</div>
                        <div class="col-total">Total</div>
                    </div>
                    
                    <?php foreach ($cart as $item): 
                        $item_total = $item['qty'] * $item['price'];
                    ?>
                    <div class="invoice-table-row">
                        <div class="col-product">
                            <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                        </div>
                        <div class="col-qty"><?= $item['qty'] ?></div>
                        <div class="col-price">₱<?= number_format($item['price'], 2) ?></div>
                        <div class="col-total">₱<?= number_format($item_total, 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Invoice Totals -->
                <div class="invoice-totals-section">
                    <div class="invoice-total-row">
                        <span>Subtotal:</span>
                        <span>₱<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="invoice-total-row">
                        <span>Shipping Fee:</span>
                        <span>₱<?= number_format($shipping_cost, 2) ?></span>
                    </div>
                    <div class="invoice-total-row">
                        <span>Tax (<?= $tax_rate * 100 ?>%):</span>
                        <span>₱<?= number_format($tax_amount, 2) ?></span>
                    </div>
                    <div class="invoice-total-row invoice-grand-total">
                        <span>GRAND TOTAL:</span>
                        <span>₱<?= number_format($grand_total, 2) ?></span>
                    </div>
                </div>
                
                <!-- Invoice Notes -->
                <div class="invoice-notes-section">
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>Please verify all information before placing order</li>
                        <li>ID verification required for all purchases</li>
                        <li>Final invoice issued after payment confirmation</li>
                    </ul>
                </div>
                
                <!-- Invoice Footer -->
                <div class="invoice-footer-section">
                    <p>Thank you for choosing SweetHeart Gold Shop</p>
                </div>
                
                <!-- Action Buttons -->
                <div class="invoice-actions">
                    <button type="button" class="btn-print-invoice" onclick="printInvoice()">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                    <button type="submit" name="place_order" form="checkoutForm" class="btn-place-order">
                        <i class="fas fa-lock"></i> Place Order
                    </button>
                </div>
            </div>

            </div>
        <?php endif; ?>
    </div>

    <div class="receipt-container" id="receiptContainer" style="<?php echo $order_success ? 'display: flex;' : 'display: none;'; ?>">
        <?php if ($order_success && $order_data && $order_id): ?>
        <div class="receipt-card">
            <div class="receipt-header">
                <div class="success-icon-container">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <h1 class="receipt-title">Order Placed Successfully!</h1>
                <p class="receipt-subtitle">Thank you for your purchase. Your identity has been verified.</p>
            </div>

            <div class="receipt-body">
                <div class="order-details-box">
                    <div class="detail-row">
                        <span class="detail-label">ID VERIFIED:</span>
                        <span class="detail-value" style="color: #28a745;">
                            <i class="fas fa-check-circle"></i> Yes - Document Uploaded
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">FACEBOOK:</span>
                        <span class="detail-value">
                            <a href="<?= htmlspecialchars($order_data['facebook_profile']) ?>" target="_blank">
                                <i class="fab fa-facebook"></i> View Profile
                            </a>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">PAYMENT:</span>
                        <span class="detail-value"><?= ucfirst($order_data['payment_method']) ?></span>
                    </div>
                </div>

                <div class="items-section">
                    <h2 class="items-title">Items Ordered:</h2>
                    
                    <?php foreach ($order_data['items'] as $item): 
                        $item_total = $item['qty'] * $item['price'];
                    ?>
                    <div class="item-row">
                        <div class="item-info">
                            <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="item-details">Qty: <?= $item['qty'] ?> &times; ₱<?= number_format($item['price'], 2) ?></span>
                        </div>
                        <span class="item-price">₱<?= number_format($item_total, 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="totals-section">
                    <div class="total-line">
                        <span>Subtotal:</span>
                        <span>₱<?= number_format($order_data['total'], 2) ?></span>
                    </div>
                    <div class="total-line">
                        <span>Shipping:</span>
                        <span>₱<?= number_format($order_data['shipping_cost'], 2) ?></span>
                    </div>
                    <div class="total-line">
                        <span>Tax (<?= $tax_rate * 100 ?>%):</span>
                        <span>₱<?= number_format($order_data['tax_amount'], 2) ?></span>
                    </div>
                    <div class="grand-total-line">
                        <span>Grand Total:</span>
                        <span>₱<?= number_format($order_data['grand_total'], 2) ?></span>
                    </div>
                </div>

                <div class="receipt-actions">
                    <a href="shop.php" class="order-again-btn">
                        <i class="fas fa-redo-alt"></i> Order Again
                    </a>
                    <a href="orders.php" class="view-history-btn">
                        <i class="fas fa-history"></i> View Order History
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="terms-modal">
        <div class="terms-modal-content">
            <div class="terms-modal-header">
                <h2>Terms and Conditions</h2>
                <button class="terms-close" id="closeTermsModal" aria-label="Close">&times;</button>
            </div>
            <div class="terms-modal-body">
              
                <p>Welcome to <strong>SweetHeart Gold Shop</strong>. By placing an order through our website, you agree to be bound by the following terms and conditions. Please read them carefully before proceeding with your purchase.</p>

                <h3>1. Identity Verification</h3>
                <ul>
                    <li>All customers are required to provide a valid government-issued ID for identity verification purposes</li>
                    <li>Your ID will be securely stored and used solely for order verification and fraud prevention</li>
                    <li>We reserve the right to cancel orders if proper identification cannot be verified</li>
                    <li>Providing false identification information is strictly prohibited and may result in legal action</li>
                </ul>

                <h3>2. Order Acceptance</h3>
                <ul>
                    <li>All orders are subject to acceptance and product availability</li>
                    <li>We reserve the right to refuse or cancel any order for any reason</li>
                    <li>Prices are subject to change without notice</li>
                    <li>Orders are not considered confirmed until you receive an order confirmation</li>
                </ul>

                <h3>3. Payment Terms</h3>
                <ul>
                    <li><strong>Cash on Delivery:</strong> Payment must be made in full upon delivery of the product</li>
                    <li><strong>PayPal:</strong> Payment must be completed before order processing begins</li>
                    <li>All prices are in Philippine Peso (₱) and include applicable taxes</li>
                    <li>Shipping fees are non-refundable unless the order is canceled by us</li>
                </ul>

                <h3>4. Shipping and Delivery</h3>
                <ul>
                    <li>Delivery times are estimates and not guaranteed</li>
                    <li>You are responsible for providing accurate shipping information</li>
                    <li>Risk of loss passes to you upon delivery</li>
                    <li>We are not responsible for delays caused by courier services or force majeure events</li>
                </ul>

                <h3>5. Returns and Refunds</h3>
                <ul>
                    <li>Returns are accepted within 7 days of delivery for unused, undamaged items in original packaging</li>
                    <li>Custom or personalized items cannot be returned unless defective</li>
                    <li>Refunds will be processed within 14 business days after receiving the returned item</li>
                    <li>Return shipping costs are the responsibility of the customer unless the item is defective</li>
                </ul>

                <h3>6. Product Quality</h3>
                <ul>
                    <li>All gold jewelry is guaranteed to be authentic and of the stated karat purity</li>
                    <li>Product images are for reference only; actual items may vary slightly in appearance</li>
                    <li>We inspect all items before shipping to ensure quality standards</li>
                </ul>

                <h3>7. Privacy and Data Protection</h3>
                <ul>
                    <li>Your personal information will be handled in accordance with our Privacy Policy</li>
                    <li>We will never share your information with third parties without your consent</li>
                    <li>Your ID and personal data are encrypted and stored securely</li>
                </ul>

                <h3>8. Fraud Prevention</h3>
                <ul>
                    <li>Orders showing suspicious activity may be flagged and investigated</li>
                    <li>We reserve the right to request additional verification for high-value purchases</li>
                    <li>Fraudulent orders will be reported to appropriate authorities</li>
                </ul>

                <h3>9. Limitation of Liability</h3>
                <ul>
                    <li>We are not liable for any indirect, incidental, or consequential damages</li>
                    <li>Our total liability shall not exceed the purchase price of the order</li>
                    <li>We are not responsible for losses due to circumstances beyond our control</li>
                </ul>

                <h3>10. Customer Responsibilities</h3>
                <ul>
                    <li>You must be 18 years or older to make a purchase</li>
                    <li>You are responsible for maintaining the confidentiality of your account</li>
                    <li>You agree to provide accurate and truthful information</li>
                    <li>You agree not to use our services for any unlawful purpose</li>
                </ul>

                <h3>11. Modifications to Terms</h3>
                <p>We reserve the right to modify these Terms and Conditions at any time. Changes will be effective immediately upon posting to our website. Your continued use of our services after changes are posted constitutes acceptance of the modified terms.</p>

                <h3>12. Contact Information</h3>
                <p>If you have any questions about these Terms and Conditions, please contact us:<br>
                📧 Facebook: <a href="https://www.facebook.com/SweetheartGoldShopv2" target="_blank" rel="noopener" style="color: #d4af37;">SweetHeart Gold Shop</a></p>

                <p style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(212, 175, 55, 0.3);">
                    By checking the agreement box and placing your order, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.
                </p>
            </div>
        </div>
    </div>

<script>
    // File upload handling
    const fileInput = document.getElementById('id_image');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadContainer = document.getElementById('uploadContainer');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    fileInput.value = '';
                    return;
                }

                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('active');
                
                uploadContainer.style.borderColor = '#28a745';
                uploadContainer.style.background = 'linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05))';
            }
        });
    }

    function removeFile() {
        if (fileInput) fileInput.value = '';
        if (filePreview) filePreview.classList.remove('active');
        if (uploadContainer) {
            uploadContainer.style.borderColor = '#d4af37';
            uploadContainer.style.background = 'linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(212, 175, 55, 0.05))';
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

// Modify your existing selectPayment function
function selectPayment(method) {
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    const radio = event.currentTarget.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
        // Update shipping cost when payment method changes
        updateShippingCost(radio.value);
    }
}

    // Terms and Conditions Modal
    const termsModal = document.getElementById('termsModal');
    const openTermsBtn = document.getElementById('openTermsModal');
    const closeTermsBtn = document.getElementById('closeTermsModal');
    const termsCheckbox = document.getElementById('termsAgreement');
    const termsError = document.getElementById('termsError');

    // Open modal
    if (openTermsBtn) {
        openTermsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            termsModal.style.display = 'block';
            document.body.classList.add('terms-modal-open');
        });
    }

    // Close modal
    if (closeTermsBtn) {
        closeTermsBtn.addEventListener('click', function() {
            termsModal.style.display = 'none';
            document.body.classList.remove('terms-modal-open');
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === termsModal) {
            termsModal.style.display = 'none';
            document.body.classList.remove('terms-modal-open');
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && termsModal && termsModal.style.display === 'block') {
            termsModal.style.display = 'none';
            document.body.classList.remove('terms-modal-open');
        }
    });

    // Remove error when checkbox is checked
    if (termsCheckbox) {
        termsCheckbox.addEventListener('change', function() {
            if (this.checked) {
                termsError.classList.remove('show');
            }
        });
    }

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
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
        
        document.addEventListener('click', function(e) {
            if (!profileIcon.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
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
    // Initialize shipping cost on page load
    document.addEventListener('DOMContentLoaded', function() {
        const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
        if (selectedPayment) {
            updateShippingCost(selectedPayment.value);
        }
    });
    // Close sidebar on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebarOverlay && sidebarOverlay.classList.contains('active')) {
            closeSidebar();
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        const checkoutForm = document.getElementById("checkoutForm");
        const loadingScreen = document.getElementById("loadingScreen");
        const receiptContainer = document.getElementById("receiptContainer");
        const checkoutContent = document.getElementById("checkoutContent");
        window.paypal_payment_status = 'pending';

        // Check if PayPal payment was successful
        const checkPaypalStatus = setInterval(() => {
            if (window.paypal_payment_status === 'success') {
                clearInterval(checkPaypalStatus);

                const paypalOption = document.getElementById('paypalOption');
                if (paypalOption) {
                    paypalOption.click();
                }

                window.paypal_payment_status = null;

                const checkoutButton = document.querySelector('.checkout-button');
                if (checkoutButton) {
                    checkoutButton.click();
                }
            }
        }, 500);

        function validateForm() {
            let isValid = true;
            
            document.querySelectorAll('#checkoutForm [required]').forEach(field => {
                if (field.type !== 'file' && field.type !== 'radio' && field.type !== 'checkbox') {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#d4af37';
                    }
                }
            });

            const fileInput = document.getElementById('id_image');
            const uploadContainer = document.getElementById('uploadContainer');
            if (fileInput && (!fileInput.files || fileInput.files.length === 0)) {
                uploadContainer.style.borderColor = '#dc3545';
                isValid = false;
            } else if (fileInput) {
                uploadContainer.style.borderColor = '#28a745';
            }

            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            const paymentSelected = Array.from(paymentRadios).some(radio => radio.checked);
            if (!paymentSelected) {
                isValid = false;
            }

            // Check terms agreement
            const termsCheckbox = document.getElementById('termsAgreement');
            const termsError = document.getElementById('termsError');
            
            if (termsCheckbox && !termsCheckbox.checked) {
                if (termsError) {
                    termsError.classList.add('show');
                }
                // Scroll to terms section
                document.querySelector('.terms-agreement-section')?.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                isValid = false;
            }
            
            return isValid;
        }

        if (checkoutForm) {
            checkoutForm.addEventListener("submit", function (event) {
                if (!validateForm()) {
                    event.preventDefault();
                    alert('Please fill in all required fields, upload your ID, and agree to the Terms and Conditions.');
                    return false;
                }

                const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;

                if (paymentMethod === 'paypal') {
                    event.preventDefault();
                    openPopup();
                    return false;
                }

                if (checkoutContent) checkoutContent.style.display = 'none';
                if (loadingScreen) {
                    loadingScreen.style.display = 'flex';
                    loadingScreen.classList.add("active");
                    
                    const progressFill = document.querySelector('.progress-fill');
                    if (progressFill) {
                        progressFill.style.animation = 'progress 2000ms linear forwards';
                    }
                }
            });
        }

        <?php if ($order_success): ?>
            if (checkoutContent) checkoutContent.style.display = 'none';
            if (receiptContainer) receiptContainer.style.display = 'none';
            
            if (loadingScreen) {
                loadingScreen.style.display = 'flex';
                loadingScreen.classList.add("active");
                
                const progressFill = document.querySelector('.progress-fill');
                if (progressFill) {
                    progressFill.style.animation = 'progress 2000ms linear forwards';
                }

                setTimeout(() => {
                    loadingScreen.classList.remove("active");
                    
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                        
                        if (receiptContainer) {
                            receiptContainer.style.display = 'flex';
                            receiptContainer.classList.add("show");
                        }
                        
                        window.scrollTo({top: 0, behavior: 'smooth'});
                    }, 300);
                }, 2500);
            }
        <?php endif; ?>
    });

    function openPopup() {
        const width = 500;
        const height = 500;

        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 1);

        const grandTotalPHP = <?= $grand_total ?>;

        window.open(
            "../Payments/payment.php?total_php=" + grandTotalPHP,
            "PopupWindow",
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes`
        );
    }

    function printInvoice() {
        const originalTitle = document.title;
        document.title = 'Invoice - SweetHeart Gold Shop';
        window.print();
        document.title = originalTitle;
    }

    // Update shipping/tax/grand total values in the invoice area
    function updateShippingCost(paymentMethod) {
        const totalValueSpans = document.querySelectorAll('.invoice-totals-section .invoice-total-row span:last-child');
        const subtotal = <?= $total ?>;
        const taxRate = <?= $tax_rate ?>;
        const baseShipping = <?= $base_shipping_cost ?>;

        const shippingCost = paymentMethod === 'Store Pickup' ? 0 : baseShipping;
        const taxAmount = subtotal * taxRate;
        const grandTotal = subtotal + shippingCost + taxAmount;

        if (totalValueSpans && totalValueSpans.length >= 4) {
            totalValueSpans[0].textContent = '₱' + subtotal.toFixed(2);
            totalValueSpans[1].textContent = '₱' + shippingCost.toFixed(2);
            totalValueSpans[2].textContent = '₱' + taxAmount.toFixed(2);
            totalValueSpans[3].textContent = '₱' + grandTotal.toFixed(2);
        }
    }
</script>

</body>
</html>
