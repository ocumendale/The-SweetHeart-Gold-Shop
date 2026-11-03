<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
  exit;
}
if (!isset($_GET['id'])) {
    die("Order ID not provided.");
}

$order_id = $_GET['id'];

// Fetch order details
$order_query = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

// Fetch order items
$items_query = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$order_items = $items_query->get_result();

// Fetch order details with customer email
$order_query = $conn->prepare("
    SELECT o.*, u.username as customer_email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.id = ?
");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

// Add function to get status options based on payment method
function getStatusOptions($payment_method) {
    if ($payment_method === 'Store Pickup') {
        return [
            'Pending' => 'Pending',
            'Processing' => 'Processing',
            'Ready for Pickup' => 'Ready for Pickup',
            'Picked Up' => 'Picked Up',
            'Cancelled' => 'Cancelled'
        ];
    } else {
        return [
            'Pending' => 'Pending',
            'Processing' => 'Processing',
            'Shipped' => 'Shipped',
            'Delivered' => 'Delivered',
            'Cancelled' => 'Cancelled'
        ];
    }
}

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['status'])) {
    $new_status = $_POST['status'];
    $update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $update->bind_param("si", $new_status, $order_id);
    $update->execute();

    echo "<script>alert('Order status updated!');window.location.href='view_order.php?id=$order_id';</script>";
}

// ===== FIXED IMAGE PATH =====
$image_src = null;
$debug_info = [];

if (!empty($order['id_image_path'])) {
    // Database stores: uploads/customer_ids/ID_3_1760513793.jpg
    // Remove the 'uploads/' prefix to get: customer_ids/ID_3_1760513793.jpg
    $db_path = $order['id_image_path'];
    $relative_path = str_replace('uploads/', '', $db_path);
    
    // Build paths - go up from AdminDashboard/smpAdminwithBE to Capstone
    // Then into customer/uploads/customer_ids/
    $image_src = '../../customer/uploads/' . $relative_path;
    $server_path = __DIR__ . '/../../customer/uploads/' . $relative_path;
    
    // Debug information
    $debug_info['db_stored'] = $db_path;
    $debug_info['relative_path'] = $relative_path;
    $debug_info['web_path'] = $image_src;
    $debug_info['server_path'] = $server_path;
    $debug_info['file_exists'] = file_exists($server_path) ? 'Yes' : 'No';
    
    // Verify file exists
    if (!file_exists($server_path)) {
        $image_src = null;
    }
}

// Calculate order total
$order_total = 0;
$items_array = [];
while($item = $order_items->fetch_assoc()) {
    $items_array[] = $item;
    $order_total += $item['price'] * $item['quantity'];
}
// ==============================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order #<?= $order_id ?> - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="CSS/Vieworder.css">

    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
    <script type="text/javascript">
        (function() {
            emailjs.init("j16wRtf0Lbq-vxO9r"); // Replace with your actual EmailJS public key
        })();
    </script>
    <style>
        
.logout-btn {
        background-color: #676059;
        color: #F7CB85;
        font-family: 'Playfair Display', serif;
        font-weight: 600;
        padding: 10px 25px;
        border-radius: 5px;
        text-decoration: none;
        border: 2px solid #F7CB85;
        transition: all 0.3s ease;
        display: inline-block;
}
.logout-btn:hover {
        background-color: #F7CB85;
        color: #676059;
        border-color: #676059;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}
    .paypal-transaction-btn:hover {
        transform: scale(1.07);
        transition: transform 0.2s ease;
    }
    </style>
</head>
<body>
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
                <a href="orders.php" class="nav-link active">
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
                    <img src="../icons/salesreport.png" alt="Sales Report" class="nav-icon" />
                    Sales Report
                </a>
            </li>
            <li class="mb-4">
                <a href="activitylog.php" class="nav-link text-white">
                    <img src="../icons/restore.png" alt="Activity Log" class="nav-icon" />
                    Log and Bin
                </a>
            </li>
        </ul>
    </div>

    <!-- Content Area -->
    <div class="content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mt-3 mx-3 mb-4">
            <div>
                <a href="orders.php" class="back-button">
                    ← Back to Orders
                </a>
            </div>

             <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <!-- Page Header -->
        <div class="container-fluid">
            <div class="page-header">
                <h1>Order #<?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?></h1>
                <p>Order details and shipping information</p>
            </div>

            <!-- Order Details Grid -->
            <div class="row g-4 mb-4">
                <!-- Shipping Details -->
                <div class="col-lg-6">
                    <div class="detail-card">
                        <h2>Shipping Details</h2>
                        
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?= htmlspecialchars($order['shipping_name']) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?= htmlspecialchars($order['shipping_address']) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">City:</span>
                            <span class="info-value"><?= htmlspecialchars($order['shipping_city']) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?= htmlspecialchars($order['shipping_phone']) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Uploaded ID:</span>
                            <span class="info-value">
                                <?php if ($image_src): ?>
                                    <span class="id-badge">Verified</span>
                                <?php else: ?>
                                    <span class="no-id">Not uploaded</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($image_src): ?>
                        <div class="id-image-container">
                            <img src="<?= htmlspecialchars($image_src) ?>" 
                                 alt="Customer ID" 
                                 class="id-image"
                                 onclick="openImageModal(this.src)">
                            
                            <!-- Debug Info (remove after fixing) -->
                           <!-- <details>
                                <summary>Debug Info</summary>
                                <pre><?php print_r($debug_info); ?></pre>
                            </details> -->
                        </div>
                        <?php else: ?>
                            <?php if (!empty($debug_info)): ?>
                                <!-- Debug Info -->
                                <details>
                                    <summary>Debug Info - Why image not showing?</summary>
                                    <pre><?php print_r($debug_info); ?></pre>
                                    <p><strong>From Database:</strong> <?= htmlspecialchars($order['id_image_path']) ?></p>
                                </details>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="info-row">
                            <span class="info-label">Facebook Profile:</span>
                            <span class="info-value">
                                <?php if (!empty($order['facebook_profile'])): ?>
                                    <a href="<?= htmlspecialchars($order['facebook_profile']) ?>" 
                                       class="facebook-link" 
                                       target="_blank">View Profile</a>
                                <?php else: ?>
                                    <span class="no-id">Not provided</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Order Info -->
                <div class="col-lg-6">
                    <div class="detail-card">
                        <h2>Order Info</h2>
                        
                        <div class="info-row">
                            <span class="info-label">Order Date:</span>
                            <span class="info-value">
                                <?= date('F d, Y', strtotime($order['order_date'])) ?><br>
                                <small style="color: #999;"><?= date('h:i A', strtotime($order['order_date'])) ?></small>
                            </span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Payment Method:</span>
                            <span class="info-value"><?= htmlspecialchars($order['payment_method']) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '_', $order['status'])) ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </span>
                        </div>

                        <div class="total-price-row">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="info-label">Total Price:</span>
                                <span class="info-value">₱<?= number_format($order['total_price'], 2) ?></span>
                            </div>
                        </div>

                        <form method="POST" class="status-form">
                            <label>Change Status:</label>
                            <select name="status" class="status-select" id="statusSelect">
                                <?php 
                                // Get the appropriate status options for this order's payment method
                                $status_options = getStatusOptions($order['payment_method']);
                                foreach ($status_options as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" 
                                            <?= $order['status'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="action-buttons">
                                <button type="submit" class="btn-update">Update Status</button>
                               <!-- <button type="button" class="btn-print" onclick="window.print()">Print Order</button> -->
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="items-card">
                <h2>Order Items</h2>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th style="text-align: center;">Quantity</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items_array) > 0): ?>
                            <?php foreach ($items_array as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td>₱<?= number_format($item['price'], 2) ?></td>
                                <td style="text-align: center;"><?= htmlspecialchars($item['quantity']) ?></td>
                                <td style="text-align: right;">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px;">No items found for this order</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($items_array) > 0): ?>
                    <tfoot>
                        <tr>
                            <td colspan="3">Order Total:</td>
                            <td>₱<?= number_format($order_total, 2) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Image modal functions
    function openImageModal(src) {
        document.getElementById('imageModal').style.display = 'block';
        document.getElementById('modalImage').src = src;
    }

    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeImageModal();
        }
    });

    // Confirm before updating status with EmailJS notification
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        const currentStatus = "<?= $order['status'] ?>";
        const newStatus = document.getElementById('statusSelect').value;
        const paymentMethod = "<?= htmlspecialchars($order['payment_method']) ?>";
        
        if (newStatus === currentStatus) {
            alert('Status is already set to ' + newStatus);
            return false;
        }
        
        if (!confirm('Are you sure you want to change the order status to "' + newStatus + '"?')) {
            return false;
        }

        // Extract order data for email
        const orderId = "<?= $order_id ?>";
        const customerName = "<?= htmlspecialchars($order['shipping_name']) ?>";
        const customerEmail = "<?= htmlspecialchars($order['customer_email'] ?? '') ?>"; // ✅ Now using the joined email
        const orderAmount = "₱<?= number_format($order['total_price'], 2) ?>";

        // Get product names from order items
        const productNames = [];
        <?php 
        $product_names = [];
        foreach ($items_array as $item) {
            $product_names[] = $item['product_name'] . ' (Qty: ' . $item['quantity'] . ')';
        }
        $product_names_str = implode(', ', $product_names);
        ?>
        const orderName = "<?= addslashes($product_names_str) ?>";

        // Validate email exists
        if (!customerEmail || !customerEmail.includes('@')) {
            alert('Error: Customer email not found. Cannot send notification.');
            return false;
        }

        console.log('Sending email to:', customerEmail);
        console.log('Payment Method:', paymentMethod);
        console.log('New Status:', newStatus);

        // Show loading indicator
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating...';

        // Submit the form via AJAX
        const formData = new FormData(this);
        
        fetch('view_order.php?id=<?= $order_id ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Determine which email template to use
            // Use template_yf0odyr ONLY if payment is Store Pickup AND status is Ready for Pickup
            // Otherwise use template_2ahychk
            let emailTemplate = 'template_2ahychk'; // Default template
            
            if (paymentMethod === 'Store Pickup' && newStatus === 'Ready for Pickup') {
                emailTemplate = 'template_yf0odyr';
            }

            console.log('Using email template:', emailTemplate);

            // Send email notification
            return emailjs.send("service_y594p76", emailTemplate, {
                to_email: customerEmail,
                to_name: customerName,
                customer_name: customerName,
                order_id: orderId,
                order_name: orderName,
                order_status: newStatus,
                order_amount: orderAmount,
                payment_method: paymentMethod
            })
            .then(function(emailResponse) {
                console.log('Email sent successfully!', emailResponse.status, emailResponse.text);
                alert('Order status updated successfully! Notification email sent.');
                window.location.reload();
            })
            .catch(function(emailError) {
                console.error('Email sending failed:', emailError);
                alert('Order status updated, but email notification failed to send.');
                window.location.reload();
            });
        })
        .catch(error => {
            console.error('Error updating order:', error);
            alert('Error updating order status. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
</script>
</body>
</html>