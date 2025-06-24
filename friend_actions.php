<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Invalid action'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'send_request' && isset($_POST['receiver_id'])) {
        $receiver_id = (int)$_POST['receiver_id'];
        
        $stmt = $conn->prepare("SELECT * FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $response['message'] = 'Friend request already exists or blocked';
        } else {
            $stmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("ii", $user_id, $receiver_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Friend request sent'];
            } else {
                $response['message'] = 'Failed to send friend request';
            }
        }
    } elseif ($action == 'accept_request' && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];

        $stmt = $conn->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $request_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'Friend request accepted'];
        } else {
            $response['message'] = 'Failed to accept friend request';
        }
    } elseif ($action == 'block_request' && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];

        $stmt = $conn->prepare("UPDATE friend_requests SET status = 'blocked' WHERE id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $request_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'Friend request blocked'];
        } else {
            $response['message'] = 'Failed to block friend request';
        }
    } elseif ($action == 'unfriend' && isset($_POST['friend_id'])) {
        $friend_id = (int)$_POST['friend_id'];

        $stmt = $conn->prepare("DELETE FROM friend_requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = 'accepted'");
        $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response = ['success' => true, 'message' => 'Friend removed'];
        } else {
            $response['message'] = 'Failed to unfriend user';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>