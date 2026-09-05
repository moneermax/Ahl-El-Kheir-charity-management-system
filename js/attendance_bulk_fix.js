(function(){
    'use strict';
    if (!/\/modules\/hr\/attendance\.php(?:$|[?#])/.test(window.location.pathname + window.location.search)) return;

    var form = document.getElementById('bulkForm');
    if (!form) return;

    form.addEventListener('submit', function(e){
        var submitter = e.submitter || document.activeElement;
        if (!submitter || !/^bulk_/.test(submitter.value || '')) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var ids = Array.prototype.map.call(
            document.querySelectorAll('.att3 .row-check:checked'),
            function(cb){ return parseInt(cb.value, 10); }
        ).filter(function(id){ return id > 0; });

        ids = ids.filter(function(id, index){ return ids.indexOf(id) === index; });

        if (!ids.length) {
            if (window.Swal) {
                Swal.fire({icon:'warning',title:'لم يتم تحديد موظفين',text:'يرجى تحديد موظف واحد على الأقل.',confirmButtonText:'حسناً'});
            } else {
                alert('يرجى تحديد موظف واحد على الأقل.');
            }
            return;
        }

        var dateInput = form.querySelector('input[name="selected_date"]');
        var modeInput = form.querySelector('[name="bulk_work_mode"]');
        var data = new FormData();
        data.append('action', submitter.value);
        data.append('selected_date', dateInput ? dateInput.value : '');
        data.append('bulk_work_mode', modeInput ? modeInput.value : 'remote');
        data.append('employee_ids_json', JSON.stringify(ids));

        submitter.disabled = true;

        fetch('bulk_attendance.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {'X-Requested-With':'XMLHttpRequest'}
        }).then(function(response){
            return response.json().then(function(payload){
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'تعذر تنفيذ العملية.');
                }
                return payload;
            });
        }).then(function(payload){
            if (window.Swal) {
                return Swal.fire({
                    icon:'success',
                    title:'تم بنجاح',
                    text:(payload.message || 'تم تنفيذ العملية بنجاح.') + ' عدد الموظفين: ' + payload.affected,
                    confirmButtonText:'حسناً'
                });
            }
        }).then(function(){
            window.location.reload();
        }).catch(function(error){
            submitter.disabled = false;
            if (window.Swal) {
                Swal.fire({icon:'error',title:'تعذر تنفيذ العملية',text:error.message || 'حدث خطأ غير متوقع.',confirmButtonText:'حسناً'});
            } else {
                alert(error.message || 'حدث خطأ غير متوقع.');
            }
        });
    }, true);
})();
