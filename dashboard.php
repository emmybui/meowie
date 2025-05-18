<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sửa lại dòng này
require_once __DIR__ . '/config.php';

// Kiểm tra kết nối database
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not properly initialized");
}

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

// Lấy thông tin user từ session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("User not authenticated");
}

// Thư mục upload
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Sửa phần xử lý upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_image') {
    header('Content-Type: application/json');
    
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];
        
        // Kiểm tra lỗi upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
            exit;
        }
        
        // Kiểm tra kích thước file
        if ($file['size'] > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'error' => 'File size exceeds limit']);
            exit;
        }
        
        // Kiểm tra loại file
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ALLOWED_MIME_TYPES)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type']);
            exit;
        }
        
        // Tạo tên file an toàn
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = uniqid('img_', true) . '.' . strtolower($ext);
        $destination = UPLOAD_DIR . $new_name;
        
        // Di chuyển file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Đảm bảo quyền truy cập file
            chmod($destination, 0644);
            
            echo json_encode([
                'success' => true,
                'image_url' => 'uploads/' . $new_name
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        }
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}


// Xử lý POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['content'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $_POST['content'];
    
    // Xử lý cả update nếu có note_id
    if (isset($_POST['note_id']) && !empty($_POST['note_id'])) {
        $note_id = (int)$_POST['note_id'];
        $query = "UPDATE notes SET title='".$conn->real_escape_string($title)."', 
                 content='".$conn->real_escape_string($content)."' 
                 WHERE id=$note_id AND user_id=$user_id";
    } else {
        $query = "INSERT INTO notes (user_id, title, content, created_at) 
                 VALUES ($user_id, 
                        '".$conn->real_escape_string($title)."', 
                        '".$conn->real_escape_string($content)."', 
                        NOW())";
    }
    
    if ($conn->query($query)) {
        $note_id = isset($note_id) ? $note_id : $conn->insert_id;
        echo json_encode([
            'success' => true,
            'note' => [
                'id' => $note_id,
                'title' => $title,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit();
}

// Lấy danh sách ghi chú
$notes = [];
$notes_query = $conn->query("SELECT * FROM notes WHERE user_id = $user_id ORDER BY is_pinned DESC, pinned_at DESC, created_at DESC");

if ($notes_query === false) {
    error_log("Notes query failed: " . $conn->error);
    $error_message = "Failed to load notes. Please try again later.";
} else {
    while ($row = $notes_query->fetch_assoc()) {
        $notes[] = $row;
    }
}   
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>MEOWIÉ Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        window.currentUserId = <?php echo $user_id; ?>;     
        window.sharedNotes = [];    
    </script>
    <style>
        @font-face {
            font-family: "Font";
            src: url("aespa.ttf") format("truetype");
        }

        @font-face {
            font-family: "Font2";
            src: url("HelveticaLTNarrowBold.otf") format("opentype");
        }

        .content-toolbar {
            margin-bottom: 10px;
        }

        .image-preview {
            position: relative;
            display: inline-block;
            margin: 10px 10px 10px 0;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 4px;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            display: block;
        }
        .remove-image-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-image-btn:hover {
            background: #d32f2f;
        }

        #image-preview-container {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
        }
        :root {
            --sidebar-bg: #f7f6f3;
            --primary-color: #2d2d2d;
            --accent-color: #db4c3f;
            --hover-bg: #f0efec;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family:"Font2", -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        body {
            display: flex;
            background-image: url(background_dashboard2.jpg);
            color: var(--primary-color);
        }
        /* Enhanced Sidebar */
        .sidebar {
            width: 200px;
            height: 100vh;
            padding: 24px 16px;
            background-image: url(background_sidebar.jpg);
            position: fixed;
            border-right: 1px solid #e0e0e0;
            position: fixed;
            z-index: 100;
        }
        .logo {
            font-family: "Font", Arial, Helvetica;
            font-weight: 700;
            font-size: 1.5rem;
            padding: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo i {
            color: var(--accent-color);
        }

        .section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 0.75rem;
            color: #5f5f5f;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-left: 8px;
        }

        .menu-item {
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin: 4px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }

        .menu-item:hover {
            background: var(--hover-bg);
            transform: translateX(4px);
        }

        .menu-item.active {
            background: var(--hover-bg);
            font-weight: 500;
        }

        .menu-item i {
            width: 20px;
            color: #666;
        }

        /* Enhanced Search Bar with Hologram Effect */
        .search-container {
            position: relative;
            margin: 20px 0;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #fff;
            color: #333;
        }

        .search-input:focus {
            border-color: #2196F3;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
            outline: none;
            animation: hologram 2s ease-in-out infinite;
        }

        @keyframes hologram {
            0% {
                box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
            }
            50% {
                box-shadow: 0 0 25px rgba(33, 150, 243, 0.5), 
                           0 0 35px rgba(33, 150, 243, 0.3);
            }
            100% {
                box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
            }
        }

        .search-input::placeholder {
            color: #999;
            font-style: italic;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            transition: color 0.3s ease;
        }

        .search-container:focus-within .search-icon {
            color: #2196F3;
        }

        .clear-search {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: none;
        }

        .clear-search:hover {
            background-color: #f0f0f0;
            color: var(--accent-color);
        }

        .search-results {
            position: absolute;
            width: 100%;
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border: 1px solid #2196F3;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.2);
            z-index: 1000;
            display: none;
            margin-top: 8px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-result-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-title {
            font-weight: 500;
            margin-bottom: 4px;
            color: #333;
        }

        .search-result-content {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .search-result-meta {
            font-size: 0.8em;
            color: #888;
        }

        .search-result-highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }

        .no-results {
            padding: 16px;
            text-align: center;
            color: #666;
            font-style: italic;
        }

        /* Scrollbar styling for search results */
        .search-results::-webkit-scrollbar {
            width: 8px;
        }

        .search-results::-webkit-scrollbar-track {
            background: rgba(33, 150, 243, 0.1);
            border-radius: 4px;
        }

        .search-results::-webkit-scrollbar-thumb {
            background: rgba(33, 150, 243, 0.3);
            border-radius: 4px;
        }

        .search-results::-webkit-scrollbar-thumb:hover {
            background: rgba(33, 150, 243, 0.5);
        }

        /* Main Content Improvements */
        .main-content {
            margin-left: 300px;
            padding: 40px;
            width: calc(100% - 300px);
            background: none;
        }

        .event-group {
            margin-bottom: 40px;
            max-width: 800px;
        }

        .time-label {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .time-label i {
            font-size: 0.8rem;
        }

        .task-list {
            padding-left: 32px;
            border-left: 2px solid #eee;
            margin-left: 8px;
        }

        .task-item {
            margin: 12px 0;
            padding: 12px 16px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease;
        }

        .task-item:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        }

        .task-item::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--accent-color);
            border-radius: 50%;
            position: absolute;
            left: -24px;
        }

        .meeting-category {
            color: #666;
            font-size: 0.85em;
            margin: 16px 0 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #eee 50%, transparent 100%);
            margin: 32px 0;
        }

        /* Notes Section */
        .notes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .notes-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .view-options {
            display: flex;
            gap: 12px;
        }

        .view-option {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .view-option:hover {
            background: var(--hover-bg);
        }

        .view-option.active {
            background: var(--hover-bg);
            font-weight: 500;
        }

        /* Grid View */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .note-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid #e0e0e0;
        }        
        
        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .note-title {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .note-content {
            color: #555;
            font-size: 14px;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .note-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
        }

        /* List View */
        .notes-list {
            display: none;
        }

                .note-list-item {            display: flex;            background: white;            border-radius: 8px;            padding: 16px;            margin-bottom: 12px;            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);            transition: all 0.2s ease;            cursor: pointer;            border: 1px solid #e0e0e0;        }        .note-list-item:hover {            transform: translateX(4px);            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);        }

        .list-note-content {
            flex: 1;
        }

        .list-note-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            min-width: 120px;
        }

        /* Empty State */
        .empty-notes {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .empty-notes i {
            font-size: 2rem;
            margin-bottom: 16px;
        }

        /* Form tạo note mới */
        .note-form-container {
            display: none;
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .note-form-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .note-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .note-form-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .close-form-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #888;
        }

        .note-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        #note-content {
            min-height: 200px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--accent-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: #f0f0f0;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .error-message {
            color: #d32f2f;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        /* Modal Styling */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 200px;
            width: calc(100% - 200px);
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .modal {
            background-color: #fff;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 32px;
            position: relative;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 16px;
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d2d2d;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-modal:hover {
            background-color: #f5f5f5;
            color: #333;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background-color: #f8f9fa;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2d2d2d;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(45, 45, 45, 0.1);
        }

        .form-group textarea {
            min-height: 200px;
            resize: vertical;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 32px;
            padding-top: 16px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #2d2d2d;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #1a1a1a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #2d2d2d;
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #fff;
            color: #dc3545;
            border: 2px solid #dc3545;
            margin-right: auto;
        }

        .btn-danger:hover {
            background: #dc3545;
            color: #fff;
            transform: translateY(-1px);
        }

        /*search*/
        .search-result-item mark {
            background-color: #ffe082;
            padding: 0 2px;
            font-weight: bold;
        }

        .highlight {
            background-color: #fff9c4;
            animation: flash 1.5s ease;
        }

        @keyframes flash {
            0% {
                background-color: #fff9c4;
            }

            100% {
                background-color: transparent;
            }
        }

        .toast-notice {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

                /* Note options styling */
                .note-options {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .option-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-option {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-option:hover {
            background-color: #e0e0e0;
            color: #333;
        }

        /* Pin indicator styles */
        .note-card, .note-list-item {
            position: relative;
        }
        
        .pin-indicator {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #ffc107;
            color: #7d5f00;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
            transform: rotate(45deg);
            z-index: 5;
            transition: all 0.3s ease;
        }
        
        .note-card:first-child .pin-indicator,
        .note-list-item:first-child .pin-indicator {
            background-color: #ff9800;
            color: #fff;
            box-shadow: 0 3px 10px rgba(255, 152, 0, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: rotate(45deg) scale(1);
                box-shadow: 0 3px 10px rgba(255, 152, 0, 0.4);
            }
            50% {
                transform: rotate(45deg) scale(1.1);
                box-shadow: 0 3px 15px rgba(255, 152, 0, 0.6);
            }
            100% {
                transform: rotate(45deg) scale(1);
                box-shadow: 0 3px 10px rgba(255, 152, 0, 0.4);
            }
        }
        
        .pin-indicator i {
            font-size: 14px;
        }
        
        .note-card.pinned, .note-list-item.pinned {
            border-left: 3px solid #ffc107;
            box-shadow: 0 3px 8px rgba(255, 193, 7, 0.2);
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo"><i class="fas"></i> MEOWIÉ</div>
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Search notes...">

            <div class="search-results" id="searchResults"></div>
        </div>
        <div class="section">
            <div class="section-title">Option</div>
            <div class="menu-item"><i class="fas fa-tasks"></i> My Note</div>
            <div class="menu-item" id="new-note-btn"><i class="fas fa-plus-square"></i> New Note
            </div>
        </div>
        <div class="divider"></div>
        <div class="section">
            <div class="section-title">Menu</div>
            <div class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-user"></i>
                <a href="profile.php" style="text-decoration: none; color: inherit;">Profile</a>
            </div>
            <div class="menu-item">
                <i class="fas fa-pen-alt"></i>
                <a href="change_password.php" style="text-decoration: none; color: inherit;">Change Password</a>
            </div>
        </div>
        <div class="divider"></div>
        <div class="section">
            <div class="section-title">Account</div>
            <div class="menu-item" id="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log out</span>
            </div>
        </div>
    </div>

    <!-- My Note (main) -->
    <div class="main-content">
        <div class="notes-header">
            <h1 class="notes-title">My Notes</h1>
            <div class="view-options">
                <div class="view-option active" id="grid-view-option"><i class="fas fa-th-large"></i> Grid</div>
                <div class="view-option" id="list-view-option"><i class="fas fa-list-ul"></i> List</div>
            </div>
        </div>

        <?php if (count($notes) > 0): ?>
            <!-- Grid view -->
            <div class="notes-grid" id="notes-grid">
                <?php foreach ($notes as $note): ?>
                    <div class="note-card<?= $note['is_pinned'] ? ' pinned' : '' ?>" data-id="<?= $note['id'] ?>">
                        <?php if($note['is_pinned']): ?>
                            <div class="pin-indicator" title="Pinned note"><i class="fas fa-thumbtack"></i></div>
                        <?php endif; ?>
                        <div class="note-title">
                            <?= htmlspecialchars($note['title']) ?>
                            <?php if(isset($note['is_password_protected']) && $note['is_password_protected'] == 1): ?>
                                <i class="fas fa-lock lock-icon" style="display:inline-block;" title="Password protected"></i>
                            <?php endif; ?>
                        </div>

                        <div class="note-content"><?= htmlspecialchars_decode($note['content']) ?></div>
                        <div class="note-meta">
                            <span><?= date('M j, Y', strtotime($note['created_at'])) ?></span>
                            <div class="note-actions">
                                <span class="pin-icon <?= $note['is_pinned'] ? 'pinned' : '' ?>" onclick="togglePin(event, <?= $note['id'] ?>)" title="Toggle pin">
                                    <i class="fas fa-thumbtack"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List view -->
            <div class="notes-list" id="notes-list" style="display: none;">
                <?php foreach ($notes as $note): ?>
                    <div class="note-list-item<?= $note['is_pinned'] ? ' pinned' : '' ?>" data-id="<?= $note['id'] ?>">
                        <?php if($note['is_pinned']): ?>
                            <div class="pin-indicator" title="Pinned note"><i class="fas fa-thumbtack"></i></div>
                        <?php endif; ?>
                        <div class="list-note-content">
                            <div class="note-title">
                                <?= htmlspecialchars($note['title']) ?>
                                <?php if(isset($note['is_password_protected']) && $note['is_password_protected'] == 1): ?>
                                    <i class="fas fa-lock lock-icon" style="display:inline-block;" title="Password protected"></i>
                                <?php endif; ?>
                            </div>
                            <div class="note-content"><?= htmlspecialchars_decode($note['content']) ?></div>
                        </div>
                        <div class="list-note-meta">
                            <span><?= date('M j, Y', strtotime($note['created_at'])) ?></span>
                            <div class="note-actions">
                                <span class="pin-icon <?= $note['is_pinned'] ? 'pinned' : '' ?>" onclick="togglePin(event, <?= $note['id'] ?>)" title="Toggle pin">
                                    <i class="fas fa-thumbtack"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-notes">
                <i class="fas fa-sticky-note"></i>
                <h3>No notes yet</h3>
                <p>Create your first note by clicking "New Note" in the sidebar</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Note -->
    <div class="modal-overlay" id="new-note-modal" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">Create New Note</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="new-note-form">
                <input type="hidden" id="note-id" name="note_id" value="">
                <div class="form-group">
                    <label for="note-title">Title</label>
                    <input type="text" id="note-title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="note-content">Content</label>
                    <div class="content-toolbar">
                        <button type="button" id="attach-image-btn" class="btn btn-secondary">
                            <i class="fas fa-paperclip"></i> Attach Image
                        </button>
                    </div>
                    <textarea id="note-content" name="content" required rows="10"></textarea>
                    <div id="image-preview-container"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="delete-note-btn" style="display: none;">Delete</button>
                    <!--<button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button> -->
                    <button type="button" class="btn btn-secondary" id="modal-password-btn">
                        <i class="fas fa-lock"></i> Password
                    </button>
                    <button type="button" class="btn btn-secondary" id="modal-share-btn">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {

            // Xử lý upload ảnh
        $('#attach-image-btn').click(function() {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();
            
            input.onchange = function() {
                const file = input.files[0];
                if (!file) return;
                
                const formData = new FormData();
                formData.append('image', file);
                formData.append('action', 'upload_image');
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        if (data.success) {
                            // Thêm ảnh vào textarea content
                            const currentContent = $('#note-content').val();
                            const imgTag = `<img src="${data.image_url}" alt="Uploaded Image" style="max-width: 100%; height: auto;">`;
                            $('#note-content').val(currentContent + (currentContent ? '\n\n' : '') + imgTag);
                            // Hiển thị preview
                            $('#image-preview-container').append(
                                `<div class="image-preview">
                                    <img src="${data.image_url}" style="max-width: 200px; max-height: 200px;">
                                    <button class="remove-image-btn" data-url="${data.image_url}">&times;</button>
                                </div>`
                            );
                            
                            setTimeout(() => {
                                autoSaveNote();
                                closeModal();
                            }, 3000);
                        } else {
                            showToast('Upload failed: ' + (data.error || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        showToast('Error uploading image!', 'error');
                    }
                });
            };
        });

        // Xử lý xóa ảnh preview
        $(document).on('click', '.remove-image-btn', function() {
            const imageUrl = $(this).data('url');
            $(this).parent().remove();
            
            // Xóa thẻ img khỏi nội dung
            const content = $('#note-content').val();
            const updatedContent = content.replace(new RegExp(`<img[^>]*src="${imageUrl}"[^>]*>`, 'g'), '');
            $('#note-content').val(updatedContent);
            
            triggerAutoSave();
        });

        // Thêm đoạn này vào trong $(document).ready()
        $('#logout-btn').on('click', function() {
            console.log("Logout button clicked"); // Kiểm tra xem hàm có được gọi
            if (confirm("Do you want to log out?")) {
                console.log("User confirmed logout"); // Kiểm tra xác nhận
                window.location.href = "logout.php";
            }
        });
        // Biến cờ kiểm soát
        let saveTimeout;
        let isModalOpen = false;
        let currentNoteId = null;

        // Hàm chung
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'success') {
            const toast = $(`<div class="toast-notice ${type}">${message}</div>`)
                .appendTo('body')
                .fadeIn();
            
            setTimeout(() => toast.fadeOut(() => toast.remove()), 3000);
        }

        // Mở modal
        $('#new-note-btn').click(function() {
            resetModal();
            $('#modal-title').text('New Note');
            $('#new-note-modal').show();
            $('#note-title').focus();
            $('#delete-note-btn').hide();

            $('#modal-password-btn').show();
            $('#modal-share-btn').hide(); // Hide share button for new notes
            isModalOpen = true;
            setupAutoSave();
        });

        // Đóng modal
        $('.close-modal').click(closeModal);

        function resetModal() {
            clearTimeout(saveTimeout);
            currentNoteId = null;
            $('#note-id').val('');
            $('#note-title').val('');
            $('#note-content').val('');
            $('#image-preview-container').empty(); // Thêm dòng này để xóa preview ảnh
        }

        function closeModal() {
            clearTimeout(saveTimeout);
            isModalOpen = false;
            $('#new-note-modal').hide();
        }

        // Auto-save system
        function setupAutoSave() {
            clearTimeout(saveTimeout);
            
            $('#note-title, #note-content').off('input').on('input', function() {
                if (!isModalOpen) return;
                
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(autoSaveNote, 1000);
            });
        }

        function autoSaveNote() {
            if (!isModalOpen) return;
            
            const title = $('#note-title').val().trim();
            const content = $('#note-content').val().trim();
            const noteId = $('#note-id').val();
            
            if (title && content) {
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: { 
                        note_id: noteId,
                        title: title,
                        content: content 
                    },
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                showToast('Note saved successfully!');
                                
                                if (!noteId) {
                                    $('#note-id').val(data.note.id);
                                    currentNoteId = data.note.id;
                                    $('#modal-share-btn').show(); // Show share button after note is saved
                                    addNewNoteToUI(data.note);
                                } else {
                                    updateNoteInUI(data.note);
                                }
                                setTimeout(() => {
                                    closeModal();
                                }, 500);
                            }
                        } catch (e) {
                            console.error("Error parsing response:", e);
                        }
                    },
                    error: function(xhr) {
                        showToast('Error saving note!', 'error');
                        console.error("AJAX error:", xhr.responseText);
                    }
                });
            }
        }

        // Mở modal edit khi click vào note
        $(document).on('click', '.note-card, .note-list-item', function() {
            const noteId = $(this).data('id');
            const isProtected = $(this).find('.lock-icon').length > 0 && $(this).find('.lock-icon').css('display') !== 'none';
            
            if (isProtected) {
                // Hiển thị modal xác thực mật khẩu
                $('#verify-note-id').val(noteId);
                $('#note-password').val('');
                $('#password-error-message').hide();
                $('#password-verify-modal').show();
            } else {
                // Mở ghi chú ngay lập tức nếu không có mật khẩu
                openNote(noteId);
            }
        });
        
        // Xử lý form xác thực mật khẩu
        $('#verify-password-form').submit(function(e) {
            e.preventDefault();
            
            const noteId = $('#verify-note-id').val();
            const password = $('#note-password').val();
            
            if (!password) {
                $('#password-error-message').text('Please enter the password').show();
                return;
            }
            
            // Hiển thị nút đang xử lý
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Verifying...');
            submitBtn.prop('disabled', true);
            
            // Gửi yêu cầu xác thực mật khẩu
            $.ajax({
                url: 'verify_note_password.php',
                method: 'POST',
                data: {
                    note_id: noteId,
                    password: password
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Mật khẩu đúng, hiệu ứng thành công
                        $('.lock-animation').addClass('unlock-success');
                        
                        setTimeout(() => {
                            // Đóng modal và mở ghi chú sau khi hiệu ứng hoàn thành
                            $('#password-verify-modal').hide();
                            openNote(noteId);
                        }, 1000);
                    } else {
                        // Mật khẩu sai, hiển thị thông báo lỗi và hiệu ứng rung lắc
                        $('#password-error-message').text(response.error || 'Incorrect password').show();
                        $('#verify-password-form').addClass('shake-error');
                        
                        // Xóa hiệu ứng sau khi hoàn thành
                        setTimeout(() => {
                            $('#verify-password-form').removeClass('shake-error');
                        }, 500);
                        
                        // Khôi phục nút
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    console.error('Error verifying password:', xhr.responseText);
                    $('#password-error-message').text('Error verifying password. Please try again.').show();
                    
                    // Khôi phục nút
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }
            });
        });
        
        // Xử lý nút hiển thị/ẩn mật khẩu
        $(document).on('click', '.toggle-password', function() {
            const passwordInput = $(this).closest('.password-input-wrapper').find('input');
            const icon = $(this).find('i');
            
            // Chuyển đổi loại input giữa password và text
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
        
        // Hàm mở ghi chú
        function openNote(noteId) {
            $.ajax({
                url: 'get_note.php',
                method: 'GET',
                data: { note_id: noteId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const note = response.note;
                        
                        // Kiểm tra xem ghi chú có yêu cầu mật khẩu không
                        if (note.requires_password) {
                            // Hiển thị modal xác thực mật khẩu
                            $('#verify-note-id').val(noteId);
                            $('#note-password').val('');
                            $('#password-error-message').hide();
                            $('#password-verify-modal').show();
                            return;
                        }
                        
                        resetModal();
                        currentNoteId = noteId;
                        $('#note-id').val(noteId);
                        $('#note-title').val(note.title);
                        $('#note-content').val(note.content);
                        $('#modal-title').text('Edit Note');
                        $('#modal-password-btn').show();
                        $('#modal-share-btn').show();
                        
                        // Xóa preview cũ
                        $('#image-preview-container').empty();
                        // Tìm tất cả thẻ img trong content và thêm vào preview
                        const tempDiv = $('<div>').html(note.content);
                        tempDiv.find('img').each(function() {
                            const imgSrc = $(this).attr('src');
                            $('#image-preview-container').append(
                                `<div class="image-preview">
                                    <img src="${imgSrc}" style="max-width: 200px; max-height: 200px;">
                                    <button class="remove-image-btn" data-url="${imgSrc}">&times;</button>
                                </div>`
                            );
                        });
                        
                        $('#new-note-modal').show();
                        $('#delete-note-btn').show();
                        isModalOpen = true;
                        setupAutoSave();
                    } else {
                        showToast(response.error || 'Failed to load note', 'error');
                    }
                },
                error: function(xhr) {
                    console.error('Error loading note:', xhr.responseText);
                    showToast('Error loading note. Please try again.', 'error');
                }
            });
        }

        // Handle password button click
        $('#modal-password-btn').click(function() {
            const noteId = $('#note-id').val() || currentNoteId;
            if (!noteId) {
                showToast('Please save the note first', 'error');
                return;
            }
            
            console.log('Password button clicked for note ID:', noteId);
            
            // Show loading indicator
            showToast('Loading note information...', 'info');
            
            // Get note info to check if it's password protected
            $.ajax({
                url: 'get_note.php',
                method: 'GET',
                data: { note_id: noteId },
                dataType: 'json',
                success: function(response) {
                    console.log('Note info response:', response);
                    if (response && response.success) {
                        const isProtected = response.note.is_password_protected == 1;
                        console.log('Note is currently password protected:', isProtected);
                        togglePasswordProtection(null, noteId, isProtected);
                    } else {
                        showToast(response?.error || 'Failed to get note information', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.error('Response Text:', xhr.responseText);
                    showToast('Failed to get note information. Please check the console for details.', 'error');
                    
                    try {
                        // Attempt to log the response if it's JSON
                        const errorResponse = JSON.parse(xhr.responseText);
                        console.log('Parsed error response:', errorResponse);
                    } catch(e) {
                        // If it's not JSON, log the raw response
                        console.log('Raw error response:', xhr.responseText);
                    }
                }
            });
        });
        
            // Handle share button click
        $('#modal-share-btn').click(function() {
            const noteId = $('#note-id').val() || currentNoteId;
            if (!noteId) {
                showToast('Please save the note first', 'error');
                return;
            }
            
            shareNote(null, noteId);
        });

        // Xử lý xóa note
        $('#delete-note-btn').click(function() {
            const noteId = $('#note-id').val();
            if (noteId && confirm('Are you sure you want to delete this note?')) {
                $.ajax({
                    url: 'delete_note.php',
                    method: 'POST',
                    data: { note_id: noteId },
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                showToast('Note deleted successfully!');
                                removeNoteFromUI(noteId);
                                closeModal();
                            }
                        } catch (e) {
                            console.error("Error parsing response:", e);
                        }
                    },
                    error: function(xhr) {
                        showToast('Error deleting note!', 'error');
                        console.error("AJAX error:", xhr.responseText);
                    }
                });
            }
        });

        function removeNoteFromUI(noteId) {
            $(`.note-card[data-id="${noteId}"], .note-list-item[data-id="${noteId}"]`).remove();
            if ($('.note-card').length === 0) {
                $('.empty-notes').show();
            }
        }

        function addNewNoteToUI(note) {
            const date = new Date(note.created_at).toLocaleDateString('en-US', {
                month: 'short', 
                day: 'numeric', 
                year: 'numeric'
            });

            const noteHTML = `
                <div class="note-card" data-id="${note.id}">
                    <div class="note-title">${escapeHtml(note.title)}</div>
                    <div class="note-content">${escapeHtml(note.content.substring(0, 200))}</div>
                    <div class="note-meta">
                        <span>${date}</span>
                        <span><i class="fas fa-edit"></i></span>
                    </div>
                </div>`;

            $('#notes-grid').prepend(noteHTML);
            $('.empty-notes').hide();
        }

        function updateNoteInUI(note) {
            const noteElement = $(`.note-card[data-id="${note.id}"], .note-list-item[data-id="${note.id}"]`);
            if (noteElement.length) {
                noteElement.find('.note-title').text(note.title);
                noteElement.find('.note-content').text(note.content.substring(0, 200));
                
                const date = new Date(note.created_at).toLocaleDateString('en-US', {
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric'
                });
                noteElement.find('.note-meta span:first').text(date);

                            // Update lock icon if needed
                if ('is_password_protected' in note) {
                    if (note.is_password_protected == 1) {
                        if (noteElement.find('.lock-icon').length === 0) {
                            noteElement.find('.note-title').append('<i class="fas fa-lock lock-icon" style="display:inline-block;" title="Password protected"></i>');
                        } else {
                            noteElement.find('.lock-icon').css('display', 'inline-block');
                        }
                    } else {
                        noteElement.find('.lock-icon').css('display', 'none');
                    }
                }
            }
        }

        // Chuyển đổi view
        $('#grid-view-option').click(function() {
            $('#notes-grid').show();
            $('#notes-list').hide();
            $(this).addClass('active');
            $('#list-view-option').removeClass('active');
        });

        $('#list-view-option').click(function() {
            $('#notes-grid').hide();
            $('#notes-list').show();
            $(this).addClass('active');
            $('#grid-view-option').removeClass('active');
        });

            // Password form submission
        $('#password-form').submit(function(e) {
            e.preventDefault();
            
            const noteId = $('#password-note-id').val();
            const action = $('#password-action').value || $('#password-action').val(); // Try both ways to get value
            const newPassword = $('#new-password').val();
            const oldPassword = $('#old-password').val() || '';
            
            console.log('Form submission:', { action, noteId });
            
            if (action === 'enable' && !newPassword) {
                showToast('Please enter a new password', 'error');
                return false;
            }
            
            if ((action === 'disable' || action === 'change_password') && !oldPassword) {
                showToast('Please enter your current password', 'error');
                return false;
            }
            
            const formData = new FormData();
            formData.append('note_id', noteId);
            formData.append('action', action);
            
            // For disable action with new password, we're changing the password
            if (action === 'disable') {
                formData.append('password', newPassword);
                formData.append('old_password', oldPassword);
            } else {
                formData.append('password', newPassword);
                if (action === 'change_password') {
                    formData.append('old_password', oldPassword);
                }
            }
            
            // For debugging
            console.log('Sending data to server:');
            console.log('  - Action:', action);
            console.log('  - Note ID:', noteId);
            console.log('  - Has old password:', !!oldPassword.length);
            console.log('  - Has new password:', !!newPassword.length);
            
            // Show loading indicator
            showToast('Processing...', 'info');
            
            fetch('toggle_password_protection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    $('#password-action-modal').hide();
                    showToast(data.message);
                    
                    // Update UI if necessary
                    if (noteId) {
                        $.ajax({
                            url: 'get_note.php',
                            method: 'GET',
                            data: { note_id: noteId },
                            success: function(response) {
                                if (response.success) {
                                    updateNoteInUI(response.note);
                                    closeModal(); // Close the note editing modal and return to dashboard
                                }
                            }
                        });
                    }
                } else {
                    showToast(data.error || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to process request', 'error');
            });
        });
        
        // Search functionality
        let searchTimeout;
        $('#searchInput').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length === 0) {
                $('#searchResults').hide().empty();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: 'search_notes.php',
                    method: 'POST',
                    data: { query: query },
                    dataType: 'json', // Thêm dòng này để jQuery tự parse JSON
                    success: function(response) {
                        if (response.success) {
                            displaySearchResults(response.data);
                        } else {
                            console.error('Search error:', response.error);
                            $('#searchResults').html('<div class="no-results">Search error: ' + response.error + '</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        $('#searchResults').html('<div class="no-results">Search failed. Please try again.</div>').show();
                    }
                });
            }, 300);
        });

        function displaySearchResults(results) {
            const $resultsContainer = $('#searchResults');
            $resultsContainer.empty();
            
            if (results.length === 0) {
                $resultsContainer.append('<div class="no-results">No results found</div>');
            } else {
                results.forEach(note => {
                    // Highlight matching text
                    const highlightedTitle = highlightText(note.title, $('#searchInput').val());
                    const highlightedContent = highlightText(note.content.substring(0, 100), $('#searchInput').val());
                    
                    const resultItem = $(`
                        <div class="search-result-item" data-id="${note.id}">
                            <div class="search-result-title">${highlightedTitle}</div>
                            <div class="search-result-content">${highlightedContent}...</div>
                            <div class="search-result-meta">
                                ${new Date(note.created_at).toLocaleDateString()}
                            </div>
                        </div>
                    `);
                    
                    // Double click to open note
                    resultItem.on('dblclick', function() {
                        openNoteModal(note.id);
                    });
                    
                    $resultsContainer.append(resultItem);
                });
            }
            
            $resultsContainer.show();
        }

        function highlightText(text, query) {
            if (!query) return text;
            const regex = new RegExp(query, 'gi');
            return text.replace(regex, match => `<mark class="highlight">${match}</mark>`);
        }

        function openNoteModal(noteId) {
            const noteElement = $(`.note-card[data-id="${noteId}"], .note-list-item[data-id="${noteId}"]`);
            if (noteElement.length) {
                noteElement.trigger('click');
            }
            $('#searchResults').hide();
        }

        // Close search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#searchInput, #searchResults').length) {
                $('#searchResults').hide();
            }
        });

        // Define global functions for password protection and sharing
        window.togglePin = function(event, noteId) {
            event.stopPropagation();
            
            fetch('toggle_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'note_id=' + noteId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI for both grid and list views
                    const gridNote = document.querySelector(`.notes-grid .note-card[data-id="${noteId}"]`);
                    const listNote = document.querySelector(`.notes-list .note-list-item[data-id="${noteId}"]`);
                    const gridPinIcon = gridNote?.querySelector('.pin-icon');
                    const listPinIcon = listNote?.querySelector('.pin-icon');

                    if (gridNote) {
                        gridNote.classList.toggle('pinned');
                        if (gridPinIcon) {
                            gridPinIcon.classList.toggle('pinned');
                        }
                    }

                    if (listNote) {
                        listNote.classList.toggle('pinned');
                        if (listPinIcon) {
                            listPinIcon.classList.toggle('pinned');
                        }
                    }

                    // Reorder notes
                    reorderNotes();
                }
            })
            .catch(error => console.error('Error:', error));
        };

        // Function to reorder notes based on pin status
        function reorderNotes() {
            const gridContainer = document.getElementById('notes-grid');
            const listContainer = document.getElementById('notes-list');
            
            // Reorder grid view
            if (gridContainer) {
                const gridNotes = Array.from(gridContainer.children);
                gridNotes.sort((a, b) => {
                    const aPinned = a.classList.contains('pinned');
                    const bPinned = b.classList.contains('pinned');
                    
                    // First sort by pinned status
                    if (aPinned !== bPinned) {
                        return bPinned ? 1 : -1;
                    }
                    
                    // If both are pinned or both are not pinned, sort by creation timestamp
                    const aId = parseInt(a.dataset.id);
                    const bId = parseInt(b.dataset.id);
                    
                    // Higher IDs are generally more recent
                    return bId - aId;
                });
                
                // Reorder with pinned items at the top
                gridNotes.forEach(note => {
                    if (note.classList.contains('pinned')) {
                        gridContainer.prepend(note);
                    } else {
                        gridContainer.appendChild(note);
                    }
                });
            }

            // Reorder list view
            if (listContainer) {
                const listNotes = Array.from(listContainer.children);
                
                // Reorder with pinned items at the top
                listNotes.forEach(note => {
                    if (note.classList.contains('pinned')) {
                        listContainer.prepend(note);
                    } else {
                        listContainer.appendChild(note);
                    }
                });
            }
        }

        window.togglePasswordProtection = function(event, noteId, isProtected) {
            if (event) {
                event.stopPropagation();
            }
            
            const actionModal = document.getElementById('password-action-modal');
            const passwordForm = document.getElementById('password-form');
            const oldPasswordField = document.getElementById('old-password-field');
            const oldPasswordLabel = document.getElementById('old-password-label');
            const passwordTitle = document.getElementById('password-modal-title');
            const noteIdInput = document.getElementById('password-note-id');
            const actionInput = document.getElementById('password-action');
            
            // Reset form
            passwordForm.reset();
            
            // Configure the modal based on current protection status
            if (isProtected) {
                // If password is already set, we want to disable it or change it
                passwordTitle.textContent = 'Password Protection Settings';
                actionInput.value = 'disable';
                oldPasswordField.style.display = 'block';
                oldPasswordLabel.textContent = 'Current Password:';
                $('#new-password').attr('placeholder', 'New password (leave blank to remove protection)');
                // Always show new password field for both disable and change
                document.querySelector('label[for="new-password"]').parentElement.style.display = 'block';
            } else {
                // If no password is set, we want to enable it - require new password
                passwordTitle.textContent = 'Enable Password Protection';
                actionInput.value = 'enable';
                oldPasswordField.style.display = 'none';
                // Show new password field as it's required for enabling
                document.querySelector('label[for="new-password"]').parentElement.style.display = 'block';
                $('#new-password').attr('placeholder', 'Enter new password');
            }
            
            noteIdInput.value = noteId;
            actionModal.style.display = 'flex';
            
            // Focus on the appropriate field
            setTimeout(() => {
                if (isProtected) {
                    document.getElementById('old-password').focus();
                } else {
                    document.getElementById('new-password').focus();
                }
            }, 100);
        };

        window.changeNotePassword = function(event, noteId) {
            if (event) {
                event.stopPropagation();
            }
            
            const actionModal = document.getElementById('password-action-modal');
            const passwordTitle = document.getElementById('password-modal-title');
            const noteIdInput = document.getElementById('password-note-id');
            const actionInput = document.getElementById('password-action');
            const oldPasswordField = document.getElementById('old-password-field');
            const oldPasswordLabel = document.getElementById('old-password-label');
            
            passwordTitle.textContent = 'Change Note Password';
            actionInput.value = 'change_password';
            noteIdInput.value = noteId;
            oldPasswordField.style.display = 'block';
            oldPasswordLabel.textContent = 'Current Password:';
            
            actionModal.style.display = 'flex';
            
            setTimeout(() => {
                document.getElementById('old-password').focus();
            }, 100);
        };

        window.shareNote = function(event, noteId) {
            if (event) {
                event.stopPropagation();
            }
            
            // Set note ID for sharing
            document.getElementById('share-note-id').value = noteId;
            
            // Show/hide sections based on current sharing status
            loadSharedUsers(noteId);
            
            // Generate sharing link
            generateSharingLink(noteId);
            
            // Display the modal
            document.getElementById('share-note-modal').style.display = 'flex';
        };

        window.copyShareLink = function() {
            const shareUrlDisplay = document.getElementById('share-url-display');
            shareUrlDisplay.select();
            document.execCommand('copy');
            
            // Show toast notification
            const toast = document.createElement('div');
            toast.className = 'toast success';
            toast.textContent = 'Link copied to clipboard!';
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        };

        // Function to generate sharing link
        function generateSharingLink(noteId) {
            fetch('share_note.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `note_id=${noteId}&action=generate_link`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('share-link-section').style.display = 'block';
                    document.getElementById('share-url-display').value = data.share_url;
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to generate sharing link', 'error');
            });
        }

        // Function to load users with whom the note is shared
        function loadSharedUsers(noteId) {
            fetch(`get_shared_users.php?note_id=${noteId}`)
            .then(response => response.json())
            .then(data => {
                const sharedUsersList = document.getElementById('shared-users-list');
                
                if (data.success && data.users.length > 0) {
                    sharedUsersList.innerHTML = data.users.map(user => `
                        <div class="shared-user-item">
                            <div class="shared-user-info">
                                <span>${user.username || user.email}</span>
                                <small>${user.email}</small>
                            </div>
                            <div class="shared-user-actions">
                                <div class="permission-label">Can edit:</div>
                                <label class="switch">
                                    <input type="checkbox" ${user.can_edit == 1 ? 'checked' : ''} 
                                        onchange="updateSharePermission(${noteId}, ${user.id}, this.checked)">
                                    <span class="slider round"></span>
                                </label>
                                <button class="remove-share-btn" onclick="removeSharing(${noteId}, ${user.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    sharedUsersList.innerHTML = '<p>Not shared with any users yet.</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('shared-users-list').innerHTML = 
                    '<p>Failed to load shared users. Please try again.</p>';
            });
        }

        // Function to update sharing permissions
        window.updateSharePermission = function(noteId, userId, canEdit) {
            fetch('share_note.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `note_id=${noteId}&action=share_with_user&shared_with=${userId}&can_edit=${canEdit ? 1 : 0}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Permissions updated');
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to update permissions', 'error');
            });
        };

        // Function to show toast messages
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
    });
</script>

<style>
    .btn-danger {
        background: #f44336;
        color: white;
        border: none;
        margin-right: auto; /* Đẩy nút Delete sang trái */
    }

    .btn-danger:hover {
        background: #d32f2f;
    }

    .note-content {
        color: #555;
        font-size: 14px;
        margin-bottom: 12px;
        overflow: hidden;
        text-overflow: ellipsis;
        /* Bỏ -webkit-line-clamp để hiển thị ảnh */
    }
    .note-content img {
        max-width: 100%;
        height: auto;
        display: block;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin: 5px 0;
    }
    .toast-notice {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 24px;
        border-radius: 4px;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
        color: white;
    }
    #image-preview-container {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        max-height: 300px;
        overflow-y: auto;
    }

    .image-preview {
        position: relative;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        background: white;
    }

    .image-preview img {
        max-width: 200px;
        max-height: 200px;
        display: block;
    }
    #note-content {
        min-height: 200px;
        white-space: pre-wrap; /* Giữ nguyên định dạng HTML */
    }
    /* Đảm bảo modal hiển thị đủ lớn */
    .modal {
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    #image-preview-container {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        max-height: 200px;
        overflow-y: auto;
        padding: 5px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    .remove-image-btn {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #f44336;
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .toast-notice.success { background: #4CAF50; }
    .toast-notice.error { background: #f44336; }
    @keyframes slideIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    /* Thêm vào phần style */
    .search-results {
        position: absolute;
        width: 100%;
        max-height: 400px;
        overflow-y: auto;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
        margin-top: 5px;
    }

    .search-result-item {
        padding: 12px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background 0.2s;
    }

    .search-result-item:hover {
        background-color: #f5f5f5;
    }

    .search-result-title {
        font-weight: 500;
        margin-bottom: 4px;
    }

    .search-result-content {
        font-size: 0.9em;
        color: #666;
        margin-bottom: 4px;
    }

    .search-result-meta {
        font-size: 0.8em;
        color: #888;
    }

    .highlight {
        background-color: #ffeb3b;
        padding: 0 2px;
        border-radius: 2px;
    }

    .no-results {
        padding: 12px;
        color: #888;
        text-align: center;
        font-style: italic;
    }
    
    /* Cải thiện giao diện các modal */
    .modal-overlay {
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal, .modal-container {
        animation: modalSlideIn 0.4s ease-out;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
        background: linear-gradient(to bottom, #ffffff, #f8f9fa);
        border: none;
    }

    @keyframes modalSlideIn {
        from { 
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 20px;
        position: relative;
    }

    .modal-header:after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 80px;
        height: 2px;
        background: linear-gradient(to right, #db4c3f, #ff9800);
    }

    .modal-title {
        font-size: 1.8rem;
        background: linear-gradient(to right, #2d2d2d, #666);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
    }

    .modal-close, .close-modal {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f5f5f5;
        color: #666;
        font-size: 22px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
    }

    .modal-close:hover, .close-modal:hover {
        background: #e0e0e0;
        color: #333;
        transform: rotate(90deg);
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group label {
        display: block;
        font-size: 0.95rem;
        font-weight: 500;
        color: #555;
        margin-bottom: 10px;
        transition: color 0.3s;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background-color: #f8f9fa;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #db4c3f;
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(219, 76, 63, 0.1);
    }

    .form-group:focus-within label {
        color: #db4c3f;
    }

    .btn {
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
    }

    .btn-primary {
        background: linear-gradient(to right, #db4c3f, #e74c3c);
        color: white;
        box-shadow: 0 4px 10px rgba(219, 76, 63, 0.2);
    }

    .btn-primary:hover {
        background: linear-gradient(to right, #c0392b, #d35400);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(219, 76, 63, 0.3);
    }

    .btn-secondary {
        background: #f0f0f0;
        color: #555;
        border: 1px solid #ddd;
    }

    .btn-secondary:hover {
        background: #e0e0e0;
        transform: translateY(-2px);
    }

    /* Cải thiện giao diện danh sách ghi chú */
    .note-card {
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .note-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(to bottom, #db4c3f, #e74c3c);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .note-card:hover::before {
        opacity: 1;
    }

    .note-card:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .note-title {
        font-weight: 600;
        position: relative;
        padding-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .lock-icon {
        color: #db4c3f;
        margin-left: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .note-card:hover .lock-icon {
        transform: scale(1.2);
    }

    .password-prompt-message {
        text-align: center;
        margin-bottom: 20px;
    }

    .password-prompt-message i {
        display: block;
        margin: 0 auto 15px;
        font-size: 40px;
        color: #db4c3f;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    /* Cải thiện thanh tìm kiếm */
    .search-input {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 2px solid rgba(219, 76, 63, 0.1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s;
    }

    .search-input:focus {
        background: white;
        border-color: #db4c3f;
        box-shadow: 0 6px 20px rgba(219, 76, 63, 0.15);
    }

    .note-list-item {
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .note-list-item:hover {
        transform: translateX(5px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.12);
    }

    /* Hiệu ứng cho thông báo */
    .toast-notice {
        border-radius: 10px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    /* Style cho modal xác thực mật khẩu */
    #password-verify-modal .password-prompt-message {
        background: rgba(255,255,255,0.9);
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        text-align: center;
    }
    
    .password-prompt-message p {
        font-size: 16px;
        margin-bottom: 5px;
        color: #333;
    }
    
    .password-prompt-message .subtitle {
        font-size: 14px;
        color: #777;
    }

    .lock-animation {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #db4c3f, #ff9800);
        border-radius: 50%;
        margin: 0 auto 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        box-shadow: 0 6px 20px rgba(219, 76, 63, 0.3);
        animation: pulse 2s infinite;
    }

    .lock-animation i {
        font-size: 36px;
        color: white;
    }

    .lock-animation::before {
        content: '';
        position: absolute;
        width: 90%;
        height: 90%;
        border-radius: 50%;
        border: 2px dashed white;
        animation: spin 15s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    #note-password {
        letter-spacing: 2px;
        font-size: 1.2rem;
        padding-right: 40px;
    }
    
    .password-input-wrapper {
        position: relative;
    }
    
    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #777;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        padding: 5px;
    }
    
    .toggle-password:hover {
        color: #db4c3f;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .btn-primary i {
        margin-right: 8px;
    }
    
    /* Hiệu ứng thông báo thành công khi mở khóa */
    .unlock-success {
        animation: unlockSuccess 1s forwards;
    }
    
    @keyframes unlockSuccess {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(0); opacity: 0; }
    }
    
    /* Hiệu ứng lắc khi mật khẩu sai */
    .shake-error {
        animation: shakeError 0.5s;
    }
    
    @keyframes shakeError {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-10px); }
        40%, 80% { transform: translateX(10px); }
    }
    
    /* Cải thiện giao diện modal cài đặt mật khẩu */
    #password-action-modal .modal-body {
        padding: 20px 30px;
    }
    
    #password-form {
        max-width: 500px;
        margin: 0 auto;
    }
    
    #password-modal-title {
        position: relative;
        display: inline-block;
    }
    
    #password-modal-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 60%;
        height: 3px;
        background: linear-gradient(to right, #db4c3f, #ff9800);
    }

    .error-message {
        padding: 10px 15px;
        background-color: rgba(244, 67, 54, 0.1);
        border-left: 3px solid #f44336;
        border-radius: 4px;
        color: #d32f2f;
        font-size: 0.9rem;
        margin-top: 10px;
        animation: fadeInLeft 0.5s;
    }

    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .empty-notes {
        padding: 60px 20px;
        text-align: center;
        background: linear-gradient(to bottom right, rgba(255,255,255,0.8), rgba(248,249,250,0.8));
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        margin: 30px auto;
        max-width: 600px;
        animation: fadeIn 1s;
    }

    .empty-notes i {
        font-size: 60px;
        color: #db4c3f;
        margin-bottom: 20px;
        opacity: 0.6;
    }

    .empty-notes h3 {
        font-size: 24px;
        margin-bottom: 15px;
        color: #333;
    }

    .empty-notes p {
        font-size: 16px;
        color: #666;
        line-height: 1.6;
    }
</style>

<!-- Password Protection Modal -->
<div class="modal-overlay" id="password-action-modal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="password-modal-title">Password Protection</h3>
                <button class="modal-close" onclick="document.getElementById('password-action-modal').style.display='none'; closeModal();">&times;</button>
            </div>
            <div class="modal-body">
                <form id="password-form">
                    <input type="hidden" id="password-note-id" name="note_id">
                    <input type="hidden" id="password-action" name="action" value="enable">

                    <div class="form-group" id="old-password-field">
                        <label id="old-password-label" for="old-password">Current Password:</label>
                        <input type="password" id="old-password" name="old_password">
                    </div>

                    <div class="form-group">
                        <label for="new-password">New Password:</label>
                        <input type="password" id="new-password" name="password" placeholder="Enter password">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('password-action-modal').style.display='none'; closeModal();">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Verification Modal -->
    <div class="modal-overlay" id="password-verify-modal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Password Protected Note</h3>
                <button class="modal-close" onclick="document.getElementById('password-verify-modal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <div class="password-prompt-message">
                    <div class="lock-animation">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                    </div>
                    <p>This note is password protected.</p>
                    <p class="subtitle">Please enter the password to view its contents.</p>
                </div>
                <form id="verify-password-form">
                    <input type="hidden" id="verify-note-id" name="note_id">
                    
                    <div class="form-group password-input-group">
                        <label for="note-password">Password:</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="note-password" name="note_password" placeholder="Enter password" required>
                            <button type="button" class="toggle-password" tabindex="-1">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div id="password-error-message" class="error-message" style="display: none;"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('password-verify-modal').style.display='none'">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-unlock-alt" aria-hidden="true"></i> Unlock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Share Note Modal -->
    <div class="modal-overlay" id="share-note-modal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Share Note</h3>
                <button class="modal-close" onclick="document.getElementById('share-note-modal').style.display='none'; closeModal();">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="share-note-id">

                <!-- Public sharing section -->
                <div id="share-link-section" style="display: none;">
                    <h4>Public Sharing Link</h4>
                    <p>Anyone with this link can view this note:</p>
                    <div class="share-link-container">
                        <input type="text" id="share-url-display" readonly class="share-url-display">
                        <button type="button" class="copy-link-btn" onclick="copyShareLink()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>

                <!-- Share with specific users section -->
                <div id="share-with-users-section">
                    <h4>Share with specific users</h4>
                    <form id="share-with-user-form">
                        <div class="form-group">
                            <label for="share-email">User Email:</label>
                            <input type="email" id="share-email" placeholder="Enter email address" required>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="can-edit-checkbox">
                                Allow editing
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">Share</button>
                    </form>
                </div>

                <!-- Shared users list -->
                <div class="shared-users-section">
                    <h4 class="shared-users-title">Shared with</h4>
                    <div id="shared-users-list"></div>
                </div>
                
                <div class="modal-footer" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('share-note-modal').style.display='none'; closeModal();">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

<?php $conn->close(); ?> 