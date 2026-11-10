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

        var label = document.createElement('label');
        label.textContent = 'Appearance';

        var select = document.createElement('select');
        select.className = 'wpdt-theme-select';
        select.setAttribute('aria-label', 'Choose plugin appearance');

        [
            { value: 'system', label: 'System' },
            { value: 'light', label: 'Light' },
            { value: 'dark', label: 'Dark' }
        ].forEach(function (optionData) {
            var option = document.createElement('option');
            option.value = optionData.value;
            option.textContent = optionData.label;
            select.appendChild(option);
        });

        var currentTheme = getSavedTheme();
        var activeTheme = currentTheme;
        select.value = currentTheme;
        applyTheme(currentTheme, root);

        label.appendChild(select);
        control.appendChild(label);

        var settingsControl = root.querySelector('.wpdt-settings-appearance-control');
        var headerActions = header.querySelector('.wpdt-header-action');

        if (settingsControl) {
            settingsControl.appendChild(control);
        } else if (headerActions) {
            headerActions.appendChild(control);
        } else {
            header.appendChild(control);
        }

        select.addEventListener('change', function () {
            var selectedTheme = allowedThemes.indexOf(select.value) !== -1
                ? select.value
                : 'system';

            activeTheme = selectedTheme;

            try {
                window.localStorage.setItem(themeStorageKey, selectedTheme);
            } catch (error) {
                // The selected theme still applies for the current page.
            }

            applyTheme(selectedTheme, root);
        });

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
