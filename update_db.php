<?php
/**
 * GGser BBS Database Upgrader
 * Upload this file to your server and access it to upgrade an existing database.
 * Delete this file after use.
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_file = __DIR__ . '/bbs.db';
$pdo = null;
$messages = [];

try {
    if (!file_exists($db_file)) {
        die("Error: bbs.db not found. Please upload this file to the same directory as bbs.db.");
    }

    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $messages[] = "Connected to database.";

    // 1. Add 'is_hidden' to 'threads'
    try {
        $pdo->exec("ALTER TABLE threads ADD COLUMN is_hidden INTEGER DEFAULT 0");
        $messages[] = "Added 'is_hidden' column to 'threads' table.";
    } catch (PDOException $e) {
        // Likely already exists
        $messages[] = "Column 'is_hidden' in 'threads' likely already exists (Skipped).";
    }

    // 2. Add 'is_hidden' to 'posts'
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN is_hidden INTEGER DEFAULT 0");
        $messages[] = "Added 'is_hidden' column to 'posts' table.";
    } catch (PDOException $e) {
        $messages[] = "Column 'is_hidden' in 'posts' likely already exists (Skipped).";
    }

    // 3. Create New Tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS changelogs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            body TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            target_type TEXT,
            target_id TEXT,
            action TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    $messages[] = "Ensured new tables (requests, changelogs, admin_logs) exist.";

    $messages[] = "Upgrade Complete!";

} catch (Exception $e) {
    $messages[] = "Critical Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>GGser BBS - Database Upgrade</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .log { background: #f0f0f0; padding: 10px; border: 1px solid #ddd; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Database Upgrade Tool</h1>
    <div class="log">
        <?php foreach ($messages as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    </div>
    <p>
        <a href="/">Go to Top Page</a> | 
        <strong>Security Warning: Please delete this file (update_db.php) from your server after use.</strong>
    </p>
</body>
</html>
