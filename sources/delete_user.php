<?php
session_start();
require_once 'models/UserModel.php';
$userModel = new UserModel();

// Chỉ cho phép phương thức POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF token
    if (!empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token hợp lệ -> xử lý xoá
        if (!empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $userModel->deleteUserById($id);
        }
    } else {
        // Token không hợp lệ -> chặn
        http_response_code(403);
        die("CSRF token không hợp lệ!");
    }
}

header('Location: list_users.php');
exit;
