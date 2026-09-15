/* Visual indicator for unread application notifications and menu-only clearing. */
(function () {
    'use strict';

    function getCookie(name) {
        var prefix = name + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (part.indexOf(prefix) === 0) {
                return decodeURIComponent(part.substring(prefix.length));
            }
        }
        return '';
    }

    function clearedBeforeId() {
        var value = parseInt(getCookie('ak_notif_menu_cleared_before'), 10);
        return Number.isFinite(value) ? value : 0;
    }

    function hideCleared(root) {
        if (!root) return;
        var cutoff = clearedBeforeId();
        if (cutoff <= 0) return;

        root.querySelectorAll('.ak-notif-read-form').forEach(function (form) {
            var input = form.querySelector('input[name="notification_id"]');
            var id = input ? parseInt(input.value, 10) : 0;
            if (id > 0 && id <= cutoff) {
                form.style.display = 'none';
            }
        });
    }

    function updateVisibleUnreadCount(root) {
        if (!root) return;
        var toggle = root.querySelector('.ak-notif-toggle');
        if (!toggle) return;

        var count = 0;
        root.querySelectorAll('.ak-notif-read-form').forEach(function (form) {
            if (form.style.display === 'none') return;
            var item = form.querySelector('.ak-notif-item.ak-notif-unread');
            if (item) count++;
        });

        var badge = toggle.querySelector('.ak-notif-badge');
        if (count <= 0) {
            if (badge) badge.remove();
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'ak-notif-badge';
            toggle.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : String(count);
    }

    function markUnread(root) {
        if (!root) return;
        hideCleared(root);
        root.querySelectorAll('.ak-notif-item.ak-notif-unread strong').forEach(function (title) {
            if (title.querySelector('.ak-notif-unread-icon')) return;
            var icon = document.createElement('i');
            icon.className = 'fas fa-circle ak-notif-unread-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.title = 'إشعار جديد';
            title.insertBefore(icon, title.firstChild);
        });
        updateVisibleUnreadCount(root);
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
