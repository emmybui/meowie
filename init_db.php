<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS meowie";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db("meowie");

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    full_name VARCHAR(100),
    bio TEXT,
    phone VARCHAR(20),
    address TEXT,
    birth_date DATE,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully or already exists<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// Add remember_token column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
if ($check_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL";
    if ($conn->query($alter_sql) === TRUE) {
        echo "Remember token column added successfully<br>";
    } else {
        echo "Error adding remember token column: " . $conn->error . "<br>";
    }
}

// Add avatar column if it doesn't exist
$check_avatar_column = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($check_avatar_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT 'default_avatar.png'";
    if ($conn->query($alter_sql) === TRUE) {
        echo "Avatar column added successfully<br>";
    } else {
        echo "Error adding avatar column: " . $conn->error . "<br>";
    }
}

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/uploads/avatars';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
    echo "Uploads directory created successfully<br>";
}

// Copy default avatar if it doesn't exist
$defaultAvatar = __DIR__ . '/uploads/avatars/default_avatar.png';
if (!file_exists($defaultAvatar)) {
    copy(__DIR__ . '/default_avatar.png', $defaultAvatar);
    echo "Default avatar copied successfully<br>";
}

// Create notes table if not exists
$sql = "CREATE TABLE IF NOT EXISTS notes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_password_protected TINYINT(1) DEFAULT 0,
    password_hash VARCHAR(255) DEFAULT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    pinned_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Notes table created successfully or already exists<br>";
} else {
    die("Error creating notes table: " . $conn->error);
}

// Check if notes table already exists but needs the password columns
$check_protected_column = $conn->query("SHOW COLUMNS FROM notes LIKE 'is_password_protected'");
if ($check_protected_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE notes ADD COLUMN is_password_protected TINYINT(1) DEFAULT 0";
    if ($conn->query($alter_sql) === TRUE) {
        echo "is_password_protected column added successfully<br>";
    } else {
        echo "Error adding is_password_protected column: " . $conn->error . "<br>";
    }
}

$check_password_hash_column = $conn->query("SHOW COLUMNS FROM notes LIKE 'password_hash'");
if ($check_password_hash_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE notes ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL";
    if ($conn->query($alter_sql) === TRUE) {
        echo "password_hash column added successfully<br>";
    } else {
        echo "Error adding password_hash column: " . $conn->error . "<br>";
    }
}

// Check if notes table needs the pin columns
$check_pinned_column = $conn->query("SHOW COLUMNS FROM notes LIKE 'is_pinned'");
if ($check_pinned_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE notes ADD COLUMN is_pinned TINYINT(1) DEFAULT 0";
    if ($conn->query($alter_sql) === TRUE) {
        echo "is_pinned column added successfully<br>";
    } else {
        echo "Error adding is_pinned column: " . $conn->error . "<br>";
    }
}

$check_pinned_at_column = $conn->query("SHOW COLUMNS FROM notes LIKE 'pinned_at'");
if ($check_pinned_at_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE notes ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL";
    if ($conn->query($alter_sql) === TRUE) {
        echo "pinned_at column added successfully<br>";
    } else {
        echo "Error adding pinned_at column: " . $conn->error . "<br>";
    }
}

// Create shared_notes table if not exists
$sql = "CREATE TABLE IF NOT EXISTS shared_notes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    note_id INT(11) NOT NULL,
    shared_by INT(11) NOT NULL,
    shared_with INT(11) NOT NULL,
    can_edit TINYINT(1) DEFAULT 0,
    share_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (note_id, shared_with)
)";

if ($conn->query($sql) === TRUE) {
    echo "Shared notes table created successfully or already exists<br>";
} else {
    echo "Error creating shared notes table: " . $conn->error . "<br>";
}

// Check if the users table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Create a test user with a known password
    $email = "test@example.com";
    $username = "testuser";
    $password = password_hash("Test@123", PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (email, username, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $username, $password);
    
    if ($stmt->execute()) {
        echo "Test user created successfully<br>";
        echo "Email: test@example.com<br>";
        echo "Password: Test@123<br>";
        
        // Add a sample note for the test user
        $user_id = $stmt->insert_id;
        $title = "Welcome Note";
        $content = "Welcome to MEOWIÉ! This is your first note. You can create, edit, and manage your notes here.";
        
        $sql = "INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $title, $content);
        
        if ($stmt->execute()) {
            echo "Sample note created successfully<br>";
        } else {
            echo "Error creating sample note: " . $stmt->error . "<br>";
        }
    } else {
        echo "Error creating test user: " . $stmt->error . "<br>";
    }
}

$conn->close();
echo "Database initialization completed.";
?> 