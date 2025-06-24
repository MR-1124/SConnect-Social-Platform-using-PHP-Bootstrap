<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    $user = ['name' => 'Unknown User', 'profile_pic' => 'default.jpg'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header("Location: notifications.php");
    exit();
}

$stmt = $conn->prepare("SELECT n.id, n.message_id, n.sender_id, n.message_preview, n.created_at, u.name, u.profile_pic 
                        FROM notifications n 
                        JOIN users u ON n.sender_id = u.id 
                        WHERE n.receiver_id = ? AND n.is_read = FALSE 
                        ORDER BY n.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Notifications - Social App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
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
        <h2>Notifications</h2>
        <div class="card">
            <div class="card-header"><i class="fas fa-bell me-2"></i> New Messages</div>
            <div class="card-body">
                <?php if (!empty($notifications)): ?>
                    <ul class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="Uploads/<?php echo htmlspecialchars($notification['profile_pic']); ?>" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notification['name']); ?></strong> sent you a message: 
                                        <span><?php echo htmlspecialchars($notification['message_preview']); ?></span>
                                        <small class="d-block text-muted"><?php echo $notification['created_at']; ?></small>
                                    </div>
                                </div>
                                <div>
                                    <a href="chat.php?friend_id=<?php echo htmlspecialchars($notification['sender_id']); ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-envelope me-1"></i> View Chat</a>
                                    <form method="POST" class="d-inline-block">
                                        <input type="hidden" name="notification_id" value="<?php echo htmlspecialchars($notification['id']); ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-check me-1"></i> Mark as Read</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No new notifications.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <footer class="footer">
        <p>Social Platform by Mayan Roy </p>
    </footer>
</body>
</html>