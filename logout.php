<?php
session_start();
require_once 'config.php';

// Clear remember token from database if it exists
if (isset($_SESSION['user_id'])) {
    try {
        $update_query = "UPDATE users SET remember_token = NULL WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if ($stmt === false) {
            error_log("Failed to prepare statement: " . $conn->error);
        } else {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error in logout.php: " . $e->getMessage());
    }
}

// Clear remember token cookies
setcookie('remember_token', '', time() - 3600, '/');
setcookie('user_id', '', time() - 3600, '/');

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>