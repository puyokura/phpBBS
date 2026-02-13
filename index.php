<?php
require_once 'db.php';

$user = current_user();
$error = '';
$success = '';

// Handle New Thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_thread') {
    if (!$user) {
        $error = "スレッドを作成するにはログインが必要です。";
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (empty($title) || empty($body)) {
            $error = "タイトルと本文は必須です。";
        } else {
            try {
                $pdo->beginTransaction();

                // Generate Random ID (Collision Check loop optional but recommended for high volume, omitted for vibe coding)
                // For safety in a real app, you'd verify uniqueness. Here we trust 8 hex chars (4 billion combinations).
                // Actually, let's use a simple loop just in case.
                $thread_id = '';
                $max_retries = 5;
                for ($i = 0; $i < $max_retries; $i++) {
                    $tmp_id = nanoid(10); // Uses 10 chars
                    $check = $pdo->prepare("SELECT COUNT(*) FROM threads WHERE id = ?");
                    $check->execute([$tmp_id]);
                    if ($check->fetchColumn() == 0) {
                        $thread_id = $tmp_id;
                        break;
                    }
                }
                if (!$thread_id) {
                    throw new Exception("ID生成エラー。再試行してください。");
                }

                $now = date('Y-m-d H:i:s');

                // Insert Thread
                $stmt = $pdo->prepare("INSERT INTO threads (id, title, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$thread_id, $title, $user['id'], $now, $now]);

                // Insert First Post
                $stmt = $pdo->prepare("INSERT INTO posts (thread_id, user_id, body, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$thread_id, $user['id'], $body, $now]);

                $pdo->commit();
                $success = "スレッドを作成しました！";
                header("Location: /thread?id=" . $thread_id);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "作成エラー: " . $e->getMessage();
            }
        }
    }
}

// Fetch MOTD
$stmt = $pdo->prepare("SELECT value FROM config WHERE key = 'motd'");
$stmt->execute();
$motd = $stmt->fetchColumn();

// Fetch Threads
$stmt = $pdo->prepare("
    SELECT t.*, u.username, 
    (SELECT count(*) FROM posts WHERE thread_id = t.id) as reply_count
    FROM threads t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.updated_at DESC
");
$stmt->execute();
$threads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <div class="nav">
            <?php if ($user): ?>
                ようこそ <strong><?= h($user['username']) ?></strong> さん
                <?php if ($user['is_admin']): ?>
                    | <a href="/admin">管理画面</a>
                <?php endif; ?>
                | <a href="/login?action=logout">ログアウト</a>
            <?php else: ?>
                <a href="/login">ログイン / 新規登録</a>
            <?php endif; ?>
        </div>
        <h1><a href="/" style="color: #333; text-decoration: none;">GGser BBS</a></h1>
    </header>

    <?php if ($motd): ?>
        <div class="card" style="background-color: #fffff0; border-left: 5px solid #ffeeba;">
            <h3>お知らせ</h3>
            <div><?= markdown($motd) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert" style="color: red; background: #ffe6e6; border-color: #ffcccc;"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>新規スレッド作成</h2>
        <?php if ($user): ?>
            <form method="post" action="/">
                <input type="hidden" name="action" value="create_thread">
                <input type="text" name="title" placeholder="スレッドタイトル" required>
                <textarea name="body" placeholder="本文を入力... (Markdown対応)" rows="3" required></textarea>
                <div style="font-size: 0.8em; color: gray;">
                    対応記法: # 見出し, **太字**, [リンク](url)
                </div>
                <button type="submit" style="margin-top: 5px;">作成する</button>
            </form>
        <?php else: ?>
            <p>スレッドを作成するには<a href="/login">ログイン</a>してください。</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>スレッド一覧</h2>
        <div class="thread-list">
            <?php if (count($threads) > 0): ?>
                <?php foreach ($threads as $thread): ?>
                    <div class="thread-item">
                        <div style="font-size: 1.2em; font-weight: bold;">
                            <a href="/thread?id=<?= $thread['id'] ?>"><?= h($thread['title']) ?></a>
                        </div>
                        <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                            作成者: <?= h($thread['username']) ?> | 
                            <?= $thread['created_at'] ?> | 
                            <?= $thread['reply_count'] ?> レス
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>スレッドはまだありません。</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
