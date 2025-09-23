<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/configs/redis.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $redis = redis_client();

    // Lấy tất cả session
    $keys = $redis->keys("session:*");

    echo "<h2>Danh sách session trong Redis</h2>";
    if (empty($keys)) {
        echo "<p><i>Không có session nào</i></p>";
        exit;
    }

    echo "<table border='1' cellspacing='0' cellpadding='6'>";
    echo "<tr><th>Token</th><th>User ID</th><th>TTL (giây)</th><th>Metadata</th></tr>";

    foreach ($keys as $key) {
        $token = substr($key, strlen("session:")); 
        $userId = $redis->get($key);
        $ttl    = $redis->ttl($key);

        $metaKey = "session_meta:$token";
        $meta = $redis->hgetall($metaKey);

        echo "<tr>";
        echo "<td style='max-width:420px;word-break:break-all;'>$token</td>";
        echo "<td>$userId</td>";
        echo "<td>$ttl</td>";
        echo "<td><pre>" . htmlspecialchars(print_r($meta, true)) . "</pre></td>";
        echo "</tr>";
    }

    echo "</table>";

} catch (Throwable $e) {
    echo "<p style='color:red;'>Redis error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
