<?php
require_once 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getRoles();
        break;
    case 'POST':
        addRole();
        break;
    case 'PUT':
        updateRole();
        break;
    case 'DELETE':
        deleteRole();
        break;
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method không được hỗ trợ"]);
        break;
}

// Lấy danh sách roles
function getRoles() {
    global $conn;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $sql = "SELECT r.*, 
            (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count 
            FROM roles r 
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND r.name LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }
    
    $sql .= " ORDER BY r.id ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "data" => $roles
    ]);
}

// Thêm role mới
function addRole() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Tên role là bắt buộc"]);
        return;
    }
    
    $name = strtolower(trim($data['name']));
    
    // Kiểm tra role đã tồn tại chưa
    $checkSql = "SELECT id FROM roles WHERE name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Role đã tồn tại"]);
        return;
    }
    
    // Thêm role
    $sql = "INSERT INTO roles (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        echo json_encode([
            "success" => true,
            "message" => "Thêm role thành công",
            "data" => ["id" => $newId, "name" => $name]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi thêm role: " . $conn->error]);
    }
}

// Cập nhật role
function updateRole() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID là bắt buộc"]);
        return;
    }
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Tên role là bắt buộc"]);
        return;
    }
    
    $id = (int)$data['id'];
    $name = strtolower(trim($data['name']));
    
    // Kiểm tra role trùng tên
    $checkSql = "SELECT id FROM roles WHERE name = ? AND id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $name, $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Role đã tồn tại"]);
        return;
    }
    
    $sql = "UPDATE roles SET name = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $name, $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Cập nhật role thành công"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi cập nhật role: " . $conn->error]);
    }
}

// Xóa role
function deleteRole() {
    global $conn;
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID không hợp lệ"]);
        return;
    }
    
    // Kiểm tra có user nào đang sử dụng role này không
    $checkSql = "SELECT COUNT(*) as count FROM users WHERE role_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    
    if ($row['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "message" => "Không thể xóa! Có " . $row['count'] . " user đang sử dụng role này"
        ]);
        return;
    }
    
    $sql = "DELETE FROM roles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Xóa role thành công"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Không tìm thấy role"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi xóa role: " . $conn->error]);
    }
}

$conn->close();
?>
