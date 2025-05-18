<?php
header('Content-Type: application/json');

// Include database configuration
require_once 'config.php';

// Required tables
$requiredTables = [
    'users',
    'notes', 
    'shared_notes'
];

// Check each required table
$results = [];

foreach ($requiredTables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($query);
    
    if ($result) {
        $exists = $result->num_rows > 0;
        $results[$table] = [
            'exists' => $exists
        ];
        
        // If table exists, get its structure
        if ($exists) {
            $structure_query = "DESCRIBE $table";
            $structure_result = $conn->query($structure_query);
            
            if ($structure_result) {
                $columns = [];
                while ($row = $structure_result->fetch_assoc()) {
                    $columns[] = $row;
                }
                $results[$table]['columns'] = $columns;
            }
        }
    } else {
        $results[$table] = [
            'exists' => false,
            'error' => $conn->error
        ];
    }
}

// Check notes table specifically for required columns
if (isset($results['notes']) && $results['notes']['exists']) {
    $requiredColumns = ['is_password_protected', 'password_hash'];
    $foundColumns = array_map(function($col) { 
        return $col['Field']; 
    }, $results['notes']['columns']);
    
    $missingColumns = array_diff($requiredColumns, $foundColumns);
    
    if (!empty($missingColumns)) {
        $results['notes']['missing_columns'] = $missingColumns;
    }
}

// Return results
echo json_encode([
    'success' => true,
    'database' => $dbname,
    'table_status' => $results
]);

$conn->close();
?> 