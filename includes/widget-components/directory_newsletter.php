<?php
require_once __DIR__ . '/../public_forms.php';

return [
    'render' => static fn(PDO $pdo): string => renderPublicForm($pdo, 'newsletter_modal_signup', csrfToken()),
];
