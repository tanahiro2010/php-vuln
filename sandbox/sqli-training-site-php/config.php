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
    'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'port'    => (int)(getenv('DB_PORT') ?: 3306),

    // MySQL account used by both apps. Training-only credentials.
    'user'    => getenv('DB_USER') ?: 'training_user',
    'pass'    => getenv('DB_PASS') ?: 'training-demo-password',

    // Which database this instance uses. docker-compose sets this to
    // hifumi_vulnerable for the vuln app and hifumi_secure for the fixed
    // app so a second-order attack demo doesn't contaminate both.
    'dbname'  => getenv('DB_NAME') ?: 'hifumi_vulnerable',

    // Cookie name so the two apps don't clobber each other's sessions when
    // opened on the same host (browsers scope cookies by host, not port).
    'cookie'  => getenv('SESSION_COOKIE_NAME') ?: 'hifumi_php_session',
];
