/* Search/filter helper for the long sponsorship selector on payment entry. */
(function () {
    'use strict';

    function initSponsorshipSearch() {
        var select = document.querySelector('select[name="sponsorship_id"]');
        if (!select || select.dataset.searchHelperReady === '1') return;
        select.dataset.searchHelperReady = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'ak-sponsorship-search mb-2';

        var row = document.createElement('div');
        row.className = 'input-group';

        var icon = document.createElement('span');
        icon.className = 'input-group-text';
        icon.innerHTML = '<i class="fas fa-magnifying-glass"></i>';

        var input = document.createElement('input');
        input.type = 'search';
        input.className = 'form-control';
        input.placeholder = 'ابحث باسم الكفيل أو اليتيم أو كود الكفالة...';
        input.setAttribute('aria-label', 'البحث عن الكفيل أو اليتيم أو كود الكفالة');
        input.autocomplete = 'off';

        var clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn btn-outline-secondary';
        clear.innerHTML = '<i class="fas fa-xmark"></i>';
        clear.title = 'مسح البحث';
        clear.setAttribute('aria-label', 'مسح البحث');

        row.appendChild(icon);
        row.appendChild(input);
        row.appendChild(clear);
        wrapper.appendChild(row);

        var status = document.createElement('div');
        status.className = 'form-text';
        status.setAttribute('aria-live', 'polite');
        wrapper.appendChild(status);

        select.parentNode.insertBefore(wrapper, select);

        function applyFilter() {
            var query = input.value.trim().toLocaleLowerCase();
            var visible = 0;
            var total = 0;

            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                total++;
                var text = (option.textContent || '').toLocaleLowerCase();
                var matches = query === '' || text.indexOf(query) !== -1;
                option.hidden = !matches;
                if (matches) visible++;
            });

            if (query === '') {
                status.textContent = 'يمكنك البحث باسم الكفيل أو اليتيم أو كود الكفالة.';
            } else if (visible === 0) {
                status.textContent = 'لا توجد كفالات مطابقة للبحث.';
            } else {
                status.textContent = 'تم العثور على ' + visible.toLocaleString('ar-EG') + ' من أصل ' + total.toLocaleString('ar-EG') + ' كفالة.';
            }
        }

        input.addEventListener('input', applyFilter);
        clear.addEventListener('click', function () {
            input.value = '';
            applyFilter();
            input.focus();
        });

        select.addEventListener('change', function () {
            if (select.value) {
                input.value = '';
                applyFilter();
            }
        });

        applyFilter();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSponsorshipSearch);
    } else {
        initSponsorshipSearch();
    }
})();
