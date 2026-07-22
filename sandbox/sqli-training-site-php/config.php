<?php
/**
 * config.php -- Hifumi Secure Shop (PHP training build) DB config.
 *
 * Both app_vulnerable.php and app_secure.php include this file to open a
 * PDO connection to MySQL. Values are read from environment variables when
 * available (docker-compose sets these), and fall back to defaults for a
 * local `php -S` run.
 *
 * WARNING: This is a **training** config. The DB credentials below are
 * hard-coded on purpose so the class doesn't need to configure anything.
 * In real projects: never commit real credentials, read them from a
 * secrets manager, and use a per-service DB user with least privilege.
 */

return [
    // MySQL host. Inside docker-compose this is the service name "mysql".
    // Outside docker (local `php -S`), point at 127.0.0.1:3306 or wherever.
    'host'    => getenv('DB_HOST') ?: 'mysql84-1.lolipop.lan',
    'port'    => (int)(getenv('DB_PORT') ?: 3306),

    // MySQL account used by both apps. Training-only credentials.
    'user'    => getenv('DB_USER') ?: 'LAA1715842',
    'pass'    => getenv('DB_PASS') ?: 'Abcd1234',

    // Which database this instance uses.
    // - Docker: set via env, hifumi_vulnerable for the vuln container and
    //   hifumi_secure for the secure one (two separate databases).
    // - Single-database rental hosting (no env vars): both /vuln/ and
    //   /secure/ share this ONE dbname/table set. That's fine -- the
    //   lesson ("same data, vulnerable vs. parameterized query") still
    //   holds when both apps read/write the same tables. Resetting via
    //   init.php resets both apps' state at once, which is expected.
    'dbname'  => getenv('DB_NAME') ?: 'LAA1715842-sqltest',

    // Cookie name so the two apps don't clobber each other's sessions when
    // opened on the same host (browsers scope cookies by host, not port).
    'cookie'  => getenv('SESSION_COOKIE_NAME') ?: 'hifumi_php_session',

    // Shared secret for the web-based schema/seed initializer
    // (vuln/init.php, secure/init.php -- see lib/init_web.php). Needed on
    // FTP-only hosting where there is no SSH/cron to run init_db.php.
    //
    // SAFE BY DEFAULT: an empty string means the init endpoint always
    // 404s, regardless of what token is supplied. Set a long random
    // value here BEFORE deploying publicly, e.g.:
    //   php -r 'echo bin2hex(random_bytes(24));'
    // Consider deleting/renaming vuln/init.php and secure/init.php after
    // the first successful run.
    'init_token' => getenv('INIT_TOKEN') ?: 'init-token',
];
