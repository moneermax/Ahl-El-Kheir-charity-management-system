(function () {
    'use strict';

    var messageUrl = new URL('index.php', window.location.href).href;
    var uploadUrl = new URL('attachment.php', messageUrl).href;
    var MAX_SIZE = 10 * 1024 * 1024;
    var ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','jpg','jpeg','png','gif','webp','zip'];
    var activeSend = false;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    function bytesLabel(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function extension(name) {
        var value = String(name || '');
        var dot = value.lastIndexOf('.');
        return dot >= 0 ? value.substring(dot + 1).toLowerCase() : '';
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

    function fileInput(form) {
        return form ? form.querySelector('input[type="file"]') : null;
    }

    function filesFor(form) {
        var input = fileInput(form);
        return input && input.files ? Array.prototype.slice.call(input.files) : [];
    }

    function validate(files) {
        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            if (ALLOWED_EXT.indexOf(extension(f.name)) === -1) return 'نوع الملف غير مسموح: ' + f.name;
            if (f.size <= 0) return 'الملف فارغ: ' + f.name;
            if (f.size > MAX_SIZE) return 'الحد الأقصى لحجم كل ملف هو 10 ميجابايت: ' + f.name;
        }
        return '';
    }

    function errorBox(form) {
        return document.getElementById(form.id === 'replyForm' ? 'replyError' : 'composeError');
    }

    function statusBox(form) {
        return document.getElementById(form.id === 'replyForm' ? 'replyStatus' : 'composeStatus');
    }

    function showError(form, message) {
        var box = errorBox(form);
        if (!box) return;
        box.textContent = message || '';
        box.classList.toggle('show', !!message);
    }

    function setBusy(form, busy) {
        var button = form.querySelector('button[type="submit"]');
        var status = statusBox(form);
        if (button) button.disabled = busy;
        if (status) status.classList.toggle('show', busy);
    }

    function renderPreview(form) {
        var input = fileInput(form);
        if (!input) return;
        var files = filesFor(form);
        var label = document.getElementById(form.id === 'composeForm' ? 'composeAttachmentName' : 'replyAttachmentName');
        if (label) label.textContent = files.length === 0 ? '' : files.length === 1 ? files[0].name + ' (' + bytesLabel(files[0].size) + ')' : files.length + ' ملفات محددة';
        var row = form.querySelector('.msg-attachment-preview');
        if (!row) return;
        if (!files.length) { row.innerHTML = ''; return; }
        var problem = validate(files);
        if (problem) {
            input.value = '';
            row.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0" style="font-size:.65rem">' + esc(problem) + '</div>';
            if (label) label.textContent = problem;
            return;
        }
        row.innerHTML = '<div class="msg-attachment-file-list">' + files.map(function (f) {
            return '<div class="msg-attachment-selected"><i class="fas ' + iconFor(f.name) + '"></i><span>' + esc(f.name) + '</span><small>' + bytesLabel(f.size) + '</small></div>';
        }).join('') + '</div>';
    }

    async function jsonResponse(response, endpoint) {
        var text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error(endpoint + ' returned non-JSON:', text);
            throw new Error('استجاب الخادم برد غير صالح.');
        }
    }

    async function uploadFiles(messageId, files, csrf) {
        var failed = [];
        for (var i = 0; i < files.length; i++) {
            var fd = new FormData();
            fd.append('action', 'upload_attachment');
            fd.append('csrf_token', csrf);
            fd.append('message_id', String(messageId));
            fd.append('attachment', files[i], files[i].name);
            try {
                var response = await fetch(uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd
                });
                var result = await jsonResponse(response, 'attachment.php');
                if (!response.ok || !result.ok) failed.push(files[i].name);
            } catch (e) {
                console.error('Attachment upload failed:', e);
                failed.push(files[i].name);
            }
        }
        return failed;
    }

    async function sendWithAttachments(form, files) {
        if (activeSend) return;
        activeSend = true;
        setBusy(form, true);
        showError(form, '');

        try {
            var payload = new URLSearchParams();
            var formData = new FormData(form);
            formData.forEach(function (value, key) {
                if (key !== 'attachment') payload.append(key, value);
            });

            var response = await fetch(messageUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            });
            var result = await jsonResponse(response, 'index.php');
            if (!response.ok || !result.ok || !result.id) throw new Error(result.message || 'تعذر إرسال الرسالة.');

            var csrfEl = form.querySelector('input[name="csrf_token"]');
            var csrf = csrfEl ? csrfEl.value : '';
            var failed = await uploadFiles(result.id, files, csrf);

            if (failed.length) {
                var warning = 'تم إرسال الرسالة، لكن تعذر رفع: ' + failed.join('، ');
                showError(form, warning);
                if (typeof window.toast === 'function') window.toast(warning);
                setBusy(form, false);
                activeSend = false;
                return;
            }

            if (form.id === 'composeForm' && window.bootstrap) {
                var modal = document.getElementById('composeModal');
                var instance = modal ? bootstrap.Modal.getInstance(modal) : null;
                if (instance) instance.hide();
            }
            if (typeof window.toast === 'function') window.toast('تم إرسال الرسالة والمرفق بنجاح.');
            window.location.href = messageUrl + '?message=' + encodeURIComponent(result.id);
        } catch (e) {
            console.error('Messaging attachment send failed:', e);
            showError(form, e.message || 'تعذر تنفيذ الطلب.');
            setBusy(form, false);
            activeSend = false;
        }
    }

    function intercept(event, form) {
        if (!form || (form.id !== 'replyForm' && form.id !== 'composeForm')) return;
        var files = filesFor(form);
        if (!files.length) return;
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        var problem = validate(files);
        if (problem) showError(form, problem);
        else sendWithAttachments(form, files);
    }

    function bindForm(form) {
        var input = fileInput(form);
        if (!input || input.dataset.messagingAttachmentBound === '1') return;
        input.dataset.messagingAttachmentBound = '1';
        input.multiple = true;
        input.addEventListener('change', function () { renderPreview(form); });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form') : event.target;
        intercept(event, form);
    }, true);

    document.addEventListener('click', function (event) {
        var submit = event.target && event.target.closest ? event.target.closest('button[type="submit"]') : null;
        if (!submit) return;
        var form = submit.form || (submit.closest ? submit.closest('form') : null);
        if (!form) return;
        var files = filesFor(form);
        if (!files.length || (form.id !== 'replyForm' && form.id !== 'composeForm')) return;
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        var problem = validate(files);
        if (problem) showError(form, problem);
        else sendWithAttachments(form, files);
    }, true);

    function scan() {
        bindForm(document.getElementById('replyForm'));
        bindForm(document.getElementById('composeForm'));
    }

    async function loadThreadAttachments() {
        var reply = document.getElementById('replyForm');
        var idInput = reply && reply.querySelector('input[name="message_id"]');
        var thread = document.getElementById('messageThread');
        if (!idInput || !thread || thread.querySelector('.msg-attachments-section')) return;
        var messageId = parseInt(idInput.value, 10) || 0;
        if (!messageId) return;
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
                card.innerHTML = '<div class="msg-attachment-icon"><i class="fas ' + iconFor(item.original_name) + '"></i></div><div class="msg-attachment-info"><strong title="' + esc(item.original_name) + '">' + esc(item.original_name) + '</strong><small>' + esc(item.size_label || bytesLabel(item.size_bytes)) + ' · ' + esc(item.uploader_name || '') + '</small></div><div class="msg-attachment-actions"><a href="' + esc(item.download_url) + '" title="تحميل"><i class="fas fa-download"></i></a><button type="button" title="حذف"><i class="fas fa-trash"></i></button></div>';
                card.querySelector('button').addEventListener('click', function () { deleteAttachment(item.id, card); });
                grid.appendChild(card);
            });
            thread.appendChild(section);
        } catch (e) {
            console.warn('Messaging attachments list failed', e);
        }
    }

    async function deleteAttachment(id, card) {
        if (!window.confirm('هل أنت متأكد من حذف هذا المرفق؟')) return;
        var csrfEl = document.querySelector('#replyForm input[name="csrf_token"]') || document.querySelector('#composeForm input[name="csrf_token"]');
        var csrf = csrfEl ? csrfEl.value : '';
        try {
            var response = await fetch(uploadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
                body: new URLSearchParams({action:'delete_attachment',csrf_token:csrf,attachment_id:String(id)})
            });
            var result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'تعذر حذف المرفق.');
            card.remove();
            if (typeof window.toast === 'function') window.toast(result.message || 'تم حذف المرفق.');
        } catch (e) {
            if (typeof window.toast === 'function') window.toast(e.message || 'تعذر حذف المرفق.');
        }
    }

    function injectStyles() {
        if (document.getElementById('messagingAttachmentStyles')) return;
        var style = document.createElement('style');
        style.id = 'messagingAttachmentStyles';
        style.textContent = '.msg-attachment-preview{display:flex!important;align-items:flex-start;gap:8px;padding:7px 9px!important;background:#f4f7fb;border-bottom:1px solid #e7ebf0}.msg-attachment-file-list{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}.msg-attachment-selected{display:flex;align-items:center;gap:7px;font-size:.65rem;color:#59677a}.msg-attachment-selected i{width:18px;text-align:center;color:#6d7f96}.msg-attachment-selected span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-attachment-selected small{color:#9aa4b2;white-space:nowrap}.msg-attachments-section{max-width:740px;margin:16px auto 6px;padding:12px 14px;border:1px solid #e3e9f1;border-radius:14px;background:#fbfcfe}.msg-attachments-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px;font-size:.7rem;font-weight:800;color:#5d6b7d}.msg-attachments-title span:last-child{min-width:22px;height:22px;border-radius:7px;display:grid;place-items:center;background:#eaf2ff;color:#1b4d8f;font-size:.6rem}.msg-attachments-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.msg-attachment-card{display:flex;align-items:center;gap:9px;min-width:0;padding:9px 10px;border:1px solid #e5eaf1;border-radius:10px;background:#fff}.msg-attachment-icon{width:30px;height:30px;display:grid;place-items:center;border-radius:8px;background:#eef4fb;color:#1b4d8f;flex:0 0 30px}.msg-attachment-info{min-width:0;flex:1;display:flex;flex-direction:column;gap:2px}.msg-attachment-info strong{font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-attachment-info small{font-size:.58rem;color:#8b96a4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-attachment-actions{display:flex;align-items:center;gap:3px}.msg-attachment-actions a,.msg-attachment-actions button{width:28px;height:28px;border:0;background:transparent;border-radius:7px;display:grid;place-items:center;color:#6f7c8e;text-decoration:none}.msg-attachment-actions a:hover{background:#eaf2ff;color:#1b4d8f}.msg-attachment-actions button:hover{background:#fff0f0;color:#c0392b}@media(max-width:767px){.msg-attachments-grid{grid-template-columns:1fr}}';
        document.head.appendChild(style);
    }

    function init() {
        scan();
        injectStyles();
        loadThreadAttachments();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    new MutationObserver(scan).observe(document.documentElement, {childList:true, subtree:true});
})();
