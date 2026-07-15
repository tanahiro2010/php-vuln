<?php
/**
 * lib/init_web.php -- web-triggered schema+seed runner.
 *
 * For FTP-only rental hosting with no SSH/cron access to run init_db.php
 * directly. Gated by a shared-secret token read from config.php's
 * 'init_token'. If that value is left empty (the default), this endpoint
 * ALWAYS 404s -- it must be explicitly enabled by setting a long random
 * token in config.php before deploying.
 *
 * WARNING: hitting this URL with the correct token DROPS AND RECREATES
 * every table in the configured database (see lib/schema.php). Do not
 * use a guessable token, and consider deleting or renaming vuln/init.php
 * and secure/init.php once the initial deployment is done.
 */

function handle_training_init_request(): void
{
    $config   = training_config();
    $expected = (string)($config['init_token'] ?? '');
    $supplied = (string)($_GET['token'] ?? '');

    // Disabled by default: an unset/empty init_token means "never run",
    // regardless of what token value is supplied in the URL.
    if ($expected === '' || !hash_equals($expected, $supplied)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found\n";
        return;
    }

    require __DIR__ . '/schema.php';

    header('Content-Type: text/plain; charset=utf-8');
    try {
        $pdo = training_pdo();
        training_run_schema($pdo);
        echo "OK: tables (re)initialised in database '{$config['dbname']}'.\n";
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }
}
