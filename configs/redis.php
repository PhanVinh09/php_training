<?php
// configs/redis.php
require_once __DIR__ . '/../vendor/autoload.php';

function redis_client(): Predis\Client {
    // Nếu Redis chạy trong VM/Vagrant, sửa host thành IP VM (vd "192.168.33.10")
    $host = getenv('REDIS_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('REDIS_PORT') ?: 6379);

    return new Predis\Client([
        'scheme' => 'tcp',
        'host'   => $host,
        'port'   => $port,
    ]);
}
