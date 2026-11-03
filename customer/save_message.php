<?php
session_start();
header('Content-Type: application/json');

// Database connection
require_once '../AdminDashboard/smpAdminwithBE/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['message']) || empty(trim($data['message']))) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

$user_id = $_SESSION['user_id'];
$message = trim($data['message']);
$sender_type = 'user'; // Default is user, admin messages will be saved differently

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
$conn->close();
?>