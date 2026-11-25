<?php

function configuredFormDestination(PDO $pdo, string $name, string $default, string $defaultKey): array
{
    $query = $pdo->prepare('SELECT form_table, table_index FROM forms WHERE form_name = ? LIMIT 1');
    $query->execute([$name]);
    $form = $query->fetch(PDO::FETCH_ASSOC) ?: [];
    $table = trim((string)($form['form_table'] ?? '')) ?: $default;
    $key = trim((string)($form['table_index'] ?? '')) ?: ($table === $default ? $defaultKey : '');
    $query = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $query->execute([$table]);
    $columns = $query->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'COLUMN_NAME');
    $keys = array_column(array_filter($columns, static fn($c) => $c['COLUMN_KEY'] === 'PRI'), 'COLUMN_NAME');
    if (count($keys) !== 1 || $keys[0] !== $key || !preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
        throw new RuntimeException('Invalid destination table or primary key for ' . $name);
    }
    return [$table, $key, $names];
}

function saveConfiguredMemberRecord(PDO $pdo, array $destination, int $memberId, array $values, array $metadata): void
{
    [$table, $key, $columns] = $destination;
    if (!in_array('user_id', $columns, true) && $key !== 'user_id') {
        throw new RuntimeException('Member destination requires a user_id ownership column.');
    }
    $missing = array_diff(array_keys($values), $columns);
    if ($missing) throw new RuntimeException('Destination is missing configured default fields: ' . implode(', ', $missing));
    $values = array_intersect_key($values, array_flip($columns));
    unset($values[$key]);
    $values['user_id'] = $memberId;
    $find = $pdo->prepare("SELECT `$key` FROM `$table` WHERE user_id = ? LIMIT 2 FOR UPDATE");
    $find->execute([$memberId]);
    $ids = $find->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) > 1) throw new RuntimeException('Ambiguous member destination record.');
    $names = array_keys($values);
    $quoted = array_map(static fn($n) => '`' . str_replace('`', '``', $n) . '`', $names);
    if ($ids) {
        $sets = array_map(static fn($n) => $n . ' = ?', $quoted);
        $pdo->prepare("UPDATE `$table` SET " . implode(',', $sets) . " WHERE `$key` = ? AND user_id = ?")->execute(array_merge(array_values($values), [$ids[0], $memberId]));
        $id = $ids[0];
    } else {
        $pdo->prepare("INSERT INTO `$table` (" . implode(',', $quoted) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')')->execute(array_values($values));
        $id = $key === 'user_id' ? $memberId : $pdo->lastInsertId();
    }
    if (!$id) throw new RuntimeException('Destination record has no identifier.');
    foreach ($metadata as $name => $value) {
        $find = $pdo->prepare('SELECT meta_id FROM users_meta WHERE `database` = ? AND database_id = ? AND `key` = ? ORDER BY meta_id DESC LIMIT 1');
        $find->execute([$table, $id, $name]);
        $metaId = $find->fetchColumn();
        if ($metaId) $pdo->prepare('UPDATE users_meta SET value = ? WHERE meta_id = ?')->execute([$value, $metaId]);
        else $pdo->prepare('INSERT INTO users_meta (`database`, database_id, `key`, value, date_added) VALUES (?, ?, ?, ?, ?)')->execute([$table, $id, $name, $value, date('YmdHis')]);
    }
}
