<?php
require_once 'db.php';

$user = current_user();

// Admin Check
if (!$user || !$user['is_admin']) {
    die("アクセス権限がありません。管理者のみアクセス可能です。");
}

$message = '';

// Handle Post Deletion
if (isset($_GET['delete_post'])) {
    $post_id = $_GET['delete_post'];
    $thread_id = $_GET['thread_id'] ?? null;
    
    // Verify post exists
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    
    $message = "投稿を削除しました。";
    if ($thread_id) {
        header("Location: /thread?id=" . $thread_id);
        exit;
    }
}

// Handle Thread Deletion
if (isset($_GET['delete_thread'])) {
    $thread_id = $_GET['delete_thread'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete posts first (foreign key constraint might handle this if cascade is set, but let's be safe)
        $stmt = $pdo->prepare("DELETE FROM posts WHERE thread_id = ?");
        $stmt->execute([$thread_id]);
        
        // Delete thread
        $stmt = $pdo->prepare("DELETE FROM threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        
        $pdo->commit();
        $message = "スレッドを削除しました。";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "スレッド削除エラー: " . $e->getMessage();
    }
}

// Handle MOTD Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_motd') {
    $motd = trim($_POST['motd'] ?? '');
    $stmt = $pdo->prepare("REPLACE INTO config (key, value) VALUES ('motd', ?)");
    $stmt->execute([$motd]);
    $message = "お知らせを更新しました。";
}

// Fetch current MOTD
$stmt = $pdo->prepare("SELECT value FROM config WHERE key = 'motd'");
$stmt->execute();
$current_motd = $stmt->fetchColumn();

// Fetch Recent Posts
$stmt = $pdo->query("SELECT p.*, u.username, t.title as thread_title FROM posts p JOIN users u ON p.user_id = u.id JOIN threads t ON p.thread_id = t.id ORDER BY p.created_at DESC LIMIT 20");
$recent_posts = $stmt->fetchAll();

// Fetch All Threads (for deletion management)
$stmt = $pdo->query("SELECT t.*, u.username, (SELECT count(*) FROM posts WHERE thread_id = t.id) as reply_count FROM threads t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");
$all_threads = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <nav class="nav">
            <a href="/">ホームへ戻る</a>
        </nav>
        <h1>管理画面</h1>
    </header>

    <?php if ($message): ?>
        <div class="alert" style="color: green; background: #e6ffe6; border-color: #ccffcc;"><?= h($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>お知らせ更新</h2>
        <form method="post" action="/admin">
            <input type="hidden" name="action" value="update_motd">
            <textarea name="motd" rows="5" style="width: 100%;"><?= h($current_motd) ?></textarea>
            <div style="font-size: 0.8em; color: gray;">Markdown対応</div>
            <button type="submit">更新する</button>
        </form>
    </div>

    <div class="card">
        <h2>スレッド管理 (削除)</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #eee;">
                    <th style="padding: 8px; text-align: left;">作成日</th>
                    <th style="padding: 8px; text-align: left;">タイトル</th>
                    <th style="padding: 8px; text-align: left;">作成者</th>
                    <th style="padding: 8px; text-align: left;">レス数</th>
                    <th style="padding: 8px; text-align: left;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_threads as $thread): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px;"><?= $thread['created_at'] ?></td>
                        <td style="padding: 8px;"><a href="/thread?id=<?= $thread['id'] ?>"><?= h($thread['title']) ?></a></td>
                        <td style="padding: 8px;"><?= h($thread['username']) ?></td>
                        <td style="padding: 8px;"><?= $thread['reply_count'] ?></td>
                        <td style="padding: 8px;">
                            <a href="/admin?delete_thread=<?= $thread['id'] ?>" 
                               onclick="return confirm('スレッド「<?= h($thread['title']) ?>」を削除しますか？\n含まれる全ての投稿も削除されます。');" 
                               style="color: red; font-weight: bold;">削除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>最近の投稿 (確認・削除)</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #eee;">
                    <th style="padding: 8px; text-align: left;">日時</th>
                    <th style="padding: 8px; text-align: left;">ユーザー</th>
                    <th style="padding: 8px; text-align: left;">スレッド</th>
                    <th style="padding: 8px; text-align: left;">本文(抜粋)</th>
                    <th style="padding: 8px; text-align: left;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_posts as $post): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px;"><?= $post['created_at'] ?></td>
                        <td style="padding: 8px;"><?= h($post['username']) ?></td>
                        <td style="padding: 8px;"><a href="/thread?id=<?= $post['thread_id'] ?>"><?= h(substr($post['thread_title'], 0, 20)) ?>...</a></td>
                        <td style="padding: 8px;"><?= h(substr($post['body'], 0, 50)) ?>...</td>
                        <td style="padding: 8px;">
                            <a href="/admin?delete_post=<?= $post['id'] ?>&thread_id=<?= $post['thread_id'] ?>" 
                               onclick="return confirm('この投稿を削除しますか？');" 
                               style="color: red;">削除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
