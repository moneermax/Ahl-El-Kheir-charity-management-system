(function () {
    'use strict';

    if (!document.querySelector('.messages-shell')) return;
    if (typeof msgUrl === 'undefined' || typeof msgCsrf === 'undefined') return;

    const apiUrl = msgUrl.replace('index.php', 'delete_message.php');
    const placeholder = 'تم حذف هذه الرسالة';

    function getArticles() {
        return Array.from(document.querySelectorAll('.msg-root[data-message-id], .msg-bubble[data-message-id]'));
    }

    function setDeleted(article) {
        if (!article) return;
        article.classList.add('msg-deleted');
        const body = article.querySelector('.msg-body');
        if (body) body.textContent = placeholder;
        article.querySelector('.msg-attachments')?.replaceChildren();
        article.querySelector('.msg-delete-message')?.remove();
    }

    function addDeleteButton(article) {
        if (!article || article.querySelector('.msg-delete-message')) return;
        const id = Number(article.dataset.messageId || 0);
        if (!id) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'msg-delete-message';
        button.title = 'حذف الرسالة';
        button.setAttribute('aria-label', 'حذف الرسالة');
        button.innerHTML = '<i class="fas fa-trash"></i>';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            deleteMessage(id, article);
        });

        const head = article.querySelector('.msg-bubble-head');
        if (head) {
            const actions = document.createElement('span');
            actions.className = 'msg-message-actions';
            actions.appendChild(button);
            head.appendChild(actions);
        } else {
            const meta = article.querySelector('.msg-root-meta');
            if (meta) meta.appendChild(button);
            else article.prepend(button);
        }
    }

    async function confirmDelete() {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'هل أنت متأكد؟',
                text: 'سيتم حذف هذه الرسالة من المحادثة.',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف الرسالة',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                focusCancel: true
            });
            return result.isConfirmed;
        }
        return window.confirm('هل تريد حذف هذه الرسالة؟');
    }

    async function deleteMessage(id, article) {
        if (!(await confirmDelete())) return;

        const button = article?.querySelector('.msg-delete-message');
        if (button) button.disabled = true;

        try {
            const body = new URLSearchParams({
                action: 'delete_message',
                message_id: String(id),
                csrf_token: msgCsrf
            });
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body,
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'تعذر حذف الرسالة.');

            setDeleted(article);
            if (typeof toast === 'function') toast(data.message || placeholder);
        } catch (error) {
            if (button) button.disabled = false;
            if (typeof toast === 'function') toast(error.message || 'تعذر حذف الرسالة.');
            else if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'تعذر الحذف', text: error.message || 'تعذر حذف الرسالة.' });
        }
    }

    async function loadMessageState() {
        const articles = getArticles();
        if (!articles.length) return;

        const ids = articles.map(a => Number(a.dataset.messageId || 0)).filter(Boolean);
        if (!ids.length) return;

        try {
            const response = await fetch(apiUrl + '?action=state&message_ids=' + encodeURIComponent(ids.join(',')), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!response.ok || !data.ok) return;

            articles.forEach(article => {
                const state = data.messages?.[String(article.dataset.messageId)];
                if (!state) return;
                if (state.deleted) {
                    setDeleted(article);
                } else if (state.can_delete) {
                    addDeleteButton(article);
                }
            });
        } catch (error) {
            // Message deletion controls are non-critical to rendering the thread.
        }
    }

    const style = document.createElement('style');
    style.textContent = `
        .msg-delete-message{border:0;background:transparent;color:#c0392b;cursor:pointer;width:27px;height:27px;border-radius:7px;display:inline-grid;place-items:center;margin-inline-start:7px;font-size:.68rem;vertical-align:middle}
        .msg-delete-message:hover{background:#fff0ef;color:#a93226}
        .msg-delete-message:disabled{opacity:.55;cursor:wait}
        .msg-message-actions{display:inline-flex;align-items:center;margin-inline-start:auto}
        .msg-bubble-head .msg-message-actions{flex:0 0 auto}
        .msg-root-meta .msg-delete-message{margin-inline-start:8px}
        .msg-deleted{background:#f7f8fa!important;border-style:dashed!important;color:#8b95a3!important}
        .msg-deleted .msg-body{color:#8b95a3;font-style:italic}
    `;
    document.head.appendChild(style);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', loadMessageState);
    else loadMessageState();
})();
