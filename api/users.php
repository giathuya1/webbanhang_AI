<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUsers();
        break;
    case 'POST':
        addUser();
        break;
    case 'PUT':
        updateUser();
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method không được hỗ trợ"]);
        break;
}

// Lấy danh sách users
function getUsers() {
    global $conn;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $sql = "SELECT u.*, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND u.username LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }
    
    if ($status !== 'all') {
        $sql .= " AND u.status = ?";
        $params[] = (int)$status;
        $types .= "i";
    }
    
    $sql .= " ORDER BY u.id DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        unset($row['password']);
        $users[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "data" => $users
    ]);
}

// Thêm user mới
function addUser() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Username và password là bắt buộc"]);
        return;
    }
    
    $username = trim($data['username']);
    $fullname = isset($data['fullname']) ? trim($data['fullname']) : '';
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $role_id = isset($data['role_id']) ? (int)$data['role_id'] : 1;
    $status = isset($data['status']) ? (int)$data['status'] : 1;
    
    // Kiểm tra username đã tồn tại chưa
    $checkSql = "SELECT id FROM users WHERE username = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Username đã tồn tại"]);
        return;
    }
    
    $sql = "INSERT INTO users (username, fullname, password, role_id, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $username, $fullname, $password, $role_id, $status);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        echo json_encode([
            "success" => true,
            "message" => "Thêm user thành công",
            "data" => ["id" => $newId]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi thêm user: " . $conn->error]);
    }
}

// Cập nhật user
function updateUser() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID là bắt buộc"]);
        return;
    }
    
    $id = (int)$data['id'];
    $username = isset($data['username']) ? trim($data['username']) : null;
    $fullname = isset($data['fullname']) ? trim($data['fullname']) : null;
    $role_id = isset($data['role_id']) ? (int)$data['role_id'] : null;
    $status = isset($data['status']) ? (int)$data['status'] : null;
    $password = isset($data['password']) && !empty($data['password']) ? $data['password'] : null;
    
    if ($username) {
        $checkSql = "SELECT id FROM users WHERE username = ? AND id != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("si", $username, $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Username đã tồn tại"]);
            return;
        }
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    if ($username) {
        $updates[] = "username = ?";
        $params[] = $username;
        $types .= "s";
    }
    
    if ($fullname) {
        $updates[] = "fullname = ?";
        $params[] = $fullname;
        $types .= "s";
    }
    
    if ($password) {
        $updates[] = "password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
        $types .= "s";
    }
    
    if ($role_id !== null) {
        $updates[] = "role_id = ?";
        $params[] = $role_id;
        $types .= "i";
    }
    
    if ($status !== null) {
        $updates[] = "status = ?";
        $params[] = $status;
        $types .= "i";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Không có dữ liệu để cập nhật"]);
        return;
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Cập nhật user thành công"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi cập nhật user: " . $conn->error]);
    }
}

// Xóa user
function deleteUser() {
    global $conn;
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(["success" => false, "message" => "ID không hợp lệ"]);
        return;
    }
    
    // Kiểm tra user có tồn tại không
    $checkUserSql = "SELECT username FROM users WHERE id = ?";
    $checkUserStmt = $conn->prepare($checkUserSql);
    $checkUserStmt->bind_param("i", $id);
    $checkUserStmt->execute();
    $userResult = $checkUserStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Không tìm thấy user"]);
        return;
    }
    
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    try {
        // 1. Lấy danh sách order_id của user
        $orderIds = [];
        $getOrdersSql = "SELECT id FROM orders WHERE user_id = ?";
        $getOrdersStmt = $conn->prepare($getOrdersSql);
        $getOrdersStmt->bind_param("i", $id);
        $getOrdersStmt->execute();
        $ordersResult = $getOrdersStmt->get_result();
        while ($row = $ordersResult->fetch_assoc()) {
            $orderIds[] = $row['id'];
        }
        
        // 2. Xóa dữ liệu liên quan đến orders
        if (!empty($orderIds)) {
            $orderIdsStr = implode(',', $orderIds);
            
            // Xóa payments
            $conn->query("DELETE FROM payments WHERE order_id IN ($orderIdsStr)");
            
            // Xóa invoices
            $conn->query("DELETE FROM invoices WHERE order_id IN ($orderIdsStr)");
            
            // Xóa order_items
            $conn->query("DELETE FROM order_items WHERE order_id IN ($orderIdsStr)");
            
            // Xóa orders
            $conn->query("DELETE FROM orders WHERE user_id = $id");
        }
        
        // 3. Lấy danh sách import_order_id của user
        $importOrderIds = [];
        $getImportSql = "SELECT id FROM import_orders WHERE user_id = ?";
        $getImportStmt = $conn->prepare($getImportSql);
        $getImportStmt->bind_param("i", $id);
        $getImportStmt->execute();
        $importResult = $getImportStmt->get_result();
        while ($row = $importResult->fetch_assoc()) {
            $importOrderIds[] = $row['id'];
        }
        
        // 4. Xóa dữ liệu liên quan đến import_orders
        if (!empty($importOrderIds)) {
            $importIdsStr = implode(',', $importOrderIds);
            
            // Xóa import_order_items
            $conn->query("DELETE FROM import_order_items WHERE import_order_id IN ($importIdsStr)");
            
            // Xóa import_orders
            $conn->query("DELETE FROM import_orders WHERE user_id = $id");
        }
        
        // 5. Xóa user
        $deleteUserSql = "DELETE FROM users WHERE id = ?";
        $deleteUserStmt = $conn->prepare($deleteUserSql);
        $deleteUserStmt->bind_param("i", $id);
        $deleteUserStmt->execute();
        
        if ($deleteUserStmt->affected_rows > 0) {
            // Commit transaction
            $conn->commit();
            echo json_encode([
                "success" => true,
                "message" => "Xóa user và tất cả dữ liệu liên quan thành công"
            ]);
        } else {
            throw new Exception("Không thể xóa user");
        }
        
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollback();
        echo json_encode([
            "success" => false,
            "message" => "Lỗi khi xóa: " . $e->getMessage()
        ]);
    }
}

$conn->close();
?>
