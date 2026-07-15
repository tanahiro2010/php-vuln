<?php
/**
 * lib/db.php -- shared PDO connection helper.
 *
 * ALL of the SQL injection vulnerabilities in the training app are in
 * app_vulnerable.php's *own query construction* (string interpolation
 * into the query text). The PDO object returned here is just the
 * connection -- it does not itself introduce vulnerabilities and it is
 * used identically by the secure version.
 */

function training_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function training_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = training_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['dbname'],
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Emulated prepares are OFF so the driver uses server-side
        // parameterized queries in secure_app -- keeps the placeholder
        // fix genuinely equivalent to what production PHP + MySQL setups
        // do. Vulnerable code still concatenates strings, so this has no
        // effect on the training vulnerabilities themselves.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
