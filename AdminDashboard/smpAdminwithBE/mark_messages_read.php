<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

// Mark all messages from this user as read
$stmt = $conn->prepare("UPDATE live_chat SET is_read = 1 WHERE user_id = ? AND sender_type = 'user' AND is_read = 0");
$stmt->bind_param('i', $user_id);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
?>