<?php
require_once 'db.php';

$user = current_user();
$error = '';
$success = '';

// Handle New Thread
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header("Location: /login");
        exit;
    }
    
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    
    if (empty($title) || empty($body)) {
        $error = "タイトルと本文は必須です。";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate Unique ID (NanoID)
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
            // Redirect to new thread
            header("Location: /thread?id=" . $thread_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "作成エラー: " . $e->getMessage();
        }
    }
}

// Fetch Threads (Updated DESC)
// Filter hidden threads unless Admin?
// User said: "Hide it".
$sql = "SELECT t.*, u.username, (SELECT COUNT(*) FROM posts WHERE thread_id = t.id) as post_count 
        FROM threads t 
        JOIN users u ON t.user_id = u.id ";

if (!$user || !$user['is_admin']) {
    $sql .= "WHERE t.is_hidden = 0 ";
}

$sql .= "ORDER BY t.updated_at DESC";

$stmt = $pdo->query($sql);
$threads = $stmt->fetchAll();

// Get MOTD
$stmt = $pdo->prepare("SELECT value FROM config WHERE key = 'motd'");
$stmt->execute();
$motd = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
    <script>
        // Simple Polling for Index (Update Thread List)
        setInterval(function() {
             fetch(window.location.href).then(response => response.text()).then(html => {
                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');
                let newThreads = doc.querySelector('#thread-list-container').innerHTML;
                document.querySelector('#thread-list-container').innerHTML = newThreads;
            });
        }, 5000);
    </script>
</head>
<body>
    <header>
        <div class="nav">
            <?php if ($user): ?>
                ようこそ <strong><?= h($user['username']) ?></strong> さん
                | <a href="/request">要望を送る</a>
                | <a href="/changelog">更新履歴</a>
                <?php if ($user['is_admin']): ?>
                    | <a href="/admin">管理画面</a>
                <?php endif; ?>
                | <a href="/login?action=logout">ログアウト</a>
            <?php else: ?>
                <a href="/changelog">更新履歴</a>
                | <a href="/login">ログイン / 新規登録</a>
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
    <?php if ($success): ?>
        <div class="alert" style="color: green; background: #e6ffe6; border-color: #ccffcc;"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>新規スレッド作成</h2>
        <?php if ($user): ?>
            <form method="post" action="/">
                <label>タイトル</label>
                <input type="text" name="title" required>
                <label>本文</label>
                <textarea name="body" required rows="4"></textarea>
                <div style="font-size: 0.8em; color: gray; margin-bottom: 10px;">
                    対応記法: # 見出し, **太字**, [リンク](url)
                </div>
                <button type="submit">作成する</button>
            </form>
        <?php else: ?>
            <p>スレッドを作成するには<a href="/login">ログイン</a>してください。</p>
        <?php endif; ?>
    </div>

    <div class="card" id="thread-list-container">
        <h2>スレッド一覧</h2>
        <div class="thread-list">
            <?php if (count($threads) > 0): ?>
                <?php foreach ($threads as $thread): ?>
                    <div class="thread-item" style="<?= $thread['is_hidden'] ? 'opacity: 0.5; background: #f0f0f0;' : '' ?>">
                        <div style="flex-grow: 1;">
                            <a href="/thread?id=<?= $thread['id'] ?>" style="font-size: 1.1em; font-weight: bold;"><?= h($thread['title']) ?></a>
                            <span style="font-size: 0.9em; color: #666;">(<?= $thread['post_count'] ?>)</span>
                            <?php if ($thread['is_hidden']): ?>
                                <span style="color: red; font-size: 0.9em;">[非表示]</span>
                            <?php endif; ?>
                        </div>
                        <div class="thread-meta">
                            作成者: <?= h($thread['username']) ?> | 更新: <?= $thread['updated_at'] ?>
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
