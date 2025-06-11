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


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $target_dir = "uploads/";
    $old_pic = $user['profile_pic'];
    
    if ($old_pic != 'default.jpg' && file_exists($target_dir . $old_pic)) {
        unlink($target_dir . $old_pic); // Remove old profile picture using ulink
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

// friend search implementation also added 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .sidebar { width: 250px; height: 100vh; position: fixed; background: #f8f9fa; }
        .content { margin-left: 270px; padding: 20px; }
        .profile-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }
    </style>
    <script>

// implemneted friend request system using ajax

        
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
</head>
<body>
    <div class="sidebar">
        <img src="uploads/<?php echo $user['profile_pic']; ?>" class="profile-pic m-3" alt="Profile Picture">
        <h5 class="m-3"><?php echo $user['name']; ?></h5>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Friends</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Message</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Notification</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Scrap</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Post</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Setting</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Game</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Group Chat</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="content">
        <h2>Dashboard</h2>
        

        <h3>Update Profile Picture</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="profile_pic" class="form-label">Upload New Picture</label>
                <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Picture</button>
        </form>
        

        <h3 class="mt-5">Search Friends</h3>
        <form method="POST" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search by name or email">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
        <?php if (!empty($search_results)): ?>
            <h4>Search Results</h4>
            <ul class="list-group">
                <?php foreach ($search_results as $result): ?>
                    <li class="list-group-item">
                        <?php echo $result['name'] . " (" . $result['email'] . ")"; ?>
                        <?php

    
                        $stmt = $conn->prepare("SELECT status FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
                        $stmt->bind_param("ii", $user_id, $result['id']);
                        $stmt->execute();
                        $request = $stmt->get_result()->fetch_assoc();
                        if ($request && $request['status'] == 'pending') {
                            echo '<span class="text-muted">Request Sent</span>';
                        } elseif (!$request) {
                            echo '<button class="btn btn-sm btn-success send-request" data-receiver-id="' . $result['id'] . '">Send Request</button>';
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
     
        <h3 class="mt-5">Friend Requests</h3>
        <?php if (!empty($friend_requests)): ?>
            <ul class="list-group" id="friend-requests">
                <?php foreach ($friend_requests as $request): ?>
                    <li class="list-group-item">
                        <?php echo $request['name']; ?>
                        <button class="btn btn-sm btn-success accept-request" data-request-id="<?php echo $request['id']; ?>">Accept</button>
                        <button class="btn btn-sm btn-danger block-request" data-request-id="<?php echo $request['id']; ?>">Block</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No pending friend requests.</p>
        <?php endif; ?>
    </div>
</body>
</html>
