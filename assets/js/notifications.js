/*
 * Ahl El Kheir - Centralized notification system
 *
 * Provides one visual language for:
 * - server-side flash messages (toast notifications)
 * - application confirmations (SweetAlert2 modal)
 * - legacy inline confirm(...) handlers on forms/clickable elements
 *
 * Pages should prefer:
 *   data-confirm="Your message"
 * and should never add browser-native confirm() for new functionality.
 */
(function (window, document) {
    'use strict';

    function fallbackToast(message) {
        var node = document.createElement('div');
        node.textContent = message || '';
        node.style.cssText = [
            'position:fixed', 'top:20px', 'right:20px', 'z-index:99999',
            'padding:12px 18px', 'background:#1b4d8f', 'color:#fff',
            'border-radius:8px', 'box-shadow:0 4px 16px rgba(0,0,0,.18)',
            'font-family:Cairo, sans-serif'
        ].join(';');
        document.body.appendChild(node);
        window.setTimeout(function () {
            if (node.parentNode) node.parentNode.removeChild(node);
        }, 3000);
    }

    if (!window.Swal) {
        window.AKNotify = {
            toast: fallbackToast,
            confirm: function (message, onConfirm) {
                /* Last-resort compatibility only if the CDN is unavailable. */
                if (window.confirm(message || 'هل أنت متأكد؟') && typeof onConfirm === 'function') {
                    onConfirm();
                }
            }
        };
        return;
    }

    var Toast = window.Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
        showCloseButton: true,
        customClass: {
            popup: 'ak-swal-toast'
        },
        didOpen: function (toast) {
            toast.addEventListener('mouseenter', window.Swal.stopTimer);
            toast.addEventListener('mouseleave', window.Swal.resumeTimer);
        }
    });

    window.AKNotify = {
        toast: function (type, message) {
            var icon = type === 'danger' || type === 'error' ? 'error' : type;
            if (!['success', 'error', 'warning', 'info', 'question'].includes(icon)) {
                icon = 'info';
            }
            return Toast.fire({
                icon: icon,
                title: message || ''
            });
        },

        modal: function (options) {
            options = options || {};
            options.rtl = true;
            options.heightAuto = false;
            options.confirmButtonText = options.confirmButtonText || 'حسناً';
            options.cancelButtonText = options.cancelButtonText || 'إلغاء';
            options.reverseButtons = true;
            return window.Swal.fire(options);
        },

        confirm: function (message, onConfirm, options) {
            options = options || {};
            return window.Swal.fire({
                title: options.title || 'هل أنت متأكد؟',
                text: message || '',
                icon: options.icon || 'warning',
                showCancelButton: true,
                confirmButtonText: options.confirmButtonText || 'نعم، متابعة',
                cancelButtonText: options.cancelButtonText || 'إلغاء',
                reverseButtons: true,
                focusCancel: true,
                allowOutsideClick: false,
                rtl: true,
                heightAuto: false,
                customClass: {
                    popup: 'ak-swal-confirm',
                    confirmButton: 'ak-swal-confirm-btn',
                    cancelButton: 'ak-swal-cancel-btn'
                }
            }).then(function (result) {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm();
                }
                return result;
            });
        }
    };

    function extractConfirmMessage(source) {
        var text = source || '';
        var match = text.match(/confirm\s*\(\s*(['"])([\s\S]*?)\1\s*\)/i);
        return match ? match[2] : '';
    }

    function showConfirmation(message, proceed) {
        window.AKNotify.confirm(message, proceed);
    }

    /*
     * Preferred declarative API: data-confirm.
     * Capture phase runs before inline handlers, so it also lets us neutralize
     * legacy onsubmit="return confirm(...)" handlers without editing every page.
     */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;

        if (form.dataset.akConfirmBypass === '1') {
            delete form.dataset.akConfirmBypass;
            return;
        }

        var message = form.getAttribute('data-confirm');
        var inlineHandler = form.getAttribute('onsubmit');

        if (!message && inlineHandler && /\bconfirm\s*\(/i.test(inlineHandler)) {
            message = extractConfirmMessage(inlineHandler);
        }

        if (!message) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        /* Remove only the legacy inline confirmation. Other declarative logic
         * should use data-confirm and remain untouched. */
        if (inlineHandler && /\bconfirm\s*\(/i.test(inlineHandler)) {
            form.removeAttribute('onsubmit');
        }

        var submitter = event.submitter || null;
        showConfirmation(message, function () {
            form.dataset.akConfirmBypass = '1';
            if (typeof form.requestSubmit === 'function') {
                if (submitter && submitter.form === form) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
            } else {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    }, true);

    /* Also cover legacy onclick="return confirm(...)" on buttons/links. */
    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest
            ? event.target.closest('[data-confirm], [onclick*="confirm("]')
            : null;

        if (!target || target.dataset.akConfirmBypass === '1') {
            if (target) delete target.dataset.akConfirmBypass;
            return;
        }

        var message = target.getAttribute('data-confirm');
        var inlineHandler = target.getAttribute('onclick');

        if (!message && inlineHandler && /\bconfirm\s*\(/i.test(inlineHandler)) {
            message = extractConfirmMessage(inlineHandler);
        }

        if (!message) return;

        /* Forms are handled by submit capture above. */
        if (target.tagName === 'BUTTON' && target.type === 'submit' && target.form) return;
        if (target.tagName === 'INPUT' && target.type === 'submit' && target.form) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        if (inlineHandler && /\bconfirm\s*\(/i.test(inlineHandler)) {
            target.removeAttribute('onclick');
        }

        showConfirmation(message, function () {
            target.dataset.akConfirmBypass = '1';
            if (typeof target.click === 'function') target.click();
        });
    }, true);

    /* Convert server-side flash messages into the same toast design. */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ak-flash]').forEach(function (node) {
            var type = node.getAttribute('data-ak-flash') || 'info';
            var message = node.getAttribute('data-ak-message') || node.textContent || '';
            window.AKNotify.toast(type, message.trim());
            node.remove();
        });
    });

    /* Small project-specific visual refinements. */
    var style = document.createElement('style');
    style.textContent = '\n' +
        '.ak-swal-toast{font-family:Cairo,sans-serif;font-size:.9rem;border-radius:12px!important;}\n' +
        '.ak-swal-confirm{font-family:Cairo,sans-serif;border-radius:14px!important;}\n' +
        '.ak-swal-confirm .swal2-title{font-size:1.25rem;}\n' +
        '.ak-swal-confirm-btn,.ak-swal-cancel-btn{font-family:Cairo,sans-serif!important;border-radius:8px!important;padding:.55rem 1.2rem!important;}\n';
    document.head.appendChild(style);
})(window, document);
