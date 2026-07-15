<?php
/**
 * lib/schema.php -- shared "create tables + seed data" logic.
 *
 * Used by both:
 *  - init_db.php   (CLI, Docker: run automatically on container start)
 *  - lib/init_web.php (web endpoint, for FTP-only rental hosting where
 *    there is no SSH/cron access to run a CLI script directly)
 *
 * Table names are NOT prefixed on purpose: the vulnerable and secure
 * apps are meant to share the exact same schema and (on single-database
 * rental hosting) the exact same tables, so every SQLi payload used in
 * the slides/README works identically regardless of deployment target.
 * Isolation between "vulnerable" and "secure" comes from using two
 * separate MySQL databases (Docker: hifumi_vulnerable / hifumi_secure),
 * not from table-name prefixes.
 */

function training_run_schema(PDO $pdo): void
{
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

    // NOTE: passwords are stored in plaintext for the training. Never do
    // that in production; use password_hash() / bcrypt.
    $users = [
        [1, 'admin',  'admin-secret', 'admin', 'admin@example.local'],
        [2, 'tanaka', 'pass1234',     'staff', 'tanaka@example.local'],
        [3, 'sato',   'password',     'staff', 'sato@example.local'],
    ];
    $stmt = $pdo->prepare('INSERT INTO users (id, username, password, role, email) VALUES (?, ?, ?, ?, ?)');
    foreach ($users as $row) { $stmt->execute($row); }

    $products = [
        [1, 'Premium Keyboard',  19800, 12],
        [2, 'Secure Mouse',       4980, 30],
        [3, 'Cloud Backup Plan',  1200, 100],
        [4, 'CRM License',       30000, 8],
    ];
    $stmt = $pdo->prepare('INSERT INTO products (id, name, price, stock) VALUES (?, ?, ?, ?)');
    foreach ($products as $row) { $stmt->execute($row); }

    $customers = [
        [1, 'Alice Demo', 'alice@example.local', '090-0000-0001', 24800, 'VIP customer'],
        [2, 'Bob Sample', 'bob@example.local',   '090-0000-0002',  4980, 'Needs follow-up'],
        [3, 'Chika Test', 'chika@example.local', '090-0000-0003', 31200, 'Internal demo data'],
    ];
    $stmt = $pdo->prepare('INSERT INTO customers (id, name, email, phone, last_order_total, note) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($customers as $row) { $stmt->execute($row); }

    $orders = [
        [1, 1, 'ORD-1001', 'shipped',   'gift option enabled'],
        [2, 2, 'ORD-1002', 'preparing', 'payment confirmed'],
        [3, 3, 'ORD-1003', 'delayed',   'call customer before shipping'],
    ];
    $stmt = $pdo->prepare('INSERT INTO orders (id, customer_id, tracking_code, status, secret_note) VALUES (?, ?, ?, ?, ?)');
    foreach ($orders as $row) { $stmt->execute($row); }

    $contacts = [
        [1, 'Alice Demo', 'alice@example.local', 'Please send invoice.',      'High priority'],
        [2, 'Bob Sample', 'bob@example.local',   'I want to change my plan.', 'Sales follow-up'],
        [3, 'Chika Test', 'chika@example.local', 'Delivery date question.',   'Demo memo'],
    ];
    $stmt = $pdo->prepare('INSERT INTO contacts (id, customer_name, email, message, internal_memo) VALUES (?, ?, ?, ?, ?)');
    foreach ($contacts as $row) { $stmt->execute($row); }

    $tokens = [
        [1, 'INVITE-2026-STAFF-A1', 0],
        [2, 'INVITE-2026-STAFF-A2', 0],
    ];
    $stmt = $pdo->prepare('INSERT INTO invite_tokens (id, token, used) VALUES (?, ?, ?)');
    foreach ($tokens as $row) { $stmt->execute($row); }
}
