<?php
require_once __DIR__ . '/_init.php';
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'version' => APP_VERSION,
    'db_driver' => db_driver(),
    'headers_exists' => is_file(headers_file_path()),
], JSON_PRETTY_PRINT);
