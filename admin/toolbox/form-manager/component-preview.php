<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require __DIR__ . '/../../../includes/custom_components.php';
require_once __DIR__ . '/../../../includes/widgets.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'html' => '']);
    exit;
}
if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'html' => 'Preview session expired. Refresh the page.']);
    exit;
}
if (isset($_POST['widget_content'])) {
    $content = (string)$_POST['widget_content'];
    if (strlen($content) > 10000) { http_response_code(400); echo json_encode(['ok'=>false,'html'=>'Widget reference is too long.']); exit; }
    echo json_encode(['ok'=>true,'html'=>renderWidgetShortcodes($pdo,$content)],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
$filename = trim((string)($_POST['filename'] ?? ''));
if (!isTrustedComponentReference($filename)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'html' => '<div class="custom-component-error">Enter the filename of an installed trusted PHP component.</div>']);
    exit;
}
$html = renderTrustedPhpComponent($filename, [
    'pdo' => $pdo,
    'member' => null,
    'form_id' => (int)($_POST['form_id'] ?? 0),
    'field_id' => 0,
    'user_id' => 0,
    'context' => 'form-manager-live-preview',
    'preview' => true,
    'csrf' => csrfToken(),
    'base_url' => $config['base_url']
]);
echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
