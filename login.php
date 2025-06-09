<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = $_POST['identifier'];
    $password = $_POST['password'];

 
    $stmt = $conn->prepare("SELECT id, password, login_attempts, locked_until FROM users WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($user['locked_until'] && $user['locked_until'] > date('Y-m-d H:i:s')) {
            $error = "Account locked. Try again later.";
        } elseif (password_verify($password, $user['password'])) {
 
            $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            $_SESSION['user_id'] = $user['id'];
            header("Location: dashboard.php");
            exit();
        } else {

            $attempts = $user['login_attempts'] + 1;
            $locked_until = $attempts >= 3 ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : NULL;
            
            $stmt = $conn->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
            $stmt->bind_param("isi", $attempts, $locked_until, $user['id']);
            $stmt->execute();
            
            $error = $attempts >= 3 ? "Too many failed attempts. Account locked for 30 minutes." : "Invalid credentials.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Login</h2>
        <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="identifier" class="form-label">Email or Phone</label>
                <input type="text" class="form-control" id="identifier" name="identifier" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
            <a href="register.php" class="btn btn-link">Register</a>
        </form>
    </div>
</body>
</html>