<?php
session_start();
require_once __DIR__ . '/config.php';

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

// Check note_id
if (!isset($_POST['note_id'])) {
    die(json_encode(['success' => false, 'error' => 'Note ID required']));
}

$note_id = (int)$_POST['note_id'];
$user_id = $_SESSION['user_id'];

// Delete note
$query = "DELETE FROM notes WHERE id = $note_id AND user_id = $user_id";
if ($conn->query($query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>