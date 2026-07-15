<?php
/**
 * vuln/init.php -- web-triggered DB initializer (token-gated).
 *
 * For FTP-only hosting with no SSH/cron access. Visit:
 *   https://your-domain/vuln/init.php?token=YOUR_INIT_TOKEN
 * once after uploading, to create tables and load seed data. See
 * config.php's 'init_token' and lib/init_web.php for details.
 *
 * Disabled by default (404) until 'init_token' is set in config.php.
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/init_web.php';

handle_training_init_request();
