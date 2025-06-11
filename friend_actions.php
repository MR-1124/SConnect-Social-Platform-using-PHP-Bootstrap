<?php
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Invalid action'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'send_request' && isset($_POST['receiver_id'])) {
        $receiver_id = $_POST['receiver_id'];
        

        
        $stmt = $conn->prepare("SELECT id FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $user_id, $receiver_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $receiver_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Friend request sent'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to send request'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Request already sent'];
        }
    } elseif ($action == 'accept_request' && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $stmt = $conn->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $request_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'Friend request accepted'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to accept request'];
        }
    } elseif ($action == 'block_request' && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $stmt = $conn->prepare("UPDATE friend_requests SET status = 'blocked' WHERE id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $request_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'User blocked'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to block user'];
        }
    }
}

echo json_encode($response);
?>