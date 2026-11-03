<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
    exit;
}
// Get list of users with active chats
$users_query = "SELECT DISTINCT u.id, u.username, 
                (SELECT COUNT(*) FROM live_chat WHERE user_id = u.id AND sender_type = 'user' AND is_read = 0 AND is_active = 1) as unread_count,
                (SELECT created_at FROM live_chat WHERE user_id = u.id AND is_active = 1 ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM users u
                INNER JOIN live_chat lc ON u.id = lc.user_id
                WHERE lc.is_active = 1
                ORDER BY last_message_time DESC";
$users_result = $conn->query($users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Chat - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/LiveChat.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="d-flex flex-column flex-shrink-0 p-3 sidebar">
        <br><br><br>
        <div class="logo">
            <div class="sweetheart">SweetHeart</div>
            <div class="goldshop">Gold Shop</div>
        </div>
        <br><br><br>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item mb-4">
                <a href="dashboard.php" class="nav-link text-white">
                    <img src="../icons/home.png" alt="Dashboard" class="nav-icon" />
                    Dashboard
                </a>
            </li>
            <li class="mb-4">
                <a href="inventory.php" class="nav-link text-white">
                    <img src="../icons/inventory.png" alt="Inventory" class="nav-icon" />
                    Inventory
                </a>
            </li>
            <li class="mb-4">
                <a href="orders.php" class="nav-link text-white">
                    <img src="../icons/orders.png" alt="Orders" class="nav-icon" />
                    Orders
                </a>
            </li>
            <li class="mb-4">
                <a href="customers.php" class="nav-link text-white">
                    <img src="../icons/customers.png" alt="Customers" class="nav-icon" />
                    Customers
                </a>
            </li>
            <li class="mb-4">
                <a href="sales_report.php" class="nav-link text-white">
                    <img src="../icons/salesreport.png" alt="salesreport" class="nav-icon" />
                    Sales Report
                </a>
            </li>
            <li class="mb-4">
                <a href="activitylog.php" class="nav-link text-white">
                    <img src="../icons/restore.png" alt="Activity Log and Recycle Bin" class="nav-icon" />
                    Log and Bin
                </a>
            </li>
        </ul>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <div class="header">
            <div>
                <h1>LIVE CHAT SUPPORT</h1>
                 <div>
                <a href="customers.php" class="back-button">
                    ← Back to Customers
                </a>
            </div>
            </div>    
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>  
        </div>

        <!-- Chat Container -->
        <div class="chat-layout">
            <!-- Users List -->
            <div class="users-sidebar">
                <div class="users-header">
                    <h3>Active Conversations</h3>
                </div>
                <div class="users-list" id="users-list">
                    <?php if ($users_result->num_rows > 0): ?>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <div class="user-item" data-user-id="<?= $user['id'] ?>" onclick="loadChat(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </div>
                                <div class="user-info">
                                    <strong class="user-name"><?= htmlspecialchars($user['username']) ?></strong>
                                    <?php if ($user['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?= $user['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-users">
                            <p>💬 No active chats yet</p>
                            <small>New conversations will appear here</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Window -->
            <div class="chat-window">
                <div class="chat-header">
                    <div class="chat-header-content">
                        <h5 id="chat-username">Select a user to start chatting</h5>
                        <button class="end-chat-btn" id="end-chat-btn" style="display: none;" onclick="endChat()">
                            End Chat
                        </button>
                    </div>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <div class="empty-chat">
                        <div class="empty-chat-icon">💬</div>
                        <h4>Select a conversation from the left</h4>
                        <p>Choose a user to view and respond to messages</p>
                    </div>
                </div>
                <div class="chat-input" id="chat-input-area" style="display: none;">
                    <input type="text" id="message-input" placeholder="Type a message..." />
                    <button class="send-btn" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
let currentUserId = null;
let lastMessageId = 0;
let pollingInterval = null;
let userListPollingInterval = null;

function loadChat(userId, userName) {
    currentUserId = userId;
    lastMessageId = 0;

    document.getElementById('chat-username').textContent = userName;
    document.getElementById('chat-input-area').style.display = 'flex';
    document.getElementById('end-chat-btn').style.display = 'block';

    document.querySelectorAll('.user-item').forEach(item => item.classList.remove('active'));
    document.querySelector(`[data-user-id="${userId}"]`).classList.add('active');

    markMessagesAsRead(userId);
    loadMessages();

    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(pollNewMessages, 1500);
}

function markMessagesAsRead(userId) {
    fetch('mark_messages_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: userId})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector(`.user-item[data-user-id='${userId}'] .unread-badge`);
            if (badge) badge.remove();
        }
    });
}

function loadMessages() {
    if (!currentUserId) return;
    fetch(`get_admin_messages.php?user_id=${currentUserId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const messagesDiv = document.getElementById('chat-messages');
            messagesDiv.innerHTML = '';
            if (data.messages.length === 0) {
                messagesDiv.innerHTML = '<div class="empty-chat"><div class="empty-chat-icon">💬</div><h4>No messages yet</h4><p>Start the conversation!</p></div>';
            } else {
                data.messages.forEach(msg => {
                    appendMessage(msg);
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });
            }
        });
}

function pollNewMessages() {
    if (!currentUserId) return;
    fetch(`get_admin_messages.php?user_id=${currentUserId}&after_id=${lastMessageId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || data.messages.length === 0) return;
            data.messages.forEach(msg => {
                appendMessage(msg);
                lastMessageId = msg.id;
            });
            markMessagesAsRead(currentUserId);
        });
}

function appendMessage(msg) {
    const messagesDiv = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', msg.sender_type);
    const time = new Date(msg.created_at).toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
    messageDiv.innerHTML = `<div class="message-content">${msg.message}</div><div class="message-time">${time}</div>`;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function sendMessage() {
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    if (!message || !currentUserId) return;

    fetch('send_admin_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: currentUserId, message})
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) return;
        input.value = '';
        appendMessage({id: data.message_id, sender_type:'admin', message, created_at: new Date().toISOString()});
        lastMessageId = data.message_id;
    });
}

function updateUserList() {
    fetch('get_users_list.php?t=' + Date.now())
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            
            const usersList = document.getElementById('users-list');
            const hasNoUsersDiv = usersList.querySelector('.no-users');
            const users = data.users || [];
            
            if (hasNoUsersDiv && users.length > 0) {
                location.reload();
                return;
            }
            
            const existingUserIds = Array.from(document.querySelectorAll('.user-item')).map(item => item.dataset.userId);
            const hasNewUser = users.some(u => !existingUserIds.includes(u.id.toString()));
            
            if (hasNewUser && !currentUserId) {
                location.reload();
                return;
            }
            
            users.forEach(u => {
                let userItem = document.querySelector(`.user-item[data-user-id='${u.id}']`);
                
                if (userItem) {
                    const badge = userItem.querySelector('.unread-badge');
                    if (u.unread_count > 0 && u.id !== currentUserId) {
                        if (!badge) {
                            const span = document.createElement('span');
                            span.classList.add('unread-badge');
                            span.textContent = u.unread_count;
                            userItem.querySelector('.user-info').appendChild(span);
                        } else {
                            badge.textContent = u.unread_count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                }
            });
        })
        .catch(err => console.error('Error updating user list:', err));
}

userListPollingInterval = setInterval(updateUserList, 2000);

function endChat() {
    if (!currentUserId) return;
    
    if (!confirm('Are you sure you want to end this chat? The conversation will be archived.')) {
        return;
    }
    
    fetch('end_chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: currentUserId})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const userItem = document.querySelector(`.user-item[data-user-id='${currentUserId}']`);
            if (userItem) userItem.remove();
            
            currentUserId = null;
            lastMessageId = 0;
            document.getElementById('chat-username').textContent = 'Select a user to start chatting';
            document.getElementById('chat-messages').innerHTML = '<div class="empty-chat"><div class="empty-chat-icon">💬</div><h4>Select a conversation from the left</h4><p>Choose a user to view and respond to messages</p></div>';
            document.getElementById('chat-input-area').style.display = 'none';
            document.getElementById('end-chat-btn').style.display = 'none';
            
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
            
            const remainingUsers = document.querySelectorAll('.user-item');
            if (remainingUsers.length === 0) {
                document.getElementById('users-list').innerHTML = '<div class="no-users"><p>💬 No active chats yet</p><small>New conversations will appear here</small></div>';
            }
        } else {
            alert('Failed to end chat: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error ending chat:', err);
        alert('Failed to end chat. Please try again.');
    });
}

document.getElementById('message-input')?.addEventListener('keypress', e => {
    if (e.key === 'Enter') sendMessage();
});

window.addEventListener('beforeunload', () => { 
    if (pollingInterval) clearInterval(pollingInterval);
    if (userListPollingInterval) clearInterval(userListPollingInterval);
});
    </script>
</body>
</html>