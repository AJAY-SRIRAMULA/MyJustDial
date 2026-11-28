<?php
// Shared membership rules for existing public directory and account routes.
function membershipPlanFor(PDO $pdo, int $id): array {
    $q = $pdo->prepare('SELECT * FROM subscription_types WHERE subscription_id = ?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function membershipVisibilitySql(string $alias = 'u'): string {
    // Existing members without a plan retain legacy visibility; explicitly disabled plans do not.
    return "NOT EXISTS (SELECT 1 FROM subscription_types mp WHERE mp.subscription_id = $alias.subscription_id AND mp.searchable = 0)";
}
function membershipPrioritySql(string $alias = 'u'): string {
    return "COALESCE((SELECT mp.search_priority FROM subscription_types mp WHERE mp.subscription_id = $alias.subscription_id), 2147483647) ASC";
}
function membershipCategoryLimit(array $plan): ?int {
    $value = strtolower(trim((string)($plan['service_limit'] ?? '')));
    return ctype_digit($value) ? (int)$value : null;
}
function membershipAccountForm(PDO $pdo, array $member, string $section, string $fallback): string {
    $keys = ['contact'=>'contact_details_form','listing'=>'listing_details_form','about'=>'about_form'];
    $plan = membershipPlanFor($pdo, (int)$member['subscription_id']);
    $name = trim((string)($plan[$keys[$section] ?? ''] ?? ''));
    if ($name !== '') {
        $resolved = resolveMemberFormName($pdo, $name);
        $q = $pdo->prepare('SELECT form_name FROM forms WHERE form_name = ?');
        $q->execute([$resolved]);
        if ($q->fetchColumn() !== false) return $resolved;
    }
    return resolveMemberFormName($pdo, $fallback);
}
