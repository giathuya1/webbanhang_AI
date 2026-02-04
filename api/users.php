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
        unset($row['password']); // Không trả về password
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
    
    // Validate dữ liệu
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Username và password là bắt buộc"]);
        return;
    }
    
    $username = trim($data['username']);
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
    
    // Thêm user
    $sql = "INSERT INTO users (username, password, role_id, status, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $username, $password, $role_id, $status);
    
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
    $role_id = isset($data['role_id']) ? (int)$data['role_id'] : null;
    $status = isset($data['status']) ? (int)$data['status'] : null;
    $password = isset($data['password']) && !empty($data['password']) ? $data['password'] : null;
    
    // Kiểm tra username trùng (nếu có thay đổi)
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
    
    // Build query động
    $updates = [];
    $params = [];
    $types = "";
    
    if ($username) {
        $updates[] = "username = ?";
        $params[] = $username;
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
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID không hợp lệ"]);
        return;
    }
    
    // Kiểm tra user có đơn hàng không
    $checkOrdersSql = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkOrdersSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    
    if ($row['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "message" => "Không thể xóa! User này có " . $row['count'] . " đơn hàng. Hãy vô hiệu hóa thay vì xóa."
        ]);
        return;
    }
    
    // Kiểm tra user có hóa đơn không
    $checkInvoicesSql = "SELECT COUNT(*) as count FROM invoices WHERE user_id = ?";
    $checkStmt2 = $conn->prepare($checkInvoicesSql);
    $checkStmt2->bind_param("i", $id);
    $checkStmt2->execute();
    $checkResult2 = $checkStmt2->get_result();
    $row2 = $checkResult2->fetch_assoc();
    
    if ($row2['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "message" => "Không thể xóa! User này có " . $row2['count'] . " hóa đơn. Hãy vô hiệu hóa thay vì xóa."
        ]);
        return;
    }
    
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Xóa user thành công"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Không tìm thấy user"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi xóa user: " . $conn->error]);
    }
}

$conn->close();
?>
