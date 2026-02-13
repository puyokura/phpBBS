<?php
require_once 'db.php';

$user = current_user();

// Admin Check
if (!$user || !$user['is_admin']) {
    die("アクセス権限がありません。管理者のみアクセス可能です。");
}

$message = '';
$tab = $_GET['tab'] ?? 'dashboard';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_motd') {
        $motd = trim($_POST['motd'] ?? '');
        $stmt = $pdo->prepare("REPLACE INTO config (key, value) VALUES ('motd', ?)");
        $stmt->execute([$motd]);
        $message = "お知らせを更新しました。";
        
        // Log
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], 'update_motd', 'Updated MOTD']);
    }
    elseif ($action === 'add_changelog') {
        $body = trim($_POST['body'] ?? '');
        if ($body) {
            $stmt = $pdo->prepare("INSERT INTO changelogs (body) VALUES (?)");
            $stmt->execute([$body]);
            $message = "更新履歴を追加しました。";
        }
    }
    elseif ($action === 'toggle_thread_hide') {
        $thread_id = $_POST['thread_id'];
        $current_status = $_POST['current_status']; // 1 or 0
        $new_status = $current_status ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE threads SET is_hidden = ? WHERE id = ?");
        $stmt->execute([$new_status, $thread_id]);
        $message = "スレッドの表示状態を変更しました。";
        
        // Log
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, target_type, target_id, action, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], 'thread', $thread_id, 'toggle_hide', "Changed to $new_status"]);
    }
}

// Fetch Logic based on Tab
if ($tab === 'dashboard') {
    // Stats
    $stats = [];
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['threads'] = $pdo->query("SELECT COUNT(*) FROM threads")->fetchColumn();
    $stats['posts'] = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $stats['requests'] = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
}
elseif ($tab === 'requests') {
    $requests = $pdo->query("SELECT r.*, u.username FROM requests r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC")->fetchAll();
}
elseif ($tab === 'changelogs') {
    $changelogs = $pdo->query("SELECT * FROM changelogs ORDER BY created_at DESC")->fetchAll();
}
elseif ($tab === 'logs') {
    $admin_logs = $pdo->query("SELECT l.*, u.username FROM admin_logs l JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100")->fetchAll();
}
elseif ($tab === 'content') {
    // Hidden Content Only? Or All?
    // Let's list all threads for management
    $all_threads = $pdo->query("SELECT t.*, u.username, (SELECT count(*) FROM posts WHERE thread_id = t.id) as reply_count FROM threads t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 50")->fetchAll();
}

// Common: Fetch MOTD
$stmt = $pdo->prepare("SELECT value FROM config WHERE key = 'motd'");
$stmt->execute();
$current_motd = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .tabs { margin-bottom: 20px; border-bottom: 1px solid #ddd; }
        .tabs a { display: inline-block; padding: 10px 20px; text-decoration: none; color: #333; border: 1px solid transparent; border-bottom: none; }
        .tabs a.active { background: #fff; border-color: #ddd; border-bottom-color: #fff; font-weight: bold; }
        .tabs a:hover { background: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
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

    <div class="tabs">
        <a href="?tab=dashboard" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">ダッシュボード</a>
        <a href="?tab=requests" class="<?= $tab === 'requests' ? 'active' : '' ?>">要望一覧</a>
        <a href="?tab=content" class="<?= $tab === 'content' ? 'active' : '' ?>">コンテンツ管理</a>
        <a href="?tab=changelogs" class="<?= $tab === 'changelogs' ? 'active' : '' ?>">更新履歴</a>
        <a href="?tab=logs" class="<?= $tab === 'logs' ? 'active' : '' ?>">操作ログ</a>
    </div>

    <!-- Dashboard -->
    <?php if ($tab === 'dashboard'): ?>
        <div class="card">
            <h2>システム統計</h2>
            <p><strong>総ユーザー数:</strong> <?= $stats['users'] ?></p>
            <p><strong>総スレッド数:</strong> <?= $stats['threads'] ?></p>
            <p><strong>総投稿数:</strong> <?= $stats['posts'] ?></p>
            <p><strong>未読要望:</strong> <?= $stats['requests'] ?></p>
        </div>
        <div class="card">
            <h2>お知らせ更新</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_motd">
                <textarea name="motd" rows="5" style="width: 100%;"><?= h($current_motd) ?></textarea>
                <button type="submit">更新する</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Requests -->
    <?php if ($tab === 'requests'): ?>
        <div class="card">
            <h2>ユーザーからの要望</h2>
            <table>
                <tr><th>日時</th><th>ユーザー</th><th>内容</th></tr>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?= $req['created_at'] ?></td>
                        <td><?= h($req['username']) ?></td>
                        <td><?= nl2br(h($req['body'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <!-- Content Management -->
    <?php if ($tab === 'content'): ?>
        <div class="card">
            <h2>スレッド管理</h2>
            <table>
                <tr><th>状態</th><th>タイトル</th><th>作成者</th><th>操作</th></tr>
                <?php foreach ($all_threads as $thread): ?>
                    <tr style="<?= $thread['is_hidden'] ? 'background: #f9f9f9; color: #999;' : '' ?>">
                        <td><?= $thread['is_hidden'] ? '非表示' : '公開' ?></td>
                        <td><a href="/thread?id=<?= $thread['id'] ?>"><?= h($thread['title']) ?></a></td>
                        <td><?= h($thread['username']) ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_thread_hide">
                                <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $thread['is_hidden'] ?>">
                                <button type="submit" style="font-size: 0.8em; padding: 2px 5px;">
                                    <?= $thread['is_hidden'] ? '再表示' : '非表示' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <!-- Changelogs -->
    <?php if ($tab === 'changelogs'): ?>
        <div class="card">
            <h2>更新履歴追加</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_changelog">
                <textarea name="body" rows="3" placeholder="更新内容..." required></textarea>
                <button type="submit">追加</button>
            </form>
        </div>
        <div class="card">
            <h2>履歴一覧</h2>
            <table>
                <?php foreach ($changelogs as $log): ?>
                    <tr>
                        <td width="150"><?= $log['created_at'] ?></td>
                        <td><?= nl2br(h($log['body'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <!-- Logs -->
    <?php if ($tab === 'logs'): ?>
        <div class="card">
            <h2>操作ログ (最新100件)</h2>
            <table>
                <tr><th>日時</th><th>ユーザー</th><th>アクション</th><th>詳細</th></tr>
                <?php foreach ($admin_logs as $log): ?>
                    <tr>
                        <td><?= $log['created_at'] ?></td>
                        <td><?= h($log['username']) ?></td>
                        <td><?= h($log['action']) ?></td>
                        <td><?= h($log['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

</body>
</html>
