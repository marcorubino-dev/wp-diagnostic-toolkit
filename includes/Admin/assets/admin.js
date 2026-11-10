(function () {
    'use strict';

    var themeStorageKey = 'wpdt-admin-theme';
    var allowedThemes = ['system', 'light', 'dark'];

    function getSavedTheme() {
        try {
            var savedTheme = window.localStorage.getItem(themeStorageKey);
            return allowedThemes.indexOf(savedTheme) !== -1 ? savedTheme : 'system';
        } catch (error) {
            return 'system';
        }
    }

    function applyTheme(theme, root) {
        var resolvedTheme = theme;

        if (theme === 'system') {
            resolvedTheme = window.matchMedia &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';
        }

        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-resolved', resolvedTheme);
    }

    function createThemeControl(root) {
        var header = root.querySelector('.wpdt-header');

        if (!header || header.querySelector('.wpdt-theme-control')) {
            return;
        }

        var control = document.createElement('div');
        control.className = 'wpdt-theme-control';

        var label = document.createElement('span');
        label.className = 'wpdt-theme-label';
        label.textContent = 'Appearance';

        var options = document.createElement('div');
        options.className = 'wpdt-theme-options';
        options.setAttribute('role', 'group');
        options.setAttribute('aria-label', 'Choose plugin appearance');

        var themeOptions = [
            {
                value: 'system',
                label: 'System',
                icon: '<rect x="3" y="4" width="18" height="13" rx="2"></rect><path d="M8 21h8m-4-4v4"></path>'
            },
            {
                value: 'light',
                label: 'Light',
                icon: '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>'
            },
            {
                value: 'dark',
                label: 'Dark',
                icon: '<path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5Z"></path>'
            }
        ];

        var currentTheme = getSavedTheme();
        var activeTheme = currentTheme;

        themeOptions.forEach(function (optionData) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'wpdt-theme-option';
            button.setAttribute('data-theme-option', optionData.value);
            button.setAttribute('aria-label', optionData.label);
            button.setAttribute('title', optionData.label);
            button.setAttribute('aria-pressed', optionData.value === currentTheme ? 'true' : 'false');

            var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.setAttribute('aria-hidden', 'true');
            icon.setAttribute('focusable', 'false');
            icon.innerHTML = optionData.icon;

            button.appendChild(icon);
            button.addEventListener('click', function () {
                activeTheme = optionData.value;

                try {
                    window.localStorage.setItem(themeStorageKey, activeTheme);
                } catch (error) {
                    // The selected theme still applies for the current page.
                }

                updateThemeButtons(options, activeTheme);
                applyTheme(activeTheme, root);
            });

            options.appendChild(button);
        });

        control.appendChild(label);
        control.appendChild(options);
        applyTheme(currentTheme, root);

        var settingsControl = root.querySelector('.wpdt-settings-appearance-control');
        var headerActions = header.querySelector('.wpdt-header-action');

        if (settingsControl) {
            settingsControl.appendChild(control);
        } else if (headerActions) {
            headerActions.appendChild(control);
        } else {
            header.appendChild(control);
        }

        if (window.matchMedia) {
            var colorScheme = window.matchMedia('(prefers-color-scheme: dark)');
            var handleSystemChange = function () {
                if (activeTheme === 'system') {
                    applyTheme('system', root);
                }
            };

            if (typeof colorScheme.addEventListener === 'function') {
                colorScheme.addEventListener('change', handleSystemChange);
            } else if (typeof colorScheme.addListener === 'function') {
                colorScheme.addListener(handleSystemChange);
            }
        }
    }

    function updateThemeButtons(container, activeTheme) {
        container.querySelectorAll('[data-theme-option]').forEach(function (button) {
            button.setAttribute(
                'aria-pressed',
                button.getAttribute('data-theme-option') === activeTheme ? 'true' : 'false'
            );
        });
    }

    function fallbackCopy(text) {
        var field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.left = '-9999px';
        document.body.appendChild(field);
        field.select();
        field.setSelectionRange(0, field.value.length);

        var copied = false;

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(field);
        return copied;
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(function () {
                return true;
            }).catch(function () {
                return fallbackCopy(text);
            });
        }

        return Promise.resolve(fallbackCopy(text));
    }

    document.querySelectorAll('.wpdt-admin').forEach(function (root) {
        createThemeControl(root);
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-wpdt-copy]');

        if (!button || button.disabled) {
            return;
        }

        var control = button.closest('.wpdt-copy-control');
        var source = control ? control.querySelector('.wpdt-copy-source') : null;
        var feedback = control ? control.querySelector('.wpdt-copy-feedback') : null;

        if (!source) {
            return;
        }

        var originalLabel = button.getAttribute('data-original-label') || button.textContent.trim();
        button.setAttribute('data-original-label', originalLabel);
        button.disabled = true;

        copyText(source.value).then(function (copied) {
            if (copied) {
                button.textContent = 'Copied';
                if (feedback) {
                    feedback.textContent = 'Copied to clipboard.';
                }
            } else {
                button.textContent = 'Copy failed';
                if (feedback) {
                    feedback.textContent = 'Could not copy automatically. Select and copy the text manually.';
                }
            }

            window.setTimeout(function () {
                button.textContent = originalLabel;
                button.disabled = false;
            }, 2200);
        }).catch(function () {
            button.textContent = 'Copy failed';
            if (feedback) {
                feedback.textContent = 'Could not copy automatically. Select and copy the text manually.';
            }

            window.setTimeout(function () {
                button.textContent = originalLabel;
                button.disabled = false;
            }, 2200);
        });
    });
}());
