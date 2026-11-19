<?php
// Set these environment variables for your local MySQL installation.
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = (int) (getenv('DB_PORT') ?: 3306);
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASSWORD') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'docconnect';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ($conn->connect_error) {
    die('Database connection failed. Check local configuration.');
}
$conn->set_charset('utf8mb4');
