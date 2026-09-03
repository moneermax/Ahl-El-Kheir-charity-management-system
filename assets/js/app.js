document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // Global confirmation handler for supervisor lifecycle actions.
    // Prefer data-confirm on forms/links. The legacy inline confirm() fallback
    // below keeps existing supervisor actions working while they are migrated.
    if (typeof Swal === 'undefined') return;

    const swalConfirm = function (message, options) {
        options = options || {};
        return Swal.fire({
            title: options.title || 'تأكيد العملية',
            text: message || 'هل أنت متأكد من تنفيذ هذه العملية؟',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'نعم، متابعة',
            cancelButtonText: options.cancelText || 'إلغاء',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false
        });
    };

    // Preferred pattern: <form data-confirm="...">.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form || !form.matches('form[data-confirm]') || form.dataset.confirming === '1') return;

        event.preventDefault();
        event.stopImmediatePropagation();

        const message = form.getAttribute('data-confirm') || '';
        swalConfirm(message, {
            title: form.getAttribute('data-confirm-title') || 'تأكيد العملية',
            confirmText: form.getAttribute('data-confirm-ok') || 'نعم، متابعة',
            cancelText: form.getAttribute('data-confirm-cancel') || 'إلغاء'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            form.dataset.confirming = '1';
            form.submit();
        });
    }, true);

    // Preferred pattern for standalone links/buttons.
    document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-confirm]:not(form)');
        if (!target || target.dataset.confirming === '1') return;

        // A submit button inside a data-confirm form is handled by the form listener.
        if (target.form && target.form.matches('form[data-confirm]')) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        swalConfirm(target.getAttribute('data-confirm'), {
            title: target.getAttribute('data-confirm-title') || 'تأكيد العملية',
            confirmText: target.getAttribute('data-confirm-ok') || 'نعم، متابعة',
            cancelText: target.getAttribute('data-confirm-cancel') || 'إلغاء'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            target.dataset.confirming = '1';
            if (target.tagName === 'A') {
                window.location.href = target.href;
            } else {
                target.click();
            }
            delete target.dataset.confirming;
        });
    }, true);

    // Backward-compatible bridge for existing supervisor buttons that still use
    // onclick="return confirm(...)". This replaces the browser dialog globally
    // without requiring every supervisor page to be fixed separately.
    document.addEventListener('click', function (event) {
        const target = event.target.closest('[onclick*="confirm("]');
        if (!target || target.dataset.confirming === '1') return;

        const inlineHandler = target.getAttribute('onclick') || '';
        const match = inlineHandler.match(/confirm\((['"])([\s\S]*?)\1\)/);
        if (!match) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        target.removeAttribute('onclick');
        swalConfirm(match[2], {
            title: 'تأكيد العملية',
            confirmText: 'نعم، متابعة',
            cancelText: 'إلغاء'
        }).then(function (result) {
            if (result.isConfirmed) {
                target.dataset.confirming = '1';
                target.click();
                delete target.dataset.confirming;
            }
            target.setAttribute('onclick', inlineHandler);
        });
    }, true);
});
