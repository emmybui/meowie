<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Enable error logging
error_log("toggle_password_protection.php called with params: ".json_encode($_POST));

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

    // Get note ID from POST data
    if (!isset($_POST['note_id']) || empty($_POST['note_id'])) {
        echo json_encode(['success' => false, 'error' => 'Note ID is required']);
        exit();
    }

    $note_id = (int)$_POST['note_id'];
    $action = $_POST['action'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Processing action: $action for note ID: $note_id");

    // Check if the note belongs to the user
    $check_query = "SELECT * FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Note not found or unauthorized']);
        exit();
    }

    $note = $result->fetch_assoc();
    error_log("Found note: " . json_encode($note));

    if ($action === 'enable' && !empty($password)) {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the note
        $update_query = "UPDATE notes SET is_password_protected = 1, password_hash = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('sii', $password_hash, $note_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Password protection enabled',
                'is_password_protected' => true
            ]);
        } else {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        $stmt->close();
    } elseif ($action === 'disable') {
        // If the note is password protected, verify the password first
        if ($note['is_password_protected'] == 1) {
            if (empty($_POST['old_password'])) {
                echo json_encode(['success' => false, 'error' => 'Current password is required']);
                exit();
            }
            
            // Verify the password
            $old_password = $_POST['old_password'];
            if (!password_verify($old_password, $note['password_hash'])) {
                echo json_encode(['success' => false, 'error' => 'Incorrect password']);
                exit();
            }
        }
        
        // Check if user wants to remove password completely or set a new one
        if (!empty($password)) {
            // User wants to change password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_query = "UPDATE notes SET is_password_protected = 1, password_hash = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param('sii', $password_hash, $note_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password has been changed',
                    'is_password_protected' => true
                ]);
            } else {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }
        } else {
            // User wants to remove password protection
            $update_query = "UPDATE notes SET is_password_protected = 0, password_hash = NULL WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param('ii', $note_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password protection disabled',
                    'is_password_protected' => false
                ]);
            } else {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }
        }
        $stmt->close();
    } elseif ($action === 'change_password') {
        // Verify old password first if the note is already protected
        if ($note['is_password_protected'] == 1) {
            $old_password = $_POST['old_password'] ?? '';
            
            if (empty($old_password)) {
                echo json_encode(['success' => false, 'error' => 'Current password is required']);
                exit();
            }
            
            if (!password_verify($old_password, $note['password_hash'])) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
                exit();
            }
        }
        
        if (empty($password)) {
            echo json_encode(['success' => false, 'error' => 'New password is required']);
            exit();
        }
        
        // Hash the new password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the password
        $update_query = "UPDATE notes SET is_password_protected = 1, password_hash = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('sii', $password_hash, $note_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Password changed successfully',
                'is_password_protected' => true
            ]);
        } else {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Error in toggle_password_protection.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 