<?php
/**
 * Front controller for the SECURE training app.
 *
 * - Docker (Apache vhost DOCROOT=.../secure): FallbackResource /index.php
 *   sends every non-file URL here.
 * - Rental hosting (this folder is a URL subpath, e.g. /secure/):
 *   .htaccess rewrites every non-file URL under /secure/ to this file.
 *
 * Either way we just delegate to the real router in ../app_secure.php.
 */
require __DIR__ . '/../app_secure.php';
