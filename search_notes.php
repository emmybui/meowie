<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user_id'];
    $query = $_POST['query'] ?? '';

    if (empty($query)) {
        echo json_encode([]);
        exit();
    }

    $searchQuery = "SELECT id, title, content, created_at FROM notes 
                    WHERE user_id = ? AND 
                    (LOWER(title) LIKE LOWER(?) OR 
                    LOWER(content) LIKE LOWER(?)) 
                    ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($searchQuery);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $searchTerm = "%$query%";
    $stmt->bind_param("iss", $user_id, $searchTerm, $searchTerm);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $notes = [];

    while ($row = $result->fetch_assoc()) {
        $row['content'] = htmlspecialchars_decode($row['content']);
        $notes[] = $row;
    }

    // Đảm bảo chỉ encode 1 lần
    $response = [
        'success' => true,
        'data' => $notes
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    echo json_encode($response);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>