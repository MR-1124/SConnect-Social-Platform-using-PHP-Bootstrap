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
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    $user = ['name' => 'Unknown User', 'profile_pic' => 'default.jpg'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $target_dir = "uploads/";
    $old_pic = $user['profile_pic'];
    
    if ($old_pic != 'default.jpg' && file_exists($target_dir . $old_pic)) {
        unlink($target_dir . $old_pic);
    }
    
    $target_file = $target_dir . basename($_FILES["profile_pic"]["name"]);
    if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param("si", basename($_FILES["profile_pic"]["name"]), $user_id);
        $stmt->execute();
        header("Location: dashboard.php");
        exit();
    }
}

$search_results = [];
if (isset($_POST['search'])) {
    $search = $_POST['search'];
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE (name LIKE ? OR email LIKE ?) AND id != ? AND id NOT IN (SELECT receiver_id FROM friend_requests WHERE sender_id = ? AND status = 'blocked')");
    $search_term = "%$search%";
    $stmt->bind_param("ssii", $search_term, $search_term, $user_id, $user_id);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$stmt = $conn->prepare("SELECT fr.id, u.name FROM friend_requests fr JOIN users u ON fr.sender_id = u.id WHERE fr.receiver_id = ? AND fr.status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$friend_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT u.id, u.name FROM users u JOIN friend_requests fr ON (u.id = fr.sender_id OR u.id = fr.receiver_id) WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted' AND u.id != ?");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Dashboard - Social App</title>
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
        <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-camera me-2"></i> Update Profile Picture</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profile_pic" class="form-label">Choose New Picture</label>
                        <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i> Update Picture</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-search me-2"></i> Search Friends</div>
            <div class="card-body">
                <form method="POST" class="mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or email">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <?php if (!empty($search_results)): ?>
                    <h4>Search Results</h4>
                    <ul class="list-group">
                        <?php foreach ($search_results as $result): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($result['name']) . " (" . htmlspecialchars($result['email']) . ")"; ?>
                                <?php
                                $stmt = $conn->prepare("SELECT status FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
                                $stmt->bind_param("ii", $user_id, $result['id']);
                                $stmt->execute();
                                $request = $stmt->get_result()->fetch_assoc();
                                if ($request && $request['status'] == 'pending') {
                                    echo '<span class="text-muted">Request Sent</span>';
                                } elseif (!$request) {
                                    echo '<button class="btn btn-sm btn-success send-request" data-receiver-id="' . $result['id'] . '"><i class="fas fa-user-plus me-2"></i> Send Request</button>';
                                }
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-user-friends me-2"></i> Friend Requests</div>
            <div class="card-body">
                <?php if (!empty($friend_requests)): ?>
                    <ul class="list-group" id="friend-requests">
                        <?php foreach ($friend_requests as $request): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($request['name']); ?>
                                <div>
                                    <button class="btn btn-sm btn-success accept-request" data-request-id="<?php echo $request['id']; ?>"><i class="fas fa-check me-2"></i> Accept</button>
                                    <button class="btn btn-sm btn-danger block-request" data-request-id="<?php echo $request['id']; ?>"><i class="fas fa-ban me-2"></i> Block</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No pending friend requests.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-users me-2"></i> Your Friends</div>
            <div class="card-body">
                <?php if (!empty($friends)): ?>
                    <ul class="list-group">
                        <?php foreach ($friends as $friend): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($friend['name']); ?>
                                <a href="chat.php?friend_id=<?php echo $friend['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-envelope me-2"></i> Chat</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No friends yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-newspaper me-2"></i> Public Posts</div>
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

    <script>
        $(document).ready(function() {
            $('.send-request').click(function(e) {
                e.preventDefault();
                var receiver_id = $(this).data('receiver-id');
                var button = $(this);
                
                $.ajax({
                    url: 'friend_actions.php',
                    type: 'POST',
                    data: { action: 'send_request', receiver_id: receiver_id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            button.replaceWith('<span class="text-muted">Request Sent</span>');
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error processing request.');
                    }
                });
            });

            $('.accept-request').click(function(e) {
                e.preventDefault();
                var request_id = $(this).data('request-id');
                var li = $(this).closest('li');
                
                $.ajax({
                    url: 'friend_actions.php',
                    type: 'POST',
                    data: { action: 'accept_request', request_id: request_id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            li.replaceWith('<li class="list-group-item text-success">Friend request accepted.</li>');
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error processing request.');
                    }
                });
            });

            $('.block-request').click(function(e) {
                e.preventDefault();
                var request_id = $(this).data('request-id');
                var li = $(this).closest('li');
                
                $.ajax({
                    url: 'friend_actions.php',
                    type: 'POST',
                    data: { action: 'block_request', request_id: request_id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            li.remove();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error processing request.');
                    }
                });
            });
        });
    </script>
</body>
</html>