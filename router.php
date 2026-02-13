<?php
// Simple Router for PHP Built-in Server

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files directly
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js)$/', $uri)) {
    return false;
}

// Routing Logic
switch ($uri) {
    case '/':
    case '/index':
        require 'index.php';
        break;
    case '/login':
        require 'login.php';
        break;
    case '/thread':
        require 'thread.php';
        break;
    case '/admin':
        require 'admin.php';
        break;
    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
