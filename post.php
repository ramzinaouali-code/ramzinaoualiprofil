<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: ' . BLOG_URL . '/');
    exit;
}

$post = get_post_by_slug($slug);
if (!$post) {
    http_response_code(404);
    $page_title = '404 — Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:60px 0;text-align:center"><h1>404</h1><p>Post not found. <a href="' . h(BLOG_URL) . '/">Go home</a></p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$books   = get_post_books($post['id']);
$adj     = get_adjacent_posts($post['id']);
$page_title      = $post['title'];
$meta_description = $post['meta_description'] ?: $post['excerpt'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div style="background:var(--bg-gray);border-bottom:1px solid var(--border)">
  <div class="container">
    <nav class="breadcrumb">
      <a href="<?= h(BLOG_URL) ?>/">Home</a>
      <?php if ($post['category_name']): ?>
        <span>&rsaquo;</span>
        <a href="<?= h(BLOG_URL) ?>/category.php?slug=<?= h($post['category_slug']) ?>"><?= h($post['category_name']) ?></a>
      <?php endif; ?>
      <span>&rsaquo;</span>
      <span><?= h(mb_strimwidth($post['title'], 0, 60, '...')) ?></span>
    </nav>
  </div>
</div>

<div class="container">
  <div class="page-layout">

    <!-- Article -->
    <main>
      <article>
        <header class="post-header" style="margin-top:28px">
          <?php if ($post['category_name']): ?>
            <a class="cat-badge" href="<?= h(BLOG_URL) ?>/category.php?slug=<?= h($post['category_slug']) ?>"
               style="background:<?= h($post['category_color'] ?? '#1a73e8') ?>">
              <?= h($post['category_name']) ?>
            </a>
          <?php endif; ?>
          <h1><?= h($post['title']) ?></h1>
          <div class="post-meta">
            <time datetime="<?= h($post['created_at']) ?>"><?= h(format_date($post['created_at'])) ?></time>
            <span>&middot; <?= reading_time($post['body']) ?> min read</span>
            <?php if ($post['tags']): ?>
              <span>&middot;
                <?php foreach (explode(',', $post['tags']) as $tag): ?>
                  <a href="<?= h(BLOG_URL) ?>/category.php?tag=<?= h(trim($tag)) ?>"
                     style="font-size:12px;background:var(--accent-light);color:var(--accent);padding:2px 7px;border-radius:3px;margin-left:3px">
                    <?= h(trim($tag)) ?>
                  </a>
                <?php endforeach; ?>
              </span>
            <?php endif; ?>
          </div>
        </header>

        <!-- Hero Thumbnail -->
        <div class="post-hero-thumb"
             style="background: <?= h($post['thumbnail_css'] ?: 'linear-gradient(135deg,#1a73e8,#0d47a1)') ?>">
          <?php if (!empty($post['photo_url'])): ?>
            <img src="<?= h($post['photo_url']) ?>" alt="<?= h($post['title']) ?>" loading="eager">
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="post-body">
          <?= $post['body'] /* HTML from Claude — intentionally unescaped */ ?>
        </div>

        <!-- Affiliate Books -->
        <?php if (!empty($books)): ?>
        <section class="books-section">
          <h2>&#128218; Recommended Reading</h2>
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:20px">
            Books our AI recommends to deepen your knowledge on this topic.
          </p>
          <div class="books-grid">
            <?php foreach ($books as $book): ?>
              <div class="book-card">
                <div class="book-icon">&#128218;</div>
                <div class="book-title"><?= h($book['title']) ?></div>
                <div class="book-author">by <?= h($book['author']) ?></div>
                <div class="book-reason"><?= h($book['reason']) ?></div>
                <a href="<?= h(amazon_url($book['search_query'])) ?>" class="book-link" target="_blank" rel="noopener sponsored">
                  View on Amazon &rarr;
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Post Navigation -->
        <nav class="post-nav">
          <div class="post-nav-item prev">
            <?php if ($adj['prev']): ?>
              <div class="post-nav-label">&larr; Previous</div>
              <a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($adj['prev']['slug']) ?>">
                <?= h(mb_strimwidth($adj['prev']['title'], 0, 70, '...')) ?>
              </a>
            <?php else: ?>
              <div class="post-nav-label">&larr; Previous</div>
              <span style="color:var(--text-muted)">No older posts</span>
            <?php endif; ?>
          </div>
          <div class="post-nav-item next">
            <?php if ($adj['next']): ?>
              <div class="post-nav-label">Next &rarr;</div>
              <a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($adj['next']['slug']) ?>">
                <?= h(mb_strimwidth($adj['next']['title'], 0, 70, '...')) ?>
              </a>
            <?php else: ?>
              <div class="post-nav-label">Next &rarr;</div>
              <span style="color:var(--text-muted)">No newer posts</span>
            <?php endif; ?>
          </div>
        </nav>

      </article>
    </main>

    <!-- Sidebar -->
    <aside class="sidebar" style="margin-top:28px">
      <div class="sidebar-widget">
        <div class="widget-title">Latest Posts</div>
        <div class="widget-body">
          <ul class="recent-posts-list">
            <?php foreach (get_recent_posts(6) as $rp): ?>
              <li>
                <a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($rp['slug']) ?>"><?= h($rp['title']) ?></a>
                <span class="rp-date"><?= h(format_date($rp['created_at'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="sidebar-widget">
        <div class="widget-title">Topics</div>
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
