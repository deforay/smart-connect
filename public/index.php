<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (__FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

// Setup autoloading
include __DIR__ . '/../vendor/autoload.php';

/**
 * API v2 strangler seam.
 *
 * Requests under /api/v2 are served by Slim (src/Api/bootstrap.php) and never
 * reach the Laminas MVC application. The branch lives here rather than in
 * .htaccess because several deployments override the rewrite rules in their
 * vhost config, where an .htaccess change would be silently ignored.
 */
$scRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Sub-directory deployments serve from /<dir>/index.php, so the directory part
// of SCRIPT_NAME is the base path. Only trust SCRIPT_NAME when it actually
// names the front controller — under the built-in server's router-script mode
// it holds the request path instead, and dirname() of that is meaningless.
$scScriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$scBasePath = str_ends_with($scScriptName, '.php')
    ? rtrim(dirname($scScriptName), '/')
    : '';

$scRoutePath = ($scBasePath !== '' && str_starts_with($scRequestPath, $scBasePath))
    ? substr($scRequestPath, strlen($scBasePath))
    : $scRequestPath;

if ($scRoutePath === '/api/v2' || str_starts_with($scRoutePath, '/api/v2/')) {
    (require __DIR__ . '/../src/Api/bootstrap.php')($scBasePath);
    exit;
}

unset($scRequestPath, $scRoutePath);

// Run the application!
//Laminas\Mvc\Application::init(require 'config/application.config.php')->run();

// Config
$appConfig = include 'config/application.config.php';
if (file_exists('config/development.config.php')) {
    $appConfig = Laminas\Stdlib\ArrayUtils::merge($appConfig, include 'config/development.config.php');
}

// Run the application!
Laminas\Mvc\Application::init($appConfig)->run();
