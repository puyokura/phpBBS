<?php
require_once 'db.php';

$thread_id = $_GET['id'] ?? null;
if (!$thread_id) {
    header("Location: /");
    exit;
}

// Fetch Thread
$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();

if (!$thread) {
    die("スレッドが見つかりません。");
}

// Handle Reply
$error = '';
$success = '';
$new_creds = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');
    if (empty($body)) {
        $error = "メッセージを入力してください。";
    } else {
        $user = current_user();
        $user_id = $user ? $user['id'] : null;

        // Auto-Registration if not logged in
        if (!$user) {
            try {
                $username = "名無し_" . substr(bin2hex(random_bytes(2)), 0, 4); // Shorten for JP context
                $password = bin2hex(random_bytes(4));
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $is_admin = 0;

                // Check if first user
                $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                if ($stmt->fetchColumn() == 0) {
                    $is_admin = 1;
                }

                $user_id = generate_id(); // 5 chars
                $now = date('Y-m-d H:i:s');

                $stmt = $pdo->prepare("INSERT INTO users (id, username, password, is_admin, created_at) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $username, $hash, $is_admin, $now])) {
                   // Success
                } else {
                   // Fallback or collision retry (omitted for vibe)
                   throw new Exception("ID Collision");
                }
                
                // Auto Login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = $is_admin;
                
                $new_creds = ['username' => $username, 'password' => $password];
                
            } catch (Exception $e) {
                // Retry handling for username collision could be added here
                $error = "自動登録エラー: " . $e->getMessage();
            }
        }

        if ($user_id && !$error) {
            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO posts (thread_id, user_id, body, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$thread_id, $user_id, $body, $now]);
                
                // Update Thread updated_at
                $stmt = $pdo->prepare("UPDATE threads SET updated_at = ? WHERE id = ?");
                $stmt->execute([$now, $thread_id]);

                $success = "書き込みました！";
            } catch (Exception $e) {
                $error = "書き込みエラー: " . $e->getMessage();
            }
        }
    }
}

// Fetch Posts (Newest First)
$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.thread_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$thread_id]);
$posts = $stmt->fetchAll();
$post_count = count($posts); // Total count for reverse numbering

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($thread['title']) ?> - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <nav class="nav">
            <a href="/">一覧へ戻る</a>
        </nav>
        <h1>スレッド: <?= h($thread['title']) ?></h1>
    </header>

    <?php if ($new_creds): ?>
        <div class="card" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
            <h3>ようこそ！アカウントが作成されました。</h3>
            <p>次回からは以下の情報でログインできます。</p>
            <p><strong>ユーザー名:</strong> <?= h($new_creds['username']) ?></p>
            <p><strong>パスワード:</strong> <?= h($new_creds['password']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert" style="color: red; background: #ffe6e6; border-color: #ffcccc;"><?= h($error) ?></div>
    <?php endif; ?>
    
    <!-- Reply Form (Moved to Top) -->
    <div class="card">
        <h3>レスを投稿する</h3>
        <form method="post" action="/thread?id=<?= $thread_id ?>">
            <textarea name="body" placeholder="メッセージを入力..." rows="4" required></textarea>
            <div style="font-size: 0.8em; color: gray; margin-bottom: 10px;">
                対応記法: # 見出し, **太字**, [リンク](url)
            </div>
            <button type="submit">書き込む</button>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <span style="font-size: 0.9em; color: #666; margin-left: 10px;">(自動的にアカウントが作成されます)</span>
            <?php endif; ?>
        </form>
    </div>

    <!-- Post List (Newest First) -->
    <div class="card">
        <?php foreach ($posts as $index => $post): ?>
            <?php $res_number = $post_count - $index; ?>
            <div class="post" id="res-<?= $res_number ?>">
                <div class="post-meta">
                    <?= $res_number ?>. <strong><?= h($post['username']) ?></strong> 
                    (ID: <?= h($post['user_id']) ?>) 
                    [<?= $post['created_at'] ?>]
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                         [<a href="/admin?delete_post=<?= $post['id'] ?>&thread_id=<?= $thread_id ?>" onclick="return confirm('本当に削除しますか？');" style="color: red;">削除</a>]
                    <?php endif; ?>
                </div>
                <div class="post-body">
                    <?= markdown($post['body']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
