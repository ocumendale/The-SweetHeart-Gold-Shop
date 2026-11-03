<?php
session_start();

// Ensure cart session exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get and sanitize POST data
$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$image = trim($_POST['image'] ?? '');
$qty = (int) ($_POST['qty'] ?? 1);

if ($id <= 0 || $qty <= 0) {
    die("Invalid product data.");
}

// Check if product already exists in cart
$found = false;
foreach ($_SESSION['cart'] as &$item) {
    if ((int)$item['id'] === $id) {
        $item['qty'] += $qty;
        $found = true;
        break;
    }
}
unset($item); // Always unset reference after foreach by reference

// If not found, add new item
if (!$found) {
    $_SESSION['cart'][] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'image' => $image,
        'qty' => $qty
    ];
}

// Redirect to cart page
header("Location: cart.php");
exit();
