<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id']) || !isset($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$user_id = intval($data['user_id']);
$message = trim($data['message']);
$sender_type = 'admin';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO live_chat (user_id, sender_type, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $sender_type, $message);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message_id' => $conn->insert_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>