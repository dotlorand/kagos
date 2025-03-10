<?php

// DEBUG
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// get url path
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');

switch ($request) {
    case '':
        require __DIR__ . '/public/pages/leaderboard.php';
        break;

    case 'manage':
        require __DIR__ . '/public/pages/manage.php';
        break;
    
    case 'test':
        require __DIR__ . '/public/pages/test.html';
        break;

    default:
        http_response_code(404);
        echo '404 - Page not found';
        break;
}