<?php
require_once __DIR__ . '/includes/functions.php';

$page        = max(1, (int)($_GET['page'] ?? 1));
$total       = count_posts();
$pag         = paginate($total, POSTS_PER_PAGE, $page);
$posts       = get_posts(POSTS_PER_PAGE, $pag['offset']);
$recent      = get_recent_posts(6);
$categories  = get_categories();

$page_title      = null; // Use blog name only
$meta_description = BLOG_TAGLINE;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="page-layout">

    <!-- Main Column -->
    <main>

      <?php if (empty($posts)): ?>
        <div class="empty-state">
          <h3>No posts yet</h3>
          <p>Posts are being generated. Check back soon, or trigger generation via the admin panel.</p>
        </div>

      <?php else: ?>

        <?php
        $hero = array_shift($posts); // First post is hero
        ?>

        <!-- Hero Post -->
        <article class="hero-card">
          <div class="hero-thumb" style="background: <?= h($hero['thumbnail_css'] ?: 'linear-gradient(135deg,#1a73e8,#0d47a1)') ?>">
            <?php if (!empty($hero['photo_url'])): ?>
              <img src="<?= h($hero['photo_url']) ?>" alt="<?= h($hero['title']) ?>" loading="eager">
            <?php endif; ?>
          </div>
          <div class="hero-body">
            <?php if ($hero['category_name']): ?>
              <span class="cat-badge" style="background:<?= h($hero['category_color'] ?? '#1a73e8') ?>">
                <?= h($hero['category_name']) ?>
              </span>
            <?php endif; ?>
            <h2><a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($hero['slug']) ?>"><?= h($hero['title']) ?></a></h2>
            <p class="hero-excerpt"><?= h($hero['excerpt']) ?></p>
            <div class="hero-meta">
              <?= h(format_date($hero['created_at'])) ?>
              &middot; <?= reading_time($hero['body']) ?> min read
            </div>
            <a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($hero['slug']) ?>" class="read-more">Read Article</a>
          </div>
        </article>

        <!-- Section Header -->
        <?php if (!empty($posts)): ?>
        <div class="section-header">
          <h2 class="section-title">
            <span class="section-title-bar"></span>Latest Articles
          </h2>
          <span class="text-muted" style="font-size:13px"><?= $total ?> articles</span>
        </div>

        <!-- Post Grid -->
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
        <?php endif; ?>

        <?= pagination_html($pag, BLOG_URL . '/index.php') ?>

      <?php endif; ?>
    </main>

    <!-- Sidebar -->
    <aside class="sidebar">

      <!-- Recent Posts -->
      <div class="sidebar-widget">
        <div class="widget-title">Latest Posts</div>
        <div class="widget-body">
          <ul class="recent-posts-list">
            <?php foreach ($recent as $rp): ?>
              <li>
                <a href="<?= h(BLOG_URL) ?>/post.php?slug=<?= h($rp['slug']) ?>"><?= h($rp['title']) ?></a>
                <span class="rp-date"><?= h(format_date($rp['created_at'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <!-- Categories -->
      <div class="sidebar-widget">
        <div class="widget-title">Topics</div>
        <div class="widget-body">
          <ul class="cat-list">
            <?php foreach ($categories as $cat): ?>
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

      <!-- Stats -->
      <div class="sidebar-widget">
        <div class="widget-title">About This Blog</div>
        <div class="widget-body">
          <div class="stat-grid">
            <div class="stat-item">
              <div class="num"><?= $total ?></div>
              <div class="lbl">Articles</div>
            </div>
            <div class="stat-item">
              <div class="num"><?= count($categories) ?></div>
              <div class="lbl">Topics</div>
            </div>
          </div>
          <p style="font-size:12px;color:var(--text-muted);margin-top:12px;line-height:1.5">
            AI-assisted analysis for healthcare cybersecurity, privacy, and compliance professionals.
          </p>
        </div>
      </div>

    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
