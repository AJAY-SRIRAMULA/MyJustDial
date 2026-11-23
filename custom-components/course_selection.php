<?php

$courses = [
    'btech' => [
        'label' => 'B.Tech',
        'specializations' => [
            'Computer Science',
            'Information Technology',
            'Electronics',
            'Mechanical',
            'Civil'
        ]
    ],
    'mba' => [
        'label' => 'MBA',
        'specializations' => [
            'Finance',
            'Marketing',
            'Human Resources',
            'Business Analytics'
        ]
    ],
    'bca' => [
        'label' => 'BCA',
        'specializations' => [
            'Computer Applications',
            'Web Development',
            'Data Science'
        ]
    ],
    'mtech' => [
        'label' => 'M.Tech',
        'specializations' => [
            'Computer Science',
            'VLSI',
            'Structural Engineering'
        ]
    ]
];

$savedCourse = '';
$savedSpecialization = '';

if ($pdo instanceof PDO && $userId > 0 && tableExists($pdo, 'users_meta')) {
    $savedValues = $pdo->prepare(
        "SELECT `key`, value FROM users_meta
         WHERE `database` = 'users_data'
         AND database_id = ?
         AND `key` IN (?, ?)"
    );
    $savedValues->execute([
        $userId,
        'component_' . $fieldId . '_course',
        'component_' . $fieldId . '_specialization'
    ]);

    foreach ($savedValues->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
        if ($key === 'component_' . $fieldId . '_course') $savedCourse = (string)$value;
        if ($key === 'component_' . $fieldId . '_specialization') $savedSpecialization = (string)$value;
    }
}

$postedCourse = $_POST['fields']['component_' . $fieldId . '_course'] ?? '';
$postedSpecialization = $_POST['fields']['component_' . $fieldId . '_specialization'] ?? '';

if (is_scalar($postedCourse) && isset($courses[(string)$postedCourse])) {
    $savedCourse = (string)$postedCourse;
}

if (is_scalar($postedSpecialization)) {
    $savedSpecialization = (string)$postedSpecialization;
}

if (!isset($courses[$savedCourse])) {
    $savedCourse = '';
    $savedSpecialization = '';
}

if ($savedSpecialization !== '' && !in_array($savedSpecialization, $courses[$savedCourse]['specializations'], true)) {
    $savedSpecialization = '';
}

$componentId = 'course-selection-' . (int)$fieldId . '-' . bin2hex(random_bytes(4));
?>

<div class="course-component" id="<?= e($componentId) ?>">
    <div class="form-group">
        <label for="<?= e($componentId) ?>-course">Choose Course <span class="text-danger">*</span></label>
        <select id="<?= e($componentId) ?>-course" name="fields[component_<?= (int)$fieldId ?>_course]" class="form-control course-dropdown" required>
            <option value="">Select Course</option>
            <?php foreach ($courses as $key => $course): ?>
                <option value="<?= e($key) ?>" <?= $savedCourse === $key ? 'selected' : '' ?>><?= e($course['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group specialization-group" style="<?= $savedCourse !== '' ? '' : 'display:none;' ?>">
        <label for="<?= e($componentId) ?>-specialization">Choose Specialization <span class="text-danger">*</span></label>
        <select id="<?= e($componentId) ?>-specialization" name="fields[component_<?= (int)$fieldId ?>_specialization]" class="form-control specialization-dropdown" <?= $savedCourse !== '' ? 'required' : 'disabled' ?>>
            <option value="">Select Specialization</option>
            <?php if ($savedCourse !== ''): ?>
                <?php foreach ($courses[$savedCourse]['specializations'] as $specialization): ?>
                    <option value="<?= e($specialization) ?>" <?= $savedSpecialization === $specialization ? 'selected' : '' ?>><?= e($specialization) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
</div>

<style>
.course-component { width: 100%; margin-bottom: 15px; }
.course-component .form-group { margin-bottom: 18px; }
.course-component label { display: block; font-weight: 600; margin-bottom: 7px; }
.course-component .form-control { height: 42px; border-radius: 4px; }
</style>

<script>
(function () {
    var component = document.getElementById(<?= json_encode($componentId) ?>);
    if (!component) return;

    var courses = <?= json_encode($courses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var courseDropdown = component.querySelector('.course-dropdown');
    var specializationGroup = component.querySelector('.specialization-group');
    var specializationDropdown = component.querySelector('.specialization-dropdown');

    function updateSpecializations() {
        var selectedCourse = courseDropdown.value;
        specializationDropdown.innerHTML = '<option value="">Select Specialization</option>';

        if (!selectedCourse || !courses[selectedCourse]) {
            specializationGroup.style.display = 'none';
            specializationDropdown.disabled = true;
            specializationDropdown.required = false;
            return;
        }

        courses[selectedCourse].specializations.forEach(function (specialization) {
            var option = document.createElement('option');
            option.value = specialization;
            option.textContent = specialization;
            specializationDropdown.appendChild(option);
        });

        specializationGroup.style.display = 'block';
        specializationDropdown.disabled = false;
        specializationDropdown.required = true;
    }

    courseDropdown.addEventListener('change', function () {
        updateSpecializations();
    });
})();
</script>
