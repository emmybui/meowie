<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID not found']);
    exit();
}

// Get note ID from request
if (!isset($_GET['note_id']) || empty($_GET['note_id'])) {
    echo json_encode(['success' => false, 'error' => 'Note ID is required']);
    exit();
}

$note_id = (int)$_GET['note_id'];

// Check if the note belongs to the user
$check_query = "SELECT id FROM notes WHERE id = $note_id AND user_id = $user_id";
$result = $conn->query($check_query);

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Note not found or unauthorized']);
    exit();
}

// Get users the note is shared with
$query = "SELECT u.id, u.username, u.email, sn.can_edit 
          FROM shared_notes sn
          JOIN users u ON sn.shared_with = u.id
          WHERE sn.note_id = ? AND sn.shared_by = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $note_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'can_edit' => (bool)$row['can_edit']
    ];
}

echo json_encode([
    'success' => true,
    'users' => $users
]);

$stmt->close();
?> 