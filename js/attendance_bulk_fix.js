(function(){
    'use strict';
    if (!/\/modules\/hr\/attendance\.php(?:$|[?#])/.test(window.location.pathname + window.location.search)) return;

    var form = document.getElementById('bulkForm');
    if (!form) return;

    var selectAll = document.getElementById('selectAll');
    var table = document.getElementById('attTable');
    var selectionBar = document.getElementById('selectionBar');
    var selectionCount = document.getElementById('selectionCount');

    function getRows(){
        return table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-name]')) : [];
    }

    function isOnLeave(row){
        return !!(row && row.getAttribute('data-status') === 'on_leave');
    }

    function getVisibleChecks(){
        return getRows()
            .filter(function(row){ return row.style.display !== 'none'; })
            .map(function(row){ return {row:row, check:row.querySelector('.row-check')}; })
            .filter(function(item){ return !!item.check; });
    }

    function refreshSelectionState(){
        var items = getVisibleChecks();
        var selected = items.filter(function(item){ return item.check.checked; });
        if (selectionCount) selectionCount.textContent = selected.length + ' محدد';
        if (selectionBar) selectionBar.classList.toggle('show', selected.length > 0);

        if (selectAll) {
            var eligible = items.filter(function(item){ return !isOnLeave(item.row); });
            var selectedEligible = eligible.filter(function(item){ return item.check.checked; });
            selectAll.checked = eligible.length > 0 && selectedEligible.length === eligible.length;
            selectAll.indeterminate = selectedEligible.length > 0 && selectedEligible.length < eligible.length;
        }
    }

    /*
     * Design 3: take complete control of Select All at the click level.
     * The attendance page has its own older Select All/change handlers, so
     * intercepting the click in capture phase prevents those handlers from
     * selecting employees who are already marked on leave.
     */
    if (selectAll) {
        selectAll.addEventListener('click', function(event){
            event.preventDefault();
            event.stopImmediatePropagation();

            var shouldSelect = !selectAll.checked;
            getVisibleChecks().forEach(function(item){
                item.check.checked = shouldSelect && !isOnLeave(item.row);
            });
            refreshSelectionState();
        }, true);
    }

    form.addEventListener('submit', function(e){
        var submitter = e.submitter || document.activeElement;
        if (!submitter || !/^bulk_/.test(submitter.value || '')) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        /* Final client-side guard: never submit an employee marked on leave. */
        getVisibleChecks().forEach(function(item){
            if (isOnLeave(item.row)) item.check.checked = false;
        });
        refreshSelectionState();

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
