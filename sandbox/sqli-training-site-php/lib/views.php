<?php
/**
 * lib/views.php -- one function per screen, returns HTML.
 *
 * Kept intentionally free of SQL construction. The front controllers
 * (app_vulnerable.php / app_secure.php) do all the DB work and pass rows
 * in as plain arrays. That means the SAFE-vs-VULNERABLE distinction
 * lives entirely in the router files -- the views are identical.
 */

function view_index(): string
{
    return <<<HTML
<div class="panel">
  <h2>Hifumi Secure Shop へようこそ</h2>
  <p class="muted">
    社内 CRM / EC 管理システムを模したローカル教材です。
    左のメニューから各画面を確認できます。
  </p>
  <p>
    <a class="btn" href="/login">ログイン画面へ</a>
    <a class="btn secondary" href="/dashboard">ダッシュボードへ</a>
  </p>
</div>
HTML;
}

function view_login(?string $login_error = null, ?string $last_username = null): string
{
    $err  = $login_error ? '<div class="alert danger">' . h($login_error) . '</div>' : '';
    $u    = h($last_username ?? '');
    return <<<HTML
<div class="panel" style="max-width:420px;">
  <h2>ログイン</h2>
  {$err}
  <form method="post" action="/login">
    <div class="form-row">
      <label for="username">ユーザー名</label>
      <input type="text" id="username" name="username" autocomplete="off" value="{$u}">
    </div>
    <div class="form-row">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password">
    </div>
    <button type="submit" class="btn">ログイン</button>
  </form>
</div>

<div class="panel" style="max-width:420px;">
  <h2>新規ユーザー登録</h2>
  <form method="post" action="/register">
    <div class="form-row">
      <label for="reg_token">招待コード</label>
      <input type="text" id="reg_token" name="token" autocomplete="off">
    </div>
    <div class="form-row">
      <label for="reg_username">ユーザー名</label>
      <input type="text" id="reg_username" name="username" autocomplete="off">
    </div>
    <div class="form-row">
      <label for="reg_password">パスワード</label>
      <input type="password" id="reg_password" name="password">
    </div>
    <button type="submit" class="btn secondary">登録する</button>
  </form>
</div>
HTML;
}

function view_dashboard(int $customers, int $contacts, int $orders, string $admin_user): string
{
    $user = current_user();
    $role = current_role();
    $banner = $user
        ? '<div class="success-banner">✅ Logged in as ' . h($user) . ($role ? ' (' . h($role) . ')' : '') . '</div>'
        : '<div class="alert warn">ゲストとして閲覧中です。ログインしていません。</div>';
    return <<<HTML
{$banner}

<div class="card-row">
  <div class="stat-card">
    <div class="label">Total Customers</div>
    <div class="value">{$customers}</div>
  </div>
  <div class="stat-card">
    <div class="label">Open Contacts</div>
    <div class="value">{$contacts}</div>
  </div>
  <div class="stat-card">
    <div class="label">Monthly Orders</div>
    <div class="value">{$orders}</div>
  </div>
  <div class="stat-card">
    <div class="label">Admin User</div>
    <div class="value" style="font-size:1.1rem;">{$admin_user}</div>
  </div>
</div>

<div class="panel">
  <h2>ショートカット</h2>
  <p>
    <a href="/catalog">商品検索</a> ・
    <a href="/customer?id=1">顧客詳細</a> ・
    <a href="/track?order_id=1">注文照会</a> ・
    <a href="/contacts">問い合わせ一覧</a> ・
    <a href="/admin/report">管理者レポート <span class="badge admin-only">管理者のみ</span></a>
  </p>
</div>
HTML;
}

/** Product catalog. $rows: list of [id, name, price] (numeric-indexed). */
function view_catalog(string $q, array $rows, ?string $error = null): string
{
    $qh   = h($q);
    $errh = $error ? '<div class="alert danger">クエリエラー: ' . h($error) . '</div>' : '';
    $tbody = '';
    if (!$rows) {
        $tbody = '<tr><td colspan="3" class="muted">該当する商品がありません。</td></tr>';
    } else {
        foreach ($rows as $r) {
            $tbody .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                h((string)($r[0] ?? '')),
                h((string)($r[1] ?? '')),
                h((string)($r[2] ?? '')),
            );
        }
    }
    return <<<HTML
{$errh}
<div class="panel">
  <h2>商品検索</h2>
  <form method="get" action="/catalog">
    <div class="form-row">
      <label for="q">商品名で検索</label>
      <input type="search" id="q" name="q" value="{$qh}" placeholder="例: Keyboard">
    </div>
    <button type="submit" class="btn">検索</button>
  </form>
</div>

<div class="panel">
  <h2>検索結果</h2>
  <table class="data-table">
    <thead><tr><th>ID</th><th>Name</th><th>Price</th></tr></thead>
    <tbody>{$tbody}</tbody>
  </table>
</div>
HTML;
}

function view_customer(string $raw_id, ?array $row, ?string $error = null): string
{
    $idh  = h($raw_id);
    $errh = $error ? '<div class="alert danger">クエリエラー: ' . h($error) . '</div>' : '';
    if ($row) {
        $body = sprintf(
            '<div class="panel"><h2>顧客詳細</h2><table class="data-table">'
          . '<tr><th>ID</th><td>%s</td></tr>'
          . '<tr><th>Name</th><td>%s</td></tr>'
          . '<tr><th>Email</th><td>%s</td></tr>'
          . '<tr><th>Phone</th><td>%s</td></tr>'
          . '<tr><th>Note</th><td>%s</td></tr>'
          . '</table></div>',
            h((string)($row[0] ?? '')), h((string)($row[1] ?? '')),
            h((string)($row[2] ?? '')), h((string)($row[3] ?? '')),
            h((string)($row[4] ?? '')),
        );
    } else {
        $body = '<div class="panel"><p class="muted">該当する顧客が見つかりません（id = ' . $idh . '）。</p></div>';
    }
    return <<<HTML
{$errh}
<div class="panel">
  <h2>顧客ID検索</h2>
  <form method="get" action="/customer">
    <div class="form-row">
      <label for="id">Customer ID</label>
      <input type="text" id="id" name="id" value="{$idh}">
    </div>
    <button type="submit" class="btn">表示</button>
  </form>
</div>
{$body}
HTML;
}

function view_order_track(string $raw_order_id, ?array $row, ?string $error = null): string
{
    $idh  = h($raw_order_id);
    $errh = $error ? '<div class="alert danger">クエリエラー: ' . h($error) . '</div>' : '';
    if ($row) {
        $body = sprintf(
            '<div class="panel"><h2>注文詳細</h2><table class="data-table">'
          . '<tr><th>ID</th><td>%s</td></tr>'
          . '<tr><th>Tracking Code</th><td>%s</td></tr>'
          . '<tr><th>Status</th><td>%s</td></tr>'
          . '</table></div>',
            h((string)($row[0] ?? '')), h((string)($row[1] ?? '')),
            h((string)($row[2] ?? '')),
        );
    } else {
        $body = '<div class="panel"><p class="muted">該当する注文が見つかりません（order_id = ' . $idh . '）。</p></div>';
    }
    return <<<HTML
{$errh}
<div class="panel">
  <h2>注文照会</h2>
  <form method="get" action="/track">
    <div class="form-row">
      <label for="order_id">Order ID</label>
      <input type="text" id="order_id" name="order_id" value="{$idh}">
    </div>
    <button type="submit" class="btn">照会</button>
  </form>
</div>
{$body}
HTML;
}

function view_contacts(string $q, array $rows, ?string $error = null): string
{
    $qh   = h($q);
    $errh = $error ? '<div class="alert danger">クエリエラー: ' . h($error) . '</div>' : '';
    $tbody = '';
    if (!$rows) {
        $tbody = '<tr><td colspan="4" class="muted">該当する問い合わせがありません。</td></tr>';
    } else {
        foreach ($rows as $r) {
            $tbody .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                h((string)($r[0] ?? '')), h((string)($r[1] ?? '')),
                h((string)($r[2] ?? '')), h((string)($r[3] ?? '')),
            );
        }
    }
    return <<<HTML
{$errh}
<div class="panel">
  <h2>問い合わせ検索</h2>
  <form method="get" action="/contacts">
    <div class="form-row">
      <label for="q">問い合わせ内容で検索</label>
      <input type="search" id="q" name="q" value="{$qh}" placeholder="例: invoice">
    </div>
    <button type="submit" class="btn">検索</button>
  </form>
</div>

<div class="panel">
  <h2>問い合わせ一覧</h2>
  <table class="data-table">
    <thead><tr><th>ID</th><th>Customer</th><th>Email</th><th>Message</th></tr></thead>
    <tbody>{$tbody}</tbody>
  </table>
</div>
HTML;
}

/** Admin report. $rows: list of ['username'=>..., 'activity'=>...]. */
function view_report(array $rows, ?string $error = null): string
{
    $errh = $error ? '<div class="alert danger">クエリエラー: ' . h($error) . '</div>' : '';
    $tbody = '';
    if (!$rows) {
        $tbody = '<tr><td colspan="2" class="muted">登録済みユーザーがいません。</td></tr>';
    } else {
        foreach ($rows as $r) {
            $tbody .= sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                h((string)($r['username'] ?? '')),
                h((string)($r['activity'] ?? '')),
            );
        }
    }
    return <<<HTML
{$errh}
<div class="panel">
  <h2>ユーザー別 アクティビティレポート</h2>
  <table class="data-table">
    <thead><tr><th>Username</th><th>Latest Activity</th></tr></thead>
    <tbody>{$tbody}</tbody>
  </table>
</div>
HTML;
}
