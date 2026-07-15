<?php
/**
 * app_secure.php -- Hifumi Secure Shop (SECURE VERSION).
 *
 * The fixed half of the PHP + MySQL training pair. Every route mirrors a
 * route in app_vulnerable.php, but every query is built with a PDO
 * placeholder (`?`) instead of string interpolation, every numeric
 * input is validated before use, and the login-failure message is
 * always identical so an attacker cannot enumerate accounts.
 *
 * Try the same attack inputs from README.md against this app (port
 * 5010) and compare: the login bypass fails, the UNION leaks return
 * nothing, invalid ids get a 400, and the invite-token bypass is
 * treated as a literal string that matches no invitation.
 *
 * Run:
 *     docker compose up --build
 * Then open http://127.0.0.1:5010
 */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';
require __DIR__ . '/lib/views.php';

$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

if (PHP_SAPI === 'cli-server' && preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|map)$/i', $path)) {
    return false;
}

start_training_session('hifumi_secure_session');
$pdo = training_pdo();


// =====================================================================
// Routes
// =====================================================================

if ($path === '/' && $method === 'GET') {
    render_layout('Home', view_index());
    return;
}


// ---------------------------------------------------------------------
// Scenario 1 & 2 (fixed): generic error message, parameterized login.
// ---------------------------------------------------------------------
if ($path === '/login') {
    if ($method === 'GET') {
        render_layout('Login', view_login());
        return;
    }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // FIX: parameterized query -- the "'#" / "' OR '1'='1" tricks that
    // break app_vulnerable.php's login are treated as plain literal text
    // here.
    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE username = ? AND password = ?'
    );
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        flash('ok', 'Logged in as ' . $user['username']);
        redirect('/dashboard');
    }

    // FIX: identical, generic message whether the username exists or
    // not -- no enumeration signal is leaked either way.
    render_layout('Login', view_login('ユーザー名またはパスワードが正しくありません', $username));
    return;
}

if ($path === '/logout') {
    $_SESSION = [];
    session_destroy();
    start_training_session('hifumi_secure_session');
    flash('info', 'ログアウトしました。');
    redirect('/');
}


// ---------------------------------------------------------------------
// Scenario 7 (part 1, fixed): parameterized invite-token check.
// ---------------------------------------------------------------------
if ($path === '/register' && $method === 'POST') {
    $token    = $_POST['token']    ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '' || $token === '') {
        flash('warn', '招待コード・ユーザー名・パスワードをすべて入力してください。');
        redirect('/login');
    }

    // FIX: parameterized token check. `' OR '1'='1' #` is now a literal
    // string that matches no real token.
    $stmt = $pdo->prepare(
        'SELECT 1 FROM invite_tokens WHERE token = ? AND used = 0'
    );
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
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
// Scenario 3 (fixed): parameterized product search.
// ---------------------------------------------------------------------
if ($path === '/catalog' && $method === 'GET') {
    require_login();
    $q = $_GET['q'] ?? '';
    // FIX: parameterized query -- "%' UNION SELECT ..." becomes a literal
    // search string and matches nothing.
    $stmt = $pdo->prepare(
        'SELECT id, name, price FROM products WHERE name LIKE ?'
    );
    $stmt->execute(["%{$q}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    render_layout('Product Catalog', view_catalog($q, $rows));
    return;
}


// ---------------------------------------------------------------------
// Scenario 4 (fixed): validated integer id + parameterized query.
// ---------------------------------------------------------------------
if ($path === '/customer' && $method === 'GET') {
    require_login();
    $raw_id = $_GET['id'] ?? '1';
    // FIX: reject anything that isn't a plain integer before it ever
    // reaches SQL, instead of interpolating into a numeric context.
    if (!preg_match('/\A-?\d+\z/', trim($raw_id))) {
        http_response_code(400);
        render_layout('Customer Detail',
            view_customer($raw_id, null, 'idは整数で指定してください。'));
        return;
    }
    $stmt = $pdo->prepare(
        'SELECT id, name, email, phone, note FROM customers WHERE id = ?'
    );
    $stmt->execute([(int)$raw_id]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    render_layout('Customer Detail', view_customer($raw_id, $row ?: null));
    return;
}


// ---------------------------------------------------------------------
// Scenario 5 (fixed): validated integer order_id.
// ---------------------------------------------------------------------
if ($path === '/track' && $method === 'GET') {
    require_login();
    $raw_order_id = $_GET['order_id'] ?? '1';
    if (!preg_match('/\A\d+\z/', trim($raw_order_id))) {
        http_response_code(400);
        render_layout('Order Tracking',
            view_order_track($raw_order_id, null, 'order_idは整数で指定してください。'));
        return;
    }
    $stmt = $pdo->prepare(
        'SELECT id, tracking_code, status FROM orders WHERE id = ?'
    );
    $stmt->execute([(int)$raw_order_id]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    render_layout('Order Tracking', view_order_track($raw_order_id, $row ?: null));
    return;
}


// ---------------------------------------------------------------------
// Scenario 6 (fixed): parameterized contact search.
// ---------------------------------------------------------------------
if ($path === '/contacts' && $method === 'GET') {
    require_login();
    $q = $_GET['q'] ?? '';
    $stmt = $pdo->prepare(
        'SELECT id, customer_name, email, message FROM contacts WHERE message LIKE ?'
    );
    $stmt->execute(["%{$q}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    render_layout('Contacts', view_contacts($q, $rows));
    return;
}


// ---------------------------------------------------------------------
// Scenario 7 (part 2, fixed): stored values are still parameterized on
// use, not just on insert. Trusting "we already saved it safely" is
// not enough -- every SQL that uses a stored value needs the same
// care.
// ---------------------------------------------------------------------
if ($path === '/admin/report' && $method === 'GET') {
    require_admin();
    $usernames = $pdo->query('SELECT username FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare(
        'SELECT o.status AS activity FROM orders o '
      . 'JOIN customers c ON o.customer_id = c.id '
      . 'WHERE c.name = ?'
    );
    $rows = [];
    foreach ($usernames as $uname) {
        $stmt->execute([$uname]);
        $r = $stmt->fetch();
        $rows[] = [
            'username' => $uname,
            'activity' => $r ? (string)$r['activity'] : '(no matching activity)',
        ];
    }
    render_layout('Admin Report', view_report($rows));
    return;
}


http_response_code(404);
flash('warn', 'ページが見つかりません。');
render_layout('Not Found', view_index());
