<?php
/**
 * init_db.php -- Hifumi Secure Shop (PHP training build).
 *
 * CLI helper that drops and recreates the schema in a single MySQL
 * database and reloads seed data. Runs on each app container's startup
 * (see docker-compose.yml) so every demo begins from a clean slate --
 * important because the second-order SQLi scenario intentionally dirties
 * the users table with a payload as a stored username.
 *
 * Usage (inside docker):
 *     php init_db.php                 # uses DB_NAME env var
 *     php init_db.php hifumi_vulnerable
 *     php init_db.php hifumi_secure
 */

$config = require __DIR__ . '/config.php';

// Optional CLI argument overrides the DB_NAME env var.
$dbname = $argv[1] ?? $config['dbname'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;charset=utf8mb4',
    $config['host'],
    $config['port']
);

// Wait for MySQL to be ready (docker-compose depends_on is not enough --
// the mysql container reports "started" before it can actually accept
// connections). Retry for up to ~60 seconds.
$pdo = null;
for ($attempt = 1; $attempt <= 60; $attempt++) {
    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        break;
    } catch (PDOException $e) {
        fwrite(STDERR, "[init_db] mysql not ready yet (attempt {$attempt}): {$e->getMessage()}\n");
        sleep(1);
    }
}
if ($pdo === null) {
    fwrite(STDERR, "[init_db] failed to connect to MySQL after 60s -- aborting\n");
    exit(1);
}

$pdo->exec("USE `{$dbname}`");

// ----------------------------------------------------------------------
// Schema.  Every table is dropped & recreated on each run so demos start
// from a known state.
// ----------------------------------------------------------------------
$schema = <<<'SQL'
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS invite_tokens;

CREATE TABLE users (
    id       INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role     VARCHAR(32)  NOT NULL,
    email    VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id    INT PRIMARY KEY AUTO_INCREMENT,
    name  VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    stock INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    name              VARCHAR(255) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    phone             VARCHAR(64)  NOT NULL,
    last_order_total  INT NOT NULL,
    note              VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    customer_id    INT NOT NULL,
    tracking_code  VARCHAR(64)  NOT NULL,
    status         VARCHAR(64)  NOT NULL,
    secret_note    VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contacts (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    customer_name  VARCHAR(255) NOT NULL,
    email          VARCHAR(255) NOT NULL,
    message        VARCHAR(255) NOT NULL,
    internal_memo  VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invite_tokens (
    id    INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(255) NOT NULL,
    used  TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
    $pdo->exec($stmt);
}

// ----------------------------------------------------------------------
// Seed data.  Kept parameterised so no accidental SQLi creeps in here.
// NOTE: user passwords are stored in plaintext for the training. Never
// do that in production; use password_hash() / bcrypt.
// ----------------------------------------------------------------------
$users = [
    [1, 'admin',  'admin-secret', 'admin', 'admin@example.local'],
    [2, 'tanaka', 'pass1234',     'staff', 'tanaka@example.local'],
    [3, 'sato',   'password',     'staff', 'sato@example.local'],
];
$stmt = $pdo->prepare(
    "INSERT INTO users (id, username, password, role, email) VALUES (?, ?, ?, ?, ?)"
);
foreach ($users as $u) { $stmt->execute($u); }

$products = [
    [1, 'Premium Keyboard',  19800, 12],
    [2, 'Secure Mouse',       4980, 30],
    [3, 'Cloud Backup Plan',  1200, 100],
    [4, 'CRM License',       30000, 8],
];
$stmt = $pdo->prepare("INSERT INTO products (id, name, price, stock) VALUES (?, ?, ?, ?)");
foreach ($products as $p) { $stmt->execute($p); }

$customers = [
    [1, 'Alice Demo', 'alice@example.local', '090-0000-0001', 24800, 'VIP customer'],
    [2, 'Bob Sample', 'bob@example.local',   '090-0000-0002',  4980, 'Needs follow-up'],
    [3, 'Chika Test', 'chika@example.local', '090-0000-0003', 31200, 'Internal demo data'],
];
$stmt = $pdo->prepare(
    "INSERT INTO customers (id, name, email, phone, last_order_total, note) VALUES (?, ?, ?, ?, ?, ?)"
);
foreach ($customers as $c) { $stmt->execute($c); }

$orders = [
    [1, 1, 'ORD-1001', 'shipped',   'gift option enabled'],
    [2, 2, 'ORD-1002', 'preparing', 'payment confirmed'],
    [3, 3, 'ORD-1003', 'delayed',   'call customer before shipping'],
];
$stmt = $pdo->prepare(
    "INSERT INTO orders (id, customer_id, tracking_code, status, secret_note) VALUES (?, ?, ?, ?, ?)"
);
foreach ($orders as $o) { $stmt->execute($o); }

$contacts = [
    [1, 'Alice Demo', 'alice@example.local', 'Please send invoice.',    'High priority'],
    [2, 'Bob Sample', 'bob@example.local',   'I want to change my plan.', 'Sales follow-up'],
    [3, 'Chika Test', 'chika@example.local', 'Delivery date question.',  'Demo memo'],
];
$stmt = $pdo->prepare(
    "INSERT INTO contacts (id, customer_name, email, message, internal_memo) VALUES (?, ?, ?, ?, ?)"
);
foreach ($contacts as $ct) { $stmt->execute($ct); }

$tokens = [
    [1, 'INVITE-2026-STAFF-A1', 0],
    [2, 'INVITE-2026-STAFF-A2', 0],
];
$stmt = $pdo->prepare("INSERT INTO invite_tokens (id, token, used) VALUES (?, ?, ?)");
foreach ($tokens as $t) { $stmt->execute($t); }

echo "[init_db] training database initialised: {$dbname}\n";
