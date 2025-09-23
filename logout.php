<?php
// logout.php — nếu gọi bằng fetch (có Authorization header) thì xoá Redis và trả JSON.
// Nếu truy cập trực tiếp qua trình duyệt, hiển thị trang nhỏ để xoá localStorage rồi điều hướng.

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/configs/redis.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST' || (isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['Authorization']))) {
    header('Content-Type: application/json');
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if ($auth && preg_match('/Bearer\s+(.+)/', $auth, $m)) {
        $token = $m[1];
        $redis = redis_client();
        $redis->del("session:$token");
        $redis->del("session_meta:$token");
    }
    echo json_encode(['status' => 'ok']);
    exit;
}
?>
<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><title>Logout</title></head>
<body>
<script>
  // Xoá token phía client và (tuỳ chọn) gọi API xoá server-side nếu có
  const token = localStorage.getItem('auth_token');
  if (token) {
    fetch('logout.php', {method:'POST', headers:{'Authorization': 'Bearer ' + token}});
  }
  localStorage.removeItem('auth_token');
  window.location.href = 'login.php';
</script>
Đang đăng xuất...
</body>
</html>
