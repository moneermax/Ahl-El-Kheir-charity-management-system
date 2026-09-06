(function(){
    'use strict';
    if (!/\/modules\/hr\/attendance\.php(?:$|[?#])/.test(window.location.pathname + window.location.search)) return;

    var form = document.getElementById('bulkForm');
    var table = document.getElementById('attTable');
    var selectAll = document.getElementById('selectAll');
    var selectionBar = document.getElementById('selectionBar');
    var selectionCount = document.getElementById('selectionCount');
    if (!form || !table || !selectAll) return;

    function rows(){
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-name]'));
    }

    function items(){
        return rows()
            .filter(function(row){ return row.style.display !== 'none'; })
            .map(function(row){ return {row:row, check:row.querySelector('.row-check')}; })
            .filter(function(item){ return !!item.check; });
    }

    function onLeave(row){
        return row.getAttribute('data-status') === 'on_leave';
    }

    function refresh(){
        var list = items();
        var eligible = list.filter(function(item){ return !onLeave(item.row); });
        var selected = eligible.filter(function(item){ return item.check.checked; });

        if (selectionCount) selectionCount.textContent = selected.length + ' محدد';
        if (selectionBar) selectionBar.classList.toggle('show', selected.length > 0);
        selectAll.checked = eligible.length > 0 && selected.length === eligible.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < eligible.length;
    }

    /*
     * The page contains an older Select All change handler.  Handle the
     * change event in CAPTURE phase and stop propagation before that handler
     * can select every checkbox.  At this point the browser has already
     * toggled Select All, so its new checked state is reliable.
     */
    selectAll.addEventListener('change', function(event){
        event.stopImmediatePropagation();

        var shouldSelect = selectAll.checked;
        items().forEach(function(item){
            item.check.checked = shouldSelect && !onLeave(item.row);
        });

        refresh();
    }, true);

    /* Keep the selection counter/filter state synchronized for normal rows. */
    table.addEventListener('change', function(event){
        if (event.target && event.target.classList.contains('row-check')) refresh();
    });

    form.addEventListener('submit', function(e){
        var submitter = e.submitter || document.activeElement;
        if (!submitter || !/^bulk_/.test(submitter.value || '')) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        /* Never send an employee marked on leave to the bulk endpoint. */
        items().forEach(function(item){
            if (onLeave(item.row)) item.check.checked = false;
        });
        refresh();

        var ids = Array.prototype.map.call(
            table.querySelectorAll('.row-check:checked'),
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
            method:'POST',
            body:data,
            credentials:'same-origin',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        }).then(function(response){
            return response.json().then(function(payload){
                if (!response.ok || !payload.ok) throw new Error(payload.message || 'تعذر تنفيذ العملية.');
                return payload;
            });
        }).then(function(payload){
            var message = (payload.message || 'تم تنفيذ العملية بنجاح.') + ' عدد الموظفين: ' + payload.affected;
            if (window.Swal) return Swal.fire({icon:'success',title:'تم بنجاح',text:message,confirmButtonText:'حسناً'});
        }).then(function(){
            window.location.reload();
        }).catch(function(error){
            submitter.disabled = false;
            if (window.Swal) Swal.fire({icon:'error',title:'تعذر تنفيذ العملية',text:error.message || 'حدث خطأ غير متوقع.',confirmButtonText:'حسناً'});
            else alert(error.message || 'حدث خطأ غير متوقع.');
        });
    }, true);

    refresh();
})();
