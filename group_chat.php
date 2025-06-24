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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    if ($group_name) {
        $stmt = $conn->prepare("INSERT INTO group_chats (name, creator_id) VALUES (?, ?)");
        $stmt->bind_param("si", $group_name, $user_id);
        $stmt->execute();
        $group_id = $conn->insert_id;
        
        $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        header("Location: group_chat.php?group_id=$group_id");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($_POST['group_id'])) {
    $group_id = (int)$_POST['group_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();
    $is_member = $stmt->get_result()->fetch_assoc()['member_count'] > 0;
    
    if ($is_member) {
        $message = trim($_POST['message']);
        if ($message) {
            $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $group_id, $user_id, $message);
            $stmt->execute();
        }
    }
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$messages = [];
$group_name = '';
$is_member = false;
$group_members = [];
$non_member_friends = [];

if ($group_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();
    $is_member = $stmt->get_result()->fetch_assoc()['member_count'] > 0;
    
    if ($is_member) {
        $stmt = $conn->prepare("SELECT name FROM group_chats WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $group = $stmt->get_result()->fetch_assoc();
        $group_name = $group ? $group['name'] : '';
        
        $stmt = $conn->prepare("SELECT gm.sender_id, gm.message, gm.created_at, u.name FROM group_messages gm JOIN users u ON gm.sender_id = u.id WHERE gm.group_id = ? ORDER BY gm.created_at");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stmt = $conn->prepare("SELECT u.id, u.name, u.profile_pic FROM users u JOIN group_members gm ON u.id = gm.user_id WHERE gm.group_id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $group_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stmt = $conn->prepare("SELECT u.id, u.name FROM users u JOIN friend_requests fr ON (u.id = fr.sender_id OR u.id = fr.receiver_id) 
                                WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted' AND u.id != ? 
                                AND u.id NOT IN (SELECT user_id FROM group_members WHERE group_id = ?)");
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $group_id);
        $stmt->execute();
        $non_member_friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$stmt = $conn->prepare("SELECT g.id, g.name FROM group_chats g JOIN group_members gm ON g.id = gm.group_id WHERE gm.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Group Chats - Social App</title>
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
        <h2>Group Chats</h2>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-plus me-2"></i> Create New Group</div>
            <div class="card-body">
                <form method="POST">
                    <div class="input-group">
                        <input type="text" class="form-control" name="group_name" placeholder="Group name" required>
                        <button type="submit" name="create_group" class="btn btn-primary"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($group_id): ?>
            <?php if ($is_member): ?>
                <div class="card">
                    <div class="card-header"><i class="fas fa-users me-2"></i> Group Members: <?php echo htmlspecialchars($group_name); ?></div>
                    <div class="card-body">
                        <?php if (!empty($group_members)): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($group_members as $member): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <img src="Uploads/<?php echo htmlspecialchars($member['profile_pic']); ?>" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                        <span><?php echo htmlspecialchars($member['name']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No members in this group yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fas fa-user-plus me-2"></i> Add Members to <?php echo htmlspecialchars($group_name); ?></div>
                    <div class="card-body">
                        <?php if (!empty($non_member_friends)): ?>
                            <ul class="list-group" id="non-member-friends">
                                <?php foreach ($non_member_friends as $friend): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($friend['name']); ?></span>
                                        <button class="btn btn-sm btn-success add-member-btn" data-group-id="<?php echo $group_id; ?>" data-friend-id="<?php echo $friend['id']; ?>"><i class="fas fa-user-plus me-2"></i> Add</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No friends available to add. Make more friends on the dashboard!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fas fa-comments me-2"></i> Group: <?php echo htmlspecialchars($group_name); ?></div>
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
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <div class="input-group">
                                <input type="text" class="form-control" name="message" placeholder="Type a message..." required>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p class="text-danger"><i class="fas fa-exclamation-circle me-2"></i> You are not a member of this group.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-comments me-2"></i> Your Groups</div>
                <div class="card-body">
                    <?php if (!empty($groups)): ?>
                        <ul class="list-group">
                            <?php foreach ($groups as $group): ?>
                                <li class="list-group-item">
                                    <a href="group_chat.php?group_id=<?php echo $group['id']; ?>"><i class="fas fa-comment me-2"></i> <?php echo htmlspecialchars($group['name']); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No groups yet. Create one above!</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <footer class="footer">
        <p>Social Platform by Mayan Roy </p>
    </footer>

    <script>
        $(document).ready(function() {
            <?php if ($group_id && $is_member): ?>
                function loadMessages() {
                    $.ajax({
                        url: 'group_chat.php',
                        type: 'GET',
                        data: { group_id: <?php echo $group_id; ?> },
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

                $('.add-member-btn').click(function(e) {
                    e.preventDefault();
                    var group_id = $(this).data('group-id');
                    var friend_id = $(this).data('friend-id');
                    var li = $(this).closest('li');

                    $.ajax({
                        url: 'group_actions.php',
                        type: 'POST',
                        data: { action: 'add_member', group_id: group_id, friend_id: friend_id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                li.fadeOut(500, function() {
                                    $(this).remove();
                                    if ($('#non-member-friends .list-group-item').length === 0) {
                                        $('#non-member-friends').replaceWith('<p>No friends available to add. Make more friends on the dashboard!</p>');
                                    }
                                    $.ajax({
                                        url: 'group_chat.php',
                                        type: 'GET',
                                        data: { group_id: group_id },
                                        success: function(data) {
                                            $('.card-body').first().html($(data).find('.card-body').first().html());
                                        }
                                    });
                                });
                            } else {
                                alert(response.message);
                            }
                        },
                        error: function() {
                            alert('Error processing request.');
                        }
                    });
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>