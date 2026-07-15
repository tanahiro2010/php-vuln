<?php
/**
 * secure/init.php -- web-triggered DB initializer (token-gated).
 *
 * For FTP-only hosting with no SSH/cron access. Visit:
 *   https://your-domain/secure/init.php?token=YOUR_INIT_TOKEN
 * once after uploading, to create tables and load seed data. See
 * config.php's 'init_token' and lib/init_web.php for details.
 *
 * NOTE: on single-database rental hosting, /vuln/init.php and
 * /secure/init.php both reset the SAME shared database -- either one is
 * enough. Both are provided for convenience/symmetry.
 *
 * Disabled by default (404) until 'init_token' is set in config.php.
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/init_web.php';

handle_training_init_request();
