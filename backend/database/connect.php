<?php
// load dependencies
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// .env-ben levo adatokkal csatlakozzon
$host = $_ENV['DB_HOST'];
$dbnm = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$port = $_ENV['DB_PORT'];

// csatlakozas
$connection = mysqli_connect($host, $user, $pass, $dbnm, $port);

if (!$connection) {
    error_log("Database connection failed: " . mysqli_connect_error());
    exit("Database connection error.");
}

// charset
mysqli_set_charset($connection, 'utf8mb4_hungarian_ci');