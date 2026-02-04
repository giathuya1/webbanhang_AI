<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        getProductById($_GET['id']);
    } else {
        getProducts();
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method không được hỗ trợ"]);
}

// Lấy danh sách sản phẩm
function getProducts() {
    global $conn;
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 12;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category_id = isset($_GET['category_id']) ? $_GET['category_id'] : 'all';
    
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where .= " AND p.name LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }
    
    if ($category_id !== 'all' && $category_id !== '') {
        $where .= " AND p.category_id = ?";
        $params[] = (int)$category_id;
        $types .= "i";
    }
    
    // Đếm tổng
    $countSql = "SELECT COUNT(*) as total FROM products p $where";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Lấy sản phẩm
    $sql = "SELECT p.*, c.name as category_name,
            (SELECT image_url FROM images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $where
            ORDER BY p.id DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $types .= "i";
    $params[] = $offset;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "data" => $products,
        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_records" => (int)$totalRecords,
            "total_pages" => (int)$totalPages
        ]
    ]);
}

// Lấy chi tiết sản phẩm
function getProductById($id) {
    global $conn;
    
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if (!$product) {
        echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm"]);
        return;
    }
    
    // Lấy danh sách hình ảnh
    $imgSql = "SELECT * FROM images WHERE product_id = ? ORDER BY is_primary DESC";
    $imgStmt = $conn->prepare($imgSql);
    $imgStmt->bind_param("i", $id);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();
    
    $images = [];
    while ($img = $imgResult->fetch_assoc()) {
        $images[] = $img;
    }
    
    $product['images'] = $images;
    
    echo json_encode([
        "success" => true,
        "data" => $product
    ]);
}

$conn->close();
?>
