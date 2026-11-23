<?php

function newWidgetShortCode(PDO $pdo): string
{
    $query = $pdo->prepare('SELECT COUNT(*) FROM data_widgets WHERE short_code = ?');
    do {
        $shortCode = bin2hex(random_bytes(16));
        $query->execute([$shortCode]);
    } while ((int)$query->fetchColumn() !== 0);
    return $shortCode;
}

function setWidgetMetaValue(PDO $pdo, int $widgetId, string $key, string $value): void
{
    if (!tableExists($pdo, 'users_meta')) {
        throw new RuntimeException('The users_meta table is required to map readable widget shortcodes.');
    }

    $find = $pdo->prepare("SELECT meta_id FROM users_meta WHERE `database`='data_widgets' AND database_id=? AND `key`=? ORDER BY meta_id ASC LIMIT 1");
    $find->execute([$widgetId, $key]);
    $metaId = $find->fetchColumn();

    if ($metaId) {
        $update = $pdo->prepare('UPDATE users_meta SET value=?, date_added=? WHERE meta_id=?');
        $update->execute([$value, date('YmdHis'), (int)$metaId]);
        return;
    }

    $insert = $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('data_widgets',?,?,?,?)");
    $insert->execute([$widgetId, $key, $value, date('YmdHis')]);
}

function widgetMetaValue(PDO $pdo, int $widgetId, string $key): string
{
    if (!tableExists($pdo, 'users_meta')) return '';
    $query = $pdo->prepare("SELECT value FROM users_meta WHERE `database`='data_widgets' AND database_id=? AND `key`=? ORDER BY meta_id ASC LIMIT 1");
    $query->execute([$widgetId, $key]);
    return (string)($query->fetchColumn() ?: '');
}

function newWidgetPublicAlias(PDO $pdo, string $widgetName, int $widgetId = 0): string
{
    $base = trim($widgetName);
    if ($base === '') $base = 'Widget';
    $base = substr($base, 0, 180);

    $sql = "SELECT COUNT(*) FROM users_meta WHERE `database`='data_widgets' AND `key`='widget_shortcode' AND value=?";
    if ($widgetId > 0) $sql .= ' AND database_id <> ?';
    $check = $pdo->prepare($sql);
    $alias = $base;
    $suffix = 2;
    do {
        $params = [$alias];
        if ($widgetId > 0) $params[] = $widgetId;
        $check->execute($params);
        if ((int)$check->fetchColumn() === 0) break;
        $tail = ' (' . $suffix++ . ')';
        $alias = substr($base, 0, 180 - strlen($tail)) . $tail;
    } while (true);

    return $alias;
}

function ensureWidgetShortcodeMapping(PDO $pdo, array $widget): array
{
    $widgetId = (int)($widget['widget_id'] ?? 0);
    if ($widgetId <= 0) throw new RuntimeException('Invalid widget identifier.');
    if (!tableExists($pdo, 'users_meta')) {
        throw new RuntimeException('The users_meta table is required to map readable widget shortcodes.');
    }

    $storedCode = trim((string)($widget['short_code'] ?? ''));
    $hexCode = '';
    $legacyCode = '';

    if (preg_match('/^[a-f0-9]{32}$/i', $storedCode)) {
        $hexCode = strtolower($storedCode);
    } elseif (preg_match('/^data_widgets-([a-f0-9]{32})\.bd$/i', $storedCode, $match)) {
        $hexCode = strtolower($match[1]);
        $legacyCode = $storedCode;
    } else {
        $hexCode = newWidgetShortCode($pdo);
        $legacyCode = $storedCode;
    }

    if ($storedCode !== $hexCode) {
        if ($legacyCode !== '') {
            setWidgetMetaValue($pdo, $widgetId, 'widget_legacy_shortcode', $legacyCode);
        }
        $update = $pdo->prepare('UPDATE data_widgets SET short_code=? WHERE widget_id=?');
        $update->execute([$hexCode, $widgetId]);
    }

    $widgetName = trim((string)($widget['widget_name'] ?? 'Widget'));
    $alias = widgetMetaValue($pdo, $widgetId, 'widget_shortcode');
    if ($alias !== $widgetName) {
        $alias = newWidgetPublicAlias($pdo, $widgetName, $widgetId);
        setWidgetMetaValue($pdo, $widgetId, 'widget_shortcode', $alias);
    }

    $widget['short_code'] = $hexCode;
    $widget['public_shortcode'] = $alias;
    return $widget;
}

function ensureWidgetShortcodeMappings(PDO $pdo): void
{
    if (!tableExists($pdo, 'data_widgets')) return;
    $widgets = $pdo->query('SELECT * FROM data_widgets ORDER BY widget_id ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($widgets as $widget) ensureWidgetShortcodeMapping($pdo, $widget);
}

function widgetPublicShortcode(PDO $pdo, array $widget): string
{
    $mapped = ensureWidgetShortcodeMapping($pdo, $widget);
    return '[widget=' . $mapped['public_shortcode'] . ']';
}

function widgetByReference(PDO $pdo, string $reference): ?array
{
    if (!tableExists($pdo, 'data_widgets')) return null;
    $reference = trim($reference);
    if (!preg_match('/^[^\]\r\n]{1,255}$/', $reference)) return null;

    $statement = $pdo->prepare('SELECT * FROM data_widgets WHERE short_code = ? LIMIT 1');
    $statement->execute([$reference]);
    $widget = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$widget && tableExists($pdo, 'users_meta')) {
        $mapped = $pdo->prepare("SELECT w.* FROM users_meta m INNER JOIN data_widgets w ON w.widget_id=m.database_id WHERE m.`database`='data_widgets' AND m.`key` IN ('widget_shortcode','widget_legacy_shortcode') AND m.value=? ORDER BY CASE m.`key` WHEN 'widget_shortcode' THEN 0 ELSE 1 END, m.meta_id ASC LIMIT 1");
        $mapped->execute([$reference]);
        $widget = $mapped->fetch(PDO::FETCH_ASSOC);
    }

    if (!$widget && ctype_digit($reference)) {
        $byId = $pdo->prepare('SELECT * FROM data_widgets WHERE widget_id = ? LIMIT 1');
        $byId->execute([(int)$reference]);
        $widget = $byId->fetch(PDO::FETCH_ASSOC);
    }

    return $widget ? ensureWidgetShortcodeMapping($pdo, $widget) : null;
}

function composeWidgetContent(PDO $pdo, array $widget, array $seen = [], int $depth = 0): array
{
    $widgetId = (int)($widget['widget_id'] ?? 0);
    if ($depth >= 12 || $widgetId <= 0 || in_array($widgetId, $seen, true)) {
        return ['html' => '<span class="widget-unavailable">Widget composition unavailable</span>', 'css' => '', 'js' => ''];
    }

    $seen[] = $widgetId;
    $html = (string)($widget['widget_data'] ?? '');
    $css = (string)($widget['widget_style'] ?? '');
    $js = (string)($widget['widget_javascript'] ?? '');
    $js = preg_replace('#^\s*<script(?:\s[^>]*)?>|</script>\s*$#i', '', $js);
    $nestedCss = [];
    $nestedJs = [];

    $html = preg_replace_callback('/\[widget=([^\]\r\n]+)\]/', function ($match) use ($pdo, $seen, $depth, &$nestedCss, &$nestedJs) {
        $child = widgetByReference($pdo, $match[1]);
        if (!$child || (string)($child['widget_viewport'] ?? '') === 'disabled') {
            return '<span class="widget-unavailable">Widget unavailable</span>';
        }
        $composition = composeWidgetContent($pdo, $child, $seen, $depth + 1);
        if ($composition['css'] !== '') $nestedCss[] = $composition['css'];
        if ($composition['js'] !== '') $nestedJs[] = $composition['js'];
        return $composition['html'];
    }, $html);

    $combinedCss = implode("\n", array_filter([$css, ...$nestedCss]));
    $combinedJs = implode("\n", array_filter([$js, ...$nestedJs]));
    return ['html' => $html, 'css' => $combinedCss, 'js' => $combinedJs];
}

function renderWidgetShortcodes(PDO $pdo, string $content): string
{
    $parts = preg_split('/(\[widget=[^\]\r\n]+\])/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = '';
    foreach ($parts as $part) {
        if (!preg_match('/^\[widget=([^\]\r\n]+)\]$/', $part, $match)) {
            $result .= nl2br(e($part));
            continue;
        }
        $widget = widgetByReference($pdo, $match[1]);
        if (!$widget || (string)$widget['widget_viewport'] === 'disabled') {
            $result .= '<span class="widget-unavailable">Widget unavailable</span>';
            continue;
        }
        $composition = composeWidgetContent($pdo, $widget);
        $widgetHtml = $composition['html'];
        $widgetHtml = preg_replace_callback('/\bname\s*=\s*(["\'])(.*?)\1/i', static function ($m) {
            $original = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($original === '' || str_contains($original, '[') || str_contains($original, ']')) return $m[0];
            return 'name=' . $m[1] . $m[2] . $m[1] . ' data-widget-original-name="' . htmlspecialchars($original, ENT_QUOTES, 'UTF-8') . '" data-widget-submit-name="widget_fields[' . htmlspecialchars($original, ENT_QUOTES, 'UTF-8') . ']"';
        }, $widgetHtml);
        $css = str_replace('</style', '<\/style', $composition['css']);
        $js = preg_replace('#^\s*<script(?:\s[^>]*)?>|</script>\s*$#i', '', $composition['js']);
        $js = str_replace('</script', '<\/script', $js);
        $result .= '<div class="embedded-widget-inline" data-widget-id="' . (int)$widget['widget_id'] . '"><style>' . $css . '</style>' . $widgetHtml . '<script>' . $js . '</script><script>(function(){var root=document.currentScript.parentElement;var form=root.closest("form");if(!form)return;form.addEventListener("submit",function(){root.querySelectorAll("[data-widget-submit-name]").forEach(function(el){el.name=el.getAttribute("data-widget-submit-name");});},{once:true});})();</script></div>';
    }
    return $result;
}
