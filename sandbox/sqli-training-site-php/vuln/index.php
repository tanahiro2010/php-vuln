<?php
/**
 * Front controller for the VULNERABLE training app.
 *
 * - Docker (Apache vhost DOCROOT=.../vuln): FallbackResource /index.php
 *   sends every non-file URL here.
 * - Rental hosting (this folder is a URL subpath, e.g. /vuln/): .htaccess
 *   rewrites every non-file URL under /vuln/ to this file.
 *
 * Either way we just delegate to the real router in ../app_vulnerable.php.
 */
require __DIR__ . '/../app_vulnerable.php';
