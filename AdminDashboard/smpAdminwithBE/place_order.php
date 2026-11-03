<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? 1; // example fallback
$total_price = $_POST['total_price'];
$shipping_name = $_POST['shipping_name'];
$shipping_address = $_POST['shipping_address'];
$shipping_city = $_POST['shipping_city'];
$shipping_phone = $_POST['shipping_phone'];
$payment_method = $_POST['payment_method'];

// Start transaction
$conn->begin_transaction();

try {
    // 1️⃣ Insert into orders
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, shipping_name, shipping_address, shipping_city, shipping_phone, payment_method, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("idsssss", $user_id, $total_price, $shipping_name, $shipping_address, $shipping_city, $shipping_phone, $payment_method);
    $stmt->execute();
    $order_id = $conn->insert_id; // get the new order id

    // 2️⃣ Insert order items + update product stock
    foreach ($_SESSION['cart'] as $item) {
        $product_id = $item['id'];
        $product_name = $item['product_name'];
        $price = $item['price'];
        $quantity = $item['quantity'];

        // insert into order_items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisdi", $order_id, $product_id, $product_name, $price, $quantity);
        $stmt->execute();

        // subtract from stock
        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $product_id);
        $stmt->execute();
    }

    // 3️⃣ Commit changes
    $conn->commit();

    // clear cart
    unset($_SESSION['cart']);

    echo "<script>alert('Order placed successfully!'); window.location='user/orders.php';</script>";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error placing order: " . $e->getMessage();
}
?>
