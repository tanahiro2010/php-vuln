<?php
/**
 * lib/layout.php -- HTML rendering helpers shared by both apps.
 *
 * Reproduces the same UI the Flask/Jinja templates produced so any
 * screenshots taken against the previous version still match. No SQLi-
 * suggesting badges, banners, or hints anywhere in the markup -- the
 * site reads as a real internal CRM/EC admin tool.
 */

/** h() = htmlspecialchars() shorthand, always UTF-8, always both quotes. */
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_layout(string $page_title, string $inner_html): void
{
    $flashes  = drain_flashes();
    $user     = current_user();
    $role     = current_role();
    ?><!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?> - Hifumi Secure Shop</title>
  <link rel="stylesheet" href="/static/style.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        Hifumi Secure Shop
        <small>Internal CRM / EC Admin</small>
      </div>
      <nav>
        <a href="/dashboard">📊 Dashboard</a>
        <a href="/catalog">🛒 Product Catalog</a>
        <a href="/customer?id=1">👤 Customers <span class="badge internal">社内用</span></a>
        <a href="/track?order_id=1">📦 Order Tracking</a>
        <a href="/contacts">✉️ Contacts <span class="badge internal">顧客情報</span></a>
        <a href="/admin/report">📈 Admin Report <span class="badge admin-only">管理者のみ</span></a>
        <?php if ($user): ?>
          <a href="/logout">🚪 Logout (<?= h($user) ?>)</a>
        <?php else: ?>
          <a href="/login">🔑 Login</a>
        <?php endif; ?>
      </nav>
    </aside>

    <div class="main-col">
      <div class="topbar">
        <div class="title"><?= h($page_title) ?></div>
      </div>

      <div class="content">
        <?php foreach ($flashes as $f): ?>
          <div class="alert <?= h($f['category']) ?>" data-autohide><?= h($f['message']) ?></div>
        <?php endforeach; ?>

        <?= $inner_html /* trusted -- each route builds this with h() */ ?>
      </div>

      <footer class="app-footer">
        &copy; 2026 Hifumi Secure Shop
      </footer>
    </div>
  </div>
  <script src="/static/app.js"></script>
</body>
</html><?php
}
