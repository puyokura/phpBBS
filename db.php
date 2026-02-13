<?php
// PHP Strict Mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start Session
session_start();

// Timezone
date_default_timezone_set('Asia/Tokyo');

// Database Connection
$db_file = __DIR__ . '/bbs.db';
$pdo = new PDO("sqlite:$db_file");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Initialize Tables
// Note: threads.id is now TEXT (Random ID)
$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        is_admin INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS threads (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        user_id TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    "CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        thread_id TEXT NOT NULL,
        user_id TEXT NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (thread_id) REFERENCES threads(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    "CREATE TABLE IF NOT EXISTS config (
        key TEXT PRIMARY KEY,
        value TEXT
    )"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (PDOException $e) {
        die("DB Init Error: " . $e->getMessage());
    }
}

// Initialize Default MOTD if not set
$stmt = $pdo->prepare("SELECT COUNT(*) FROM config WHERE key = 'motd'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $stmt = $pdo->prepare("INSERT INTO config (key, value) VALUES ('motd', '# ようこそ！\nGGser BBSへ。')");
    $stmt->execute();
}

/**
 * XSS Prevention Helper
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Simple Markdown Parser
 */
function markdown($text) {
    $text = h($text);
    
    // Bold: **Text** -> <strong>Text</strong>
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    
    // Link: [Text](URL) -> <a href="URL">Text</a>
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $text);
    
    // Heading 1: # Text -> <h1>Text</h1>
    // Strategy: Parse H1 FIRST, then nl2br, then remove the inserted <br> after H1
    $text = preg_replace('/^#\s+(.*)$/m', '<h1>$1</h1>', $text);
    
    // Newlines -> <br>
    $text = nl2br($text);
    
    // Cleanup: Remove <br /> that follows </h1> (caused by the newline after Header)
    $text = str_replace("</h1><br />", "</h1>", $text);

    // Anchors: >>123 -> <a href="#res-123">>>123</a>
    // Note: h() converts '>' to '&gt;', so we match that.
    $text = preg_replace('/&gt;&gt;(\d+)/', '<a href="#res-$1">&gt;&gt;$1</a>', $text);

    return $text;
}

/**
 * Get current logged in user or null
 */
function current_user() {
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

/**
 * Generate NanoID-like String (URL Safe, 10 chars default)
 */
function nanoid($length = 10) {
    // URL-safe characters
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
    $res = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $res .= $chars[random_int(0, $max)];
    }
    return $res;
}

/**
 * Generate Random ID (Alphanumeric Uppercase, 5 chars) - For Users
 */
function generate_id($length = 5) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $res = '';
    for ($i = 0; $i < $length; $i++) {
        $res .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $res;
}
