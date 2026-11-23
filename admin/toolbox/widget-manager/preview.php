<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/widgets.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed']); exit; }
if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Invalid request']); exit; }
$html = (string)($_POST['widget_data'] ?? '');
$css = (string)($_POST['widget_style'] ?? '');
$js = (string)($_POST['widget_javascript'] ?? '');
$id = (int)($_POST['id'] ?? 0);
if (strlen($html) + strlen($css) + strlen($js) > 2000000) { http_response_code(413); echo json_encode(['ok'=>false,'message'=>'Preview content is too large']); exit; }
$parent = ['widget_id'=>$id, 'widget_data'=>$html, 'widget_style'=>$css, 'widget_javascript'=>$js];
try {
    $composition = composeWidgetContent($pdo, $parent, [], 0);
    $document = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;font-family:Arial,sans-serif}' . str_replace('</style', '<\/style', $composition['css']) . '</style></head><body>' . $composition['html'] . '<script>' . str_replace('</script', '<\/script', $composition['js']) . '</script></body></html>';
    echo json_encode(['ok'=>true,'document'=>$document], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Widget preview failed']);
}
