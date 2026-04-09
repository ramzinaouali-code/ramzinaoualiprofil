<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('PRAGMA foreign_keys=ON');

    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            slug  TEXT NOT NULL UNIQUE,
            name  TEXT NOT NULL,
            color TEXT NOT NULL DEFAULT '#1a73e8'
        );

        CREATE TABLE IF NOT EXISTS posts (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            slug             TEXT NOT NULL UNIQUE,
            title            TEXT NOT NULL,
            excerpt          TEXT NOT NULL DEFAULT '',
            meta_description TEXT NOT NULL DEFAULT '',
            body             TEXT NOT NULL,
            tags             TEXT NOT NULL DEFAULT '',
            category_id      INTEGER REFERENCES categories(id),
            thumbnail_css    TEXT NOT NULL DEFAULT '',
            status           TEXT NOT NULL DEFAULT 'published',
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS affiliate_books (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id      INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            position     INTEGER NOT NULL DEFAULT 1,
            title        TEXT NOT NULL,
            author       TEXT NOT NULL,
            reason       TEXT NOT NULL DEFAULT '',
            search_query TEXT NOT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS generation_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            status     TEXT NOT NULL,
            topic      TEXT,
            message    TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");

    seed_categories($pdo);
    seed_settings($pdo);
    migrate_photo_url($pdo);
}

function migrate_photo_url(PDO $pdo): void {
    // Add photo_url column if it doesn't exist (safe to run every boot)
    $cols = $pdo->query("PRAGMA table_info(posts)")->fetchAll(PDO::FETCH_ASSOC);
    $has  = array_filter($cols, fn($c) => $c['name'] === 'photo_url');
    if (!$has) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN photo_url TEXT NOT NULL DEFAULT ''");
    }
}

function seed_categories(PDO $pdo): void {
    $cats = [
        ['cyber-risk',        'Cyber Risk',         '#c62828'],
        ['privacy',           'Privacy',             '#00695c'],
        ['compliance',        'Compliance',          '#6a1b9a'],
        ['ai-implementation', 'AI Implementation',   '#2e7d32'],
        ['frameworks',        'Frameworks',          '#0d47a1'],
        ['ransomware',        'Ransomware',          '#b71c1c'],
        ['hipaa',             'HIPAA',               '#1a73e8'],
    ];
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO categories (slug, name, color) VALUES (?, ?, ?)'
    );
    foreach ($cats as $c) {
        $stmt->execute($c);
    }
}

function seed_settings(PDO $pdo): void {
    $defaults = [
        'test_mode'        => '1',
        'posts_per_page'   => (string)POSTS_PER_PAGE,
        'last_topic_index' => '0',
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }
}

function get_setting(string $key, string $default = ''): string {
    $db   = get_db();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void {
    $db = get_db();
    $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')
       ->execute([$key, $value]);
}
