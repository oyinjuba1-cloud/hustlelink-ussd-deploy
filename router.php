<?php
// Router for PHP built-in server on Render.
// Use this to ensure / serves index.php and other files are served normally.
if (php_sapi_name() !== 'cli-server') {
    return false;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($requestUri === '/' || $requestUri === '') {
    return require __DIR__ . '/index.php';
}

$requested = __DIR__ . $requestUri;
if (is_file($requested)) {
    return false;
}

return false;
