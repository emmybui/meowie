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

// Get note ID from POST data
if (!isset($_POST['note_id']) || empty($_POST['note_id'])) {
    echo json_encode(['success' => false, 'error' => 'Note ID is required']);
    exit();
}

$note_id = (int)$_POST['note_id'];
$action = $_POST['action'] ?? '';

// Check if the note belongs to the user
$check_query = "SELECT * FROM notes WHERE id = $note_id AND user_id = $user_id";
$result = $conn->query($check_query);

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Note not found or unauthorized']);
    exit();
}

$note = $result->fetch_assoc();

// Handle different actions
if ($action === 'generate_link') {
    // Generate a unique share code if it doesn't exist
    if (empty($note['share_code'])) {
        $share_code = md5(uniqid(rand(), true));
        
        $update_query = "UPDATE notes SET share_code = ?, is_public = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sii', $share_code, $note_id, $user_id);
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Failed to generate sharing link']);
            exit();
        }
        $stmt->close();
    } else {
        $share_code = $note['share_code'];
        
        // Make sure it's public
        $update_query = "UPDATE notes SET is_public = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $note_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Base URL for sharing
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $share_url = "$protocol://$host/view_shared_note.php?code=$share_code";
    
    echo json_encode([
        'success' => true,
        'share_url' => $share_url,
        'share_code' => $share_code
    ]);
} elseif ($action === 'disable_sharing') {
    // Disable public sharing
    $update_query = "UPDATE notes SET is_public = 0 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ii', $note_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sharing disabled'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to disable sharing']);
    }
    $stmt->close();
} elseif ($action === 'share_with_user') {
    // Share with a specific user
    if (!isset($_POST['share_email']) || empty($_POST['share_email'])) {
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        exit();
    }
    
    $share_email = $conn->real_escape_string($_POST['share_email']);
    $can_edit = isset($_POST['can_edit']) && $_POST['can_edit'] == '1' ? 1 : 0;
    
    // Get user ID from email
    $user_query = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param('s', $share_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $shared_with = $result->fetch_assoc()['id'];
    $stmt->close();
    
    // Don't allow sharing with yourself
    if ($shared_with == $user_id) {
        echo json_encode(['success' => false, 'error' => 'Cannot share with yourself']);
        exit();
    }
    
    // Check if already shared with this user
    $check_shared = "SELECT id FROM shared_notes WHERE note_id = ? AND shared_with = ?";
    $stmt = $conn->prepare($check_shared);
    $stmt->bind_param('ii', $note_id, $shared_with);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if ($exists) {
        // Update existing share
        $update_query = "UPDATE shared_notes SET can_edit = ? WHERE note_id = ? AND shared_with = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iii', $can_edit, $note_id, $shared_with);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Share permissions updated'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update share permissions']);
        }
    } else {
        // Create new share
        $share_query = "INSERT INTO shared_notes (note_id, shared_by, shared_with, can_edit) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($share_query);
        $stmt->bind_param('iiii', $note_id, $user_id, $shared_with, $can_edit);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Note shared successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to share note']);
        }
    }
    $stmt->close();
} elseif ($action === 'remove_share') {
    // Remove sharing with a specific user
    if (!isset($_POST['shared_with']) || empty($_POST['shared_with'])) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit();
    }
    
    $shared_with = (int)$_POST['shared_with'];
    
    $delete_query = "DELETE FROM shared_notes WHERE note_id = ? AND shared_by = ? AND shared_with = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('iii', $note_id, $user_id, $shared_with);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sharing removed'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to remove sharing']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?> 