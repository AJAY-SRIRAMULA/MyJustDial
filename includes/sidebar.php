<?php
$sidebarGroups = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5M5.5 9.5V21h13V9.5M9.5 21v-7h5v7"/></svg>',
        'items' => [
            [
                'label' => 'Go To Dashboard',
                'url' => $config['base_url'] . '/admin/dashboard.php',
                'active' => $activeMenu === 'dashboard'
            ],
            ['label' => 'Manage Add-Ons'],
            ['label' => 'Activity Tracker'],
            ['label' => 'Visit My Website'],
            ['label' => 'Refresh Live Site', 'url' => '#refresh-live-site']
        ]
    ],
    'members' => [
        'label' => 'My Members',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 20c.4-4 2.8-6 7-6s6.6 2 7 6H2ZM17 4a3.5 3.5 0 0 1 0 7M18 14c2.5.6 3.8 2.5 4 6h-4"/></svg>',
        'items' => [
            ['label' => 'Search Members', 'url' => $config['base_url'] . '/admin/search-members.php', 'active' => $activeMenu === 'members' && basename($_SERVER['SCRIPT_NAME']) !== 'member-categories.php'],
            ['label' => 'Member Categories', 'url' => $config['base_url'] . '/admin/member-categories.php', 'active' => basename($_SERVER['SCRIPT_NAME']) === 'member-categories.php'],
            ['label' => 'Smart Lists & Tags'],
            ['label' => 'Member Images'],
            ['label' => 'Import Members'],
            ['label' => 'Business Data']
        ]
    ],
    'content' => [
        'label' => 'My Content',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 6.5h7l2 2H21a1.5 1.5 0 0 1 1.5 1.5v9a2 2 0 0 1-2 2h-16a2 2 0 0 1-2-2V8a1.5 1.5 0 0 1 1.5-1.5Z"/><path d="M2.5 10h19"/></svg>',
        'items' => [
            ['label' => 'Web Page Builder'],
            ['label' => 'Manage Posts'],
            ['label' => 'Edit Post Settings'],
            ['label' => 'SEO Templates'],
            ['label' => 'Media Manager']
        ]
    ],
    'interactions' => [
        'label' => 'Interactions',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.2 3 2 6.7 2 11.5c0 2.2 1 4.1 2.7 5.6L4 21l4.5-2.1c1.1.4 2.3.6 3.5.6 5.8 0 10-3.7 10-8S17.8 3 12 3Z"/><circle cx="7.5" cy="11.5" r=".8"/><circle cx="12" cy="11.5" r=".8"/><circle cx="16.5" cy="11.5" r=".8"/></svg>',
        'items' => [
            ['label' => 'Member Leads'],
            ['label' => 'Member Reviews'],
            ['label' => 'Post Comments'],
            ['label' => 'Private Chats']
        ]
    ],
    'finance' => [
        'label' => 'Finance',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21V13h4v8H3ZM10 21V7h4v14h-4ZM17 21V3h4v18h-4Z"/></svg>',
        'items' => [
            ['label' => 'Membership Plans'],
            ['label' => 'Transaction History'],
            ['label' => 'Search Subscriptions'],
            ['label' => 'Coupon Codes'],
            ['label' => 'Payment Settings'],
            ['label' => 'Manage Offers']
        ]
    ],
    'emails' => [
        'label' => 'Emails',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="15" rx="1.8"/><path d="m3 6 9 7 9-7"/><circle cx="18.5" cy="18.5" r="4"/><path d="m16.8 18.5 1.2 1.2 2.2-2.5"/></svg>',
        'items' => [
            ['label' => 'Forms Inbox'],
            ['label' => 'Email Outbox'],
            ['label' => 'Compose Email'],
            ['label' => 'Email Templates'],
            ['label' => 'Send Newsletter'],
            ['label' => 'Email Accounts'],
            ['label' => 'View Contacts']
        ]
    ],
    'toolbox' => [
        'label' => 'Toolbox',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6.5 3-3a5 5 0 0 1-6.2 6.2L5 16l3 3 6.3-6.3a5 5 0 0 1 6.2-6.2l-3 3M3 3l5 5M2.5 17.5l4 4M16 16l5 5"/></svg>',
        'items' => [
            ['label' => 'Widget Manager', 'url' => $config['base_url'] . '/admin/toolbox/widget-manager/index.php', 'active' => $activeMenu === 'widgets'],
            ['label' => 'Sidebar Manager'],
            ['label' => 'Menu Manager'],
            [
                'label' => 'Form Manager',
                'url' => $config['base_url'] . '/admin/toolbox/form-manager/index.php',
                'active' => $activeMenu === 'forms'
            ],
            ['label' => 'Sitemap Generator']
        ]
    ],
    'settings' => [
        'label' => 'Settings',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 2-.5 2.2a8 8 0 0 0-2 .9L5.5 4 3 6.5l1.1 2a8 8 0 0 0-.8 2L1 11v3l2.3.5a8 8 0 0 0 .8 2L3 18.5 5.5 21l2-1.1a8 8 0 0 0 2 .8L10 23h3l.5-2.3a8 8 0 0 0 2-.8l2 1.1 2.5-2.5-1.1-2a8 8 0 0 0 .8-2L22 14v-3l-2.3-.5a8 8 0 0 0-.8-2L20 6.5 17.5 4l-2 1.1a8 8 0 0 0-2-.9L13 2h-3Z"/><circle cx="11.5" cy="12.5" r="3.5"/></svg>',
        'items' => [
            ['label' => 'General Settings', 'active' => $activeMenu === 'website-settings'],
            ['label' => 'Design Settings'],
            ['label' => 'Domain Manager'],
            ['label' => 'Admin Accounts'],
            ['label' => 'Text Labels'],
            ['label' => 'Advanced Settings']
        ]
    ]
];

$activeSidebarGroup = [
    'forms' => 'toolbox',
    'widgets' => 'toolbox',
    'dashboard' => 'dashboard',
    'members' => 'members',
    'website-settings' => 'settings',
][$activeMenu] ?? $activeMenu;
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <img
            class="brand-logo"
            src="<?= e($config['base_url']) ?>/assets/images/admin-brand-logo.png"
            alt="Brilliant Directories Demo"
        >
    </div>

    <nav class="side-nav" aria-label="Main navigation">
        <?php foreach ($sidebarGroups as $key => $group): ?>
            <?php
            $isCurrentGroup = $activeSidebarGroup === $key;
            $isExpanded = $isCurrentGroup;
            ?>
            <div class="nav-group <?= $isCurrentGroup ? 'current' : '' ?>">
                <button
                    class="nav-group-toggle"
                    type="button"
                    data-menu-toggle="<?= e($key) ?>"
                    aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                    aria-controls="submenu-<?= e($key) ?>"
                >
                    <span class="nav-icon"><?= $group['icon'] ?></span>
                    <span class="nav-label"><?= e($group['label']) ?></span>
                    <span class="nav-arrow">›</span>
                </button>

                <div
                    class="sub-nav <?= $isExpanded ? 'is-open' : '' ?>"
                    id="submenu-<?= e($key) ?>"
                    data-menu="<?= e($key) ?>"
                >
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                        $itemUrl = $item['url'] ?? '#';
                        $itemActive = !empty($item['active']);
                        ?>
                        <a
                            href="<?= e($itemUrl) ?>"
                            class="<?= $itemActive ? 'active' : '' ?>"
                            <?= empty($item['url']) ? 'aria-disabled="true"' : '' ?>
                        >
                            <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <a href="<?= e($config['base_url']) ?>/admin/devTools.php" class="nav-direct-link">
            <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
            <span class="nav-label">Developer Hub</span>
        </a>

        <button class="collapse-sidebar" id="collapseSidebar" type="button" aria-label="Collapse sidebar">
            <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="3" width="19" height="18" rx="1.5"/><path d="M7 3v18"/></svg></span>
            <span class="nav-label">Collapse</span>
        </button>
    </nav>
</aside>
