<?php
// admin/update_order_status.php
session_start();
header('Content-Type: application/json');
include 'db.php';

// You may want to check admin authentication here
// if (empty($_SESSION['is_admin'])) { echo json_encode(['success'=>false,'message'=>'Not allowed']); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$status   = isset($data['status']) ? trim($data['status']) : '';

// UPDATE THIS LINE - Add all the new status values
$allowed = ['Pending', 'Processing', 'Ready for Pickup', 'Picked Up', 'Shipped', 'Delivered', 'Cancelled'];

if ($order_id <= 0 || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$conn->begin_transaction();
try {
    // Update orders table
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $order_id);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    // Get user_id from orders table
    $stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $user_id = $row['user_id'] ?? null;

    // Create notification for user (if applicable)
    if ($user_id) {
        $message = "Your order #{$order_id} status has been updated to {$status}.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param('is', $user_id, $message);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();
    }

    // Log activity (no user_id column)
    $action = 'Update Order Status';
    $description = "Order #{$order_id} status changed to {$status}" . ($user_id ? " (user_id={$user_id})" : "");

    $stmt = $conn->prepare("INSERT INTO activity_log (action, description) VALUES (?, ?)");
    $stmt->bind_param('ss', $action, $description);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('Order status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>