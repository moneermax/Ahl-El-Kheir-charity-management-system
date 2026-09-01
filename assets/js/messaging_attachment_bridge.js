(function () {
    'use strict';

    // The reply tools create the file input inside .msg-reply-box.
    // The attachment workflow expects the input to belong to the form so
    // it can read the selected File object when the form is submitted.
    function moveReplyAttachmentInputs() {
        document.querySelectorAll('.msg-reply-box').forEach(function (box) {
            var form = box.closest('form');
            var input = box.querySelector('input.msg-attachment-input[type="file"]');
            if (!form || !input || input.form === form) return;
            form.appendChild(input);
        });
    }

    function boot() {
        moveReplyAttachmentInputs();
        // The floating conversation can be inserted/rebuilt dynamically.
        var observer = new MutationObserver(function () {
            moveReplyAttachmentInputs();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
