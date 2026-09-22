<?php

/**
 * P4-003 verification server router (verification tooling).
 *
 * Serves existing files from the public docroot and routes everything else to
 * Laravel's front controller. Used only for the local Playwright verification
 * server; not application code.
 */
$publicPath = __DIR__.'/../public';
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
