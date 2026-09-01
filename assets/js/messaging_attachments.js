(function () {
    'use strict';

    var MAX_SIZE = 10 * 1024 * 1024;
    var ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','jpg','jpeg','png','gif','webp','zip'];
    var uploadUrl = (window.msgUrl || '') .replace(/index\.php(?:\?.*)?$/, 'attachment.php');
    if (!uploadUrl || uploadUrl === window.msgUrl) {
        uploadUrl = new URL('attachment.php', window.location.href).href;
    }

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    function bytesLabel(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function extension(name) {
        return String(name || '').split('.').pop().toLowerCase();
    }

    function iconFor(name) {
        var ext = extension(name);
        if (['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1) return 'fa-file-image';
        if (ext === 'pdf') return 'fa-file-pdf';
        if (['doc','docx'].indexOf(ext) !== -1) return 'fa-file-word';
        if (['xls','xlsx'].indexOf(ext) !== -1) return 'fa-file-excel';
        if (['ppt','pptx'].indexOf(ext) !== -1) return 'fa-file-powerpoint';
        if (ext === 'zip') return 'fa-file-zipper';
        return 'fa-file-lines';
    }

    function formFiles(form) {
        var input = form && form.querySelector('input[type="file"]');
        if (!input || !input.files) return [];
        return Array.prototype.slice.call(input.files);
    }

    function validateFiles(files) {
        if (!files.length) return '';
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var ext = extension(file.name);
            if (ALLOWED_EXT.indexOf(ext) === -1) return 'نوع الملف غير مسموح: ' + file.name;
            if (file.size <= 0) return 'الملف فارغ: ' + file.name;
            if (file.size > MAX_SIZE) return 'الحد الأقصى لحجم كل ملف هو 10 ميجابايت: ' + file.name;
        }
        return '';
    }

    function setAttachmentInputMultiple(form) {
        var input = form && form.querySelector('input[type="file"]');
        if (!input) return;
        input.multiple = true;
    }

    function renderFilePreview(form) {
        var input = form && form.querySelector('input[type="file"]');
        if (!input) return;
        var files = Array.prototype.slice.call(input.files || []);
        var row = form.querySelector('.msg-attachment-preview');
        var composeRow = form.querySelector('.msg-compose-attachment');
        var target = row || composeRow;
        if (!target) return;

        if (!files.length) {
            if (row) row.innerHTML = '';
            if (composeRow) {
                var label = composeRow.querySelector('.msg-compose-file');
                if (label) label.textContent = '';
            }
            return;
        }

        var error = validateFiles(files);
        if (error) {
            input.value = '';
            if (row) row.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0" style="font-size:.65rem">' + esc(error) + '</div>';
            if (composeRow) {
                var errLabel = composeRow.querySelector('.msg-compose-file');
                if (errLabel) errLabel.textContent = error;
            }
            return;
        }

        if (row) {
            row.innerHTML = '<div class="msg-attachment-file-list">' + files.map(function (file) {
                return '<div class="msg-attachment-selected"><i class="fas ' + iconFor(file.name) + '"></i><span>' + esc(file.name) + '</span><small>' + bytesLabel(file.size) + '</small></div>';
            }).join('') + '</div><button type="button" class="msg-attachment-remove-all" aria-label="إزالة المرفقات"><i class="fas fa-xmark"></i></button>';
            var remove = row.querySelector('.msg-attachment-remove-all');
            if (remove) remove.addEventListener('click', function () {
                input.value = '';
                renderFilePreview(form);
            });
        }

        if (composeRow) {
            var label = composeRow.querySelector('.msg-compose-file');
            if (label) label.textContent = files.length === 1 ? files[0].name + ' (' + bytesLabel(files[0].size) + ')' : files.length + ' ملفات محددة';
        }
    }

    async function uploadFiles(messageId, files, csrf) {
        var failed = [];
        for (var i = 0; i < files.length; i++) {
            var data = new FormData();
            data.append('action', 'upload_attachment');
            data.append('csrf_token', csrf || '');
            data.append('message_id', String(messageId));
            data.append('attachment', files[i], files[i].name);
            try {
                var response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: data,
                    credentials: 'same-origin'
                });
                var text = await response.text();
                var result;
                try { result = JSON.parse(text); } catch (e) { result = null; }
                if (!response.ok || !result || !result.ok) {
                    failed.push(files[i].name);
                }
            } catch (e) {
                failed.push(files[i].name);
            }
        }
        return failed;
    }

    async function sendWithAttachments(form, files) {
        var statusId = form.id === 'replyForm' ? 'replyStatus' : 'composeStatus';
        var errorId = form.id === 'replyForm' ? 'replyError' : 'composeError';
        var status = document.getElementById(statusId);
        var error = document.getElementById(errorId);
        var button = form.querySelector('button[type="submit"]');
        if (error) { error.textContent = ''; error.classList.remove('show'); }
        if (button) button.disabled = true;
        if (status) status.classList.add('show');

        try {
            var payload = new URLSearchParams(new FormData(form));
            payload.delete('attachment');
            var response = await fetch(window.msgUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload,
                credentials: 'same-origin'
            });
            var text = await response.text();
            var result;
            try { result = JSON.parse(text); } catch (e) { throw new Error('استجاب الخادم برد غير صالح.'); }
            if (!response.ok || !result.ok) throw new Error(result.message || 'تعذر إرسال الرسالة.');

            var csrf = form.querySelector('input[name="csrf_token"]')?.value || window.msgCsrf || '';
            var failed = await uploadFiles(result.id, files, csrf);

            if (form.id === 'composeForm') {
                if (window.bootstrap) bootstrap.Modal.getInstance(document.getElementById('composeModal'))?.hide();
            }

            if (failed.length) {
                var warning = 'تم إرسال الرسالة، لكن تعذر رفع: ' + failed.join('، ');
                if (error) { error.textContent = warning; error.classList.add('show'); }
                if (typeof window.toast === 'function') window.toast(warning);
                setTimeout(function () { window.location.href = window.msgUrl + '?message=' + encodeURIComponent(result.id); }, 1100);
            } else {
                if (typeof window.toast === 'function') window.toast(result.message || 'تم إرسال الرسالة بنجاح.');
                setTimeout(function () { window.location.href = window.msgUrl + '?message=' + encodeURIComponent(result.id); }, 250);
            }
        } catch (e) {
            if (error) { error.textContent = e.message || 'تعذر تنفيذ الطلب.'; error.classList.add('show'); }
            else if (typeof window.toast === 'function') window.toast(e.message || 'تعذر تنفيذ الطلب.');
            if (button) button.disabled = false;
            if (status) status.classList.remove('show');
        }
    }

    function installFormHandlers() {
        ['replyForm','composeForm'].forEach(function (id) {
            var form = document.getElementById(id);
            if (!form || form.dataset.attachmentWorkflowReady === '1') return;
            form.dataset.attachmentWorkflowReady = '1';
            setAttachmentInputMultiple(form);
            var input = form.querySelector('input[type="file"]');
            if (input) input.addEventListener('change', function () { renderFilePreview(form); });

            form.addEventListener('submit', function (event) {
                var files = formFiles(form);
                if (!files.length) return;
                var error = validateFiles(files);
                if (error) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    var box = document.getElementById(form.id === 'replyForm' ? 'replyError' : 'composeError');
                    if (box) { box.textContent = error; box.classList.add('show'); }
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                window.__akPendingMessageAttachment = null;
                sendWithAttachments(form, files);
            }, true);
        });
    }

    async function loadThreadAttachments() {
        var form = document.getElementById('replyForm');
        var messageInput = form && form.querySelector('input[name="message_id"]');
        if (!messageInput) return;
        var messageId = parseInt(messageInput.value, 10) || 0;
        if (!messageId) return;
        var thread = document.getElementById('messageThread');
        if (!thread || thread.querySelector('.msg-attachments-section')) return;

        try {
            var response = await fetch(uploadUrl + '?action=list&message_id=' + encodeURIComponent(messageId), {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            var result = await response.json();
            if (!result.ok || !Array.isArray(result.attachments) || !result.attachments.length) return;

            var section = document.createElement('section');
            section.className = 'msg-attachments-section';
            section.innerHTML = '<div class="msg-attachments-title"><span><i class="fas fa-paperclip"></i> مرفقات المحادثة</span><span>' + result.attachments.length + '</span></div><div class="msg-attachments-grid"></div>';
            var grid = section.querySelector('.msg-attachments-grid');

            result.attachments.forEach(function (item) {
                var card = document.createElement('div');
                card.className = 'msg-attachment-card';
                card.dataset.attachmentId = item.id;
                card.innerHTML = '<div class="msg-attachment-icon"><i class="fas ' + iconFor(item.original_name) + '"></i></div>'
                    + '<div class="msg-attachment-info"><strong title="' + esc(item.original_name) + '">' + esc(item.original_name) + '</strong><small>' + esc(item.size_label || bytesLabel(parseInt(item.size_bytes,10)||0)) + ' · ' + esc(item.uploader_name || '') + '</small></div>'
                    + '<div class="msg-attachment-actions"><a href="' + esc(item.download_url) + '" class="msg-attachment-download" title="تحميل" aria-label="تحميل"><i class="fas fa-download"></i></a><button type="button" class="msg-attachment-delete" title="حذف" aria-label="حذف"><i class="fas fa-trash"></i></button></div>';
                var del = card.querySelector('.msg-attachment-delete');
                del.addEventListener('click', function () { deleteAttachment(item.id, card, messageId); });
                grid.appendChild(card);
            });
            thread.appendChild(section);
        } catch (e) {
            console.warn('Messaging attachments list failed', e);
        }
    }

    async function deleteAttachment(id, card, messageId) {
        if (!window.confirm('هل أنت متأكد من حذف هذا المرفق؟')) return;
        var csrf = document.querySelector('#replyForm input[name="csrf_token"]')?.value || window.msgCsrf || '';
        var data = new URLSearchParams({action:'delete_attachment',csrf_token:csrf,attachment_id:String(id)});
        try {
            var response = await fetch(uploadUrl, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
                body:data,
                credentials:'same-origin'
            });
            var result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'تعذر حذف المرفق.');
            card.remove();
            if (typeof window.toast === 'function') window.toast(result.message || 'تم حذف المرفق.');
            var thread = document.getElementById('messageThread');
            var section = thread && thread.querySelector('.msg-attachments-section');
            var grid = section && section.querySelector('.msg-attachments-grid');
            if (grid && !grid.children.length) section.remove();
        } catch (e) {
            if (typeof window.toast === 'function') window.toast(e.message || 'تعذر حذف المرفق.');
        }
    }

    function injectStyles() {
        if (document.getElementById('messagingAttachmentStyles')) return;
        var style = document.createElement('style');
        style.id = 'messagingAttachmentStyles';
        style.textContent = `
            .msg-attachment-preview{display:flex!important;align-items:flex-start;justify-content:space-between;gap:8px;padding:7px 9px!important;background:#f4f7fb;border-bottom:1px solid #e7ebf0;min-height:0}
            .msg-attachment-file-list{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}
            .msg-attachment-selected{display:flex;align-items:center;gap:7px;min-width:0;font-size:.65rem;color:#59677a}.msg-attachment-selected i{width:18px;text-align:center;color:#6d7f96}.msg-attachment-selected span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-attachment-selected small{white-space:nowrap;color:#9aa4b2}
            .msg-attachment-remove-all{border:0;background:transparent;color:#8b96a4;width:27px;height:27px;border-radius:7px;flex:0 0 auto}.msg-attachment-remove-all:hover{background:#fff;color:#c0392b}
            .msg-compose-attachment{display:flex!important;align-items:center;gap:8px;margin-top:10px;font-size:.66rem;color:#69768a}.msg-compose-attach{border:1px solid #dfe5ed;background:#fff;color:#657286;border-radius:8px;padding:6px 9px;font-size:.66rem}.msg-compose-file{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .msg-attachments-section{max-width:740px;margin:16px auto 6px;padding:12px 14px;border:1px solid #e3e9f1;border-radius:14px;background:#fbfcfe}.msg-attachments-title{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px;font-size:.7rem;font-weight:800;color:#5d6b7d}.msg-attachments-title span:last-child{min-width:22px;height:22px;border-radius:7px;display:grid;place-items:center;background:#eaf2ff;color:#1b4d8f;font-size:.6rem}
            .msg-attachments-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.msg-attachment-card{display:flex;align-items:center;gap:9px;min-width:0;padding:9px;border:1px solid #e5eaf1;border-radius:10px;background:#fff}.msg-attachment-icon{width:32px;height:32px;flex:0 0 32px;border-radius:8px;display:grid;place-items:center;background:#eef4fb;color:#5d7594}.msg-attachment-info{min-width:0;flex:1}.msg-attachment-info strong{display:block;font-size:.66rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#455368}.msg-attachment-info small{display:block;margin-top:2px;font-size:.55rem;color:#9aa4b2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-attachment-actions{display:flex;align-items:center;gap:2px}.msg-attachment-actions a,.msg-attachment-actions button{width:27px;height:27px;border:0;border-radius:7px;display:grid;place-items:center;background:transparent;color:#788597;text-decoration:none}.msg-attachment-actions a:hover,.msg-attachment-actions button:hover{background:#edf3fb;color:#1b4d8f}.msg-attachment-actions .msg-attachment-delete:hover{color:#c0392b}
            @media(max-width:700px){.msg-attachments-grid{grid-template-columns:1fr}.msg-attachments-section{max-width:none}}
        `;
        document.head.appendChild(style);
    }

    function boot() {
        injectStyles();
        installFormHandlers();
        loadThreadAttachments();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
