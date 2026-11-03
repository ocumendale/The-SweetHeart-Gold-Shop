<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

// Get list of users with active chats
$users_query = "SELECT DISTINCT u.id, u.username, 
                (SELECT COUNT(*) FROM live_chat WHERE user_id = u.id AND sender_type = 'user' AND is_read = 0) as unread_count,
                (SELECT created_at FROM live_chat WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM users u
                INNER JOIN live_chat lc ON u.id = lc.user_id
                ORDER BY last_message_time DESC";

$result = $conn->query($users_query);
$users = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'unread_count' => (int)$row['unread_count'],
            'last_message_time' => $row['last_message_time']
        ];
    }
}

echo json_encode(['success' => true, 'users' => $users]);
?>