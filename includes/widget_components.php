<?php

function widgetComponentPattern(): string
{
    return '/\{\{(?:component:)?([a-zA-Z0-9_-]{1,80})(?:\.php)?\}\}/';
}

function loadWidgetComponent(string $name): ?array
{
    static $cache = [];
    if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $name)) return null;
    if (array_key_exists($name, $cache)) return $cache[$name];
    $directory = realpath(__DIR__ . '/widget-components');
    $path = realpath(__DIR__ . '/widget-components/' . $name . '.php');
    if ($directory === false || $path === false || dirname($path) !== $directory || !is_file($path)) return null;
    $component = require $path;
    if (!is_array($component) || !is_callable($component['render'] ?? null) || (isset($component['validate']) && !is_callable($component['validate']))) {
        throw new RuntimeException('Invalid widget component contract.');
    }
    return $cache[$name] = $component;
}

function widgetComponentsInContent(PDO $pdo, string $content, array $seen = [], int $depth = 0): array
{
    if ($depth >= 12) return [];
    $components = [];
    if ($depth > 0 && preg_match_all(widgetComponentPattern(), $content, $matches)) {
        foreach ($matches[1] as $name) {
            $component = loadWidgetComponent($name);
            if ($component) $components[$name] = $component;
        }
    }
    if (preg_match_all('/\[widget=([^\]\r\n]+)\]/', $content, $references)) {
        foreach ($references[1] as $reference) {
            $widget = widgetByReference($pdo, $reference);
            if (!$widget || ($widget['widget_viewport'] ?? '') === 'disabled') continue;
            $id = (int)$widget['widget_id'];
            if (in_array($id, $seen, true)) continue;
            $components += widgetComponentsInContent($pdo, (string)$widget['widget_data'], [...$seen, $id], $depth + 1);
        }
    }
    return $components;
}
