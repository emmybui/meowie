<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit();
}

include('config.php');
$user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['id'], $_POST['title'], $_POST['content'])) {
        throw new Exception('Missing parameters');
    }

    $note_id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if ($title === '' || $content === '') {
        throw new Exception('Title and content cannot be empty');
    }

    // Escape input to prevent SQL injection
    $title = $conn->real_escape_string($title);
    $content = $conn->real_escape_string($content);

    // Kiểm tra xem ghi chú có thuộc về user hiện tại không
    $check_query = $conn->query("SELECT id FROM notes WHERE id = $note_id AND user_id = $user_id");
    if ($check_query->num_rows === 0) {
        throw new Exception('Note not found or access denied');
    }

    // Cập nhật ghi chú
    $update_query = "UPDATE notes SET title = '$title', content = '$content' WHERE id = $note_id";
    if ($conn->query($update_query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Note updated successfully'
        ]);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
