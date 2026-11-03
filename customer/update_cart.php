<?php
session_start();

if (!isset($_POST['id']) || !isset($_POST['action'])) {
    header("Location: cart.php");
    exit();
}
$_SESSION['cart_notification'] = [
    'type' => 'warning',  // 'success', 'warning', or 'error'
    'title' => 'Cart Limit Reached',
    'message' => 'You can only have 3 items in your cart.'
];

$id = $_POST['id'];
$action = $_POST['action'];
$maxLimit = 3; // Set your maximum limit here

if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $index => &$item) {
        if ($index == $id) {

            // Increase quantity
            if ($action === "increase") {
                if ($item['qty'] < $maxLimit) {
                    $item['qty']++;
                }
            }

            // Decrease quantity
            elseif ($action === "decrease") {
                $item['qty']--;
                if ($item['qty'] <= 0) {
                    unset($_SESSION['cart'][$index]);
                }
            }

            // Manual input (number field)
            elseif (isset($_POST['quantity'])) {
                $new_qty = (int)$_POST['quantity'];

                if ($new_qty <= 0) {
                    unset($_SESSION['cart'][$index]);
                } else {
                    // Apply limit
                    if ($new_qty > $maxLimit) {
                        $new_qty = $maxLimit;
                    }
                    $item['qty'] = $new_qty;
                }
            }

            break;
        }
    }
    unset($item);
}

header("Location: cart.php");
exit();
