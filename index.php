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

    case 'round':
        require __DIR__ . '/backend/source/pages/round.php';
        break;

    case 'ensz':
        require __DIR__ . '/backend/source/pages/ensz.php';
        break;

    case 'management':
        require __DIR__ . '/backend/source/pages/management.php';
        break;
    
    case 'szovetsegek':
        require __DIR__ . '/backend/source/pages/szovetseg.php';
        break;

    case 'haboruk':
        require __DIR__ . '/backend/source/pages/haboruk.php';
        break;

    default:
        http_response_code(404);
        echo '404 - Page not found';
        break;
}