<?php

return [
    'render' => static function (PDO $pdo): string {
        $plans = $pdo->query("SELECT subscription_id, subscription_name FROM subscription_types WHERE subscription_type='member' AND sub_active=1 ORDER BY subscription_id")->fetchAll(PDO::FETCH_ASSOC);
        $html = '<label>Membership plan<select class="form-control" name="fields[requested_membership_plan_id]"><option value="">Select plan</option>';
        foreach ($plans as $plan) {
            $html .= '<option value="' . (int)$plan['subscription_id'] . '">' . htmlspecialchars($plan['subscription_name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $html . '</select></label>';
    },
    'validate' => static function (PDO $pdo, array $submitted): array {
        $id = $submitted['requested_membership_plan_id'] ?? '';
        if ($id === '') return [];
        if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D', $id)) throw new InvalidArgumentException('Choose a valid membership plan.');
        $query = $pdo->prepare("SELECT subscription_id FROM subscription_types WHERE subscription_id=? AND subscription_type='member' AND sub_active=1");
        $query->execute([$id]);
        if (!$query->fetchColumn()) throw new InvalidArgumentException('Choose an available membership plan.');
        return ['requested_membership_plan_id' => $id];
    },
];
