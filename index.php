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
        require __DIR__ . '/backend/source/pages/leaderboard.php';
        break;

    case 'init':
        require __DIR__ . '/backend/source/pages/init.php';
        break;

    case 'manage-game':
        require __DIR__ . '/backend/source/pages/manage_game.php';
        break;

    default:
        http_response_code(404);
        echo '404 - Page not found';
        break;
}