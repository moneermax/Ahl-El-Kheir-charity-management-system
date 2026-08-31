(function () {
    'use strict';

    function initReplyComposer() {
        document.querySelectorAll('.msg-reply-box').forEach(function (box) {
            if (box.dataset.toolsReady === '1') return;
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

            function addButton(icon, title, handler, extraClass) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'msg-tool' + (extraClass ? ' ' + extraClass : '');
                btn.title = title;
                btn.setAttribute('aria-label', title);
                btn.innerHTML = '<i class="' + icon + '"></i>';
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    handler();
                });
                tools.appendChild(btn);
                return btn;
            }

            function addDivider() {
                var divider = document.createElement('span');
                divider.className = 'msg-tool-divider';
                divider.setAttribute('aria-hidden', 'true');
                tools.appendChild(divider);
            }

            function restoreSelection(start, end) {
                textarea.focus();
                textarea.setSelectionRange(start, end);
            }

            function insertAtCursor(text) {
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var value = textarea.value;
                textarea.value = value.slice(0, start) + text + value.slice(end);
                var pos = start + text.length;
                restoreSelection(pos, pos);
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            function insertLinePrefix(prefix) {
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var value = textarea.value;
                var lineStart = value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
                var selected = value.slice(lineStart, end);
                var replacement = selected.split('\n').map(function (line) {
                    return prefix + line;
                }).join('\n');
                textarea.value = value.slice(0, lineStart) + replacement + value.slice(end);
                restoreSelection(lineStart + replacement.length, lineStart + replacement.length);
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            addButton('fas fa-arrow-rotate-left', 'تراجع', function () {
                textarea.focus();
                document.execCommand('undo');
            });

            addButton('fas fa-arrow-rotate-right', 'إعادة', function () {
                textarea.focus();
                document.execCommand('redo');
            });

            addDivider();

            addButton('fas fa-list-ul', 'قائمة نقطية', function () {
                insertLinePrefix('• ');
            });

            addButton('fas fa-quote-right', 'اقتباس', function () {
                insertLinePrefix('> ');
            });

            addButton('fas fa-eraser', 'مسح تنسيق النص', function () {
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                if (start === end) return;
                var selected = textarea.value.slice(start, end)
                    .replace(/^>\s?/gm, '')
                    .replace(/^•\s?/gm, '');
                textarea.value = textarea.value.slice(0, start) + selected + textarea.value.slice(end);
                restoreSelection(start, start + selected.length);
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            });

            addDivider();

            addButton('fas fa-paperclip', 'إرفاق ملف', function () {
                if (typeof window.toast === 'function') {
                    window.toast('إرفاق الملفات سيتم تفعيله في مرحلة لاحقة.');
                } else {
                    var event = new CustomEvent('ak-message-toast', {
                        detail: 'إرفاق الملفات سيتم تفعيله في مرحلة لاحقة.'
                    });
                    document.dispatchEvent(event);
                }
            });

            var emojiButton = addButton('far fa-face-smile', 'الرموز التعبيرية', function () {
                var picker = box.querySelector('.msg-emoji-picker');
                if (picker) {
                    picker.classList.toggle('open');
                    return;
                }

                picker = document.createElement('div');
                picker.className = 'msg-emoji-picker';
                picker.setAttribute('role', 'dialog');
                picker.setAttribute('aria-label', 'الرموز التعبيرية');
                picker.innerHTML = [
                    '<div class="msg-emoji-head"><span><i class="far fa-face-smile"></i> الرموز التعبيرية</span><button type="button" class="msg-emoji-close" aria-label="إغلاق"><i class="fas fa-xmark"></i></button></div>',
                    '<div class="msg-emoji-grid"></div>'
                ].join('');
                box.appendChild(picker);

                var grid = picker.querySelector('.msg-emoji-grid');
                var emojis = '😀 😃 😄 😁 😆 😅 😂 🙂 🙃 😉 😊 😇 🥰 😍 🤩 😘 😗 😚 😋 😛 😜 🤪 🤔 🤗 🤭 🤫 🤐 🤨 😐 😑 😶 🙄 😏 😣 😥 😮 😯 😪 😫 🥱 😴 😌 🤓 😎 🥳 😭 😢 😤 😠 😡 🤬 😱 😨 😰 🙏 👏 👍 👎 ❤️ 💙 💚 💛 🧡 💜 🖤 🤝 ✨ ⭐ 🔔 📌 ✅ ❌ ⚠️ 🎉'.split(' ');

                emojis.forEach(function (emoji) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'msg-emoji';
                    b.textContent = emoji;
                    b.title = 'إدراج ' + emoji;
                    b.addEventListener('click', function () {
                        insertAtCursor(emoji);
                        picker.classList.remove('open');
                    });
                    grid.appendChild(b);
                });

                picker.querySelector('.msg-emoji-close').addEventListener('click', function () {
                    picker.classList.remove('open');
                });

                picker.classList.add('open');
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
                textarea.style.height = Math.min(Math.max(textarea.scrollHeight, 82), 190) + 'px';
            });

            document.addEventListener('click', function (event) {
                var picker = box.querySelector('.msg-emoji-picker');
                if (!picker || !picker.classList.contains('open')) return;
                if (!picker.contains(event.target) && event.target !== emojiButton) {
                    picker.classList.remove('open');
                }
            });
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
            .msg-tool-divider{width:1px;height:20px;background:#e1e6ed;margin:0 4px}
            .msg-emoji-picker{position:absolute;bottom:54px;inset-inline-start:8px;width:320px;max-width:calc(100% - 16px);background:#fff;border:1px solid #dfe5ed;border-radius:14px;box-shadow:0 14px 35px rgba(15,35,65,.18);padding:9px;z-index:20;display:none}
            .msg-emoji-picker.open{display:block;animation:msgEmojiIn .12s ease-out}
            .msg-emoji-head{display:flex;align-items:center;justify-content:space-between;padding:3px 4px 8px;font-size:.68rem;font-weight:800;color:#526176;border-bottom:1px solid #edf0f4;margin-bottom:7px}
            .msg-emoji-head i{margin-inline-end:4px;color:#1b4d8f}
            .msg-emoji-close{border:0;background:transparent;color:#8792a1;width:26px;height:26px;border-radius:7px}
            .msg-emoji-close:hover{background:#f1f4f8;color:#1b4d8f}
            .msg-emoji-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:3px;max-height:190px;overflow:auto}
            .msg-emoji{border:0;background:transparent;border-radius:8px;font-size:20px;line-height:32px;height:34px;cursor:pointer}
            .msg-emoji:hover{background:#edf3fb;transform:scale(1.08)}
            @keyframes msgEmojiIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
            @media(max-width:767px){.msg-emoji-picker{width:285px;bottom:52px}.msg-emoji-grid{grid-template-columns:repeat(7,1fr)}.msg-tool-divider{margin:0 2px}}
        `;
        document.head.appendChild(style);
    }

    function boot() {
        injectStyles();
        initReplyComposer();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
