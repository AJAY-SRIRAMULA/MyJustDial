<?php
function getPublicWebsiteSettings(PDO $pdo): array
{
    $defaults = [
        'site_name' => 'MyJustDial Directory',
        'tagline' => 'The All-in-One Business Search Directory',
        'logo_url' => '',
        'hero_image' => '',
        'hero_heading' => "Your Idea. Your Directory.\nStart Directories FOR ANY\nIndustry, Group or Location",
        'hero_description' => 'See How Directory Search Works',
        'primary_color' => '#ad0500',
        'nav_color' => '#182e45',
        'hero_overlay' => '0.18',
        'contact_phone' => '',
        'contact_email' => '',
    ];

    if (!tableExists($pdo, 'website_design_settings')) {
        return $defaults;
    }

    $statement = $pdo->prepare(
        "SELECT setting_name, setting_value
         FROM website_design_settings
         WHERE layout_group = 'myjustdial_public_site'"
    );
    $statement->execute();

    foreach ($statement->fetchAll() as $setting) {
        if (array_key_exists($setting['setting_name'], $defaults)) {
            $defaults[$setting['setting_name']] = $setting['setting_value'];
        }
    }

    if ($defaults['hero_image'] === '') {
        $legacyStatement = $pdo->prepare(
            "SELECT setting_value
             FROM website_design_settings
             WHERE setting_name = 'custom_76'
               AND layout_group = 'default_layout'
             LIMIT 1"
        );
        $legacyStatement->execute();
        $legacyHeroImage = (string)$legacyStatement->fetchColumn();

        if ($legacyHeroImage !== '') {
            $legacyImagePath = dirname(__DIR__) . $legacyHeroImage;

            if (is_file($legacyImagePath)) {
                $defaults['hero_image'] = $legacyHeroImage;
            }
        }
    }

    return $defaults;
}
