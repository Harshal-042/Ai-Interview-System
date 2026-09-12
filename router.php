<?php
// PHP Built-in Server Router for Port 3000
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1. If static asset or existing file with extension, serve directly
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    // If it's a PHP file, execute it
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
        return true;
    }
    // Static file: CSS, JS, Video, Images
    return false;
}

// 2. Default root path
if ($uri === '/' || $uri === '/index.html' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// 3. Try with .php extension if omitted (e.g. /admin/dashboard -> /admin/dashboard.php)
if (file_exists($file . '.php')) {
    require $file . '.php';
    return true;
}

// 4. If directory with index.php exists
if (is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}

// 404 fallback
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri);
return true;
