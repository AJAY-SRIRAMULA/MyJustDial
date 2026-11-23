<?php

function trustedComponentPath(string $filename): ?string
{
    if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $filename)) {
        return null;
    }

    $directory = realpath(__DIR__ . '/../custom-components');
    $path = realpath(__DIR__ . '/../custom-components/' . $filename);

    if ($directory === false || $path === false || dirname($path) !== $directory || !is_file($path)) {
        return null;
    }

    return $path;
}

function renderTrustedPhpComponent(string $filename, array $context = []): string
{
    $path = trustedComponentPath(trim($filename));
    if ($path === null) {
        return '<div class="custom-component-error">Custom component is unavailable.</div>';
    }

    $componentContext = $context;
    $pdo = $componentContext['pdo'] ?? null;
    $member = $componentContext['member'] ?? null;
    $formId = (int)($componentContext['form_id'] ?? 0);
    $fieldId = (int)($componentContext['field_id'] ?? 0);
    $userId = (int)($componentContext['user_id'] ?? 0);
    $renderContext = (string)($componentContext['context'] ?? '');
    $isPreview = !empty($componentContext['preview']);
    $csrf = (string)($componentContext['csrf'] ?? '');
    $baseUrl = (string)($componentContext['base_url'] ?? '');
    ob_start();
    try {
        require $path;
        return (string) ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        error_log('Custom component error (' . basename($path) . '): ' . $error->getMessage());
        return '<div class="custom-component-error">This custom component could not be displayed.</div>';
    }
}

function isTrustedComponentReference(string $value): bool
{
    return trustedComponentPath($value) !== null;
}
