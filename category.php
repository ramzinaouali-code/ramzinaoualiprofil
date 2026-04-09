<?php
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$slug = trim($_GET['slug'] ?? '');
$tag  = trim($_GET['tag'] ?? '');

if ($slug) {
    $cat_stmt = $db->prepare('SELECT * FROM categories WHERE slug = ?');
    $cat_stmt->execute([$slug]);
    $category = $cat_stmt->fetch();
    if (!$category) {
        header('Location: ' . BLOG_URL . '/');
        exit;
    }
    $cat_id    = (int)$category['id'];
    $cat_name  = $category['name'];
    $cat_color = $category['color'];
    $total     = count_posts($cat_id);
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $pag       = paginate($total, POSTS_PER_PAGE, $page);
    $posts     = get_posts(POSTS_PER_PAGE, $pag['offset'], $cat_id);
    $base_url  = BLOG_URL . '/category.php?slug=' . urlencode($slug);
} elseif ($tag) {
    $cat_name  = '#' . $tag;
    $cat_color = '#1a73e8';
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $tag_stmt  = $db->prepare(
        'SELECT p.*, c.name AS category_name, c.color AS category_color, c.slug AS category_slug
         FROM posts p LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.status = "published" AND ("," || p.tags || "," LIKE ?)
         ORDER BY p.created_at DESC LIMIT ? OFFSET ?'
    );
    $count_stmt = $db->prepare(
        'SELECT COUNT(*) FROM posts WHERE status = "published" AND ("," || tags || "," LIKE ?)'
    );
    $like   = '%,' . $tag . ',%';
    $count_stmt->execute([$like]);
    $total  = (int)$count_stmt->fetchColumn();
    $pag    = paginate($total, POSTS_PER_PAGE, $page);
    $tag_stmt->execute([$like, POSTS_PER_PAGE, $pag['offset']]);
    $posts    = $tag_stmt->fetchAll();
    $base_url = BLOG_URL . '/category.php?tag=' . urlencode($tag);
} else {
    header('Location: ' . BLOG_URL . '/');
    exit;
}

$page_title      = $cat_name . ' Articles';
$meta_description = 'Browse ' . $cat_name . ' articles on ' . BLOG_NAME;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Category Header -->
<div class="cat-header" style="background: linear-gradient(135deg, <?= h($cat_color ?? '#1a73e8') ?>, #1a1a2e)">
  <div class="container">
    <div class="cat-badge" style="background:rgba(255,255,255,.2);color:#fff;margin-bottom:10px">
      <?= $total ?> article<?= $total !== 1 ? 's' : '' ?>
    </div>
    <h1><?= h($cat_name) ?></h1>
    <p>Healthcare cybersecurity insights on <?= h($cat_name) ?></p>
  </div>
</div>

<div class="container">
  <div class="page-layout">

    <main style="padding-top:24px">

      <?php if (empty($posts)): ?>
        <div class="empty-state">
          <h3>No posts yet</h3>
          <p>Posts in this category are coming soon.</p>
        </div>
      <?php else: ?>
        <div class="post-grid">
          <?php foreach ($posts as $post): ?>
            <article class="card">
              <a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($post['slug']) ?>">
                <div class="card-thumb" style="background: <?= h($post['thumbnail_css'] ?: 'linear-gradient(135deg,#1a73e8,#0d47a1)') ?>">
                  <?php if (!empty($post['photo_url'])): ?>
                    <img src="<?= h($post['photo_url']) ?>" alt="<?= h($post['title']) ?>" loading="lazy">
                  <?php endif; ?>
                </div>
              </a>
              <div class="card-body">
                <?php if ($post['category_name']): ?>
                  <a class="cat-badge" href="<?= h(BLOG_URL) ?>/category.php?slug=<?= h($post['category_slug']) ?>"
                     style="background:<?= h($post['category_color'] ?? '#1a73e8') ?>">
                    <?= h($post['category_name']) ?>
                  </a>
                <?php endif; ?>
                <h3><a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($post['slug']) ?>"><?= h($post['title']) ?></a></h3>
                <p class="card-excerpt"><?= h($post['excerpt']) ?></p>
                <div class="card-meta">
                  <span><?= h(format_date($post['created_at'])) ?></span>
                  <span class="read-time"><?= reading_time($post['body']) ?> min read</span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?= pagination_html($pag, $base_url) ?>
      <?php endif; ?>

    </main>

    <aside class="sidebar" style="padding-top:24px">
      <div class="sidebar-widget">
        <div class="widget-title">All Topics</div>
        <div class="widget-body">
          <ul class="cat-list">
            <?php foreach (get_categories() as $cat): ?>
              <?php if ($cat['post_count'] > 0): ?>
              <li>
                <a href="<?= h(BLOG_URL) ?>/category.php?slug=<?= h($cat['slug']) ?>">
                  <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= h($cat['color']) ?>;margin-right:6px"></span>
                  <?= h($cat['name']) ?>
                </a>
                <span class="count"><?= $cat['post_count'] ?></span>
              </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </aside>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
