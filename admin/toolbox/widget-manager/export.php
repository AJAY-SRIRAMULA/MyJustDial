<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/widgets.php';

$widgetId = (int) ($_GET['id'] ?? 0);
$widget = widgetByReference($pdo, (string) $widgetId);

if (!$widget) {
    http_response_code(404);
    exit('Widget not found');
}

$widgetName = trim((string) ($widget['widget_name'] ?? 'Widget'));
$filenameName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $widgetName);
$filenameName = trim((string) $filenameName, '-_') ?: 'widget';
$filename = 'widget-' . (int) $widget['widget_id'] . '-' . $filenameName . '.txt';

$sections = [
    'WIDGET DETAILS' => [
        'Name: ' . $widgetName,
        'ID: ' . (int) $widget['widget_id'],
        'Type: ' . (string) ($widget['widget_type'] ?? ''),
        'Shortcode: ' . widgetPublicShortcode($pdo, $widget),
    ],
    'HTML' => (string) ($widget['widget_data'] ?? ''),
    'CSS' => (string) ($widget['widget_style'] ?? ''),
    'JAVASCRIPT' => (string) ($widget['widget_javascript'] ?? ''),
];

$content = "Widget Export\n" . str_repeat('=', 80) . "\n\n";
foreach ($sections as $heading => $sectionContent) {
    $content .= $heading . "\n" . str_repeat('-', 80) . "\n";
    if (is_array($sectionContent)) {
        $content .= implode("\n", $sectionContent);
    } else {
        $content .= $sectionContent;
    }
    $content .= "\n\n";
}

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo $content;
