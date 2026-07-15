<?php
/**
 * app_vulnerable.php -- Hifumi Secure Shop (VULNERABLE TRAINING BUILD).
 *
 * PHP + MySQL counterpart of the Flask app.py. Every query built with
 * string interpolation here is INTENTIONALLY UNSAFE and exists so an
 * instructor can demonstrate SQL injection against a local, throwaway
 * MySQL database full of fake data.
 *
 * DO NOT copy the query-building patterns in this file into real
 * projects. See app_secure.php for the fixed version of every route
 * below.
 *
 * MySQL note: comment payloads must be `#` or `-- ` (dash-dash-SPACE),
 * NOT bare `--`, since MySQL only treats `--` as a comment when the
 * next character is whitespace. The training uses `#` throughout.
 *
 * Run:
 *     docker compose up --build
 * Then open http://127.0.0.1:5050
 */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';
require __DIR__ . '/lib/views.php';

$config = require __DIR__ . '/config.php';
// Under Apache + FallbackResource, static files (style.css, app.js) are
// served by Apache directly and never reach PHP. If for some reason a
// static-looking URL hits this router (e.g. running under `php -S`),
// let the built-in server fall through to its own static handler.
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

if (PHP_SAPI === 'cli-server' && preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|map)$/i', $path)) {
    return false;
}

start_training_session('hifumi_vuln_session');
$pdo = training_pdo();

// ---------------------------------------------------------------------
// Errors -- never let PHP show a bare 500 during a live demo.
// ---------------------------------------------------------------------
set_exception_handler(function (\Throwable $e) {
    http_response_code(500);
    flash('danger', 'サーバー内部エラーが発生しました: ' . $e->getMessage());
    echo render_layout_capture('Error', view_index());
});

function render_layout_capture(string $title, string $inner): string
{
    ob_start();
    render_layout($title, $inner);
    return (string)ob_get_clean();
}


// =====================================================================
// Route dispatch
// =====================================================================

if ($path === '/' && $method === 'GET') {
    render_layout('Home', view_index());
    return;
}


// ---------------------------------------------------------------------
// Scenario 1 & 2: user enumeration + SQLi login bypass
// ---------------------------------------------------------------------
if ($path === '/login') {
    if ($method === 'GET') {
        render_layout('Login', view_login());
        return;
    }
    // POST
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $login_error = null;
    $user = null;
    try {
        // WARNING: This query is intentionally vulnerable for training.
        // Do not use this style in production. String interpolation lets
        // an attacker close the quote early and rewrite the WHERE clause
        // (username = admin' #  or  admin' OR '1'='1' # in MySQL).
        $query = "SELECT * FROM users WHERE username = '{$username}' AND password = '{$password}'";
        $stmt  = $pdo->query($query);
        $user  = $stmt ? $stmt->fetch() : null;
    } catch (\Throwable $e) {
        $login_error = 'クエリエラー: ' . $e->getMessage();
    }

    if ($user) {
        $_SESSION['user'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        flash('ok', 'Logged in as ' . $user['username']);
        redirect('/dashboard');
    }

    if ($login_error === null) {
        // Scenario 1: user enumeration via a differing error message.
        // WARNING: This query is intentionally vulnerable for training.
        try {
            $exists_query = "SELECT 1 FROM users WHERE username = '{$username}'";
            $exists = (bool)($pdo->query($exists_query)?->fetch());
        } catch (\Throwable $e) {
            $exists = false;
            $login_error = 'クエリエラー: ' . $e->getMessage();
        }
        if ($login_error === null) {
            $login_error = $exists ? 'パスワードが違います' : 'そのユーザーは存在しません';
        }
    }

    render_layout('Login', view_login($login_error, $username));
    return;
}

if ($path === '/logout') {
    $_SESSION = [];
    session_destroy();
    // Start a fresh session just to carry the flash back to /login.
    start_training_session('hifumi_vuln_session');
    flash('info', 'ログアウトしました。');
    redirect('/');
}


// ---------------------------------------------------------------------
// Scenario 7 (part 1): registration -- gated by an invite token whose
// check is INTENTIONALLY vulnerable (token concatenated into SQL).
// The INSERT itself uses a placeholder, mirroring the second-order
// lesson: the payload is stored inertly and fires later on read.
// ---------------------------------------------------------------------
if ($path === '/register' && $method === 'POST') {
    $token    = $_POST['token']    ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '' || $token === '') {
        flash('warn', '招待コード・ユーザー名・パスワードをすべて入力してください。');
        redirect('/login');
    }

    try {
        // WARNING: This query is intentionally vulnerable for training.
        // A payload like ' OR '1'='1' # in the token field bypasses the
        // check.
        $token_query = "SELECT 1 FROM invite_tokens WHERE token = '{$token}' AND used = 0";
        $ok = (bool)($pdo->query($token_query)?->fetch());
    } catch (\Throwable $e) {
        flash('danger', 'クエリエラー: ' . $e->getMessage());
        redirect('/login');
    }

    if (!$ok) {
        flash('danger', '招待コードが無効です。');
        redirect('/login');
    }

    $ins = $pdo->prepare(
        "INSERT INTO users (username, password, role, email) VALUES (?, ?, 'staff', ?)"
    );
    $ins->execute([$username, $password, $username . '@example.local']);

    flash('info', "ユーザー '{$username}' を登録しました。");
    redirect('/login');
}


if ($path === '/dashboard' && $method === 'GET') {
    require_login();
    $customers  = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $contacts   = (int)$pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    $orders     = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $admin_row  = $pdo->query("SELECT username FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    $admin_user = $admin_row['username'] ?? 'N/A';
    render_layout('Dashboard', view_dashboard($customers, $contacts, $orders, $admin_user));
    return;
}


// ---------------------------------------------------------------------
// Scenario 3: UNION-based leak of customer data through product search
// ---------------------------------------------------------------------
if ($path === '/catalog' && $method === 'GET') {
    require_login();
    $q = $_GET['q'] ?? '';
    $rows = [];
    $err  = null;
    try {
        // WARNING: This query is intentionally vulnerable for training.
        // Do not use this style in production.
        $query = "SELECT id, name, price FROM products WHERE name LIKE '%{$q}%'";
        $stmt  = $pdo->query($query);
        $rows  = $stmt ? $stmt->fetchAll(PDO::FETCH_NUM) : [];
    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
    render_layout('Product Catalog', view_catalog($q, $rows, $err));
    return;
}


// ---------------------------------------------------------------------
// Scenario 4: boolean-based blind SQLi via a numeric id parameter
// ---------------------------------------------------------------------
if ($path === '/customer' && $method === 'GET') {
    require_login();
    $raw_id = $_GET['id'] ?? '1';
    $row    = null;
    $err    = null;
    try {
        // WARNING: This query is intentionally vulnerable for training.
        // 'id' is placed directly into a numeric context with no quoting
        // and no validation, so payloads like "1 AND 1=1" / "1 AND 1=2"
        // change the WHERE clause itself (classic boolean-based blind).
        $query = "SELECT id, name, email, phone, note FROM customers WHERE id = {$raw_id}";
        $stmt  = $pdo->query($query);
        $row   = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;
        if ($row === false) { $row = null; }
    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
    render_layout('Customer Detail', view_customer($raw_id, $row, $err));
    return;
}


// ---------------------------------------------------------------------
// Scenario 5: order tracking -- vulnerable numeric context. MySQL has
// real SLEEP(), so `?order_id=1 OR SLEEP(3)` is a genuine time-based
// blind payload here (unlike the SQLite build where we had to fake it
// in Python).
// ---------------------------------------------------------------------
if ($path === '/track' && $method === 'GET') {
    require_login();
    $raw_order_id = $_GET['order_id'] ?? '1';
    $row = null;
    $err = null;
    try {
        // WARNING: This query is intentionally vulnerable for training.
        $query = "SELECT id, tracking_code, status FROM orders WHERE id = {$raw_order_id}";
        $stmt  = $pdo->query($query);
        $row   = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;
        if ($row === false) { $row = null; }
    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
    render_layout('Order Tracking', view_order_track($raw_order_id, $row, $err));
    return;
}


// ---------------------------------------------------------------------
// Scenario 6: UNION-based leak of internal_memo through contact search
// ---------------------------------------------------------------------
if ($path === '/contacts' && $method === 'GET') {
    require_login();
    $q = $_GET['q'] ?? '';
    $rows = [];
    $err  = null;
    try {
        // WARNING: This query is intentionally vulnerable for training.
        $query = "SELECT id, customer_name, email, message FROM contacts "
               . "WHERE message LIKE '%{$q}%'";
        $stmt  = $pdo->query($query);
        $rows  = $stmt ? $stmt->fetchAll(PDO::FETCH_NUM) : [];
    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
    render_layout('Contacts', view_contacts($q, $rows, $err));
    return;
}


// ---------------------------------------------------------------------
// Scenario 7 (part 2): second-order SQLi. The username was stored via
// the safe INSERT in /register above; the vulnerability only fires
// here, when the stored value is read back and interpolated into a
// new query.
// ---------------------------------------------------------------------
if ($path === '/admin/report' && $method === 'GET') {
    require_admin();
    $usernames = $pdo->query('SELECT username FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $rows = [];
    foreach ($usernames as $uname) {
        $activity = '(no matching activity)';
        try {
            // WARNING: This query is intentionally vulnerable for training.
            // 'uname' only ever entered the DB through the parameterized
            // INSERT in /register, but trusting a previously-stored value
            // and concatenating it here reintroduces SQL injection.  This
            // is a *second-order* injection: the payload does nothing at
            // registration time and only fires here.
            $q = "SELECT o.status AS activity FROM orders o "
               . "JOIN customers c ON o.customer_id = c.id "
               . "WHERE c.name = '{$uname}'";
            $stmt = $pdo->query($q);
            $r    = $stmt ? $stmt->fetch() : null;
            if ($r !== false && $r !== null) {
                $activity = (string)$r['activity'];
            }
        } catch (\Throwable $e) {
            $activity = 'クエリエラー: ' . $e->getMessage();
        }
        $rows[] = ['username' => $uname, 'activity' => $activity];
    }
    render_layout('Admin Report', view_report($rows));
    return;
}


// ---------------------------------------------------------------------
// 404
// ---------------------------------------------------------------------
http_response_code(404);
flash('warn', 'ページが見つかりません。');
render_layout('Not Found', view_index());
