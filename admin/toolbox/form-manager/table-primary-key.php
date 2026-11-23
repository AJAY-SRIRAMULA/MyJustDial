<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed']); exit; }
try {
    verifyCsrf();
    $table = trim((string)($_POST['table'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Invalid table name.');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $exists->execute([$table]);
    if ((int)$exists->fetchColumn() !== 1) throw new RuntimeException('Table not found.');
    $query = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_KEY="PRI" ORDER BY ORDINAL_POSITION');
    $query->execute([$table]);
    $keys = $query->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['ok'=>true,'primary_keys'=>$keys,'primary_key'=>$keys[0]??'','message'=>$keys?'':'This table has no primary key. Update operations may not be supported.']);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); }
