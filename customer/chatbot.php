<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
// Get current user ID (if logged in)
$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
?>

<style>
  /* --- Floating Chat Button --- */
  #chatbot-button {
    position: fixed;
    bottom: 75px;
    right: 20px;
    width: 60px;
    height: 60px;
    background-color: #d4af37;
    color: white;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 28px;
    cursor: pointer;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    z-index: 10000;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  #chatbot-button:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
  }

  /* --- Chat Window --- */
  #chatbot-window {
    position: fixed;
    bottom: 150px;
    right: 20px;
    width: 320px;
    height: 420px;
    background: #f7f7f7;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9998;
    
  }

  #chatbot-header {
    background: #d4af37;
    color: white;
    padding: 10px;
    text-align: center;
    font-weight: bold;
  }

  #chatbot-body {
    flex: 1;
    padding: 10px;
    overflow-y: auto;
    font-size: 14px;
    background-color: #fff;
    display: flex;
    flex-direction: column;

  }

  /* --- Message Bubbles --- */
  .message {
    max-width: 80%;
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 15px;
    word-wrap: break-word;
    display: inline-block;
    clear: both;
  }

  .bot-message {
    background-color: #e0e0e0;
    color: #000;
    align-self: flex-start;
    border-bottom-left-radius: 0;
  }

  .user-message {
    background-color: #d4af37;
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 0;
  }

  .admin-message {
    background-color:rgb(68, 70, 68);
    color: white;
    align-self: flex-start;
    border-bottom-left-radius: 0;
  }

  /* --- Typing Indicator --- */
  .typing-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 10px;
    align-self: flex-start;
  }

  .typing-dot {
    width: 6px;
    height: 6px;
    background: #999;
    border-radius: 50%;
    animation: blink 1.4s infinite both;
  }

  .typing-dot:nth-child(2) {
    animation-delay: 0.2s;
  }

  .typing-dot:nth-child(3) {
    animation-delay: 0.4s;
  }

  @keyframes blink {
    0%, 80%, 100% { opacity: 0; }
    40% { opacity: 1; }
  }

  /* --- Input Area --- */
  #chatbot-input {
    display: flex;
    border-top: 1px solid #ccc;
    background: #f9f9f9;
  }

  #chatbot-input input {
    flex: 1;
    border: none;
    padding: 10px;
    font-size: 14px;
    outline: none;
    background: transparent;
  }

  #chatbot-input button {
    background: #d4af37;
    border: none;
    color: white;
    padding: 10px 15px;
    cursor: pointer;
    border-top-right-radius: 10px;
  }

  #chatbot-input button:hover {
    background: #c49a2e;
  }
  #clear-chat-btn {
    position: absolute;
    right: 10px;
    top: 6px;
    background: transparent;
    border: none;
    color: white;
    font-size: 16px;
    cursor: pointer;
    transition: transform 0.2s, color 0.2s;
  }
</style>

<div id="chatbot-button">💬</div>

<div id="chatbot-window">
  <div id="chatbot-header">Chatbot
  <button id="clear-chat-btn" title="Clear Chat">🗑️</button>
  </div>
  <div id="chatbot-body">
    <div class="message bot-message">Hello! How can I help you today?<br><br>
    <strong>Quick Commands:</strong><br>
    💬 <strong>help</strong> - Show all commands<br>
    🛍️ <strong>product</strong> - View products<br>
    💰 <strong>price</strong> - Pricing info<br>
    👤 <strong>talk to a person</strong> - Live support
    </div>
  </div>
  <div id="chatbot-input">
    <input type="text" id="chat-input" placeholder="Type a message...">
    <button id="send-btn">Send</button>
  </div>
</div>

<script>
  //localStorage.clear(); // Uncomment this line to clear chat history for testing then comment again

  const currentUserId = <?php echo json_encode($currentUserId); ?>;

  const chatbotButton = document.getElementById("chatbot-button");
  const chatbotWindow = document.getElementById("chatbot-window");
  const chatInput = document.getElementById("chat-input");
  const sendBtn = document.getElementById("send-btn");
  const chatBody = document.getElementById("chatbot-body");

  // Chat key based on logged user
  const chatKey = currentUserId
    ? `chatbot_history_user_${currentUserId}`
    : "chatbot_history_guest";

  let isLiveChatMode = false;
  let pendingLiveChat = false; // NEW: Track if waiting for confirmation
  let lastMessageId = 0;
  let pollingInterval = null;
  let liveChatEnded = localStorage.getItem("liveChatEnded") === "true";


  // --- Clear chat if no user is logged in ---
  if (!currentUserId) {
    localStorage.removeItem(chatKey);
  }

  // --- Restore chat history from localStorage ---
  window.addEventListener("load", () => {
    const savedChat = localStorage.getItem(chatKey);
    if (savedChat) {
      chatBody.innerHTML = savedChat;
      chatBody.scrollTop = chatBody.scrollHeight;
    }

    // If user is logged in, check for existing live chat messages
    if (currentUserId) {
      loadLiveChatHistory();
    }
  });

  chatbotButton.addEventListener("click", () => {
    chatbotWindow.style.display =
      chatbotWindow.style.display === "flex" ? "none" : "flex";
  });

  // --- Load live chat history from database ---
  function loadLiveChatHistory() {
  if (liveChatEnded) {
    // User ended chat previously, don't auto reconnect
    console.log("Live chat session was ended previously.");
    return;
  }

  const getMessagesUrl = 'get_message.php';

  fetch(getMessagesUrl)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.messages.length > 0) {
        isLiveChatMode = true;
        chatBody.innerHTML = '<div class="message bot-message">Connected to live support 👤</div>';

        data.messages.forEach(msg => {
          const messageClass = msg.sender_type === 'admin' ? 'admin-message' : 'user-message';
          addMessageToUI(msg.message, messageClass);
          lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
        });

        startPolling();
      }
    })
    .catch(error => console.error('Error loading chat history:', error));
}


  // --- Poll for new messages ---
function startPolling() {
  if (pollingInterval) return; // Already polling
  
  pollingInterval = setInterval(() => {
    const pollUrl = `get_message.php?after_id=${lastMessageId}`;
    
    fetch(pollUrl)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Check if chat was ended by admin
          if (data.chat_ended === true) {
            stopPolling();
            isLiveChatMode = false;
            pendingLiveChat = false;
            liveChatEnded = true;
            localStorage.setItem("liveChatEnded", "true");
            
            // Show message that admin ended chat
            addMessageToUI("Admin has ended this chat session. You are now connected to the AI chatbot. 🤖", "bot-message");
            
            // Save to localStorage
            localStorage.setItem(chatKey, chatBody.innerHTML);
            
            return;
          }
          
          if (data.messages.length > 0) {
            data.messages.forEach(msg => {
              const messageClass = msg.sender_type === 'admin' ? 'admin-message' : 'user-message';
              
              // Show typing indicator for admin messages
              if (msg.sender_type === 'admin') {
                const typingIndicator = showTypingIndicator();
                
                setTimeout(() => {
                  typingIndicator.remove();
                  addMessageToUI(msg.message, messageClass);
                  lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                }, 500); // 0.5 seconds typing animation
              } else {
                addMessageToUI(msg.message, messageClass);
                lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
              }
            });
          }
        } else {
          console.error('Polling failed:', data.error);
        }
      })
      .catch(error => console.error('Error polling messages:', error));
  }, 2000); // Poll every 2 seconds
}

  // --- Stop polling ---
  function stopPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  }

// --- Bot responses ---
function getBotResponse(input) {
  const originalInput = input;
  input = input.toLowerCase();

  // Handle live chat confirmation
  if (pendingLiveChat && (input === "okay" || input === "ok" || input === "yes")) {
    pendingLiveChat = false;
    isLiveChatMode = true;
    liveChatEnded = false;
    localStorage.removeItem("liveChatEnded");

    
    // Clear chat and show connection message
    chatBody.innerHTML = '<div class="message bot-message">Connected to live support 👤<br>An admin will respond shortly...</div>';
    
    // Start polling for admin responses
    startPolling();
    
    // Save initial connection message to database
    saveMessageToDatabase("User connected to live chat");
    
    return null; // No bot response needed
  }

  // Keywords to trigger live support
  const humanKeywords = ["person", "human", "agent", "staff", "representative", "support", "real person", "live chat", "talk to someone"];

  if (humanKeywords.some(keyword => input.includes(keyword))) {
    // Check if user is logged in
    if (!currentUserId) {
      return `To use live support, please <a href="register.php" target="_blank">register</a> or <a href="login.php" target="_blank">login</a> first. 🔐`;
    }

    // Set pending state and ask for confirmation
    pendingLiveChat = true;
    return `Connecting you to live support... 👤<br>Type "Okay" to confirm.`;
  }

  // **FIX: Reset pendingLiveChat if user asks something else**
  if (pendingLiveChat) {
    pendingLiveChat = false;
  }

  // Help command
  if (input.includes("help") || input === "?" || input === "commands") {
    return `<strong>Available Commands:</strong><br>
      💬 <strong>help</strong> - Show this help menu<br>
      🛍️ <strong>product</strong> - View our products<br>
      💰 <strong>price</strong> - Ask about pricing<br>
      📍 <strong>location</strong> - Our store location<br>
      🛒 <strong>order</strong> - How to place an order<br>
      💳 <strong>payment</strong> - Payment methods<br>
      👤 <strong>talk to a person</strong> - Connect to live support<br><br>
      Just type any keyword to get started!`;
  }

  if (input.includes("hi") || input.includes("hello")) {
    return "Hi there! 👋 How can I help you today?";
  } else if (input.includes("product")) {
    return "We offer rings, necklaces, bracelets, and earrings. 💍";
  } else if (input.includes("price") || input.includes("cost")) {
    return "Our prices vary depending on design and gold purity. Check our <a href='shop.php' target='_blank'>Shop</a> page.";
  } else if (input.includes("location")) {
    return "We're located in Valenzuela, Philippines.";
  } else if (input.includes("order")) {
    return "Go to the <a href='shop.php' target='_blank'>Shop</a> page, select an item, and proceed to checkout.";
  } else if (input.includes("payment") || input.includes("pay")) {
    return "We accept PayPal and cash-on-delivery. 💳";
  }
  else if (input.includes("gcash") || input.includes("g-cash")) {
    return "Gcash is currently unavailable as one of our payment options.";
  }     
  else if (input.includes("thank")) {
    return "You're welcome! 💛";
  } else {
    return "I'm sorry, I didn't quite catch that. Could you rephrase?<br><small>💡 Type <strong>help</strong> to see available commands.</small>";
  }
}

  // --- Add message to UI only (no localStorage) ---
  function addMessageToUI(message, customClass) {
    const msg = document.createElement("div");
    msg.classList.add("message", customClass);
    msg.innerHTML = message;
    chatBody.appendChild(msg);
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  // --- Add message bubble (with localStorage for bot mode) ---
  function addMessage(sender, message) {
    const msg = document.createElement("div");
    msg.classList.add("message", sender === "Bot" ? "bot-message" : "user-message");
    msg.innerHTML = message;
    chatBody.appendChild(msg);
    chatBody.scrollTop = chatBody.scrollHeight;
    
    // Only save to localStorage in bot mode
    if (!isLiveChatMode && !pendingLiveChat) {
      localStorage.setItem(chatKey, chatBody.innerHTML);
    }
  }

  // --- Save message to database (live chat mode) ---
  function saveMessageToDatabase(message) {
    //console.log('Saving message:', message); // Debug
    fetch('save_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
      //console.log('Save response:', data); // Debug
      if (data.success) {
        lastMessageId = Math.max(lastMessageId, data.message_id || 0);
        //console.log('Message saved with ID:', data.message_id); // Debug
      } else {
        console.error('Error saving message:', data.error);
      }
    })
    .catch(error => console.error('Error:', error));
  }

  // --- Typing indicator ---
  function showTypingIndicator() {
    const typingDiv = document.createElement("div");
    typingDiv.classList.add("typing-indicator");
    typingDiv.innerHTML = `
      <div class="typing-dot"></div>
      <div class="typing-dot"></div>
      <div class="typing-dot"></div>
    `;
    chatBody.appendChild(typingDiv);
    chatBody.scrollTop = chatBody.scrollHeight;
    return typingDiv;
  }

  sendBtn.addEventListener("click", () => {
    const userInput = chatInput.value.trim();
    if (userInput === "") return;

    addMessage("You", userInput);
    chatInput.value = "";

    if (isLiveChatMode) {
      // Live chat mode: save to database
      saveMessageToDatabase(userInput);
    } else {
      // Bot mode: get automated response
      const typingIndicator = showTypingIndicator();

      setTimeout(() => {
        typingIndicator.remove();
        const botReply = getBotResponse(userInput);
        if (botReply !== null) {
          addMessage("Bot", botReply);
        }
      }, 500); // delay to simulate typing
    }
  });

  chatInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") sendBtn.click();
  });

  // Stop polling when user leaves the page
  window.addEventListener("beforeunload", () => {
    stopPolling();
  });

  const clearChatBtn = document.getElementById("clear-chat-btn");

  clearChatBtn.addEventListener("click", () => {
  if (confirm("Are you sure you want to clear the chat? Live support will end if currently active.")) {
    if (isLiveChatMode) {
      stopPolling();
      isLiveChatMode = false;
      pendingLiveChat = false;
      liveChatEnded = true;
      localStorage.setItem("liveChatEnded", "true");
      saveMessageToDatabase("User has ended the live support session.");
    }

    chatBody.innerHTML = `<div class="message bot-message">Hello! How can I help you today?<br><br>
    <strong>Quick Commands:</strong><br>
    💬 <strong>help</strong> - Show all commands<br>
    🛍️ <strong>product</strong> - View products<br>
    💰 <strong>price</strong> - Pricing info<br>
    👤 <strong>talk to a person</strong> - Live support
    </div>`;
    localStorage.removeItem(chatKey);

    alert("Chat has been cleared and live support has ended.");
  }
});


</script>