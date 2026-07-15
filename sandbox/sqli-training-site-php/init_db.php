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
 * Only usable where you have shell access (Docker, SSH-enabled hosting).
 * For FTP-only rental hosting with no shell, use the web endpoint
 * instead: vuln/init.php?token=... or secure/init.php?token=... (see
 * lib/init_web.php and README.md).
 *
 * Usage (inside docker):
 *     php init_db.php                 # uses DB_NAME env var
 *     php init_db.php hifumi_vulnerable
 *     php init_db.php hifumi_secure
 */

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/schema.php';

$config = training_config();

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
training_run_schema($pdo);

echo "[init_db] training database initialised: {$dbname}\n";
