/* Visual indicator for unread application notifications. */
(function () {
    'use strict';

    function markUnread(root) {
        if (!root) return;
        root.querySelectorAll('.ak-notif-item.ak-notif-unread strong').forEach(function (title) {
            if (title.querySelector('.ak-notif-unread-icon')) return;
            var icon = document.createElement('i');
            icon.className = 'fas fa-circle ak-notif-unread-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.title = 'إشعار جديد';
            title.insertBefore(icon, title.firstChild);
        });
    }

    function init() {
        var root = document.getElementById('akNotificationBell');
        if (!root) return;

        var style = document.createElement('style');
        style.textContent = '.ak-notif-unread-icon{display:inline-block;margin-inline-end:6px;color:#dc3545;font-size:.55rem;vertical-align:middle;animation:ak-notif-unread-pulse 1.5s ease-in-out infinite}@keyframes ak-notif-unread-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(.82)}}';
        document.head.appendChild(style);

        markUnread(root);
        var body = root.querySelector('.ak-notif-panel-body');
        if (body && window.MutationObserver) {
            new MutationObserver(function () { markUnread(root); }).observe(body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
