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

// Start transaction
$conn->begin_transaction();

try {
    // Mark chat as ended/archived
    $stmt = $conn->prepare("UPDATE live_chat SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    
    // Insert a system message that admin ended the chat
    $stmt2 = $conn->prepare("INSERT INTO live_chat (user_id, message, sender_type, is_active, created_at) VALUES (?, 'Admin has ended this chat session. You are now connected to the AI chatbot.', 'admin', 0, NOW())");
    $stmt2->bind_param('i', $user_id);
    $stmt2->execute();
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Chat ended successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Failed to end chat: ' . $e->getMessage()]);
}
?>