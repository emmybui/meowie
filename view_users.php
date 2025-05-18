<?php
include('config.php');

$result = $conn->query("SELECT * FROM users");

echo "<h2>Danh sách tài khoản:</h2>";
echo "<table border='1'>
<tr><th>ID</th><th>Username</th><th>Password Hash</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['password']}</td></tr>";
}

echo "</table>";
?>
