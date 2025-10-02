<?php
// security-first session cookie params — set BEFORE session_start()
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // true only on HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start the session
session_start();

require_once 'models/UserModel.php';
$userModel = new UserModel();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer-when-downgrade");
// Content-Security-Policy: chỉ cho phép resources từ same-origin; điều chỉnh nếu cần tải CDN
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none';");

// Tạo CSRF token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Hàm kiểm tra token
function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Xử lý form submit (server-side)
if (!empty($_POST['submit'])) {
    // Kiểm tra CSRF token trước khi xử lý
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Invalid CSRF token!';
        // Redirect an toàn
        header('Location: login.php');
        exit;
    }

    // Lấy input, trim và hạn chế kích thước (cơ bản)
    $username = substr(trim((string)($_POST['username'] ?? '')), 0, 255);
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $_SESSION['message'] = 'Vui lòng nhập username và password.';
        header('Location: login.php');
        exit;
    }

    // Gọi hàm auth trong UserModel
    // **UserModel::auth($username, $password)** phải:
    //  - truy vấn bằng prepared statements (PDO)
    //  - lấy password_hash từ DB và dùng password_verify($password, $hash)
    //  - trả về user array (['id'=>..., 'username'=>...]) hoặc false
    $user = $userModel->auth($username, $password);

    if ($user) {
        // Nếu $user là mảng nhiều dòng, lấy dòng đầu tiên
        if (isset($user[0])) {
            $user = $user[0];
        }
        // Rotate session id
        session_regenerate_id(true);

        // Login successful — set minimal session info
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['message'] = 'Login successful';

        header('Location: list_users.php');
        exit;
    } else {
        // Login failed - dùng message chung (không tiết lộ tồn tại tài khoản)
        $_SESSION['message'] = 'Login failed: invalid credentials';
        header('Location: login.php');
        exit;
    }
}

// At this point show the form (GET)
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <title>User form</title>
    <?php include 'views/meta.php' ?>
</head>
<body>
<?php include 'views/header.php' ?>

<div class="container">
    <div id="loginbox" style="margin-top:50px;" class="mainbox col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2">
        <div class="panel panel-info" >
            <div class="panel-heading">
                <div class="panel-title">Login</div>
                <div style="float:right; font-size: 80%; position: relative; top:-10px"><a href="#">Forgot password?</a></div>
            </div>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div style="padding-top:30px" class="panel-body" >
                <form method="post" class="form-horizontal" role="form" autocomplete="off" novalidate>
                    <!-- CSRF token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="margin-bottom-25 input-group">
                        <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                        <!-- Escape giá trị repopulate (không bao giờ echo raw user input) -->
                        <input id="login-username" type="text" class="form-control" name="username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="username or email" autocomplete="username">
                    </div>

                    <div class="margin-bottom-25 input-group">
                        <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                        <input id="login-password" type="password" class="form-control" name="password" placeholder="password" autocomplete="current-password">
                    </div>

                    <div class="margin-bottom-25">
                        <input type="checkbox" tabindex="3" name="remember" id="remember" value="1">
                        <label for="remember"> Remember Me</label>
                    </div>

                    <div class="margin-bottom-25 input-group">
                        <!-- Button -->
                        <div class="col-sm-12 controls">
                            <button type="submit" name="submit" value="submit" class="btn btn-primary">Submit</button>
                            <a id="btn-fblogin" href="#" class="btn btn-primary">Login with Facebook</a>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-md-12 control">
                            Don't have an account!
                            <a href="form_user.php">Sign Up Here</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
