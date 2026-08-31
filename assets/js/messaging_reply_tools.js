(function () {
    'use strict';

    var EMOJIS = ['😀','😃','😄','😁','😆','😅','😂','🤣','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😋','😛','😜','🤪','🤔','🤗','🤭','🤫','🤐','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','😯','😪','😫','🥱','😴','😌','🤓','😎','🥳','😭','😢','😤','😠','😡','🤬','😱','😨','😰','🙏','👏','👍','👎','❤️','💙','💚','💛','🧡','💜','🖤','🤝','✨','⭐','🔔','📌','✅','❌','⚠️','🎉','🌷','🌟','💡','🔥','💬'];

    function insertAtCursor(textarea, text) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var value = textarea.value;
        textarea.value = value.slice(0, start) + text + value.slice(end);
        var pos = start + text.length;
        textarea.focus();
        textarea.setSelectionRange(pos, pos);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function focusAndRestore(textarea, start, end) {
        textarea.focus();
        textarea.setSelectionRange(start, end);
    }

    function insertLinePrefix(textarea, prefix) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var value = textarea.value;
        var lineStart = value.lastIndexOf('\n', start - 1) + 1;
        var selected = value.slice(lineStart, end);
        var replacement = selected.split('\n').map(function (line) { return prefix + line; }).join('\n');
        textarea.value = value.slice(0, lineStart) + replacement + value.slice(end);
        focusAndRestore(textarea, lineStart + replacement.length, lineStart + replacement.length);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function buildEmojiPicker(box, emojiButton, textarea) {
        var existing = box.querySelector('.msg-emoji-picker');
        if (existing) {
            existing.classList.toggle('open');
            return existing;
        }

        var picker = document.createElement('div');
        picker.className = 'msg-emoji-picker';
        picker.setAttribute('role', 'dialog');
        picker.setAttribute('aria-label', 'الرموز التعبيرية');
        picker.innerHTML = '<div class="msg-emoji-head"><span><i class="far fa-face-smile"></i> الرموز التعبيرية</span><button type="button" class="msg-emoji-close" aria-label="إغلاق"><i class="fas fa-xmark"></i></button></div><div class="msg-emoji-grid"></div>';
        box.appendChild(picker);

        var grid = picker.querySelector('.msg-emoji-grid');
        EMOJIS.forEach(function (emoji) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'msg-emoji';
            button.textContent = emoji;
            button.setAttribute('aria-label', 'إدراج ' + emoji);
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
            });
            button.addEventListener('click', function (event) {
                event.preventDefault();
                insertAtCursor(textarea, emoji);
                picker.classList.remove('open');
            });
            grid.appendChild(button);
        });

        picker.querySelector('.msg-emoji-close').addEventListener('click', function () {
            picker.classList.remove('open');
            textarea.focus();
        });

        picker.classList.add('open');
        return picker;
    }

    function addButton(tools, icon, title, handler) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'msg-tool';
        btn.title = title;
        btn.setAttribute('aria-label', title);
        btn.innerHTML = '<i class="' + icon + '"></i>';
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            handler(btn);
        });
        tools.appendChild(btn);
        return btn;
    }

    function initComposer(box) {
        if (!box || box.dataset.toolsReady === '1') return;
        var textarea = box.querySelector('textarea[name="body"]');
        var toolbar = box.querySelector('.msg-reply-tools');
        if (!textarea || !toolbar) return;
        box.dataset.toolsReady = '1';

        var tools = toolbar.querySelector('.msg-tools');
        if (!tools) {
            tools = document.createElement('div');
            tools.className = 'msg-tools';
            toolbar.insertBefore(tools, toolbar.firstChild);
        }
        tools.innerHTML = '';

        addButton(tools, 'fas fa-arrow-rotate-left', 'تراجع', function () { document.execCommand('undo'); textarea.focus(); });
        addButton(tools, 'fas fa-arrow-rotate-right', 'إعادة', function () { document.execCommand('redo'); textarea.focus(); });
        addButton(tools, 'fas fa-list-ul', 'قائمة نقطية', function () { insertLinePrefix(textarea, '• '); });
        addButton(tools, 'fas fa-quote-right', 'اقتباس', function () { insertLinePrefix(textarea, '> '); });
        addButton(tools, 'fas fa-eraser', 'مسح تنسيق النص', function () {
            var start = textarea.selectionStart || 0, end = textarea.selectionEnd || 0;
            if (start === end) return;
            var selected = textarea.value.slice(start, end).replace(/\*\*(.*?)\*\*/g, '$1').replace(/__(.*?)__/g, '$1').replace(/^>\s?/gm, '').replace(/^•\s?/gm, '');
            textarea.value = textarea.value.slice(0, start) + selected + textarea.value.slice(end);
            focusAndRestore(textarea, start, start + selected.length);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.className = 'd-none msg-attachment-input';
        fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip';
        box.appendChild(fileInput);

        addButton(tools, 'fas fa-paperclip', 'إرفاق ملف', function () { fileInput.click(); });
        addButton(tools, 'far fa-face-smile', 'الرموز التعبيرية', function (button) { buildEmojiPicker(box, button, textarea); });

        var attachment = document.createElement('div');
        attachment.className = 'msg-attachment-preview';
        attachment.setAttribute('aria-live', 'polite');
        box.insertBefore(attachment, toolbar);

        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                attachment.innerHTML = '';
                window.__akPendingMessageAttachment = null;
                return;
            }
            var max = 10 * 1024 * 1024;
            if (file.size > max) {
                fileInput.value = '';
                window.__akPendingMessageAttachment = null;
                attachment.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0" style="font-size:.65rem">الحد الأقصى لحجم الملف هو 10 ميجابايت.</div>';
                return;
            }
            window.__akPendingMessageAttachment = file;
            attachment.innerHTML = '<span><i class="fas fa-paperclip"></i> ' + escapeHtml(file.name) + ' <small>(' + formatBytes(file.size) + ')</small></span><button type="button" class="msg-attachment-remove" aria-label="إزالة الملف"><i class="fas fa-xmark"></i></button>';
            attachment.querySelector('.msg-attachment-remove').addEventListener('click', function () {
                fileInput.value = '';
                window.__akPendingMessageAttachment = null;
                attachment.innerHTML = '';
            });
        });

        textarea.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'enter') {
                var form = textarea.closest('form');
                if (form) form.requestSubmit();
            }
            if (event.key === 'Escape') {
                var picker = box.querySelector('.msg-emoji-picker');
                if (picker) picker.classList.remove('open');
            }
        });

        textarea.addEventListener('input', function () {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 190) + 'px';
        });
    }

    function initComposeAttachment() {
        var form = document.getElementById('composeForm');
        if (!form || form.dataset.attachmentReady === '1') return;
        form.dataset.attachmentReady = '1';
        var footer = form.querySelector('.compose-footer-actions');
        if (!footer) return;
        var input = document.createElement('input');
        input.type = 'file';
        input.className = 'd-none';
        input.accept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip';
        form.appendChild(input);
        var area = form.querySelector('.modal-body');
        var row = document.createElement('div');
        row.className = 'msg-compose-attachment';
        row.innerHTML = '<button type="button" class="msg-compose-attach"><i class="fas fa-paperclip"></i> إرفاق ملف</button><span class="msg-compose-file"></span>';
        area.appendChild(row);
        row.querySelector('button').addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { window.__akPendingMessageAttachment = null; row.querySelector('.msg-compose-file').textContent = ''; return; }
            if (file.size > 10 * 1024 * 1024) { input.value=''; window.__akPendingMessageAttachment=null; row.querySelector('.msg-compose-file').textContent='الحد الأقصى 10 ميجابايت'; return; }
            window.__akPendingMessageAttachment = file;
            row.querySelector('.msg-compose-file').textContent = file.name + ' (' + formatBytes(file.size) + ')';
        });
    }

    function installFetchAttachmentBridge() {
        if (window.__akMessagingFetchBridgeInstalled) return;
        window.__akMessagingFetchBridgeInstalled = true;
        var originalFetch = window.fetch.bind(window);
        window.fetch = async function (input, init) {
            var response = await originalFetch(input, init);
            try {
                var url = typeof input === 'string' ? input : input.url;
                var body = init && init.body;
                var isMessagePost = url && url.indexOf('/modules/messages/index.php') !== -1 && body instanceof URLSearchParams;
                if (!isMessagePost) return response;
                var action = body.get('action');
                if ((action !== 'send' && action !== 'reply') || !window.__akPendingMessageAttachment) return response;
                var clone = response.clone();
                var data = JSON.parse(await clone.text());
                if (!data.ok || !data.id) return response;
                var file = window.__akPendingMessageAttachment;
                window.__akPendingMessageAttachment = null;
                var upload = new FormData();
                upload.append('action', 'upload_attachment');
                upload.append('csrf_token', body.get('csrf_token') || '');
                upload.append('message_id', String(data.id));
                upload.append('attachment', file, file.name);
                var uploadResponse = await originalFetch(url, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:upload, credentials:'same-origin' });
                if (!uploadResponse.ok) window.__akAttachmentUploadFailed = true;
            } catch (e) {
                window.__akAttachmentUploadFailed = true;
            }
            return response;
        };
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; });
    }
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function scrollThreadToBottom() {
        document.querySelectorAll('.msg-thread').forEach(function (thread) {
            thread.scrollTop = thread.scrollHeight;
            requestAnimationFrame(function () { thread.scrollTop = thread.scrollHeight; });
            setTimeout(function () { thread.scrollTop = thread.scrollHeight; }, 80);
        });
    }

    function injectStyles() {
        if (document.getElementById('messagingReplyToolsStyles')) return;
        var style = document.createElement('style');
        style.id = 'messagingReplyToolsStyles';
        style.textContent = `
            .msg-reply-box{position:relative}
            .msg-tools{display:flex;align-items:center;gap:2px;flex-wrap:wrap}
            .msg-tool{position:relative}
            .msg-tool:active{transform:translateY(1px)}
            .msg-emoji-picker{position:absolute;bottom:52px;inset-inline-start:8px;width:380px;max-width:calc(100% - 16px);background:#fff;border:1px solid #d8e1ec;border-radius:15px;box-shadow:0 18px 45px rgba(15,35,65,.22);padding:10px;z-index:2000;display:none}
            .msg-emoji-picker.open{display:block;animation:msgEmojiIn .12s ease-out}
            .msg-emoji-head{display:flex;align-items:center;justify-content:space-between;padding:3px 5px 9px;font-size:.7rem;font-weight:800;color:#526176;border-bottom:1px solid #edf0f4;margin-bottom:8px}
            .msg-emoji-close{border:0;background:transparent;color:#8792a1;width:28px;height:28px;border-radius:7px}
            .msg-emoji-close:hover{background:#f1f4f8;color:#1b4d8f}
            .msg-emoji-grid{display:grid;grid-template-columns:repeat(9,1fr);gap:3px;max-height:285px;overflow-y:auto;overflow-x:hidden;padding:2px}
            .msg-emoji{border:0;background:transparent;border-radius:8px;font-size:21px;line-height:34px;height:36px;cursor:pointer}
            .msg-emoji:hover{background:#edf3fb;transform:scale(1.08)}
            .msg-attachment-preview{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:5px 9px;font-size:.65rem;color:#59677a;background:#f4f7fb;border-bottom:1px solid #e7ebf0;min-height:0}
            .msg-attachment-preview:empty{display:none}
            .msg-attachment-preview span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .msg-attachment-remove{border:0;background:transparent;color:#8b96a4;width:26px;height:26px;border-radius:7px;flex:0 0 auto}.msg-attachment-remove:hover{background:#fff;color:#c0392b}
            .msg-compose-attachment{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:.66rem;color:#69768a}.msg-compose-attach{border:1px solid #dfe5ed;background:#fff;color:#657286;border-radius:8px;padding:6px 9px;font-size:.66rem}.msg-compose-attach:hover{background:#f2f6fb;color:#1b4d8f}.msg-compose-file{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            @keyframes msgEmojiIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
            @media(max-width:767px){.msg-emoji-picker{width:320px;bottom:50px}.msg-emoji-grid{grid-template-columns:repeat(8,1fr);max-height:260px}}
        `;
        document.head.appendChild(style);
    }

    function boot() {
        injectStyles();
        document.querySelectorAll('.msg-reply-box').forEach(initComposer);
        initComposeAttachment();
        installFetchAttachmentBridge();
        scrollThreadToBottom();

        document.addEventListener('click', function (event) {
            document.querySelectorAll('.msg-emoji-picker.open').forEach(function (picker) {
                var box = picker.closest('.msg-reply-box');
                var button = box && box.querySelector('.msg-tool[aria-label="الرموز التعبيرية"]');
                if (!picker.contains(event.target) && !(button && button.contains(event.target))) picker.classList.remove('open');
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();