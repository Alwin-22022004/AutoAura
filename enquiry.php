<?php
session_start();
require 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);

// Verify if user exists in database
$user_id = $_SESSION['user_id'];
$check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param("i", $user_id);
$check_user->execute();
$user_result = $check_user->get_result();

if ($user_result->num_rows === 0) {
    session_destroy();
    header('Location: login.php');
    exit();
}
$check_user->close();

// Handle AJAX message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_message') {
        $message = trim($_POST['message']);
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a message.']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO enquiries (user_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $message);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error sending message.']);
        }
        
        $stmt->close();
        $conn->close();
        exit();
    }
    
    if ($_POST['action'] === 'get_messages') {
        $messages = [];
        $stmt = $conn->prepare("SELECT * FROM enquiries WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        $stmt->close();
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }
}

// Initial messages load
$messages = [];
$stmt = $conn->prepare("SELECT * FROM enquiries WHERE user_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Support - LUXE DRIVE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="dashstyle.css" />
    <style>
        :root {
            --primary-color: #f5b754;
            --secondary-color: #d6a04a;
            --text-color: #2d2e32;
            --text-color-light: #767676;
            --white: #fff;
            --chat-bg: #f0f2f5;
            --user-message-bg: #f5b754;
            --admin-message-bg: #e9ecef;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            background-color: var(--chat-bg);
        }

        .chat-container {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            background: var(--white);
            padding: 1rem 2rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 100;
        }

        .back-btn {
            color: var(--text-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .chat-title {
            font-size: 1.5rem;
            margin: 0;
            flex-grow: 1;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            background: var(--white);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 70%;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            position: relative;
            animation: fadeIn 0.3s ease;
            margin-bottom: 0.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-user {
            background: var(--user-message-bg);
            color: var(--white);
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }

        .message-admin {
            background: var(--admin-message-bg);
            color: var(--text-color);
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 0.5rem;
            text-align: right;
        }

        .chat-input {
            background: var(--white);
            padding: 1.5rem 2rem;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        .message-form {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .message-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: 2px solid #eee;
            border-radius: 25px;
            font-size: 1rem;
            resize: none;
            min-height: 24px;
            max-height: 120px;
            line-height: 24px;
            transition: all 0.3s ease;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
        }

        .send-btn:active {
            transform: scale(0.95);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #842029;
        }

        .no-messages {
            text-align: center;
            color: var(--text-color-light);
            padding: 2rem;
        }

        .no-messages i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .chat-container {
                padding: 0;
                height: 100vh;
            }

            .chat-header,
            .chat-input {
                border-radius: 0;
            }

            .message {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>
            <h1 class="chat-title">Chat with owner for your enquires</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <i class="fas fa-comments"></i>
                    <p>Start a conversation with us!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['is_admin_reply'] ? 'message-admin' : 'message-user'; ?>">
                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        <div class="message-time">
                            <i class="fas fa-clock"></i>
                            <?php echo date('F j, Y g:i A', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input">
            <form id="messageForm" class="message-form">
                <input type="text" name="message" class="message-input" placeholder="Type your message..." required autocomplete="off">
                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chatMessages');
            const messageForm = document.getElementById('messageForm');
            let lastMessageTime = '<?php echo !empty($messages) ? $messages[count($messages)-1]['created_at'] : ''; ?>';

            // Function to format the date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
            }

            // Function to add a new message to the chat
            function addMessage(message, isOwnerReply) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${isOwnerReply ? 'message-admin' : 'message-user'}`;
                messageDiv.innerHTML = `
                    ${message.message}
                    <div class="message-time">
                        <i class="fas fa-clock"></i>
                        ${formatDate(message.created_at)}
                    </div>
                `;
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Function to fetch new messages
            function fetchMessages() {
                const formData = new FormData();
                formData.append('action', 'get_messages');
                
                fetch('enquiry.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const lastMessage = data.messages[data.messages.length - 1];
                        if (lastMessage.created_at !== lastMessageTime) {
                            chatMessages.innerHTML = '';
                            data.messages.forEach(msg => {
                                addMessage(msg, msg.is_admin_reply);
                            });
                            lastMessageTime = lastMessage.created_at;
                        }
                    }
                })
                .catch(error => console.error('Error fetching messages:', error));
            }

            // Handle form submission
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const messageInput = this.querySelector('input[name="message"]');
                const message = messageInput.value.trim();
                
                if (!message) return;

                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);

                fetch('enquiry.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        fetchMessages();
                    } else {
                        alert(data.error || 'Error sending message');
                    }
                })
                .catch(error => console.error('Error:', error));
            });

            // Poll for new messages every 3 seconds
            setInterval(fetchMessages, 3000);

            // Scroll to bottom on load
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    </script>
</body>
</html>
