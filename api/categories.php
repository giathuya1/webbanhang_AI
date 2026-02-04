<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getCategories();
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method không được hỗ trợ"]);
}

function getCategories() {
    global $conn;
    
    $sql = "SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count 
            FROM categories c 
            ORDER BY c.name ASC";
    
    $result = $conn->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "data" => $categories
    ]);
}

$conn->close();
?>
