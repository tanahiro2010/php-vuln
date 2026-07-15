<?php
/**
 * Front controller for the SECURE training app.
 *
 * Apache is configured with FallbackResource /index.php, so every URL
 * that does not match a physical file in this directory lands here.
 * We just delegate to the real router in ../../app_secure.php.
 */
require __DIR__ . '/../../app_secure.php';
