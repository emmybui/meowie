<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit();
}

$user_id = $_SESSION['user_id'];
$note_id = intval($_POST['note_id']);

$query = "SELECT is_pinned FROM notes WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $note = $result->fetch_assoc();
    $isPinned = $note['is_pinned'] ? 0 : 1;

    $update = $conn->prepare("UPDATE notes SET is_pinned = ?, pinned_at = ? WHERE id = ? AND user_id = ?");
    $pinnedAt = $isPinned ? date("Y-m-d H:i:s") : null;
    $update->bind_param("ssii", $isPinned, $pinnedAt, $note_id, $user_id);
    $update->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}

$conn->close();
?>
