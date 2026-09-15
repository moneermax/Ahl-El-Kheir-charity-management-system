document.addEventListener('DOMContentLoaded', function () {
    var detModal = document.getElementById('detailsModal');
    if (!detModal) return;

    var statusLabels = {
        posted: 'مرحّلة',
        pending_fm_review: 'بانتظار المدير المالي',
        returned: 'مُعادة للتعديل',
        cancelled: 'ملغاة',
        pending: 'بانتظار المدير المالي'
    };

    detModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) return;
        var rawStatus = button.getAttribute('data-status') || '';
        var statusElement = document.getElementById('det-status');
        if (statusElement) {
            statusElement.textContent = statusLabels[rawStatus] || rawStatus;
        }
    });
});
