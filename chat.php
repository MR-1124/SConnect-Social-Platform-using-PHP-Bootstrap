<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$friend_id = isset($_GET['friend_id']) ? (int)$_GET['friend_id'] : 0;

$stmt = $conn->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    $user = ['name' => 'Unknown User', 'profile_pic' => 'default.jpg'];
}

if ($friend_id) {
    $stmt = $conn->prepare("SELECT u.id, u.name FROM users u JOIN friend_requests fr ON (u.id = fr.sender_id OR u.id = fr.receiver_id) WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted' AND u.id = ?");
    $stmt->bind_param("iii", $user_id, $user_id, $friend_id);
    $stmt->execute();
    $friend = $stmt->get_result()->fetch_assoc();
    
    if (!$friend) {
        header("Location: dashboard.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && $friend_id) {
    $message = trim($_POST['message']);
    if ($message) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $friend_id, $message);
        $stmt->execute();
        $message_id = $conn->insert_id;
        
        $message_preview = substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
        $stmt = $conn->prepare("INSERT INTO notifications (message_id, receiver_id, sender_id, message_preview) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $message_id, $friend_id, $user_id, $message_preview);
        $stmt->execute();
    }
}

$messages = [];
if ($friend_id) {
    $stmt = $conn->prepare("SELECT m.sender_id, m.message, m.created_at, u.name FROM messages m JOIN users u ON m.sender_id = u.id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at");
    $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE");
    $stmt->bind_param("ii", $user_id, $friend_id);
    $stmt->execute();
}

$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE receiver_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Social App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="sidebar">
        <img src="Uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" class="profile-pic" alt="Profile Picture">
        <h5 class="text-center"><?php echo htmlspecialchars($user['name']); ?></h5>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Home</a></li>
            <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li class="nav-item">
                <a class="nav-link" href="notifications.php">
                    <i class="fas fa-bell"></i> Notifications 
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" href="friends.php"><i class="fas fa-users"></i> Friends</a></li>
            <li class="nav-item"><a class="nav-link" href="chat.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li class="nav-item"><a class="nav-link" href="group_chat.php"><i class="fas fa-comments"></i> Group Chats</a></li>
            <li class="nav-item"><a class="nav-link" href="posts.php"><i class="fas fa-newspaper"></i> Posts</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <h2>Messages</h2>
        <?php if ($friend_id): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-envelope me-2"></i> Chat with <?php echo htmlspecialchars($friend['name']); ?></div>
                <div class="card-body">
                    <div class="chat-box" id="chat-box">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <strong><?php echo htmlspecialchars($msg['name']); ?>:</strong> <?php echo htmlspecialchars($msg['message']); ?>
                                <small class="d-block text-muted"><?php echo $msg['created_at']; ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" class="mt-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="message" placeholder="Type a message..." required>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-envelope me-2"></i> Select a Friend</div>
                <div class="card-body">
                    <p>Select a friend to start chatting.</p>
                    <?php
                    $stmt = $conn->prepare("SELECT u.id, u.name FROM users u JOIN friend_requests fr ON (u.id = fr.sender_id OR u.id = fr.receiver_id) WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted' AND u.id != ?");
                    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
                    $stmt->execute();
                    $friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <ul class="list-group">
                        <?php foreach ($friends as $friend): ?>
                            <li class="list-group-item">
                                <a href="chat.php?friend_id=<?php echo $friend['id']; ?>"><i class="fas fa-user me-2"></i> <?php echo htmlspecialchars($friend['name']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <footer class="footer">
        <p>Social Platform by Mayan Roy </p>
    </footer>

    <script>
        $(document).ready(function() {
            <?php if ($friend_id): ?>
                function loadMessages() {
                    $.ajax({
                        url: 'chat.php',
                        type: 'GET',
                        data: { friend_id: <?php echo $friend_id; ?> },
                        success: function(data) {
                            $('#chat-box').html($(data).find('#chat-box').html());
                            $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
                        },
                        error: function() {
                            console.log('Error loading messages.');
                        }
                    });
                }
                setInterval(loadMessages, 3000);
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
            <?php endif; ?>
        });
    </script>
</body>
</html>