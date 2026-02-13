<?php
require 'db.php';

try {
    // Add columns (ignore if exists - SQLite doesn't support IF NOT EXISTS for columns easily in one go, 
    // but duplicate column error is harmless in this context if we catch it, or we just rely on the fact they don't exist yet)
    // Actually, let's just try/catch each ALTER.
    
    $alters = [
        "ALTER TABLE threads ADD COLUMN is_hidden INTEGER DEFAULT 0",
        "ALTER TABLE posts ADD COLUMN is_hidden INTEGER DEFAULT 0"
    ];

    foreach ($alters as $sql) {
        try {
            $pdo->exec($sql);
            echo "Executed: $sql\n";
        } catch (PDOException $e) {
            echo "Skipped (or error): $sql - " . $e->getMessage() . "\n";
        }
    }

    // CREATE tables (IF NOT EXISTS is already in the SQL, but we need to run them)
    $creates = [
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

    foreach ($creates as $sql) {
        $pdo->exec($sql);
        echo "Executed Create Table\n";
    }

    echo "Migration Complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
