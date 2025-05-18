<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to check directory permissions
function checkDirectoryPermissions($dir) {
    if (!file_exists($dir)) {
        return false;
    }
    return is_writable($dir);
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $user_id = $_SESSION['user_id'];
    
    // Log file information
    error_log("File upload attempt - Name: " . $file['name'] . ", Type: " . $file['type'] . ", Size: " . $file['size']);
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/octet-stream'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG and GIF are allowed.']);
        exit();
    }
    
    if ($file['size'] > $max_size) {
        error_log("File too large: " . $file['size']);
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
        exit();
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error code: " . $file['error']);
        echo json_encode(['success' => false, 'error' => 'File upload failed with error code: ' . $file['error']]);
        exit();
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/avatars/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create directory: " . $upload_dir);
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory. Check permissions.']);
            exit();
        }
    }

    // Check directory permissions
    if (!checkDirectoryPermissions($upload_dir)) {
        error_log("Directory not writable: " . $upload_dir);
        echo json_encode(['success' => false, 'error' => 'Upload directory is not writable. Check permissions.']);
        exit();
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        $extension = 'jpg'; // Default to jpg for blob data
    }
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    error_log("Attempting to move file to: " . $filepath);
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("File moved successfully to: " . $filepath);
        
        // Check if the file was actually created
        if (!file_exists($filepath)) {
            error_log("File does not exist after move: " . $filepath);
            echo json_encode(['success' => false, 'error' => 'File move succeeded but file does not exist']);
            exit();
        }
        
        try {
            // Delete old avatar if it exists and is not default
            $query = "SELECT avatar FROM users WHERE id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception("Failed to prepare select statement: " . $conn->error);
            }
            
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute select statement: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user['avatar'] && $user['avatar'] !== 'default_avatar.png') {
                $old_avatar = $upload_dir . $user['avatar'];
                if (file_exists($old_avatar)) {
                    if (!unlink($old_avatar)) {
                        error_log("Failed to delete old avatar: " . $old_avatar);
                    } else {
                        error_log("Deleted old avatar: " . $old_avatar);
                    }
                }
            }
            
            // Update database
            $query = "UPDATE users SET avatar = ? WHERE id = ?";
            error_log("Updating database for user_id: " . $user_id . " with filename: " . $filename);
            
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception("Failed to prepare update statement: " . $conn->error);
            }
            
            $stmt->bind_param("si", $filename, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute update statement: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            error_log("Database update affected rows: " . $affected_rows);
            
            if ($affected_rows > 0) {
                // Verify file permissions
                chmod($filepath, 0644);
                echo json_encode([
                    'success' => true, 
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'filesize' => filesize($filepath)
                ]);
                error_log("Avatar update successful. File path: " . $filepath);
            } else {
                throw new Exception("No rows were updated in the database");
            }
            
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            // Delete uploaded file if database update fails
            if (file_exists($filepath)) {
                unlink($filepath);
                error_log("Deleted uploaded file due to database error");
            }
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        $upload_error = error_get_last();
        error_log("Failed to move uploaded file. PHP Error: " . print_r($upload_error, true));
        error_log("Source: " . $file['tmp_name'] . ", Destination: " . $filepath);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to save uploaded file. Check server permissions.',
            'debug' => [
                'source' => $file['tmp_name'],
                'destination' => $filepath,
                'php_error' => $upload_error
            ]
        ]);
    }
} else {
    error_log("No file uploaded or invalid request method");
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
}
?> 