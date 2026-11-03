<?php
session_start();
include '../AdminDashboard/smpAdminwithBE/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$after_id = isset($_GET['after_id']) ? intval($_GET['after_id']) : 0;

// Check if chat is still active
$check_active = $conn->prepare("SELECT COUNT(*) as active_count FROM live_chat WHERE user_id = ? AND is_active = 1");
$check_active->bind_param('i', $user_id);
$check_active->execute();
$result = $check_active->get_result()->fetch_assoc();

// If no active chats, return chat_ended flag
if ($result['active_count'] == 0) {
    echo json_encode(['success' => true, 'messages' => [], 'chat_ended' => true]);
    exit;
}

// Get messages
if ($after_id > 0) {
    $stmt = $conn->prepare("SELECT id, message, sender_type, created_at FROM live_chat WHERE user_id = ? AND id > ? AND is_active = 1 ORDER BY created_at ASC");
    $stmt->bind_param('ii', $user_id, $after_id);
} else {
    $stmt = $conn->prepare("SELECT id, message, sender_type, created_at FROM live_chat WHERE user_id = ? AND is_active = 1 ORDER BY created_at ASC");
    $stmt->bind_param('i', $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => $messages, 'chat_ended' => false]);
?>