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

    if ($action == 'add_member' && isset($_POST['group_id']) && isset($_POST['friend_id'])) {
        $group_id = (int)$_POST['group_id'];
        $friend_id = (int)$_POST['friend_id'];

        $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $is_member = $stmt->get_result()->fetch_assoc()['member_count'] > 0;

        if (!$is_member) {
            $response['message'] = 'You are not a member of this group';
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $group_id, $friend_id);
            $stmt->execute();
            $already_member = $stmt->get_result()->fetch_assoc()['member_count'] > 0;

            if ($already_member) {
                $response['message'] = 'User is already a member of this group';
            } else {
                $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $group_id, $friend_id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Member added successfully'];
                } else {
                    $response['message'] = 'Failed to add member';
                }
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>