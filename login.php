<?php
require_once 'db.php';

$error = '';
$success = '';

// Logout Logic
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: /login");
    exit;
}

// Form Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $action = $_POST['action'] ?? 'login';

    if (empty($username) || empty($password)) {
        $error = "ユーザー名とパスワードは必須です。";
    } else {
        if ($action === 'register') {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = "そのユーザー名は既に使用されています。";
            } else {
                // Register
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // First user is Admin
                $is_admin = 0;
                $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                if ($stmt->fetchColumn() == 0) {
                    $is_admin = 1;
                }

                $user_id = generate_id(); // 5 chars
                $now = date('Y-m-d H:i:s');

                $stmt = $pdo->prepare("INSERT INTO users (id, username, password, is_admin, created_at) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $username, $hash, $is_admin, $now])) {
                    // Auto Login
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['is_admin'] = $is_admin;
                    header("Location: /");
                    exit;
                } else {
                    $error = "登録に失敗しました。";
                }
            }
        } elseif ($action === 'login') {
            // Login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                header("Location: /");
                exit;
            } else {
                $error = "ユーザー名またはパスワードが違います。";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <h1>GGser BBS - ログイン</h1>
        <nav class="nav">
            <a href="/">ホームへ戻る</a>
        </nav>
        <div style="clear: both;"></div>
    </header>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert" style="color: red; background: #ffe6e6; border-color: #ffcccc;"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert" style="color: green; background: #e6ffe6; border-color: #ccffcc;"><?= h($success) ?></div>
        <?php endif; ?>

        <h2>ログイン / 新規登録</h2>
        <form method="post" action="/login">
            <label>ユーザー名</label>
            <input type="text" name="username" required>
            
            <label>パスワード</label>
            <input type="password" name="password" required>

            <button type="submit" name="action" value="login">ログイン</button>
            <button type="submit" name="action" value="register" style="background: #28a745;">新規登録</button>
        </form>
    </div>
</body>
</html>
