

<?php
// login.php — GET: hiển thị form; POST: xác thực, tạo token, lưu Redis, trả JSON
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/configs/redis.php';
require_once __DIR__ . '/models/UserModel.php';


// ===== KẾT NỐI DB (chỉnh theo môi trường của bạn) =====
$mysqli = new mysqli('127.0.0.1', 'root', '', 'app_web1');
if ($mysqli->connect_error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database connection failed";
    exit;
}
$userModel = new UserModel($mysqli);

// ===== XỬ LÝ POST: trả JSON (token) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?? '';

    header('Content-Type: application/json');

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing credentials']);
        exit;
    }

    $user = $userModel->auth($username, $password); // cần dùng password_verify trong UserModel
    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        exit;
    }

    try {
        $redis = redis_client();
        $token = bin2hex(random_bytes(32));
        $key   = "session:$token";
        $ttl   = 86400; // 24h

        // Lưu token -> userId
        $redis->setex($key, $ttl, (string)$user['id']);

        // (tuỳ chọn) metadata
        $redis->hmset("session_meta:$token", [
            'user_id'    => (string)$user['id'],
            'created_at' => (string)time(),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua'         => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        $redis->expire("session_meta:$token", $ttl);

        echo json_encode([
            'status'     => 'ok',
            'token'      => $token,
            'expires_in' => $ttl,
            'user'       => ['id' => (int)$user['id'], 'name' => $user['name'] ?? $username]
        ]);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Redis error', 'detail' => $e->getMessage()]);
        exit;
    }
}

// ===== GET: HIỂN THỊ FORM LOGIN =====
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <?php if (file_exists(__DIR__ . '/views/meta.php')) include __DIR__ . '/views/meta.php'; ?>
</head>
<body>
<?php if (file_exists(__DIR__ . '/views/header.php')) include __DIR__ . '/views/header.php'; ?>

<div class="container" style="max-width:960px;margin:24px auto;">
  <div class="row">
    <div class="col-md-6 col-md-offset-3">
      <div class="panel panel-info">
        <div class="panel-heading"><div class="panel-title">Login</div></div>
        <div class="panel-body" style="padding-top:20px">
          <form id="loginForm" method="post" class="form-horizontal" role="form" autocomplete="on">
            <div class="input-group" style="margin-bottom:10px;">
              <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
              <input id="login-username" type="text" class="form-control" name="username" placeholder="username or email" required>
            </div>
            <div class="input-group" style="margin-bottom:10px;">
              <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
              <input id="login-password" type="password" class="form-control" name="password" placeholder="password" required>
            </div>
            <div style="margin-bottom:10px;">
              <label><input type="checkbox" id="remember"> Remember Me</label>
            </div>
            <div class="controls">
              <button type="submit" class="btn btn-primary">Submit</button>
              <a id="btn-fblogin" href="#" class="btn btn-primary" disabled>Login with Facebook</a>
            </div>
            <div id="loginMsg" style="margin-top:10px;color:#555;"></div>
            <div style="margin-top:10px;">
              Don't have an account? <a href="form_user.php">Sign Up Here</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Wrapper fetch đính kèm token từ localStorage (tái dùng cho các trang khác)
async function apiFetch(url, opts = {}) {
  const token = localStorage.getItem('auth_token');
  const headers = opts.headers || {};
  if (token) headers['Authorization'] = 'Bearer ' + token;
  opts.headers = headers;
  return fetch(url, opts);
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('loginMsg');
  msg.textContent = 'Logging in...';

  const fd = new FormData(e.target);
  try {
    const res = await fetch(location.pathname, { method: 'POST', body: fd });
    if (!res.ok) {
      const text = await res.text();
      msg.textContent = 'Login failed: ' + (text || res.status);
      msg.style.color = '#b00020';
      return;
    }
    const json = await res.json();
    if (json && json.status === 'ok' && json.token) {
      // LƯU TOKEN VÀO LOCAL STORAGE
      localStorage.setItem('auth_token', json.token);
      msg.textContent = 'Success. Redirecting...';
      msg.style.color = '#0a7d2e';
      // sang trang chính
      window.location.href = 'list_users.php';
    } else {
      msg.textContent = 'No token from server';
      msg.style.color = '#b00020';
    }
  } catch (err) {
    msg.textContent = 'Network error: ' + err.message;
    msg.style.color = '#b00020';
  }
});
</script>
</body>
</html>
