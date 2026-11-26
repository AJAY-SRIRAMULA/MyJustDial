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
    $names = array_column(array_filter($columns, static fn($c) => !preg_match('/auto_increment|generated/i', $c['EXTRA']) || $c['COLUMN_KEY'] === 'PRI'), 'COLUMN_NAME');
    $keys = array_column(array_filter($columns, static fn($c) => $c['COLUMN_KEY'] === 'PRI'), 'COLUMN_NAME');
    if (count($keys) !== 1 || $keys[0] !== $key || !preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
        throw new RuntimeException('Invalid destination table or primary key for ' . $name);
    }
    return [$table, $key, $names, $name];
}

function saveConfiguredMemberRecord(PDO $pdo, array $destination, int $memberId, array $values, array $metadata): void
{
    [$table, $key, $columns] = $destination;
    if ($key !== 'user_id') {
        saveIndependentMemberDestination($pdo, $destination, $memberId, array_replace($metadata, $values));
        return;
    }
    validateIndependentDestination($pdo, $destination);
    [$values, $metadata] = splitDestinationFields($columns, $key, array_replace($metadata, $values));
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
        insertDynamicDestination($pdo, $destination, $values, $memberId);
        $id = $memberId;
    }
    if (!$id) throw new RuntimeException('Destination record has no identifier.');
    foreach ($metadata as $name => $value) {
        $value = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string)$value;
        $find = $pdo->prepare('SELECT meta_id FROM users_meta WHERE `database` = ? AND database_id = ? AND `key` = ? ORDER BY meta_id DESC LIMIT 1');
        $find->execute([$table, $id, $name]);
        $metaId = $find->fetchColumn();
        if ($metaId) $pdo->prepare('UPDATE users_meta SET value = ? WHERE meta_id = ?')->execute([$value, $metaId]);
        else $pdo->prepare('INSERT INTO users_meta (`database`, database_id, `key`, value, date_added) VALUES (?, ?, ?, ?, ?)')->execute([$table, $id, $name, $value, date('YmdHis')]);
    }
}

function splitDestinationFields(array $columns, string $key, array $submitted): array
{
    $direct = [];
    $metadata = [];
    $protected = ['password', 'password_hash', 'token', 'cookie', 'active', 'verified', 'admin_id', 'role_id', 'subscription_id', 'user_id', 'parent_id', 'featured'];
    foreach ($submitted as $name => $value) {
        if (str_starts_with(strtolower($name), '__mjd_owner_') || $name === $key || in_array(strtolower($name), $protected, true)) {
            continue;
        }
        if (in_array($name, $columns, true)) {
            $direct[$name] = $value;
        } else {
            $metadata[$name] = $value;
        }
    }
    return [$direct, $metadata];
}


function independentDestinationOwnerKey(array $destination): string
{
    return '__mjd_owner_' . hash('sha256', $destination[3] ?? 'member');
}

function validateIndependentDestination(PDO $pdo, array $destination): void
{
    [$table, $key] = $destination;
    if (preg_match('/^(admin|bd_|users_|forms?$|form_fields$|subscription|membership|data_widgets|website_|payment|rel_|list_|login|session|api|config|settings)/i', $table)) {
        throw new RuntimeException('This table is reserved for application data.');
    }
    $query = $pdo->prepare('SELECT c.EXTRA, c.DATA_TYPE, t.ENGINE FROM INFORMATION_SCHEMA.COLUMNS c JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=? AND c.COLUMN_NAME=?');
    $query->execute([$table, $key]);
    $column = $query->fetch(PDO::FETCH_ASSOC);
    if (!$column || stripos($column['EXTRA'], 'auto_increment') === false || !in_array($column['DATA_TYPE'], ['tinyint','smallint','mediumint','int','bigint'], true) || strcasecmp($column['ENGINE'] ?? '', 'InnoDB') !== 0) {
        throw new RuntimeException('Independent destinations require a transactional table with an auto-increment integer primary key.');
    }
}

function independentMemberDestinationId(PDO $pdo, array $destination, int $memberId): ?int
{
    [$table, $key] = $destination;
    $query = $pdo->prepare("SELECT DISTINCT m.database_id FROM users_meta m JOIN `$table` d ON d.`$key`=m.database_id WHERE m.`database`=? AND m.`key`=? AND m.value=? LIMIT 2");
    $query->execute([$table, independentDestinationOwnerKey($destination), (string)$memberId]);
    $ids = $query->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) > 1) throw new RuntimeException('Ambiguous independent destination ownership.');
    return $ids ? (int)$ids[0] : null;
}

function saveIndependentMemberDestination(PDO $pdo, array $destination, int $memberId, array $submitted): void
{
    [$table, $key, $columns] = $destination;
    validateIndependentDestination($pdo, $destination);
    if (!$pdo->inTransaction()) throw new RuntimeException('Destination requires a transaction.');
    $lock = $pdo->prepare('SELECT user_id FROM users_data WHERE user_id=? FOR UPDATE');
    $lock->execute([$memberId]);
    if (!$lock->fetchColumn()) throw new RuntimeException('Member not found.');
    $id = independentMemberDestinationId($pdo, $destination, $memberId);
    [$direct, $metadata] = splitDestinationFields($columns, $key, $submitted);
    $names = array_map(static fn($n) => '`' . str_replace('`', '``', $n) . '`', array_keys($direct));
    if ($id === null) {
        $id = insertDynamicDestination($pdo, $destination, $direct);
        $metadata[independentDestinationOwnerKey($destination)] = (string)$memberId;
    } elseif ($direct) {
        $sets = array_map(static fn($n) => $n . '=?', $names);
        $pdo->prepare("UPDATE `$table` SET " . implode(',', $sets) . " WHERE `$key`=?")->execute(array_merge(array_values($direct), [$id]));
    }
    foreach ($metadata as $name => $value) {
        $value = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string)$value;
        $find = $pdo->prepare('SELECT meta_id FROM users_meta WHERE `database`=? AND database_id=? AND `key`=? ORDER BY meta_id DESC LIMIT 1');
        $find->execute([$table, $id, $name]);
        $metaId = $find->fetchColumn();
        if ($metaId) $pdo->prepare('UPDATE users_meta SET value=? WHERE meta_id=?')->execute([$value, $metaId]);
        else $pdo->prepare('INSERT INTO users_meta (`database`,database_id,`key`,value,date_added) VALUES (?,?,?,?,?)')->execute([$table,$id,$name,$value,date('YmdHis')]);
    }
}


function destinationMissingValue(array $column)
{
    $name = strtolower($column['COLUMN_NAME']);
    $type = strtolower($column['DATA_TYPE']);
    // Neutral placeholders only; never invent credentials or grant privileges.
    if (preg_match('/password|secret|api_key|permission|role|privilege|credential|salt/i', $name)) {
        throw new RuntimeException('Required sensitive destination column: ' . $name);
    }
    if (in_array($type, ['char','varchar','tinytext','text','mediumtext','longtext','binary','varbinary','tinyblob','blob','mediumblob','longblob'], true)) {
        return '';
    }
    if (in_array($type, ['tinyint','smallint','mediumint','int','bigint','decimal','numeric','float','double','real','bit'], true)) {
        return 0;
    }
    if ($type === 'json') return '{}';
    throw new RuntimeException('No safe fallback for required destination column: ' . $name . ' (' . $type . ')');
}

function insertDynamicDestination(PDO $pdo, array $destination, array $direct, ?int $explicitId = null): int
{
    validateIndependentDestination($pdo, $destination);
    if (!$pdo->inTransaction()) throw new RuntimeException('Destination requires a transaction.');
    [$table, $key, $columns] = $destination;
    if ($explicitId !== null) $direct[$key] = $explicitId;
    $query = $pdo->prepare('SELECT COLUMN_NAME, EXTRA, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $query->execute([$table]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $name = $column['COLUMN_NAME'];
        if (!preg_match('/auto_increment|generated/i', $column['EXTRA']) && $column['IS_NULLABLE'] === 'NO' && $column['COLUMN_DEFAULT'] === null && !array_key_exists($name, $direct)) {
            $direct[$name] = destinationMissingValue($column);
        }
    }
    $names = array_map(static fn($n) => '`' . str_replace('`', '``', $n) . '`', array_keys($direct));
    $pdo->prepare("INSERT INTO `$table` (" . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')')->execute(array_map(static fn($v) => is_array($v) ? json_encode($v, JSON_THROW_ON_ERROR) : $v, array_values($direct)));
    $id = $explicitId ?? (int)$pdo->lastInsertId();
    if ($id <= 0 || $id > 2147483647) throw new RuntimeException('Destination identifier cannot be stored in users_meta.');
    return $id;
}
