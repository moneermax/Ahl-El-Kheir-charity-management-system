/* Ahl El Kheir — key-based client-side i18n runtime. */
(function (window, document) {
    'use strict';
    var dictionary = window.AK_TRANSLATIONS || {};

    function interpolate(value, params) {
        value = String(value == null ? '' : value); params = params || {};
        Object.keys(params).forEach(function (name) { value = value.split(':' + name).join(String(params[name] == null ? '' : params[name])); });
        return value;
    }

    function translate(key, params) {
        key = String(key == null ? '' : key);
        if (Object.prototype.hasOwnProperty.call(dictionary, key)) return interpolate(dictionary[key], params);
        return key;
    }

    function refresh(root) {
        root = root || document;
        var elements = root.querySelectorAll ? root.querySelectorAll('[data-i18n]') : [];
        Array.prototype.forEach.call(elements, function (element) {
            var key = element.getAttribute('data-i18n');
            if (!key) return;
            element.textContent = translate(key);
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

    /* FM dashboard role-specific header actions become six cards above Treasury. */
    function buildFinancialManagerCards() {
        if (!/\/modules\/accounting\/fm_dashboard\.php(?:$|[?#])/.test(window.location.pathname)) return;
        if (document.querySelector('.ak-fm-action-grid')) return;

        var source = document.querySelector('.qa-actions');
        if (!source) return;

        var buttons = Array.prototype.filter.call(source.querySelectorAll('a.qa-btn'), function (link) {
            return !/modules\/hr\/leaves\.php\?action=request/.test(link.getAttribute('href') || '');
        });
        if (!buttons.length) return;

        /* fm_dashboard.php uses the treasury .grid-4 as its first content block. */
        var anchor = document.querySelector('.main-area .grid-4');
        if (!anchor) anchor = document.querySelector('main.container-fluid .grid-4');
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
            .ak-fm-action-card.ak-fm-action-1{border-top:3px solid #d3701f}\
            .ak-fm-action-card.ak-fm-action-2{border-top:3px solid #28a745}\
            .ak-fm-action-card.ak-fm-action-3{border-top:3px solid #2195c4}\
            .ak-fm-action-card.ak-fm-action-4{border-top:3px solid #ffc107}\
            .ak-fm-action-card.ak-fm-action-5{border-top:3px solid #0d6efd}\
            .ak-fm-action-card.ak-fm-action-6{border-top:3px solid #2daf79}\
            @media(max-width:767.98px){.ak-fm-action-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}\
            @media(max-width:420px){.ak-fm-action-grid{grid-template-columns:1fr}.ak-fm-action-card{height:96px;min-height:96px}}';
        document.head.appendChild(style);

        var grid = document.createElement('div');
        grid.className = 'ak-fm-action-grid fade-in';
        grid.setAttribute('aria-label', translate('accounting.quick_actions'));

        var titleKeys = [
            'accounting.header_accounting',
            'accounting.header_disbursements',
            'accounting.header_accounts',
            'accounting.header_projects',
            'accounting.header_reports',
            'accounting.header_transactions'
        ];
        var descKeys = [
            'accounting.fm_action_accounting_desc',
            'accounting.fm_action_disbursements_desc',
            'accounting.fm_action_accounts_desc',
            'accounting.fm_action_projects_desc',
            'accounting.fm_action_reports_desc',
            'accounting.fm_action_transactions_desc'
        ];

        buttons.forEach(function (button, index) {
            var card = document.createElement('a');
            card.className = 'ak-fm-action-card ak-fm-action-' + (index + 1);
            card.href = button.href;
            var body = document.createElement('div');
            body.className = 'text-center px-2';
            var icon = document.createElement('div');
            icon.className = 'ak-fm-action-icon';
            var originalIcon = button.querySelector('i');
            if (originalIcon) icon.innerHTML = originalIcon.outerHTML;
            var title = document.createElement('div');
            title.className = 'ak-fm-action-title';
            title.textContent = translate(titleKeys[index] || '');
            var desc = document.createElement('div');
            desc.className = 'ak-fm-action-desc';
            desc.textContent = translate(descKeys[index] || '');
            body.appendChild(icon);
            body.appendChild(title);
            if (desc.textContent) body.appendChild(desc);
            card.appendChild(body);
            grid.appendChild(card);
            button.remove();
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