<?php
session_start();
require_once 'models/UserModel.php';
$userModel = new UserModel();

// Chỉ cho phép phương thức POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF token
    if (!empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token hợp lệ -> xử lý xoá
        if (!empty($_POST['id'])) {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            if ($id === false || $id === null) {
                $_SESSION['message'] = 'ID người dùng không hợp lệ.';
            } else {
                // (Tùy chọn) kiểm tra quyền ở đây (ví dụ chỉ admin mới xóa)
                // if (empty($_SESSION['type']) || $_SESSION['type'] !== 'admin') { $_SESSION['message'] = 'Bạn không có quyền.'; }

                $deleted = $userModel->deleteUserById($id);
                $_SESSION['message'] = $deleted ? 'Xóa người dùng thành công.' : 'Xóa thất bại hoặc không tìm thấy người dùng.';
            }
        } else {
            $_SESSION['message'] = 'Không cung cấp ID người dùng.';
        }
    } else {
        // Token không hợp lệ -> gán message & trả 403 trước khi redirect
        http_response_code(403);
        $_SESSION['message'] = 'CSRF token không hợp lệ — yêu cầu bị chặn.';
    }
} else {
    // Nếu ai đó truy cập bằng GET
    http_response_code(405);
    $_SESSION['message'] = 'Phương thức không hợp lệ.';
}

header('Location: list_users.php');
exit;
