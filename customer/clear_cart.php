<?php
session_start();

// Clear the cart session
unset($_SESSION['cart']);

echo "Cart cleared successfully.";
?>
