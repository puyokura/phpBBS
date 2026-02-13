<?php
require_once 'db.php';

$thread_id = $_GET['id'] ?? null;
if (!$thread_id) {
    header("Location: /");
    exit;
}

$user = current_user();

// Fetch Thread
$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();

if (!$thread) {
    die("スレッドが見つかりません。");
}

// Check if thread is hidden
if ($thread['is_hidden']) {
    // Only Admin or Creator can see hidden threads? Or maybe just Admin.
    // User requested: "Hide instead of delete".
    // For now, let's say only Admin and Owner can see it, or show a placeholder.
    // If it's hidden, regular users shouldn't index it. But if they have direct link?
    // Let's show "This thread is deleted" unless Admin/Owner.
    $is_owner = ($user && $user['id'] === $thread['user_id']);
    $is_admin = ($user && $user['is_admin']);
    
    if (!$is_admin && !$is_owner) {
        die("このスレッドは削除されました。");
    }
}

// Handle HIDDEN actions (Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$user) {
        // Should be enforced anyway
        header("Location: /login");
        exit;
    }

    if ($_POST['action'] === 'delete_thread') {
        // Check permission: Owner or Admin
        if ($user['id'] === $thread['user_id'] || $user['is_admin']) {
            $stmt = $pdo->prepare("UPDATE threads SET is_hidden = 1 WHERE id = ?");
            $stmt->execute([$thread_id]);
            
            // Log action
            if ($user['is_admin'] && $user['id'] !== $thread['user_id']) {
                $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, target_type, target_id, action, details) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], 'thread', $thread_id, 'hide', 'Owner/Admin deleted thread']);
            }
            header("Location: /");
            exit;
        }
    } elseif ($_POST['action'] === 'delete_post') {
        $post_id = $_POST['post_id'] ?? null;
        if ($post_id) {
            // Check post owner
            $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post_owner_id = $stmt->fetchColumn();

            if ($user['id'] === $post_owner_id || $user['is_admin']) {
                $stmt = $pdo->prepare("UPDATE posts SET is_hidden = 1 WHERE id = ?");
                $stmt->execute([$post_id]);
                
                // Log if admin
                if ($user['is_admin'] && $user['id'] !== $post_owner_id) {
                     $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, target_type, target_id, action, details) VALUES (?, ?, ?, ?, ?)");
                     $stmt->execute([$user['id'], 'post', $post_id, 'hide', 'Admin deleted post']);
                }
            }
        }
        // Redirect to same page to refresh
        header("Location: /thread?id=" . $thread_id);
        exit;
    }
}

// Handle Reply
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (!$user) {
         // Enforce Login
         header("Location: /login");
         exit;
    }
    
    $body = trim($_POST['body'] ?? '');
    if (empty($body)) {
        $error = "メッセージを入力してください。";
    } else {
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO posts (thread_id, user_id, body, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$thread_id, $user['id'], $body, $now]);
            
            // Update Thread updated_at
            $stmt = $pdo->prepare("UPDATE threads SET updated_at = ? WHERE id = ?");
            $stmt->execute([$now, $thread_id]);

            $success = "書き込みました！";
        } catch (Exception $e) {
            $error = "書き込みエラー: " . $e->getMessage();
        }
    }
}

// JSON API for Polling
if (isset($_GET['api']) && $_GET['api'] === 'fetch_posts') {
    // Return posts as JSON
    // Only return posts newer than last_id if specified?
    // For simplicity, return all posts HTML or JSON?
    // Client-side can filter.
    // Or just render HTML fragments.
    // Let's do simple HTML fragment return for vibe.
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username 
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.thread_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$thread_id]);
    $posts = $stmt->fetchAll();
    $post_count = count($posts);

    foreach ($posts as $index => $post) {
        $res_number = $post_count - $index;
        // Logic for hidden posts
        if ($post['is_hidden']) {
             // Show placeholder if not admin/owner?
             // Users asked: "Hide it".
             // If Admin, show "Hidden". If Owner, show "Hidden". Others: Don't show or show "Deleted".
             // Actually index math gets messed up if we skip.
             // We should show "Deleted Post".
             // Admin sees content.
        }
        include 'post_template.php'; // We'll need to refactor or inline this.
    }
    exit;
}

// Initial Fetch Posts (Newest First)
$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.thread_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$thread_id]);
$posts = $stmt->fetchAll();
$post_count = count($posts);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($thread['title']) ?> - GGser BBS</title>
    <link rel="stylesheet" href="/style.css">
    <script>
        // Simple Polling
        setInterval(function() {
            // We'll just reload the page for now as a robust "real-time".
            // Or fetch content.
            // "Real-time if possible" for a PHP/SQLite file-based setup without websockets...
            // Polling is the only way.
            // Full reload preserves state? No.
            // Let's implement a partial reloader.
            // Actually, simply reloading every 10s is annoying if typing.
            // Let's check for update.
            fetch(window.location.href).then(response => response.text()).then(html => {
                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');
                let newPosts = doc.querySelector('#post-container').innerHTML;
                document.querySelector('#post-container').innerHTML = newPosts;
            });
        }, 5000);
    </script>
</head>
<body>
    <header>
        <nav class="nav">
            <a href="/">一覧へ戻る</a>
        </nav>
        <h1>スレッド: <?= h($thread['title']) ?></h1>
    </header>

    <?php if ($error): ?>
        <div class="alert" style="color: red; background: #ffe6e6; border-color: #ffcccc;"><?= h($error) ?></div>
    <?php endif; ?>
    
    <!-- Thread Delete Button (Owner Only) -->
    <?php if ($user && ($user['id'] === $thread['user_id'] || $user['is_admin'])): ?>
        <div style="text-align: right; margin-bottom: 10px;">
             <form method="post" onsubmit="return confirm('スレッドを削除(非表示)しますか？');">
                 <input type="hidden" name="action" value="delete_thread">
                 <button type="submit" style="background: red; color: white;">スレッドを削除</button>
             </form>
        </div>
    <?php endif; ?>

    <!-- Reply Form -->
    <div class="card">
        <h3>レスを投稿する</h3>
        <?php if ($user): ?>
            <form method="post" action="/thread?id=<?= $thread_id ?>">
                <textarea name="body" placeholder="メッセージを入力..." rows="4" required></textarea>
                <div style="font-size: 0.8em; color: gray; margin-bottom: 10px;">
                    対応記法: # 見出し, **太字**, [リンク](url)
                </div>
                <button type="submit">書き込む</button>
            </form>
        <?php else: ?>
            <p>投稿するには<a href="/login">ログイン</a>してください。</p>
        <?php endif; ?>
    </div>

    <!-- Post List (Newest First) -->
    <div class="card" id="post-container">
        <?php foreach ($posts as $index => $post): ?>
            <?php 
                $res_number = $post_count - $index; 
                $is_hidden = $post['is_hidden'];
                $is_post_owner = ($user && $user['id'] === $post['user_id']);
                $is_admin = ($user && $user['is_admin']);
                
                // Visibility Logic
                if ($is_hidden) {
                    if (!$is_admin && !$is_post_owner) {
                        // Regular users see deleted placeholder
                        echo "<div class='post' style='color: gray;'>{$res_number}. この投稿は削除されました。</div>";
                        continue;
                    }
                }
            ?>
            <div class="post" id="res-<?= $res_number ?>" style="<?= $is_hidden ? 'background-color: #f9f9f9; opacity: 0.6;' : '' ?>">
                <div class="post-meta">
                    <?= $res_number ?>. <strong><?= h($post['username']) ?></strong> 
                    (ID: <?= h($post['user_id']) ?>) 
                    [<?= $post['created_at'] ?>]
                    <?php if ($is_hidden): ?>
                        <span style="color: red;">[削除済み]</span>
                    <?php endif; ?>
                    
                    <!-- Delete Button for Owner or Admin -->
                    <?php if (!$is_hidden && ($is_post_owner || $is_admin)): ?>
                         <form method="post" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');">
                             <input type="hidden" name="action" value="delete_post">
                             <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                             <button type="submit" style="background: none; border: none; color: red; cursor: pointer; padding: 0;">[削除]</button>
                         </form>
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
