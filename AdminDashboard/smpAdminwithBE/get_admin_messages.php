<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

// 🧹 AUTO-CLEANUP: Delete inactive chats older than 24 hours
try {
    $cleanup_stmt = $conn->prepare("DELETE FROM live_chat WHERE is_active = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
} catch (Exception $e) {
    // Silent fail - don't interrupt main functionality
    error_log("Cleanup error: " . $e->getMessage());
}

// Get user_id from query parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$after_id = isset($_GET['after_id']) ? intval($_GET['after_id']) : 0;

if ($user_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

try {
    if ($after_id > 0) {
        // Get only new messages after the specified ID
        $stmt = $conn->prepare("SELECT id, sender_type, message, is_read, created_at 
                               FROM live_chat 
                               WHERE user_id = ? AND id > ? AND is_active = 1
                               ORDER BY created_at ASC");
        $stmt->bind_param("ii", $user_id, $after_id);
    } else {
        // Get all messages for this user (only active chats)
        $stmt = $conn->prepare("SELECT id, sender_type, message, is_read, created_at 
                               FROM live_chat 
                               WHERE user_id = ? AND is_active = 1
                               ORDER BY created_at ASC");
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    
    // Send response FIRST (so admin sees unread messages)
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
    // THEN mark user messages as read (only on initial load)
    if (!empty($messages) && $after_id === 0) {
        $unread_ids = [];
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'user' && $msg['is_read'] == 0) {
                $unread_ids[] = $msg['id'];
            }
        }
        
        if (!empty($unread_ids)) {
            $placeholders = str_repeat('?,', count($unread_ids) - 1) . '?';
            $update_stmt = $conn->prepare("UPDATE live_chat SET is_read = 1 WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($unread_ids));
            $update_stmt->bind_param($types, ...$unread_ids);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>