<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['product_id'])) {
            getReviewsByProduct($_GET['product_id']);
        } else {
            echo json_encode(["success" => false, "message" => "Thiếu product_id"]);
        }
        break;
    case 'POST':
        addReview();
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method không được hỗ trợ"]);
}

// Lấy đánh giá theo sản phẩm
function getReviewsByProduct($productId) {
    global $conn;
    
    // Lấy danh sách reviews
    $sql = "SELECT r.*, u.fullname as user_fullname 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    
    // Tính thống kê
    $statsSql = "SELECT 
                    COUNT(*) as total_reviews,
                    ROUND(AVG(rating), 1) as avg_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
                 FROM reviews WHERE product_id = ?";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->bind_param("i", $productId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    
    echo json_encode([
        "success" => true,
        "data" => $reviews,
        "stats" => [
            "total_reviews" => (int)$stats['total_reviews'],
            "avg_rating" => $stats['avg_rating'] ? (float)$stats['avg_rating'] : 0,
            "star_5" => (int)$stats['star_5'],
            "star_4" => (int)$stats['star_4'],
            "star_3" => (int)$stats['star_3'],
            "star_2" => (int)$stats['star_2'],
            "star_1" => (int)$stats['star_1']
        ]
    ]);
}

// Thêm đánh giá mới
function addReview() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['product_id']) || !isset($data['rating'])) {
        echo json_encode(["success" => false, "message" => "Thiếu thông tin bắt buộc"]);
        return;
    }
    
    $productId = (int)$data['product_id'];
    $rating = (int)$data['rating'];
    $comment = isset($data['comment']) ? trim($data['comment']) : '';
    $customerName = isset($data['customer_name']) ? trim($data['customer_name']) : 'Khách hàng';
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(["success" => false, "message" => "Rating phải từ 1-5"]);
        return;
    }
    
    $sql = "INSERT INTO reviews (product_id, user_id, customer_name, rating, comment) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $productId, $userId, $customerName, $rating, $comment);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Đánh giá thành công!",
            "id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Lỗi: " . $conn->error]);
    }
}

$conn->close();
?>
