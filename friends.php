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

$stmt = $conn->prepare("SELECT u.id, u.name, u.profile_pic FROM users u JOIN friend_requests fr ON (u.id = fr.sender_id OR u.id = fr.receiver_id) WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted' AND u.id != ?");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Friends - Social App</title>
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
        <h2>My Friends</h2>
        <div class="card">
            <div class="card-header"><i class="fas fa-users me-2"></i> Your Friends</div>
            <div class="card-body">
                <?php if (!empty($friends)): ?>
                    <ul class="list-group">
                        <?php foreach ($friends as $friend): ?>
                            <li class="list-group-item d-flex align-items-center" id="friend-<?php echo $friend['id']; ?>">
                                <img src="Uploads/<?php echo htmlspecialchars($friend['profile_pic']); ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <a href="profile.php?user_id=<?php echo $friend['id']; ?>" class="text-decoration-none text-dark">
                                        <strong><?php echo htmlspecialchars($friend['name']); ?></strong>
                                    </a>
                                </div>
                                <div>
                                    <a href="chat.php?friend_id=<?php echo $friend['id']; ?>" class="btn btn-sm btn-primary me-2"><i class="fas fa-envelope me-2"></i> Chat</a>
                                    <button class="btn btn-sm btn-danger unfriend-btn" data-friend-id="<?php echo $friend['id']; ?>" data-friend-name="<?php echo htmlspecialchars($friend['name']); ?>"><i class="fas fa-user-minus me-2"></i> Unfriend</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No friends yet. Try searching for new friends on the dashboard!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <footer class="footer">
        <p>Social Platform by Mayan Roy </p>
    </footer>

    <script>
        $(document).ready(function() {
            $('.unfriend-btn').click(function(e) {
                e.preventDefault();
                var friend_id = $(this).data('friend-id');
                var friend_name = $(this).data('friend-name');
                var li = $(this).closest('li');

                if (confirm('Are you sure you want to unfriend ' + friend_name + '?')) {
                    $.ajax({
                        url: 'friend_actions.php',
                        type: 'POST',
                        data: { action: 'unfriend', friend_id: friend_id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                li.fadeOut(500, function() {
                                    $(this).remove();
                                    if ($('.list-group-item').length === 0) {
                                        $('.card-body').html('<p>No friends yet. Try searching for new friends on the dashboard!</p>');
                                    }
                                });
                            } else {
                                alert(response.message);
                            }
                        },
                        error: function() {
                            alert('Error processing request.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>