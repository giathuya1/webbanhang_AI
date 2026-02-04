<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getUsersPaging();
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method không được hỗ trợ"]);
}

function getUsersPaging() {
    global $conn;
    
    // Lấy tham số từ request
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 5;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Tính offset
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    // Tìm kiếm theo username hoặc fullname
    if (!empty($search)) {
        $where .= " AND (u.username LIKE ? OR u.fullname LIKE ?)";
        $params[] = "%" . $search . "%";
        $params[] = "%" . $search . "%";
        $types .= "ss";
    }
    
    // Query đếm tổng số records
    $countSql = "SELECT COUNT(*) as total FROM users u $where";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    
    // Tính tổng số trang
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) $totalPages = 1;
    
    // Query lấy data với phân trang
    $sql = "SELECT u.id, u.username, u.fullname, u.role_id, u.status, u.created_at, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            $where 
            ORDER BY u.id DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $types .= "i";
    $params[] = $offset;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    // Response
    echo json_encode([
        "success" => true,
        "data" => $users,
        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_records" => (int)$totalRecords,
            "total_pages" => (int)$totalPages
        ]
    ]);
}

$conn->close();
?>
