(function () {
    'use strict';

    function allocationInputHtml() {
        return '<div class="col-md-3 fina-share-wrap"><label class="form-label small text-muted mb-1">حصة فينا الخير</label><input type="number" step="0.01" min="0" name="fina_share[]" class="form-control form-control-sm fina-share" value="0"><div class="form-text">0 = كل المبلغ لأهل الخير</div></div>';
    }

    function addSingleAllocation(form) {
        if (!form || form.querySelector('.fina-single-wrap')) return;
        var amount = form.querySelector('input[name="amount"]');
        if (!amount) return;
        var col = amount.closest('.col-md-4') || amount.parentElement;
        var wrap = document.createElement('div');
        wrap.className = 'col-md-4 fina-single-wrap';
        wrap.innerHTML = '<label class="form-label">حصة فينا الخير</label><input type="number" step="0.01" min="0" name="fina_share[]" class="form-control fina-share" value="0"><div class="form-text">حصة من إجمالي الدفعة. الرسوم الإدارية تُحسب على حصة أهل الخير فقط.</div>';
        col.insertAdjacentElement('afterend', wrap);
    }

    function addLineAllocation(line) {
        if (!line || line.querySelector('.fina-share-wrap')) return;
        var note = line.querySelector('.line-note-wrap');
        var div = document.createElement('div');
        div.className = 'col-md-3 fina-share-wrap';
        div.innerHTML = '<label class="form-label small text-muted mb-1">حصة فينا الخير</label><input type="number" step="0.01" min="0" name="fina_share[]" class="form-control form-control-sm fina-share" value="0"><div class="form-text">0 = كل المبلغ لأهل الخير</div>';
        if (note) note.insertAdjacentElement('afterend', div);
        else line.querySelector('.row').appendChild(div);
    }

    function syncLines() {
        document.querySelectorAll('.pay-line').forEach(addLineAllocation);
    }

    function updateVisibility() {
        var type = document.getElementById('payType');
        var form = type ? type.closest('form') : null;
        if (!type || !form) return;
        var monthly = type.value === 'monthly_sponsorship';
        if (monthly) {
            form.querySelectorAll('.fina-single-wrap').forEach(function (e) { e.remove(); });
            syncLines();
        } else {
            form.querySelectorAll('.fina-share-wrap').forEach(function (e) { e.remove(); });
            addSingleAllocation(form);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var type = document.getElementById('payType');
        var form = type ? type.closest('form') : null;
        if (!form || !type) return;
        updateVisibility();
        type.addEventListener('change', updateVisibility);
        var wrap = document.getElementById('linesWrap');
        if (wrap) new MutationObserver(syncLines).observe(wrap, { childList: true, subtree: true });

        form.addEventListener('submit', function (event) {
            var hasFina = false;
            var invalid = false;
            form.querySelectorAll('.fina-share').forEach(function (input) {
                var value = parseFloat(input.value || '0') || 0;
                var line = input.closest('.pay-line');
                var amountInput = line ? line.querySelector('.line-amount') : form.querySelector('input[name="amount"]');
                var gross = amountInput ? (parseFloat(amountInput.value || '0') || 0) : 0;
                if (value > 0) hasFina = true;
                if (value < 0 || value > gross) invalid = true;
            });
            if (invalid) {
                event.preventDefault();
                window.alert('حصة فينا الخير يجب أن تكون بين صفر وإجمالي الدفعة لكل سطر.');
                return false;
            }
            if (hasFina) {
                var sponsor = form.querySelector('input[name="sponsor_id"]');
                var sponsorship = form.querySelector('select[name="sponsorship_id"]');
                if (!sponsor || parseInt(sponsor.value || '0', 10) <= 0 || !sponsorship || parseInt(sponsorship.value || '0', 10) <= 0) {
                    event.preventDefault();
                    window.alert('تخصيص حصة لفينا الخير من شاشة التحصيل العادية يتطلب تحديد الكفيل والكفالة. للأموال الخارجية استخدم شاشة تحصيل فينا الخير.');
                    return false;
                }
            }
            return true;
        });
    });
})();