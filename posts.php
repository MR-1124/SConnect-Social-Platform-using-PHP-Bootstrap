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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $content);
        $stmt->execute();
        header("Location: posts.php");
        exit();
    }
}

$stmt = $conn->prepare("SELECT p.id, p.content, p.created_at, u.name, u.profile_pic FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Posts - Social App</title>
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
        <h2>Public Posts</h2>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-pen me-2"></i> Create a Post</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <textarea class="form-control" name="content" rows="4" placeholder="What's on your mind?" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i> Post</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-newspaper me-2"></i> All Posts</div>
            <div class="card-body">
                <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post">
                            <div class="d-flex align-items-center mb-2">
                                <img src="Uploads/<?php echo htmlspecialchars($post['profile_pic']); ?>" class="rounded-circle me-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($post['name']); ?></strong>
                                    <small class="text-muted d-block"><?php echo $post['created_at']; ?></small>
                                </div>
                            </div>
                            <p><?php echo htmlspecialchars($post['content']); ?></p>
                            <small class="text-muted"><i class="fas fa-heart me-1"></i> <?php echo rand(0, 100); ?> Likes</small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No posts yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <footer class="footer">
        <p>Social Platform by Mayan Roy </p>
    </footer>
</body>
</html>