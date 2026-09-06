<?php
/**
 * Vercel Serverless Entrypoint / Router for MediQueue
 */

// Set working directory to project root
chdir(dirname(__DIR__));

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = '/' . ltrim($requestUri, '/');

// Normalize root
if ($requestUri === '/' || $requestUri === '/index' || $requestUri === '/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    require __DIR__ . '/../index.php';
    exit;
}

$baseDir = realpath(__DIR__ . '/..');
$target = realpath($baseDir . $requestUri);

// Check if directly matches a PHP file inside project root
if ($target && strpos($target, $baseDir) === 0 && is_file($target)) {
    if (pathinfo($target, PATHINFO_EXTENSION) === 'php') {
        $_SERVER['SCRIPT_NAME'] = $requestUri;
        $_SERVER['PHP_SELF'] = $requestUri;
        require $target;
        exit;
    }
}

// Check with .php extension if requested without extension (e.g. /login or /patient/dashboard)
$phpTarget = realpath($baseDir . $requestUri . '.php');
if ($phpTarget && strpos($phpTarget, $baseDir) === 0 && is_file($phpTarget)) {
    $_SERVER['SCRIPT_NAME'] = $requestUri . '.php';
    $_SERVER['PHP_SELF'] = $requestUri . '.php';
    require $phpTarget;
    exit;
}

// Check if directory index
if ($target && is_dir($target)) {
    $dirIndex = realpath($target . '/index.php');
    if ($dirIndex && is_file($dirIndex)) {
        $_SERVER['SCRIPT_NAME'] = rtrim($requestUri, '/') . '/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        require $dirIndex;
        exit;
    }
}

// 404 fallback
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light text-center py-5'><div class='container'><h1 class='display-4 text-danger fw-bold'>404</h1><h3>Page Not Found</h3><p class='text-muted'>The requested path <code>" . htmlspecialchars($requestUri) . "</code> does not exist.</p><a href='/' class='btn btn-primary mt-3'>Return to Home</a></div></body></html>";
