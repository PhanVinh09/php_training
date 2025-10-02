<?php
// Start the session
session_start();

// Production: tắt hiển thị lỗi ra trình duyệt (đặt trong config trung tâm)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'models/UserModel.php';
$userModel = new UserModel();

$params = [];
if (!empty($_GET['keyword'])) {
    // Trim và giới hạn độ dài để tránh quá dài
    $params['keyword'] = substr(trim((string)$_GET['keyword']), 0, 255);
}

$users = $userModel->getUsers($params);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Home</title>
    <?php include 'views/meta.php' ?>
</head>

<body>
    <?php include 'views/header.php' ?>
    <div class="container">
        <?php if (!empty($users)) { ?>
            <div class="alert alert-warning" role="alert">
                List of users!
            </div>
            <div class="alert alert-warning" role="alert"> List of users! <br> Hacker: http://php.local/list_users.php?keyword=ASDF%25%22%3BTRUNCATE+banks%3B%23%23 </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Username</th>
                        <th scope="col">Fullname</th>
                        <th scope="col">Type</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user) {
                        // Safe values with defaults
                        $id = isset($user['id']) ? (int)$user['id'] : 0;
                        $name = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');
                        $fullname = htmlspecialchars($user['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
                        $type = htmlspecialchars($user['type'] ?? '', ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <th scope="row"><?php echo $id; ?></th>
                            <td><?php echo $name; ?></td>
                            <td><?php echo $fullname; ?></td>
                            <td><?php echo $type; ?></td>
                            <td>
                                <a href="form_user.php?id=<?php echo $id; ?>">
                                    <i class="fa fa-pencil-square-o" aria-hidden="true" title="Update"></i>
                                </a>
                                <a href="view_user.php?id=<?php echo $id; ?>">
                                    <i class="fa fa-eye" aria-hidden="true" title="View"></i>
                                </a>
                                <a href="delete_user.php?id=<?php echo $id; ?>" onclick="return confirm('Are you sure?');">
                                    <i class="fa fa-eraser" aria-hidden="true" title="Delete"></i>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div class="alert alert-dark" role="alert">
                No users found.
            </div>
        <?php } ?>
    </div>
</body>

</html>