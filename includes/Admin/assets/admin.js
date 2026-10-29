(function () {
    'use strict';

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
