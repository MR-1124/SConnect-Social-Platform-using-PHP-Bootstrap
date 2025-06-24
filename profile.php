<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$view_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;

$stmt = $conn->prepare("SELECT name, email, phone, profile_pic, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    $user = ['name' => 'Unknown User', 'email' => '', 'phone' => '', 'profile_pic' => 'default.jpg', 'created_at' => ''];
}

$is_own_profile = ($view_user_id === $user_id);

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if ($password && (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password))) {
        $error = "Password must be 8+ characters, include 1 capital letter, and 1 number.";
    } else {
        $update_fields = [];
        $params = [];
        $types = "";

        if ($name && $name !== $user['name']) {
            $update_fields[] = "name = ?";
            $params[] = $name;
            $types .= "s";
        }
        if ($email && $email !== $user['email']) {
            $update_fields[] = "email = ?";
            $params[] = $email;
            $types .= "s";
        }
        if ($phone && $phone !== $user['phone']) {
            $update_fields[] = "phone = ?";
            $params[] = $phone;
            $types .= "s";
        }
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_fields[] = "password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }

        if (!empty($update_fields)) {
            $query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $params[] = $user_id;
            $types .= "i";

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                header("Location: profile.php?success=Profile updated successfully");
                exit();
            } else {
                $error = "Update failed. Email or phone may already exist.";
            }
        }
    }
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
    <title>Profile - Social App</title>
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
        <h2><?php echo $is_own_profile ? 'My Profile' : htmlspecialchars($user['name']) . "'s Profile"; ?></h2>
        
        <?php if ($is_own_profile && isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if ($is_own_profile && isset($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="fas fa-user me-2"></i> <?php echo $is_own_profile ? 'Your Details' : 'User Details'; ?></div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <img src="Uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" class="rounded-circle me-3" style="width: 80px; height: 80px; object-fit: cover;">
                    <div>
                        <h5><?php echo htmlspecialchars($user['name']); ?></h5>
                        <p class="text-muted mb-1"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="text-muted mb-1"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                        <p class="text-muted mb-0"><i class="fas fa-calendar-alt me-2"></i> Joined: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_own_profile): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-edit me-2"></i> Edit Profile</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Save Changes</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <footer class="footer">
        <p>Social Platform by Mayan Roy </p>
    </footer>
</body>
</html>