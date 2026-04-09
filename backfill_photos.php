<?php
/**
 * One-time script to backfill photo_url for existing posts that have none.
 * HTTP: https://yourdomain.com/backfill_photos.php?token=CRON_TOKEN
 * CLI:  php backfill_photos.php --local
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$is_cli = php_sapi_name() === 'cli';

// Auth
if (!$is_cli) {
    if (!hash_equals(CRON_TOKEN, $_GET['token'] ?? '')) {
        http_response_code(403); die('Forbidden');
    }
    header('Content-Type: text/plain');
}

$db   = get_db();
$posts = $db->query(
    "SELECT id, slug, tags FROM posts WHERE photo_url = '' OR photo_url IS NULL"
)->fetchAll();

if (empty($posts)) {
    echo "All posts already have photos.\n";
    exit;
}

echo "Backfilling photos for " . count($posts) . " posts...\n";

foreach ($posts as $post) {
    $keywords = $post['tags'] ?: $post['slug'];
    $photo_url = fetch_unsplash_photo($keywords);

    if ($photo_url) {
        $db->prepare("UPDATE posts SET photo_url = ? WHERE id = ?")
           ->execute([$photo_url, $post['id']]);
        echo "✓ Post #{$post['id']} ({$post['slug']}): {$photo_url}\n";
    } else {
        echo "✗ Post #{$post['id']} ({$post['slug']}): Unsplash fetch failed, skipping.\n";
    }

    sleep(1); // Be polite to Unsplash
}

echo "\nDone.\n";

// ─── Unsplash fetcher (same logic as generate.php) ────────────────────────────
function fetch_unsplash_photo(string $keywords): string {
    $base   = 'healthcare,technology,cybersecurity';
    $clean  = preg_replace('/[^a-z0-9,\- ]/i', '', $keywords);
    $query  = urlencode($base . ',' . $clean);
    $source = "https://source.unsplash.com/1200x630/?{$query}";

    $ch = curl_init($source);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'HealthCyberInsights/1.0',
    ]);
    curl_exec($ch);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && str_contains($final_url, 'images.unsplash.com')) {
        return $final_url;
    }
    return '';
}
