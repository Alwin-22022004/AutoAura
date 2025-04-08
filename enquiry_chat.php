<?php
session_start();
$conn = new mysqli("localhost", "root", "", "car_rental");

// Handle admin reply submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reply_message'], $_POST['user_id'])) {
    $reply = $conn->real_escape_string($_POST['reply_message']);
    $user_id = intval($_POST['user_id']);

    $insert_sql = "INSERT INTO enquiries (user_id, message, is_admin_reply, created_at) 
                   VALUES ($user_id, '$reply', 1, NOW())";
    $conn->query($insert_sql);

    header("Location: ?user=$user_id");
    exit;
}

// Fetch all users who have made enquiries
$users_sql = "SELECT DISTINCT e.user_id, u.fullname 
              FROM enquiries e 
              JOIN users u ON e.user_id = u.id";
$users_result = $conn->query($users_sql);

// If a specific user is selected
$selected_user_id = isset($_GET['user']) ? intval($_GET['user']) : null;
$selected_user_name = null;
$chat_messages = [];

if ($selected_user_id) {
    $name_result = $conn->query("SELECT fullname FROM users WHERE id = $selected_user_id");
    if ($name_result->num_rows > 0) {
        $selected_user_name = $name_result->fetch_assoc()['fullname'];

        // Get chat messages
        $msg_sql = "SELECT * FROM enquiries WHERE user_id = $selected_user_id ORDER BY created_at ASC";
        $chat_messages = $conn->query($msg_sql);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Enquiries Chat</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #ece5dd;
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #075e54;
            color: white;
            overflow-y: auto;
        }
        .sidebar h2 {
            text-align: center;
            padding: 15px;
            margin: 0;
            background: #064e45;
        }
        .user-link {
            display: block;
            padding: 15px;
            border-bottom: 1px solid #0b7a6c;
            text-decoration: none;
            color: white;
            transition: background 0.2s;
        }
        .user-link:hover, .user-link.active {
            background: #0b7a6c;
        }
        .chat-container {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            overflow-y: auto;
        }
        .user-header {
            background: #075e54;
            color: white;
            padding: 10px;
            font-size: 18px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .message {
            margin: 10px 0;
            padding: 10px 15px;
            border-radius: 10px;
            max-width: 70%;
            word-wrap: break-word;
        }
        .sent {
            background: #dcf8c6;
            margin-left: auto;
            text-align: right;
        }
        .received {
            background: #f1f1f1;
            border: 1px solid #ccc;
        }
        .timestamp {
            font-size: 10px;
            color: #555;
            margin-top: 5px;
            display: block;
        }
        .reply-form {
            margin-top: 20px;
        }
        .reply-form textarea {
            width: 100%;
            height: 60px;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            resize: vertical;
        }
        .reply-form button {
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #075e54;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .reply-form button:hover {
            background-color: #0b7a6c;
        }
        .no-chat {
            text-align: center;
            color: #777;
            margin-top: 100px;
            font-size: 18px;
        }
    </style>
</head>
<body>

<!-- Sidebar: List of users -->
<div class="sidebar">
    <h2>Enquiries</h2>
    <?php if ($users_result->num_rows > 0): ?>
        <?php while ($user = $users_result->fetch_assoc()): ?>
            <a href="?user=<?= $user['user_id'] ?>" class="user-link <?= $user['user_id'] == $selected_user_id ? 'active' : '' ?>">
                <?= htmlspecialchars($user['fullname']) ?>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="padding: 15px;">No enquiries</p>
    <?php endif; ?>
</div>

<!-- Main Chat Area -->
<div class="chat-container">
    <?php if ($selected_user_id && $selected_user_name): ?>
        <div>
            <div class="user-header"><?= htmlspecialchars($selected_user_name) ?></div>

            <?php if ($chat_messages && $chat_messages->num_rows > 0): ?>
                <?php while ($row = $chat_messages->fetch_assoc()): ?>
                    <div class="message <?= $row['is_admin_reply'] ? 'received' : 'sent' ?>">
                        <?= nl2br(htmlspecialchars($row['message'])) ?>
                        <span class="timestamp"><?= date('H:i', strtotime($row['created_at'])) ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-chat">No messages for this user.</p>
            <?php endif; ?>
        </div>

        <!-- Reply Form -->
        <form method="POST" class="reply-form">
            <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
            <textarea name="reply_message" placeholder="Type your reply here..." required></textarea>
            <button type="submit">Send Reply</button>
        </form>
    <?php else: ?>
        <div class="no-chat">Select a user to view and reply to messages.</div>
    <?php endif; ?>
</div>

</body>
</html>
