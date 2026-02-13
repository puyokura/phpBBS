<?php
require_once 'db.php';

$user = current_user();
if (!$user) {
    header("Location: /login");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');
    if (empty($body)) {
        $error = "要望を入力してください。";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO requests (user_id, body) VALUES (?, ?)");
            $stmt->execute([$user['id'], $body]);
            $success = "要望を送信しました！ありがとうございます。";
        } catch (Exception $e) {
            $error = "送信エラー: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>要望を送る - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <nav class="nav">
            <a href="/">ホームへ戻る</a>
        </nav>
        <h1>要望を送る</h1>
    </header>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert" style="color: red; background: #ffe6e6; border-color: #ffcccc;"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert" style="color: green; background: #e6ffe6; border-color: #ccffcc;"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <p>この掲示板への要望やバグ報告があれば送ってください。<br>管理者が確認します。</p>
            <textarea name="body" placeholder="要望を入力..." rows="5" required></textarea>
            <button type="submit">送信する</button>
        </form>
    </div>
</body>
</html>
