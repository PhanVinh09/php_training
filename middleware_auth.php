<?php
// middleware_auth.php — kiểm tra token trong Redis, gán $CURRENT_USER_ID
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/configs/redis.php';

header_remove('X-Powered-By'); // hygiene

$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if (!$auth || !preg_match('/Bearer\s+(.+)/', $auth, $m)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing token']);
    exit;
}
$token = $m[1];

$redis = redis_client();
$userId = $redis->get("session:$token");
if (!$userId) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

// (tuỳ chọn) refresh TTL
$redis->expire("session:$token", 86400);

$CURRENT_USER_ID = (int)$userId;
