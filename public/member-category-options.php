<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/directory_search.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Only public category IDs and labels are exposed, including to sandboxed previews.
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}
try {
    echo json_encode(directoryCategories($pdo), JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(500);
    echo '{}';
}
