<?php

// DEBUG
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);

// 1) Load your .env
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();

// 2) Retrieve the secret key from .env
$secretKey = $_ENV['SECRET_ACCESS_KEY'] ?? null;
if (!$secretKey) {
    http_response_code(500);
    exit('Configuration Error: dotenv key not defined!');
}

// 3) Determine the requested path
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');

// 4) If the request is NOT the root page, enforce the zero-trust check
if ($request !== '' && $request !== 'robots.txt') {
    if (!isset($_GET['access_key']) || $_GET['access_key'] !== $secretKey) {
        http_response_code(403);
        exit('Access denied. Invalid token');
    }
}

// 5) Now handle routing
switch ($request) {
    case '':
        // The root route => no access key needed
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

    case 'robots.txt':
        header('Content-Type: text/plain');
        readfile(__DIR__ . '/public/robots.txt');
        break;

    case 'x_database':
        require __DIR__ . '/backend/source/pages/x_database.php';
        break;

    default:
        http_response_code(404);
        echo '404 - Page not found';
        break;
}
