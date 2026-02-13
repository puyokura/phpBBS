<?php
require_once 'db.php';

// Fetch Changelogs
$stmt = $pdo->query("SELECT * FROM changelogs ORDER BY created_at DESC");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>更新履歴 - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <nav class="nav">
            <a href="/">ホームへ戻る</a>
        </nav>
        <h1>更新履歴</h1>
    </header>

    <div class="card">
        <?php if (count($logs) > 0): ?>
            <?php foreach ($logs as $log): ?>
                <div style="border-bottom: 1px solid #eee; padding: 10px 0;">
                    <div style="font-size: 0.85em; color: #666;"><?= $log['created_at'] ?></div>
                    <div style="margin-top: 5px; line-height: 1.5;">
                        <?= nl2br(h($log['body'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>更新履歴はまだありません。</p>
        <?php endif; ?>
    </div>
</body>
</html>
