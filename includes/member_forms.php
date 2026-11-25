<?php

require_once __DIR__ . '/form_fields.php';

function memberFormDefinitions(): array
{
    return [
        'member_login' => [
            'title' => 'Member Login',
            'fields' => [
                ['Email Address', 'email', 'email', 'name@yoursite.com', 1],
                ['Password', 'password', 'password', 'Enter Password', 1],
                ['Link', 'link', 'text', '', 0],
                ['Submit Message', 'login_submit', 'submit', '', 0],
            ]
        ],
        'member_contact_details' => [
            'title' => 'Contact Details',
            'fields' => [
                ['Type of Listing', 'listing_type', 'select', 'Personal / Individual|Company', 1],
                ['Best Describes You', 'profession_id', 'select', 'Top-Level Category', 1],
                ['First Name', 'first_name', 'text', 'First Name', 1],
                ['Last Name', 'last_name', 'text', 'Last Name', 1],
                ['Email Address', 'email', 'email', 'Email Address', 1],
                ['Phone Number', 'phone_number', 'tel', 'Phone Number', 0],
                ['Company Name', 'company', 'text', 'Company Name', 0],
                ['Your Position', 'position', 'text', 'Your Position', 0],
                ['Short Description', 'search_description', 'textarea', 'Tell us about yourself...', 0],
                ['Where Are You Located?', 'section_location', 'section', '', 0],
                ['Address Line 1', 'address1', 'text', 'Address Line 1', 0],
                ['Address Line 2', 'address2', 'text', 'Address Line 2', 0],
                ['City', 'city', 'text', 'City', 0],
                ['State', 'state_ln', 'text', 'State', 0],
                ['Country', 'country_ln', 'text', 'Country', 0],
                ['Postal Code', 'zip_code', 'text', 'Postal Code', 0],
                ['Enter Website Links', 'section_website', 'section', '', 0],
                ['Website', 'website', 'url', 'https://', 0],
                ['Booking Link', 'booking_link', 'url', 'https://', 0],
                ['Blog', 'blog', 'url', 'https://', 0],
                ['Facebook', 'facebook', 'url', 'https://', 0],
                ['X', 'twitter', 'url', 'https://', 0],
                ['LinkedIn', 'linkedin', 'url', 'https://', 0],
                ['YouTube', 'youtube', 'url', 'https://', 0],
                ['Instagram', 'instagram', 'url', 'https://', 0],
                ['Pinterest', 'pinterest', 'url', 'https://', 0],
                ['TikTok', 'tiktok', 'url', 'https://', 0],
                ['Snapchat', 'snapchat', 'url', 'https://', 0],
                ['WhatsApp', 'whatsapp', 'tel', 'International phone number', 0],
            ]
        ],
        'member_profile_photo' => [
            'title' => 'Profile Photo',
            'fields' => [
                ['profile_photo.php', 'profile_photo_component', 'html', '', 0],
            ]
        ],
        'member_listing_details' => [
            'title' => 'Listing Details',
            'fields' => [
                ['Select 1 Select Category You Specialize In', 'specialty_categories', 'checkbox_group', '', 0],
                ['Share More Details', 'section_details', 'section', '', 0],
                ['Upload CV or Brochure', 'cv', 'file', 'Select an image or PDF file', 0],
                ['Personal Quote/motto', 'quote', 'text', 'Your personal quote', 0],
                ['Year Established', 'experience', 'number', 'Year established', 0],
                ['Hours of Operation', 'working_days', 'textarea', 'Hours of operation', 0],
                ['Accepted Forms of Payments', 'affiliation', 'textarea', 'Accepted forms of payments', 0],
                ['Credentials', 'credentials', 'textarea', 'Credentials', 0],
                ['Honors & Awards', 'awards', 'textarea', 'Honors and awards', 0],
            ]
        ],
        'about' => [
            'title' => 'About Me',
            'fields' => [
                ['Write about you and your company', 'about_me', 'richtext', 'Include additional details about your specialties', 0],
            ]
        ],
    ];
}


function memberDefaultFieldNames(string $section): array
{
    $formBySection = [
        'contact' => 'member_contact_details',
        'profile' => 'member_profile_photo',
        'listing' => 'member_listing_details',
        'about' => 'about',
    ];
    $formName = $formBySection[$section] ?? '';
    $definitions = memberFormDefinitions();
    if ($formName === '' || !isset($definitions[$formName])) {
        return [];
    }

    return array_values(array_unique(array_map(
        static fn(array $field): string => (string)$field[1],
        $definitions[$formName]['fields']
    )));
}

function ensureMemberForms(PDO $pdo): void
{
    if (!tableExists($pdo, 'forms') || !tableExists($pdo, 'form_fields')) {
        return;
    }

    $definitions = memberFormDefinitions();

    foreach ($definitions as $name => $definition) {
        $find = $pdo->prepare('SELECT form_id, form_name FROM forms WHERE form_name = ? LIMIT 1');
        $find->execute([$name]);
        $existingForm = $find->fetch(PDO::FETCH_ASSOC);
        $formId = (int)($existingForm['form_id'] ?? 0);
        $formVariable = (string)($existingForm['form_name'] ?? $name);

        if ($formId === 0 && tableExists($pdo, 'users_meta')) {
            $renamedForm = $pdo->prepare(
                "SELECT f.form_id, f.form_name
                 FROM users_meta m
                 INNER JOIN forms f ON f.form_id = m.database_id
                 WHERE m.`database` = 'forms'
                   AND m.`key` = 'member_form_seeded'
                   AND m.value = ?
                 ORDER BY f.form_id DESC LIMIT 1"
            );
            $renamedForm->execute([$name]);
            $existingForm = $renamedForm->fetch(PDO::FETCH_ASSOC);
            $formId = (int)($existingForm['form_id'] ?? 0);
            $formVariable = (string)($existingForm['form_name'] ?? $name);
        }

        if ($formId === 0) {
            $insert = $pdo->prepare(
                "INSERT INTO forms (
                    form_name, form_desc, form_url, form_target, form_action,
                    form_database, form_table, table_index, form_email_recipient,
                    form_title, form_email_on, email_template, email_admin,
                    email_from, email_template_admin, form_class, form_style,
                    form_element_id, short_code, form_layout, trigger_data_flow,
                    data_flow_name, form_action_type, form_action_div
                ) VALUES (?, ?, '', '', 'post', '', 'users_data', 'user_id', '', ?, 0, '', 0, '', '', 'form-control', '', ?, ?, 'bootstrapvertical', 'No', '', ?, '')"
            );
            $insert->execute([
                $name,
                'Member profile form managed through the existing Form Manager.',
                $definition['title'],
                $name,
                '[form=' . $name . ']',
                $name === 'member_login' ? 'login' : 'widget'
            ]);
            $formId = (int)$pdo->lastInsertId();
        }

        if ($name === 'member_contact_details') {
            $pdo->prepare("UPDATE forms SET short_code = CONCAT('[form=', form_name, ']') WHERE form_id = ? AND short_code = '[form=contact_form]'")->execute([$formId]);
            if (tableExists($pdo, 'users_meta')) {
                $pdo->prepare("DELETE FROM users_meta WHERE `database` = 'forms' AND database_id = ? AND `key` = 'public_shortcode_contact_form'")->execute([$formId]);
            }
        }

        if ($name === 'member_login') {
            $loginType = $pdo->prepare("UPDATE forms SET form_action_type = 'login', short_code = '[form=member_login]' WHERE form_id = ?");
            $loginType->execute([$formId]);
        }

        $seedMarkerExists = false;
        if (tableExists($pdo, 'users_meta')) {
            $seedMarker = $pdo->prepare(
                "SELECT meta_id FROM users_meta
                 WHERE `database` = 'forms' AND database_id = ? AND `key` = 'member_form_seeded'
                 LIMIT 1"
            );
            $seedMarker->execute([$formId]);
            $seedMarkerExists = (bool)$seedMarker->fetchColumn();
        }

        if ($name === 'member_listing_details') {
            $hoursField = $pdo->prepare("UPDATE form_fields SET field_name = 'working_days' WHERE form_name = ? AND field_name = 'work_experience' AND field_text = 'Hours of Operation'");
            $hoursField->execute([$formVariable]);
        }

        if (!$seedMarkerExists) {
            $fieldInsert = $pdo->prepare(
                'INSERT INTO form_fields (
                    form_name, field_text, field_name, field_type, field_order,
                    field_sdesc, field_ldesc, field_placeholder, field_required,
                    field_options, input_class, display_class, default_value,
                    field_display_view, field_input_view, field_grid_view,
                    field_email_view, form_section, field_search_view,
                    display_div_id, input_div_id, field_icon, json_meta
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $existingField = $pdo->prepare('SELECT field_id FROM form_fields WHERE form_name = ? AND field_name = ? LIMIT 1');
            foreach ($definition['fields'] as $index => $field) {
                [$label, $fieldName, $type, $placeholder, $required] = $field;
                $existingField->execute([$formVariable, $fieldName]);
                if ($existingField->fetchColumn()) continue;
                $fieldInsert->execute([
                    $formVariable,
                    $label,
                    $fieldName,
                    $type,
                    $index + 1,
                    '',
                    '',
                    $placeholder,
                    $required,
                    ($fieldName === 'listing_type' ? "Personal / Individual\nCompany" : ''),
                    'form-control',
                    '',
                    '',
                    '1',
                    '1',
                    '1',
                    '1',
                    '',
                    '0',
                    '',
                    '',
                    '',
                    '{}'
                ]);
            }

        }

        if (!$seedMarkerExists && tableExists($pdo, 'users_meta')) {
            $markSeeded = $pdo->prepare(
                "INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
                 VALUES ('forms', ?, 'member_form_seeded', ?, ?)"
            );
            $markSeeded->execute([$formId, $name, date('YmdHis')]);
        }

        if (tableExists($pdo, 'users_meta')) {
            $meta = $pdo->prepare(
                "SELECT meta_id FROM users_meta
                 WHERE `database` = 'forms' AND database_id = ? AND `key` = 'form_status'
                 LIMIT 1"
            );
            $meta->execute([$formId]);

            if (!$meta->fetchColumn()) {
                $saveMeta = $pdo->prepare(
                    "INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
                     VALUES ('forms', ?, 'form_status', 'enabled', ?)"
                );
                $saveMeta->execute([$formId, date('YmdHis')]);
            }

            $nickname = $pdo->prepare(
                "SELECT meta_id FROM users_meta
                 WHERE `database` = 'forms' AND database_id = ? AND `key` = 'form_nickname'
                 LIMIT 1"
            );
            $nickname->execute([$formId]);
            if (!$nickname->fetchColumn()) {
                $saveNickname = $pdo->prepare(
                    "INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
                     VALUES ('forms', ?, 'form_nickname', ?, ?)"
                );
                $saveNickname->execute([$formId, $definition['title'], date('YmdHis')]);
            }
        }
    }

    if (tableExists($pdo, 'users_meta')) {
        $profileForm = $pdo->prepare("SELECT f.form_id, f.form_name FROM forms f LEFT JOIN users_meta m ON m.`database` = 'forms' AND m.database_id = f.form_id AND m.`key` = 'member_form_seeded' WHERE f.form_name = 'member_profile_photo' OR m.value = 'member_profile_photo' ORDER BY f.form_id DESC LIMIT 1");
        $profileForm->execute();
        $profileFormRow = $profileForm->fetch(PDO::FETCH_ASSOC);
        if ($profileFormRow) {
            $migration = $pdo->prepare("SELECT meta_id FROM users_meta WHERE `database` = 'forms' AND database_id = ? AND `key` = 'profile_photo_component_migrated' LIMIT 1");
            $migration->execute([(int)$profileFormRow['form_id']]);
            if (!$migration->fetchColumn()) {
                $componentField = $pdo->prepare("SELECT field_id FROM form_fields WHERE form_name = ? AND field_name = 'profile_photo' ORDER BY field_id LIMIT 1");
                $componentField->execute([(string)$profileFormRow['form_name']]);
                $componentFieldId = (int)$componentField->fetchColumn();
                if ($componentFieldId > 0) {
                    $pdo->prepare("UPDATE form_fields SET field_text = 'profile_photo.php', field_type = 'HTML', field_name = 'profile_photo_component', field_ldesc = '', field_sdesc = '', field_required = 0 WHERE field_id = ?")->execute([$componentFieldId]);
                    $pdo->prepare("DELETE FROM form_fields WHERE form_name = ? AND field_name = 'profile_logo'")->execute([(string)$profileFormRow['form_name']]);
                }
                $pdo->prepare("INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added) VALUES ('forms', ?, 'profile_photo_component_migrated', '1', ?)")->execute([(int)$profileFormRow['form_id'], date('YmdHis')]);
            }
        }
    }
}

function resolveMemberFormName(PDO $pdo, string $canonicalName): string
{
    if (tableExists($pdo, 'users_meta')) {
        $customized = $pdo->prepare(
            "SELECT f.form_name
             FROM users_meta m
             INNER JOIN forms f ON f.form_id = m.database_id
             WHERE m.`database` = 'forms'
               AND m.`key` = 'member_form_seeded'
               AND m.value = ?
             ORDER BY f.form_id DESC
             LIMIT 1"
        );
        $customized->execute([$canonicalName]);
        $formName = $customized->fetchColumn();
        if ($formName !== false) return (string)$formName;
    }

    $find = $pdo->prepare('SELECT form_name FROM forms WHERE form_name = ? LIMIT 1');
    $find->execute([$canonicalName]);
    $formName = $find->fetchColumn();

    return $formName !== false ? (string)$formName : $canonicalName;
}

function memberMetaValue(PDO $pdo, int $userId, string $key): string
{
    $statement = $pdo->prepare(
        "SELECT value FROM users_meta
         WHERE `database` = 'users_data' AND database_id = ? AND `key` = ?
         ORDER BY meta_id DESC LIMIT 1"
    );
    $statement->execute([$userId, $key]);
    return (string)($statement->fetchColumn() ?: '');
}

function saveMemberMeta(PDO $pdo, int $userId, string $key, string $value): void
{
    $find = $pdo->prepare(
        "SELECT meta_id FROM users_meta
         WHERE `database` = 'users_data' AND database_id = ? AND `key` = ?
         ORDER BY meta_id DESC LIMIT 1"
    );
    $find->execute([$userId, $key]);
    $metaId = $find->fetchColumn();

    if ($metaId) {
        $update = $pdo->prepare('UPDATE users_meta SET value = ? WHERE meta_id = ?');
        $update->execute([$value, $metaId]);
        return;
    }

    $insert = $pdo->prepare(
        "INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
         VALUES ('users_data', ?, ?, ?, ?)"
    );
    $insert->execute([$userId, $key, $value, date('YmdHis')]);
}

function safeMemberRichText(string $html): string
{
    $html = preg_replace('/<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?<\/\1\s*>/is', '', $html);
    $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><hr><pre><code><span><sub><sup><table><thead><tbody><tr><th><td><figure><figcaption><img><oembed>';
    $html = strip_tags($html, $allowedTags);

    $safeStyle = static function (string $style): string {
        $declarations = preg_split('/\s*;\s*/', html_entity_decode($style, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $safeStyles = [];
        foreach ($declarations as $declaration) {
            if (!str_contains($declaration, ':')) continue;
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = trim($value);
            if (in_array($property, ['color', 'background-color'], true) && preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9.,% ]+\)|[a-z]{3,20})$/i', $value)) {
                $safeStyles[] = $property . ':' . $value;
            } elseif (in_array($property, ['font-size', 'width', 'height', 'max-width'], true) && preg_match('/^(\d+(?:\.\d+)?)(px|pt|em|rem|%)$/i', $value)) {
                $safeStyles[] = $property . ':' . $value;
            } elseif ($property === 'font-family' && preg_match('/^[a-z0-9 ,"\'-]{1,100}$/i', $value)) {
                $safeStyles[] = $property . ':' . $value;
            } elseif (in_array($property, ['text-align', 'vertical-align'], true) && in_array(strtolower($value), ['left', 'right', 'center', 'justify', 'top', 'middle', 'bottom'], true)) {
                $safeStyles[] = $property . ':' . strtolower($value);
            }
        }
        return implode(';', $safeStyles);
    };

    $html = preg_replace_callback('/<a\b([^>]*)>/i', static function ($matches) {
        if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $matches[1], $hrefMatch)) {
            $href = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safe = preg_match('~^(https?://|mailto:|tel:|/|#)~i', $href) && !str_starts_with($href, '//');
            if ($safe) return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">';
        }
        return '<a>';
    }, $html);

    $html = preg_replace_callback('/<img\b([^>]*)>/i', static function ($matches) use ($safeStyle) {
        $attributes = $matches[1];
        if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attributes, $srcMatch)) return '';
        $src = html_entity_decode($srcMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^(https?://|/|images/|uploads/)~i', $src) || str_starts_with($src, '//')) return '';
        $alt = '';
        if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $attributes, $altMatch)) $alt = html_entity_decode($altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $output = '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
        foreach (['width', 'height'] as $dimension) {
            if (preg_match('/\b' . $dimension . '\s*=\s*(["\'])(\d{1,4})\1/i', $attributes, $dimensionMatch)) {
                $output .= ' ' . $dimension . '="' . (int)$dimensionMatch[2] . '"';
            }
        }
        if (preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i', $attributes, $styleMatch)) {
            $style = $safeStyle($styleMatch[2]);
            if ($style !== '') $output .= ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $output . '>';
    }, $html);

    $html = preg_replace_callback('/<oembed\b([^>]*)>/i', static function ($matches) {
        if (preg_match('/\burl\s*=\s*(["\'])(.*?)\1/i', $matches[1], $urlMatch)) {
            $url = html_entity_decode($urlMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('~^https?://~i', $url)) return '<oembed url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        }
        return '';
    }, $html);

    $html = preg_replace_callback('/<(p|span|h[1-6]|li|blockquote|td|th|table|figure|figcaption)\b([^>]*)>/i', static function ($matches) use ($safeStyle) {
        $tag = strtolower($matches[1]);
        $attributes = $matches[2];
        $output = '<' . $tag;
        if (preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i', $attributes, $styleMatch)) {
            $style = $safeStyle($styleMatch[2]);
            if ($style !== '') $output .= ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
        }
        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attributes, $classMatch)) {
            $classes = preg_split('/\s+/', trim(html_entity_decode($classMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $classes = array_values(array_filter($classes, static function ($class) {
                return preg_match('/^(image|image-style-(?:align-)?(?:left|right|center|side|block|inline)|table|table-column|table-row|media)$/', $class);
            }));
            if ($classes) $output .= ' class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . '"';
        }
        return $output . '>';
    }, $html);

    $html = preg_replace_callback('/<\/?(strong|b|em|i|u|s|ul|ol|li|h[1-6]|blockquote|hr|pre|code|sub|sup|table|thead|tbody|tr|th|td|br|figcaption)\b[^>]*>/i', static function ($matches) {
        $tag = strtolower($matches[1]);
        return preg_match('/^<\//', $matches[0]) ? '</' . $tag . '>' : '<' . $tag . '>';
    }, $html);
    return $html;
}

function memberPhotoUrl(string $file, string $original = ''): string
{
    global $config;
    $file = trim($file);
    if ($file === '') return '';
    if (preg_match('~^https?://~i', $file)) return $file;
    $file = ltrim(str_replace('\\', '/', $file), '/');
    $relative = str_starts_with($file, 'images/') ? $file : 'images/' . basename($file);
    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $localPath = $documentRoot . rtrim($config['base_url'], '/') . '/' . $relative;
    if ($documentRoot !== '' && is_file($localPath)) return rtrim($config['base_url'], '/') . '/' . $relative;
    if (preg_match('~^https?://~i', $original)) return $original;
    if (str_starts_with($file, 'images/')) return rtrim($config['base_url'], '/') . '/' . $file;
    if (str_starts_with($file, 'uploads/')) return rtrim($config['base_url'], '/') . '/' . $file;
    return rtrim($config['base_url'], '/') . '/images/' . rawurlencode(basename($file));
}

function memberFieldValue(PDO $pdo, array $member, string $fieldName, ?string $section = null): string
{
    $columns = [
        'first_name', 'last_name', 'email', 'phone_number', 'company',
        'position', 'search_description', 'address1', 'address2', 'city',
        'state_ln', 'country_ln', 'zip_code', 'website', 'blog', 'facebook',
        'twitter', 'linkedin', 'youtube', 'instagram', 'pinterest', 'quote',
        'experience', 'affiliation', 'credentials', 'awards', 'about_me',
        'working_days','work_experience','cv', 'listing_type','profession_id'
    ];

    if ($section !== null && !in_array($fieldName, memberDefaultFieldNames($section), true)) {
        return memberMetaValue($pdo, (int)$member['user_id'], $fieldName);
    }

    if (in_array($fieldName, $columns, true)) {
        $value = (string)($member[$fieldName] ?? '');
        if (in_array($fieldName, ['website','blog','facebook','twitter','linkedin','youtube','instagram','pinterest'], true)) {
            return rawurldecode($value);
        }
        return $value;
    }

    if ($fieldName === 'specialty_categories' && tableExists($pdo, 'rel_services')) {
        $selected = $pdo->prepare('SELECT service_id FROM rel_services WHERE user_id = ? AND specialty = 1 ORDER BY service_id');
        $selected->execute([(int)$member['user_id']]);
        return implode("\n", array_map('strval', $selected->fetchAll(PDO::FETCH_COLUMN)));
    }

    if ($fieldName === 'profile_photo' || $fieldName === 'profile_logo') {
        $type = $fieldName === 'profile_photo' ? 'photo' : 'logo';
        $photo = $pdo->prepare('SELECT file, original FROM users_photo WHERE user_id = ? AND type = ? ORDER BY photo_id DESC LIMIT 1');
        $photo->execute([(int)$member['user_id'], $type]);
        $photoRecord = $photo->fetch(PDO::FETCH_ASSOC) ?: [];
        return memberPhotoUrl((string)($photoRecord['file'] ?? ''), (string)($photoRecord['original'] ?? ''));
    }

    return memberMetaValue($pdo, (int)$member['user_id'], $fieldName);
}
