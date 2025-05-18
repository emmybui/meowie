<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Enable error logging
error_log("get_note.php called with params: ".json_encode($_GET));

// Ensure no warnings or notices are sent to output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

try {
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
    error_log("Fetching note ID: $note_id for user ID: $user_id");

    // Get note information
    $query = "SELECT * FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Check if the note is shared with the current user
        $shared_query = "SELECT n.* FROM notes n
                        JOIN shared_notes sn ON n.id = sn.note_id
                        WHERE n.id = ? AND sn.shared_with = ?";
        $stmt = $conn->prepare($shared_query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('ii', $note_id, $user_id);
        $stmt->execute();
        $shared_result = $stmt->get_result();
        
        if ($shared_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Note not found or unauthorized']);
            exit();
        }
        
        $note = $shared_result->fetch_assoc();
        $note['is_shared'] = true;
    } else {
        $note = $result->fetch_assoc();
        $note['is_shared'] = false;
    }

    // Kiểm tra bảo vệ mật khẩu
    if ($note['is_password_protected'] == 1) {
        // Kiểm tra xem người dùng đã xác thực mật khẩu chưa
        $verified = isset($_SESSION['verified_notes'][$note_id]) && $_SESSION['verified_notes'][$note_id] === true;
        
        if (!$verified) {
            // Trả về thông tin cơ bản mà không có nội dung đầy đủ
            $response_note = [
                'id' => $note['id'],
                'title' => $note['title'],
                'is_password_protected' => 1,
                'requires_password' => true,
                'created_at' => $note['created_at'],
                'is_pinned' => $note['is_pinned'] ?? 0,
            ];
            
            echo json_encode([
                'success' => true,
                'note' => $response_note
            ]);
            exit();
        }
    }

    // Get sharing status
    $share_query = "SELECT COUNT(*) as share_count FROM shared_notes WHERE note_id = ?";
    $stmt = $conn->prepare($share_query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param('i', $note_id);
    $stmt->execute();
    $share_result = $stmt->get_result();
    $share_data = $share_result->fetch_assoc();
    $note['share_count'] = $share_data['share_count'];

    // Remove sensitive information if needed
    if (isset($note['password_hash'])) {
        unset($note['password_hash']);
    }

    error_log("Successfully retrieved note: ".json_encode($note));
    echo json_encode([
        'success' => true,
        'note' => $note
    ]);

    $stmt->close();
} catch (Exception $e) {
    error_log("Error in get_note.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 