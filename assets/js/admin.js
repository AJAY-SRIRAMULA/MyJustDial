(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var menuToggle = event.target.closest(
            '.nav-group-toggle[data-menu-toggle]'
        );

        if (menuToggle) {
            var menuGroup = menuToggle.closest('.nav-group');
            var menuKey = menuToggle.getAttribute('data-menu-toggle');
            var submenu = menuGroup
                ? menuGroup.querySelector('[data-menu="' + menuKey + '"]')
                : null;

            if (!menuGroup || !submenu) {
                return;
            }

            var shouldOpen = !submenu.classList.contains('is-open');
            var menuGroups = document.querySelectorAll('.nav-group');

            menuGroups.forEach(function (otherGroup) {
                if (otherGroup === menuGroup) {
                    return;
                }

                var otherSubmenu = otherGroup.querySelector('.sub-nav');
                var otherToggle = otherGroup.querySelector('.nav-group-toggle');

                if (otherSubmenu) {
                    otherSubmenu.classList.remove('is-open');
                }

                otherGroup.classList.remove(
                    'is-clicked-inline',
                    'is-click-closed'
                );

                if (otherToggle) {
                    otherToggle.setAttribute('aria-expanded', 'false');
                }
            });

            submenu.classList.toggle('is-open', shouldOpen);
            menuGroup.classList.toggle('is-clicked-inline', shouldOpen);
            menuGroup.classList.toggle('is-click-closed', !shouldOpen);
            menuToggle.setAttribute(
                'aria-expanded',
                shouldOpen ? 'true' : 'false'
            );

            document.querySelectorAll(
                '.small-dropdown.is-open, .fm-action-dropdown.is-open'
            ).forEach(function (dropdown) {
                dropdown.classList.remove('is-open');
            });

            event.stopPropagation();
            return;
        }

        var otherMenuToggle = event.target.closest('[data-menu-toggle]');

        if (otherMenuToggle) {
            var otherMenuKey = otherMenuToggle.getAttribute('data-menu-toggle');
            var otherSubmenu = document.querySelector(
                '[data-menu="' + otherMenuKey + '"]'
            );

            if (!otherSubmenu) {
                return;
            }

            var shouldOpenOtherMenu = !otherSubmenu.classList.contains('is-open');

            document.querySelectorAll('.sub-nav.is-open').forEach(function (item) {
                item.classList.remove('is-open');
            });

            document.querySelectorAll('.nav-group').forEach(function (group) {
                group.classList.remove(
                    'is-clicked-inline',
                    'is-click-closed'
                );

                var groupToggle = group.querySelector('.nav-group-toggle');

                if (groupToggle) {
                    groupToggle.setAttribute('aria-expanded', 'false');
                }
            });

            document.querySelectorAll(
                '.small-dropdown.is-open, .fm-action-dropdown.is-open'
            ).forEach(function (dropdown) {
                dropdown.classList.remove('is-open');
            });

            otherSubmenu.classList.toggle('is-open', shouldOpenOtherMenu);

            if (otherMenuToggle.hasAttribute('aria-expanded')) {
                otherMenuToggle.setAttribute(
                    'aria-expanded',
                    shouldOpenOtherMenu ? 'true' : 'false'
                );
            }

            if (
                shouldOpenOtherMenu
                && otherMenuToggle.classList.contains('fm-action-toggle')
            ) {
                otherSubmenu.style.position = 'fixed';
                otherSubmenu.style.right = 'auto';
                otherSubmenu.style.transform = 'none';

                var triggerRect = otherMenuToggle.getBoundingClientRect();
                var submenuRect = otherSubmenu.getBoundingClientRect();
                var gap = 7;
                var leftPosition = triggerRect.right + gap;

                if (leftPosition + submenuRect.width > window.innerWidth - 8) {
                    leftPosition = triggerRect.left - submenuRect.width - gap;
                }

                leftPosition = Math.max(
                    8,
                    Math.min(
                        leftPosition,
                        window.innerWidth - submenuRect.width - 8
                    )
                );

                var topPosition = triggerRect.top
                    + (triggerRect.height - submenuRect.height) / 2;

                topPosition = Math.max(
                    8,
                    Math.min(
                        topPosition,
                        window.innerHeight - submenuRect.height - 8
                    )
                );

                otherSubmenu.style.left = leftPosition + 'px';
                otherSubmenu.style.top = topPosition + 'px';
            }

            event.stopPropagation();
            return;
        }

        var disabledLink = event.target.closest('a[aria-disabled="true"]');

        if (disabledLink) {
            event.preventDefault();
            return;
        }

        var clickedInsideDropdown = event.target.closest(
            '.fm-action-dropdown, .small-dropdown'
        );

        if (!clickedInsideDropdown) {
            document.querySelectorAll(
                '.fm-action-dropdown.is-open, .small-dropdown.is-open'
            ).forEach(function (dropdown) {
                dropdown.classList.remove('is-open');
            });
        }

        var copyButton = event.target.closest('.copy-shortcode');

        if (copyButton) {
            event.preventDefault();

            var shortcode = copyButton.getAttribute('data-code') || '';

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shortcode).then(function () {
                    alert('Shortcode copied: ' + shortcode);
                });
            } else {
                var temporaryInput = document.createElement('textarea');
                temporaryInput.value = shortcode;
                document.body.appendChild(temporaryInput);
                temporaryInput.select();
                document.execCommand('copy');
                temporaryInput.remove();
                alert('Shortcode copied: ' + shortcode);
            }
        }
    });

    var mobileMenuButton = document.getElementById('mobileMenu');
    var sidebar = document.getElementById('sidebar');

    var navGroups = Array.from(document.querySelectorAll('.nav-group'));
    function clearHover() {
        navGroups.forEach(function (group) { group.classList.remove('is-hovered'); });
        if (sidebar) sidebar.classList.remove('has-hovered-group');
    }
    navGroups.forEach(function (group) {
        group.addEventListener('mouseenter', function () {
            if (!window.matchMedia('(min-width: 651px) and (hover: hover) and (pointer: fine)').matches) return;
            clearHover();
            group.classList.remove('is-click-closed');
            group.classList.add('is-hovered');
            if (sidebar.classList.contains('sidebar-collapsed')) {
                sidebar.classList.add('has-hovered-group');
            }
        });
        group.addEventListener('mouseleave', clearHover);
    });

    var refreshOverlay = document.getElementById('liveRefreshOverlay');
    var refreshTimer;
    document.querySelectorAll('.refresh-site, a[href="#refresh-live-site"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (!refreshOverlay || !refreshOverlay.hidden) return;
            refreshOverlay.hidden = false;
            document.getElementById('liveRefreshClose').focus();
            refreshTimer = window.setTimeout(function () { window.location.reload(); }, 350);
        });
    });
    if (refreshOverlay) {
        document.getElementById('liveRefreshClose').addEventListener('click', function () {
            window.clearTimeout(refreshTimer);
            refreshOverlay.hidden = true;
        });
        window.addEventListener('pageshow', function () { refreshOverlay.hidden = true; });
    }

    if (mobileMenuButton && sidebar) {
        mobileMenuButton.addEventListener('click', function () {
            sidebar.classList.toggle('sidebar-open');
        });
    }

    var collapseButton = document.getElementById('collapseSidebar');

    if (collapseButton && sidebar) {
        collapseButton.addEventListener('click', function () {
            var isMobile = window.matchMedia('(max-width: 650px)').matches;

            if (isMobile) {
                sidebar.classList.remove('sidebar-open');
                return;
            }

            clearHover();
            navGroups.forEach(function (group) {
                group.classList.remove('is-clicked-inline', 'is-click-closed');
                group.querySelector('.sub-nav').classList.remove('is-open');
                group.querySelector('.nav-group-toggle').setAttribute('aria-expanded', 'false');
            });
            sidebar.classList.toggle('sidebar-collapsed');
            if (!sidebar.classList.contains('sidebar-collapsed')) {
                var current = sidebar.querySelector('.nav-group.current');
                if (current) { current.querySelector('.sub-nav').classList.add('is-open'); current.querySelector('.nav-group-toggle').setAttribute('aria-expanded', 'true'); }
            }
            document.body.classList.toggle('sidebar-is-collapsed');
        });
    }

    var selectAllCheckbox = document.getElementById('selectAll');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            document.querySelectorAll('.form-row-check').forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    var filterSelects = document.querySelectorAll('.fm-filters select');

    Array.prototype.forEach.call(filterSelects, function (select) {
        select.addEventListener('change', function () {
            var filterForm = select.closest('form');

            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    var dismissButton = document.querySelector('[data-dismiss-notice]');

    if (dismissButton) {
        dismissButton.addEventListener('click', function () {
            var notice = dismissButton.closest('.notice');

            if (notice) {
                notice.remove();
            }
        });
    }
}());

var applyBulkButton = document.getElementById('applyBulk');

if (applyBulkButton) {
    applyBulkButton.addEventListener('click', function () {
        var actionSelect = document.getElementById('bulkAction');
        var tokenInput = document.getElementById('bulkCsrfToken');
        var selectedCheckboxes = Array.prototype.filter.call(
            document.querySelectorAll('.form-row-check:checked'),
            function (checkbox) {
                return checkbox.value !== '';
            }
        );

        if (!actionSelect || !actionSelect.value) {
            alert('Choose a bulk action first.');
            return;
        }

        if (!selectedCheckboxes.length) {
            alert('Select at least one form.');
            return;
        }

        if (
            actionSelect.value === 'delete'
            && !window.confirm('Delete the selected forms and their fields?')
        ) {
            return;
        }

        var bulkForm = document.createElement('form');
        bulkForm.method = 'post';
        bulkForm.action = window.location.pathname.replace(
            /index\.php$/,
            'actions.php'
        );

        var formValues = {
            action: 'bulk',
            bulk_action: actionSelect.value,
            csrf_token: tokenInput ? tokenInput.value : ''
        };

        Object.keys(formValues).forEach(function (key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = formValues[key];
            bulkForm.appendChild(input);
        });

        selectedCheckboxes.forEach(function (checkbox) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = checkbox.value;
            bulkForm.appendChild(input);
        });

        document.body.appendChild(bulkForm);
        bulkForm.submit();
    });
}

(function () {
    'use strict';
    document.addEventListener('click', function (event) {
        var categoryOption = event.target.closest('.top-category-option');
        if (!categoryOption) return;
        var categoryGroup = categoryOption.closest('.top-search-group');
        var categoryToggle = categoryGroup ? categoryGroup.querySelector('.top-select') : null;
        var categoryMenu = categoryGroup ? categoryGroup.querySelector('[data-menu="search-category"]') : null;
        if (!categoryToggle || !categoryMenu) return;
        categoryToggle.innerHTML = categoryOption.getAttribute('data-category') + ' <i class="fa fa-chevron-down"></i>';
        categoryMenu.querySelectorAll('.top-category-option').forEach(function (option) {
            option.classList.toggle('is-selected', option === categoryOption);
        });
        categoryMenu.classList.remove('is-open');
    });
})();
