<?php
$file_path = __DIR__ . '/uploads/test.txt';
file_put_contents($file_path, 'Hello Windows!');
echo is_writable(__DIR__ . '/uploads/') ? 'Thư mục có quyền ghi!' : 'Lỗi: Không có quyền ghi!';