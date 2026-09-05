document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // Render Sudan flags with CSS so the result never depends on the browser's emoji/font support.
    const flagStyle = document.createElement('style');
    flagStyle.textContent = '.ak-sudan-flag{display:inline-block;width:1.35em;height:.9em;min-width:1.35em;vertical-align:-.12em;position:relative;overflow:hidden;border-radius:.08em;background:linear-gradient(to bottom,#d71920 0 33.333%,#fff 33.333% 66.666%,#000 66.666% 100%);box-shadow:0 0 0 1px rgba(0,0,0,.12);margin-inline-end:.3em}.ak-sudan-flag::before{content:"";position:absolute;inset:0 auto 0 0;width:42%;background:#087a3b;clip-path:polygon(0 0,100% 50%,0 100%)}';
    document.head.appendChild(flagStyle);

    document.querySelectorAll('.org-flag').forEach(function (element) {
        element.textContent = '';
        element.classList.add('ak-sudan-flag');
        element.setAttribute('role', 'img');
        element.setAttribute('aria-label', 'Sudan flag');
    });

    // Also replace any standalone Unicode Sudan flag used elsewhere in the shared layout.
    const FLAG_MARKER = '\ud83c\udde8\ud83c\udde9';
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
            if (!node.nodeValue || !node.nodeValue.includes(FLAG_MARKER)) return NodeFilter.FILTER_REJECT;
            const parent = node.parentElement;
            if (parent && /^(SCRIPT|STYLE|TEXTAREA)$/i.test(parent.tagName)) return NodeFilter.FILTER_REJECT;
            if (parent && parent.classList.contains('ak-sudan-flag')) return NodeFilter.FILTER_REJECT;
            return NodeFilter.FILTER_ACCEPT;
        }
    });

    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    nodes.forEach(function (node) {
        const parts = node.nodeValue.split(FLAG_MARKER);
        if (parts.length < 2) return;

        const fragment = document.createDocumentFragment();
        parts.forEach(function (part, index) {
            if (part) fragment.appendChild(document.createTextNode(part));
            if (index < parts.length - 1) {
                const flag = document.createElement('span');
                flag.className = 'ak-sudan-flag';
                flag.setAttribute('role', 'img');
                flag.setAttribute('aria-label', 'Sudan flag');
                fragment.appendChild(flag);
            }
        });
        node.parentNode.replaceChild(fragment, node);
    });

    /*
     * Global search redesign
     * -----------------------
     * The header no longer exposes search inputs. The existing search engine
     * remains the single source of truth at modules/search/index.php; this
     * layer only turns the header into a compact Search Center entry point and
     * upgrades the search page UI without changing its authorization/query logic.
     */
    const searchBar = document.querySelector('.ak-search-bar-wrap');
    const searchForm = document.getElementById('globalSearchForm');
    const searchPage = /\/modules\/search\/index\.php(?:$|[?#])/.test(window.location.href);

    if (searchBar) {
        searchBar.style.display = 'none';
    }

    if (searchForm) {
        const searchUrl = searchForm.getAttribute('action');
        const userControls = document.querySelector('.qa-user-controls');

        if (userControls && searchUrl && !document.getElementById('akGlobalSearchButton')) {
            const button = document.createElement('a');
            button.id = 'akGlobalSearchButton';
            button.href = searchUrl;
            button.className = 'qa-btn ak-global-search-btn';
            button.innerHTML = '<i class="fas fa-search"></i><span class="ak-search-button-label"></span>';
            button.querySelector('.ak-search-button-label').textContent =
                document.documentElement.lang === 'en' ? 'Search' : 'بحث';
            button.style.cssText = 'background:#2e63a8;border-color:rgba(255,255,255,.3);';
            userControls.insertBefore(button, userControls.firstChild);
        }
    }

    if (searchPage) {
        initSearchCenter();
    }
});

function initSearchCenter() {
    const existingWelcome = document.querySelector('.welcome-section');
    const existingFilter = document.getElementById('filterForm');
    const content = document.querySelector('.content');
    const globalForm = document.getElementById('globalSearchForm');

    if (!content || !existingWelcome || !globalForm || document.getElementById('akSearchCenter')) {
        return;
    }

    const lang = document.documentElement.lang === 'en' ? 'en' : 'ar';
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q') || '';
    const type = params.get('type') || 'all';
    const status = params.get('status') || '';
    const month = params.get('month') || '';

    const text = {
        ar: {
            title: 'مركز البحث',
            subtitle: 'ابحث في بيانات النظام من مكان واحد، ثم ضيّق النتائج باستخدام الفلاتر المتقدمة.',
            placeholder: 'ابحث بالاسم، الكود، رقم الهاتف أو أي معلومة...',
            scope: 'نطاق البحث',
            all: 'كل البيانات',
            families: 'الأسر والأيتام',
            sponsors: 'الكفلاء',
            sponsorships: 'الكفالات',
            payments: 'الدفعات الشهرية',
            advanced: 'خيارات متقدمة',
            hideAdvanced: 'إخفاء الخيارات المتقدمة',
            status: 'الحالة',
            allStatuses: 'كل الحالات',
            month: 'الشهر',
            search: 'بحث',
            reset: 'إعادة ضبط',
            activeFilters: 'الفلاتر النشطة',
            remove: 'إزالة',
            results: 'النتائج',
            close: 'إغلاق',
            hint: 'يمكنك البحث بالاسم أو الرقم أو الهاتف أو رمز السجل.'
        },
        en: {
            title: 'Search Center',
            subtitle: 'Search system data from one place, then narrow the results with advanced filters.',
            placeholder: 'Search by name, code, phone number, or any detail...',
            scope: 'Search scope',
            all: 'All data',
            families: 'Families & Orphans',
            sponsors: 'Sponsors',
            sponsorships: 'Sponsorships',
            payments: 'Monthly Payments',
            advanced: 'Advanced filters',
            hideAdvanced: 'Hide advanced filters',
            status: 'Status',
            allStatuses: 'All statuses',
            month: 'Month',
            search: 'Search',
            reset: 'Reset',
            activeFilters: 'Active filters',
            remove: 'Remove',
            results: 'Results',
            close: 'Close',
            hint: 'Search by name, number, phone, or record code.'
        }
    }[lang];

    const allowedTypes = [];
    const sourceTypeSelect = document.getElementById('searchType');
    if (sourceTypeSelect) {
        Array.from(sourceTypeSelect.options).forEach(function (option) {
            if (!option.hidden && option.value !== '') {
                allowedTypes.push({ value: option.value, label: option.textContent.trim() });
            }
        });
    }

    const typeLabels = {
        all: text.all,
        families: text.families,
        sponsors: text.sponsors,
        sponsorships: text.sponsorships,
        payments: text.payments
    };

    const statusMap = {
        families: [
            ['pending', lang === 'en' ? 'Pending' : 'قيد الانتظار'],
            ['active', lang === 'en' ? 'Active' : 'نشطة'],
            ['paused', lang === 'en' ? 'Paused' : 'موقوفة'],
            ['completed', lang === 'en' ? 'Completed' : 'مكتملة'],
            ['archived', lang === 'en' ? 'Archived' : 'مؤرشفة'],
            ['inactive', lang === 'en' ? 'Inactive' : 'غير نشطة'],
            ['closed', lang === 'en' ? 'Closed' : 'مغلقة']
        ],
        sponsors: [
            ['active', lang === 'en' ? 'Active' : 'نشط'],
            ['inactive', lang === 'en' ? 'Inactive' : 'غير نشط'],
            ['suspended', lang === 'en' ? 'Suspended' : 'موقوف'],
            ['cancelled', lang === 'en' ? 'Cancelled' : 'ملغى']
        ],
        sponsorships: [
            ['active', lang === 'en' ? 'Active' : 'نشطة'],
            ['paused', lang === 'en' ? 'Paused' : 'موقوفة'],
            ['completed', lang === 'en' ? 'Completed' : 'مكتملة'],
            ['cancelled', lang === 'en' ? 'Cancelled' : 'ملغاة']
        ],
        payments: [
            ['draft', lang === 'en' ? 'Draft' : 'مسودة'],
            ['pending_approval', lang === 'en' ? 'Pending Approval' : 'بانتظار الاعتماد'],
            ['approved', lang === 'en' ? 'Approved' : 'معتمدة'],
            ['transferred', lang === 'en' ? 'Transferred' : 'محوّلة'],
            ['received', lang === 'en' ? 'Received' : 'مستلمة'],
            ['returned', lang === 'en' ? 'Returned' : 'مغلقة مع إرجاع'],
            ['cancelled', lang === 'en' ? 'Cancelled' : 'ملغاة'],
            ['voided', lang === 'en' ? 'Voided' : 'ملغاة (فسخ)']
        ]
    };

    const style = document.createElement('style');
    style.id = 'ak-search-center-style';
    style.textContent = `
        .ak-search-center { margin-bottom: 24px; }
        .ak-search-hero { background: #fff; border: 1px solid #e3e8ef; border-radius: 16px; box-shadow: 0 4px 18px rgba(10,31,68,.06); overflow: hidden; }
        .ak-search-hero-head { padding: 22px 24px 12px; }
        .ak-search-hero-head h2 { margin: 0 0 4px; color: var(--navy,#1b4d8f); font-weight: 800; font-size: 1.35rem; }
        .ak-search-hero-head p { margin: 0; color: #64748b; font-size: .9rem; }
        .ak-search-main { padding: 8px 24px 22px; }
        .ak-search-input-wrap { display:flex; gap:10px; align-items:center; }
        .ak-search-input { flex:1; min-width:0; height:48px; border:2px solid #dfe6ef; border-radius:11px; padding:8px 16px; font-family:inherit; font-size:.95rem; outline:0; transition:.2s; }
        .ak-search-input:focus { border-color:#1f4e8c; box-shadow:0 0 0 4px rgba(31,78,140,.10); }
        .ak-search-submit { height:48px; padding:8px 20px; border-radius:11px; font-weight:700; white-space:nowrap; }
        .ak-search-scope { margin-top:14px; }
        .ak-search-label { display:block; margin-bottom:7px; font-size:.78rem; font-weight:700; color:#475569; }
        .ak-search-scopes { display:flex; gap:7px; flex-wrap:wrap; }
        .ak-search-scope-btn { border:1px solid #d8e1ec; background:#fff; color:#475569; border-radius:9px; padding:7px 12px; font-size:.78rem; font-weight:600; cursor:pointer; transition:.18s; }
        .ak-search-scope-btn:hover { border-color:#9fb3cb; background:#f8fafc; }
        .ak-search-scope-btn.active { background:#1f4e8c; color:#fff; border-color:#1f4e8c; }
        .ak-search-advanced-toggle { margin-top:14px; border:0; background:transparent; color:#1f4e8c; padding:0; font-family:inherit; font-size:.8rem; font-weight:700; cursor:pointer; }
        .ak-search-advanced-toggle i { margin-inline-end:5px; transition:transform .2s; }
        .ak-search-advanced-toggle.open i { transform:rotate(180deg); }
        .ak-search-advanced { display:none; margin-top:14px; padding-top:15px; border-top:1px solid #e8edf3; }
        .ak-search-advanced.open { display:block; }
        .ak-search-filter-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .ak-search-field label { display:block; margin-bottom:5px; font-size:.75rem; font-weight:700; color:#475569; }
        .ak-search-field select,.ak-search-field input { width:100%; height:38px; border:1px solid #d8e1ec; border-radius:8px; padding:5px 10px; font-family:inherit; font-size:.8rem; background:#fff; }
        .ak-search-actions { display:flex; gap:8px; align-items:center; margin-top:13px; }
        .ak-search-active { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin-top:12px; }
        .ak-search-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:999px; background:#eef4fb; color:#1f4e8c; font-size:.7rem; font-weight:700; }
        .ak-search-chip button { border:0; background:transparent; color:inherit; padding:0; cursor:pointer; font-size:.8rem; }
        .ak-search-result-marker { display:flex; justify-content:space-between; align-items:center; margin:0 0 12px; color:#64748b; font-size:.78rem; }
        body.theme-dark .ak-search-hero { background:#1f2937; border-color:#374151; }
        body.theme-dark .ak-search-hero-head p,.ak-search-label,.ak-search-field label { color:#a0aec0; }
        body.theme-dark .ak-search-hero-head h2 { color:#e2e8f0; }
        body.theme-dark .ak-search-input, body.theme-dark .ak-search-field select, body.theme-dark .ak-search-field input { background:#111827; color:#e2e8f0; border-color:#4b5563; }
        body.theme-dark .ak-search-scope-btn { background:#1f2937; color:#d1d5db; border-color:#4b5563; }
        body.theme-dark .ak-search-scope-btn:hover { background:#374151; }
        body.theme-dark .ak-search-scope-btn.active { background:#2e63a8; border-color:#2e63a8; color:#fff; }
        body.theme-dark .ak-search-advanced { border-top-color:#374151; }
        body.theme-dark .ak-search-chip { background:#263b55; color:#b9d4f2; }
        @media (max-width: 767.98px) {
            .ak-search-main,.ak-search-hero-head { padding-left:14px; padding-right:14px; }
            .ak-search-input-wrap { flex-direction:column; align-items:stretch; }
            .ak-search-submit { width:100%; }
            .ak-search-filter-grid { grid-template-columns:1fr; }
        }
    `;
    document.head.appendChild(style);

    const center = document.createElement('section');
    center.id = 'akSearchCenter';
    center.className = 'ak-search-center fade-in';
    center.innerHTML = `
        <div class="ak-search-hero">
            <div class="ak-search-hero-head">
                <h2><i class="fas fa-search"></i> ${escapeHtml(text.title)}</h2>
                <p>${escapeHtml(text.subtitle)}</p>
            </div>
            <div class="ak-search-main">
                <form id="akSearchForm" method="get" action="${escapeHtml(globalForm.getAttribute('action') || '')}">
                    <div class="ak-search-input-wrap">
                        <input id="akSearchInput" class="ak-search-input" type="search" name="q" value="${escapeHtml(q)}" placeholder="${escapeHtml(text.placeholder)}" autocomplete="off">
                        <button class="btn btn-primary ak-search-submit" type="submit"><i class="fas fa-search"></i> ${escapeHtml(text.search)}</button>
                    </div>
                    <div class="ak-search-scope">
                        <span class="ak-search-label">${escapeHtml(text.scope)}</span>
                        <div class="ak-search-scopes" id="akSearchScopes"></div>
                    </div>
                    <button type="button" class="ak-search-advanced-toggle" id="akAdvancedToggle">
                        <i class="fas fa-sliders"></i><span>${escapeHtml(text.advanced)}</span>
                    </button>
                    <div class="ak-search-advanced" id="akAdvancedPanel">
                        <div class="ak-search-filter-grid">
                            <div class="ak-search-field" id="akStatusField">
                                <label for="akSearchStatus">${escapeHtml(text.status)}</label>
                                <select id="akSearchStatus" name="status"><option value="">${escapeHtml(text.allStatuses)}</option></select>
                            </div>
                            <div class="ak-search-field" id="akMonthField">
                                <label for="akSearchMonth">${escapeHtml(text.month)}</label>
                                <input id="akSearchMonth" type="month" name="month" value="${escapeHtml(month)}">
                            </div>
                        </div>
                        <div class="ak-search-actions">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> ${escapeHtml(text.search)}</button>
                            <a href="${escapeHtml(globalForm.getAttribute('action') || '')}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i> ${escapeHtml(text.reset)}</a>
                        </div>
                    </div>
                    <div class="ak-search-active" id="akActiveFilters"></div>
                </form>
            </div>
        </div>
    `;

    content.insertBefore(center, existingWelcome);
    existingWelcome.style.display = 'none';

    if (existingFilter) {
        const oldFilterBar = existingFilter.closest('.filter-bar');
        if (oldFilterBar) oldFilterBar.style.display = 'none';
    }

    const scopes = document.getElementById('akSearchScopes');
    const validAllowed = allowedTypes.length ? allowedTypes : Object.keys(typeLabels).map(function (key) {
        return { value: key, label: typeLabels[key] };
    });

    validAllowed.forEach(function (item) {
        const value = item.value;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ak-search-scope-btn' + (type === value ? ' active' : '');
        button.dataset.type = value;
        button.textContent = typeLabels[value] || item.label || value;
        scopes.appendChild(button);
    });

    function updateFilters(selectedType, submitNow) {
        const statusSelect = document.getElementById('akSearchStatus');
        const statusField = document.getElementById('akStatusField');
        const monthField = document.getElementById('akMonthField');
        const statuses = statusMap[selectedType] || [];

        statusSelect.innerHTML = '<option value="">' + escapeHtml(text.allStatuses) + '</option>';
        statuses.forEach(function (pair) {
            const option = document.createElement('option');
            option.value = pair[0];
            option.textContent = pair[1];
            if (status === pair[0]) option.selected = true;
            statusSelect.appendChild(option);
        });

        statusField.style.display = statuses.length ? '' : 'none';
        monthField.style.display = selectedType === 'payments' ? '' : 'none';

        document.querySelectorAll('.ak-search-scope-btn').forEach(function (button) {
            button.classList.toggle('active', button.dataset.type === selectedType);
        });

        if (submitNow) {
            const url = new URL(window.location.href);
            url.searchParams.set('type', selectedType);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
    }

    scopes.addEventListener('click', function (event) {
        const button = event.target.closest('.ak-search-scope-btn');
        if (!button) return;
        const selected = button.dataset.type;
        const url = new URL(window.location.href);
        url.searchParams.set('type', selected);
        url.searchParams.set('page', '1');
        if (selected !== 'payments') url.searchParams.delete('month');
        window.location.href = url.toString();
    });

    const advancedToggle = document.getElementById('akAdvancedToggle');
    const advancedPanel = document.getElementById('akAdvancedPanel');
    const advancedLabel = advancedToggle.querySelector('span');
    const hasAdvancedFilter = status !== '' || month !== '';

    if (hasAdvancedFilter) {
        advancedPanel.classList.add('open');
        advancedToggle.classList.add('open');
        advancedLabel.textContent = text.hideAdvanced;
    }

    advancedToggle.addEventListener('click', function () {
        const open = advancedPanel.classList.toggle('open');
        advancedToggle.classList.toggle('open', open);
        advancedLabel.textContent = open ? text.hideAdvanced : text.advanced;
    });

    updateFilters(type, false);

    document.getElementById('akSearchForm').addEventListener('submit', function () {
        const scopeInput = document.createElement('input');
        scopeInput.type = 'hidden';
        scopeInput.name = 'type';
        scopeInput.value = document.querySelector('.ak-search-scope-btn.active')?.dataset.type || type;
        this.appendChild(scopeInput);

        const pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        pageInput.value = '1';
        this.appendChild(pageInput);
    });

    const active = document.getElementById('akActiveFilters');
    if (q || status || month) {
        const label = document.createElement('span');
        label.style.fontSize = '.72rem';
        label.style.fontWeight = '700';
        label.style.color = '#64748b';
        label.textContent = text.activeFilters + ':';
        active.appendChild(label);

        if (q) addChip(q, 'q', text.remove);
        if (status) addChip((statusMap[type] || []).find(function (x) { return x[0] === status; })?.[1] || status, 'status', text.remove);
        if (month) addChip(month, 'month', text.remove);
    }

    function addChip(label, key, aria) {
        const chip = document.createElement('span');
        chip.className = 'ak-search-chip';
        chip.innerHTML = escapeHtml(label) + ' <button type="button" aria-label="' + escapeHtml(aria) + '">×</button>';
        chip.querySelector('button').addEventListener('click', function () {
            const url = new URL(window.location.href);
            url.searchParams.delete(key);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });
        active.appendChild(chip);
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
