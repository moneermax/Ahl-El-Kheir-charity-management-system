/* Ahl El Kheir — key-based client-side i18n runtime. */
(function (window, document) {
    'use strict';
    var dictionary = window.AK_TRANSLATIONS || {};

    function interpolate(value, params) {
        value = String(value == null ? '' : value); params = params || {};
        Object.keys(params).forEach(function (name) {
            value = value.split(':' + name).join(String(params[name] == null ? '' : params[name]));
        });
        return value;
    }

    function translate(key, params) {
        key = String(key == null ? '' : key);
        if (Object.prototype.hasOwnProperty.call(dictionary, key)) return interpolate(dictionary[key], params);
        return key;
    }

    function normalizeText(value) {
        return String(value == null ? '' : value)
            .replace(/\u00a0|\u202f/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /*
     * Remaining legacy FM dashboard phrases. These are interface labels only;
     * values coming from the database are never translated unless they exactly
     * match one of these known UI phrases.
     */
    var fmPhrases = {
        'إيرادات الشهر': 'Monthly Income',
        'مصروفات الشهر': 'Monthly Expenses',
        'دخل': 'Income',
        'خرج': 'Outgoing',
        'إجمالي الإيرادات': 'Total Income',
        'إجمالي المصروفات': 'Total Expenses',
        'نقداً': 'Cash',
        'بنكياً': 'Bank',
        'صافي التدفق الشهري': 'Net Monthly Flow',
        'فائض': 'Surplus',
        'عجز': 'Deficit',
        'الإيرادات': 'Income',
        'المصروفات': 'Expenses',
        'حالة الدفعات الشهرية': 'Monthly Disbursement Status',
        'عرض الكل': 'View All',
        'بانتظار الاعتماد': 'Pending Approval',
        'محوّلة (مفتوحة)': 'Transferred (Open)',
        'مستلمة (مغلقة)': 'Received (Closed)',
        'تم الصرف الكامل': 'Fully Disbursed',
        'مُبطَلة': 'Voided',
        'قيد عكسي مُرحّل': 'Posted Reversal',
        'آخر عمليات الإرجاع': 'Recent Returns',
        'آخر الدفعات المُبطَلة': 'Recent Voided Disbursements',
        'إحصائيات سريعة': 'Quick Statistics',
        'كفالة نشطة': 'Active Sponsorships',
        'أسرة نشطة': 'Active Families',
        'أخصائية نشطة': 'Active Nannies',
        'مُصرَف هذا الشهر': 'Disbursed This Month',
        'آخر القيود المحاسبية': 'Recent Journal Entries',
        'رقم القيد': 'Entry Number',
        'التاريخ': 'Date',
        'الوصف': 'Description',
        'مدين': 'Debit',
        'دائن': 'Credit',
        'مرحّل': 'Posted',
        'الشهر': 'Month',
        'الأخصائية': 'Nanny',
        'أنشأها': 'Created By',
        'المبلغ': 'Amount',
        'تاريخ الإرسال': 'Submission Date',
        'تاريخ التحويل': 'Transfer Date',
        'أيام مفتوحة': 'Days Open',
        'الإجراء': 'Action',
        'البنود': 'Items',
        'مُصرَف': 'Paid',
        'معلّق': 'Pending',
        'إرجاع': 'Return',
        'السبب': 'Reason',
        'مراجعة': 'Review',
        'تفاصيل': 'Details',
        'طلب': 'Request',
        'المشروع': 'Project',
        'النوع': 'Type',
        'الميزانية': 'Budget',
        'متاح': 'Available',
        'نسخة': 'Version',
        'عرض المشروع': 'View Project',
        'اعتماد مالي': 'Financial Approval',
        'رفض': 'Reject',
        'توزيع مصادر التمويل من قبل المدير المالي': 'Funding Source Distribution by Financial Manager',
        'ميزانيات المشاريع بانتظار المراجعة المالية': 'Project Budgets Pending Financial Review',
        'لا توجد ميزانيات مشاريع بانتظار المراجعة المالية.': 'No project budgets are pending financial review.'
    };

    function translateKnownPhrase(value) {
        if (window.AK_LANG !== 'en') return null;
        var text = normalizeText(value);
        if (Object.prototype.hasOwnProperty.call(dictionary, text)) return dictionary[text];
        if (Object.prototype.hasOwnProperty.call(fmPhrases, text)) return fmPhrases[text];

        /* Dynamic FM headings containing counts/months. */
        var match = text.match(/^📈\s*إيرادات الشهر\s*\(([^)]+)\)$/u);
        if (match) return '📈 Monthly Income (' + match[1] + ')';
        match = text.match(/^📉\s*مصروفات الشهر\s*\(([^)]+)\)$/u);
        if (match) return '📉 Monthly Expenses (' + match[1] + ')';
        match = text.match(/^⏳\s*طابور اعتماد الدفعات\s*[—-]\s*(.+)$/u);
        if (match) return '⏳ Disbursement Approval Queue — ' + match[1].replace(/دفعة بانتظار مراجعتك/u, 'disbursement(s) awaiting your review');
        match = text.match(/^🔓\s*دفعات مفتوحة\s*[—-]\s*بانتظار صرف الأخصائيات$/u);
        if (match) return '🔓 Open Disbursements — Awaiting Nanny Payout';
        match = text.match(/^📁\s*ميزانيات المشاريع بانتظار المراجعة المالية$/u);
        if (match) return '📁 Project Budgets Pending Financial Review';
        return null;
    }

    function refresh(root) {
        root = root || document;
        var elements = root.querySelectorAll ? root.querySelectorAll('[data-i18n]') : [];
        Array.prototype.forEach.call(elements, function (element) {
            var key = element.getAttribute('data-i18n');
            if (key) element.textContent = translate(key);
        });

        var attributeElements = root.querySelectorAll ? root.querySelectorAll('[data-i18n-attr]') : [];
        Array.prototype.forEach.call(attributeElements, function (element) {
            var spec = element.getAttribute('data-i18n-attr');
            if (!spec) return;
            spec.split(';').forEach(function (entry) {
                var parts = entry.split(':');
                if (parts.length < 2) return;
                var attribute = parts.shift().trim();
                var key = parts.join(':').trim();
                if (attribute && key) element.setAttribute(attribute, translate(key));
            });
        });

        translateLegacyText(root);
    }

    function translateLegacyText(root) {
        if (window.AK_LANG !== 'en' || !root) return;
        var scope = root.nodeType === 9 ? root.body : root;
        if (!scope) return;

        var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentElement;
                if (!parent) return NodeFilter.FILTER_REJECT;
                if (/^(SCRIPT|STYLE|PRE|CODE|TEXTAREA|OPTION)$/i.test(parent.tagName)) return NodeFilter.FILTER_REJECT;
                if (parent.closest('[data-i18n]')) return NodeFilter.FILTER_REJECT;
                var text = normalizeText(node.nodeValue);
                if (!text || !/[\u0600-\u06ff]/u.test(text)) return NodeFilter.FILTER_REJECT;
                return translateKnownPhrase(text) !== null ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        }, false);

        var nodes = [], current;
        while ((current = walker.nextNode())) nodes.push(current);
        nodes.forEach(function (node) {
            var original = node.nodeValue;
            var leading = (original.match(/^\s*/u) || [''])[0];
            var trailing = (original.match(/\s*$/u) || [''])[0];
            var translated = translateKnownPhrase(original);
            if (translated !== null) node.nodeValue = leading + translated + trailing;
        });

        var attributeElements = scope.querySelectorAll ? scope.querySelectorAll('[placeholder],[title],[aria-label],[aria-description]') : [];
        Array.prototype.forEach.call(attributeElements, function (element) {
            ['placeholder', 'title', 'aria-label', 'aria-description'].forEach(function (attribute) {
                if (!element.hasAttribute(attribute)) return;
                var translated = translateKnownPhrase(element.getAttribute(attribute));
                if (translated !== null) element.setAttribute(attribute, translated);
            });
        });
    }

    function prepareLanguageUrl(href, target) {
        try {
            var url = new URL(href, window.location.href);
            url.searchParams.delete('lang');
            url.searchParams.set('lang', target);
            return url.toString();
        } catch (e) { return href; }
    }

    function switchLanguage(target, href) {
        if (target !== 'ar' && target !== 'en') return;
        var destination = prepareLanguageUrl(href || window.location.href, target);
        document.cookie = 'ak_lang=' + encodeURIComponent(target) + '; Max-Age=31536000; Path=/; SameSite=Lax';
        window.location.assign(destination);
    }

    function normalizeLanguageLinks() {
        var links = document.querySelectorAll('a[href*="lang="]');
        Array.prototype.forEach.call(links, function (link) {
            try {
                var url = new URL(link.href, window.location.href);
                var values = url.searchParams.getAll('lang');
                if (!values.length) return;
                var target = values[values.length - 1];
                if (target !== 'ar' && target !== 'en') return;
                url.searchParams.delete('lang');
                url.searchParams.set('lang', target);
                link.href = url.toString();
            } catch (e) {}
        });
    }

    function bindLanguageToggle() {
        var links = document.querySelectorAll('a[href*="lang="]');
        Array.prototype.forEach.call(links, function (link) {
            if (link.dataset.akLangBound === '1') return;
            link.dataset.akLangBound = '1';
            link.addEventListener('click', function (event) {
                event.preventDefault();
                switchLanguage(window.AK_LANG === 'en' ? 'ar' : 'en', link.href);
            });
        });
    }

    function fixBootstrapDirection() {
        var links = document.querySelectorAll('link[rel="stylesheet"]');
        Array.prototype.forEach.call(links, function (link) {
            if (window.AK_LANG === 'en' && link.href.indexOf('bootstrap.rtl') !== -1) {
                link.href = link.href.replace('bootstrap.rtl', 'bootstrap');
            } else if (window.AK_LANG === 'ar' && link.href.indexOf('bootstrap.min.css') !== -1 && link.href.indexOf('bootstrap.rtl') === -1) {
                link.href = link.href.replace('bootstrap.min.css', 'bootstrap.rtl.min.css');
            }
        });
        document.documentElement.dir = window.AK_LANG === 'en' ? 'ltr' : 'rtl';
        document.documentElement.lang = window.AK_LANG === 'en' ? 'en' : 'ar';
    }

    function buildFinancialManagerCards() {
        if (!/\/modules\/accounting\/fm_dashboard\.php(?:$|[?#])/.test(window.location.pathname)) return;
        if (document.querySelector('.ak-fm-action-grid')) return;
        var source = document.querySelector('.qa-actions');
        if (!source) return;
        var buttons = Array.prototype.filter.call(source.querySelectorAll('a.qa-btn'), function (link) {
            return !/modules\/hr\/leaves\.php\?action=request/.test(link.getAttribute('href') || '');
        });
        if (!buttons.length) return;
        var anchor = document.querySelector('.main-area .grid-4') || document.querySelector('main.container-fluid .grid-4');
        if (!anchor) return;

        var style = document.createElement('style');
        style.id = 'ak-fm-dashboard-actions-style';
        style.textContent = '\
            .ak-fm-action-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin:0 auto 1.25rem;max-width:960px}\
            .ak-fm-action-card{min-height:104px;height:104px;border:0;border-radius:10px;box-shadow:0 2px 8px rgba(10,31,68,.08);text-decoration:none;color:inherit;background:#fff;display:flex;align-items:center;justify-content:center;transition:transform .18s,box-shadow .18s}\
            .ak-fm-action-card:hover{transform:translateY(-3px);box-shadow:0 5px 14px rgba(10,31,68,.14);color:inherit}\
            .ak-fm-action-icon{font-size:1.45rem;margin-bottom:.35rem}\
            .ak-fm-action-title{font-size:.82rem;font-weight:700;line-height:1.35}\
            .ak-fm-action-desc{font-size:.66rem;line-height:1.3;color:#6c757d;margin-top:.18rem}\
            @media(max-width:767.98px){.ak-fm-action-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}\
            @media(max-width:420px){.ak-fm-action-grid{grid-template-columns:1fr}.ak-fm-action-card{height:96px;min-height:96px}}';
        document.head.appendChild(style);

        var grid = document.createElement('div');
        grid.className = 'ak-fm-action-grid fade-in';
        grid.setAttribute('aria-label', translate('accounting.quick_actions'));
        var titleKeys = ['accounting.header_accounting','accounting.header_disbursements','accounting.header_accounts','accounting.header_projects','accounting.header_reports','accounting.header_transactions'];
        var descKeys = ['accounting.fm_action_accounting_desc','accounting.fm_action_disbursements_desc','accounting.fm_action_accounts_desc','accounting.fm_action_projects_desc','accounting.fm_action_reports_desc','accounting.fm_action_transactions_desc'];

        buttons.forEach(function (button, index) {
            var card = document.createElement('a');
            card.className = 'ak-fm-action-card';
            card.href = button.href;
            var body = document.createElement('div'); body.className = 'text-center px-2';
            var icon = document.createElement('div'); icon.className = 'ak-fm-action-icon';
            var originalIcon = button.querySelector('i'); if (originalIcon) icon.innerHTML = originalIcon.outerHTML;
            var title = document.createElement('div'); title.className = 'ak-fm-action-title'; title.textContent = translate(titleKeys[index] || '');
            var desc = document.createElement('div'); desc.className = 'ak-fm-action-desc'; desc.textContent = translate(descKeys[index] || '');
            body.appendChild(icon); body.appendChild(title); if (desc.textContent) body.appendChild(desc);
            card.appendChild(body); grid.appendChild(card); button.remove();
        });
        source.classList.toggle('d-none', !source.querySelector('a.qa-btn'));
        anchor.parentNode.insertBefore(grid, anchor);
    }

    window.AKLang = {
        t: translate,
        refresh: refresh,
        switchTo: function (target) { switchLanguage(target, window.location.href); }
    };

    document.addEventListener('DOMContentLoaded', function () {
        normalizeLanguageLinks();
        bindLanguageToggle();
        fixBootstrapDirection();
        refresh(document);
        buildFinancialManagerCards();
        if (!window.MutationObserver) return;
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                    if (node.nodeType === 1) refresh(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})(window, document);