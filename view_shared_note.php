<?php
session_start();
require_once 'config.php';

// Get the share code from the URL
$share_code = $_GET['code'] ?? '';

if (empty($share_code)) {
    die("No share code provided");
}

// Get the note information
$query = "SELECT n.*, u.username FROM notes n 
          JOIN users u ON n.user_id = u.id 
          WHERE n.share_code = ? AND n.is_public = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $share_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Note not found or not publicly shared");
}

$note = $result->fetch_assoc();
$stmt->close();

// Check if note is password protected
$is_protected = $note['is_password_protected'] == 1;
$authenticated = false;

// If the note is password protected, check if the user has entered the password
if ($is_protected) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_password'])) {
        $password = $_POST['note_password'];
        
        if (password_verify($password, $note['password_hash'])) {
            // Set session variable to indicate authentication for this note
            $_SESSION['note_auth_' . $note['id']] = true;
            $authenticated = true;
        } else {
            $error_message = "Incorrect password";
        }
    } elseif (isset($_SESSION['note_auth_' . $note['id']])) {
        $authenticated = true;
    }
}

// Check if the current user is the owner or has edit permission
$current_user_id = $_SESSION['user_id'] ?? 0;
$is_owner = $current_user_id == $note['user_id'];

$can_edit = $is_owner;
if (!$is_owner && $current_user_id > 0) {
    // Check if the current user has edit permission
    $permission_query = "SELECT can_edit FROM shared_notes 
                        WHERE note_id = ? AND shared_with = ?";
    $stmt = $conn->prepare($permission_query);
    $stmt->bind_param('ii', $note['id'], $current_user_id);
    $stmt->execute();
    $perm_result = $stmt->get_result();
    
    if ($perm_result->num_rows > 0) {
        $can_edit = $perm_result->fetch_assoc()['can_edit'] == 1;
    }
    $stmt->close();
}

// Only allow viewing if the note is not password protected or if authenticated
$can_view = !$is_protected || $authenticated;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $can_view ? htmlspecialchars($note['title']) : 'Protected Note' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fc;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .note-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 30px;
            position: relative;
        }
        
        .note-header {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .note-title {
            font-size: 1.8rem;
            margin: 0;
            color: #2c3e50;
        }
        
        .note-meta {
            display: flex;
            align-items: center;
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .note-meta span {
            margin-right: 15px;
            display: flex;
            align-items: center;
        }
        
        .note-meta i {
            margin-right: 5px;
        }
        
        .note-content {
            font-size: 1.1rem;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        
        .password-form {
            max-width: 400px;
            margin: 80px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .password-form h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .password-form p {
            color: #7f8c8d;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="password"]:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .error-message {
            color: #e74c3c;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #7f8c8d;
            text-decoration: none;
        }
        
        .back-link:hover {
            color: #2c3e50;
        }
        
        .edit-btn {
            padding: 10px 15px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        
        .edit-btn:hover {
            background-color: #27ae60;
        }
        
        .lock-icon {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                margin: 20px auto;
            }
            .note-container {
                padding: 20px;
            }
            .note-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_protected && !$authenticated): ?>
            <div class="password-form">
                <i class="fas fa-lock lock-icon"></i>
                <h2>Protected Note</h2>
                <p>This note is password protected. Please enter the password to view it.</p>
                
                <?php if (isset($error_message)): ?>
                    <div class="error-message"><?= $error_message ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="password" name="note_password" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn">Unlock Note</button>
                </form>
                
                <a href="dashboard.php" class="back-link">Back to dashboard</a>
            </div>
        <?php elseif ($can_view): ?>
            <div class="note-container">
                <div class="note-header">
                    <div>
                        <h1 class="note-title"><?= htmlspecialchars($note['title']) ?></h1>
                        <div class="note-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($note['username']) ?></span>
                            <span><i class="fas fa-clock"></i> <?= date('F j, Y', strtotime($note['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($can_edit): ?>
                        <button class="edit-btn" onclick="location.href='dashboard.php?edit=<?= $note['id'] ?>'">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="note-content">
                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="dashboard.php" class="back-link">Back to dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Any additional JavaScript can go here
    </script>
</body>
</html> 