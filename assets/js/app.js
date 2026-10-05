/*
 * Global same-page POST navigation.
 *
 * A normal same-page POST creates a new document. That is the actual source
 * of the visible scroll-to-top/scroll-back effect: scroll restoration can
 * only repair a navigation that has already happened.
 *
 * For safe same-document POSTs, submit through fetch(), keep the existing
 * document alive, replace only the shared .content region, and keep the
 * current viewport. Cross-page redirects remain normal navigations.
 */
(function(){
    const AK_SCROLL_TRACE = window.location.hash.includes('ak-scroll-trace');
    const akTrace = (label, extra) => {
        if (!AK_SCROLL_TRACE) return;
        const vv = window.visualViewport;
        const state = {
            label,
            t: Math.round(performance.now()*100)/100,
            scrollX: window.scrollX || 0,
            scrollY: window.scrollY || window.pageYOffset || 0,
            innerHeight: window.innerHeight,
            docHeight: document.documentElement ? document.documentElement.scrollHeight : null,
            bodyHeight: document.body ? document.body.scrollHeight : null,
            active: document.activeElement ? {
                tag: document.activeElement.tagName,
                id: document.activeElement.id || '',
                name: document.activeElement.getAttribute('name') || '',
                cls: document.activeElement.className || ''
            } : null,
            visualViewport: vv ? {offsetTop: vv.offsetTop, pageTop: vv.pageTop, height: vv.height} : null,
            ...extra
        };
        console.log('[AK-SCROLL]', state);
    };
    if (AK_SCROLL_TRACE) {
        console.warn('[AK-SCROLL] TRACE ENABLED — diagnostic only');

        /* Causal instrumentation: record JavaScript APIs that can directly
         * move the viewport/focus/history, plus DOM/layout lifecycle events.
         * This is diagnostic only; it does not alter their behavior. */
        (function installScrollCauseTracing(){
            function stack(){
                try { return (new Error()).stack || ''; } catch (e) { return ''; }
            }
            function patch(obj, name, label){
                if (!obj || typeof obj[name] !== 'function') return;
                const original = obj[name];
                const key = '__akTraceOriginal_' + name;
                if (obj[key]) return;
                obj[key] = original;
                obj[name] = function(){
                    akTrace('API:' + label, {
                        args: Array.prototype.slice.call(arguments, 0, 3).map(function(v){
                            try { return typeof v === 'object' ? JSON.stringify(v) : v; } catch (e) { return String(v); }
                        }),
                        stack: stack()
                    });
                    return original.apply(this, arguments);
                };
            }

            patch(window, 'scrollTo', 'window.scrollTo');
            patch(window, 'scrollBy', 'window.scrollBy');
            patch(Element.prototype, 'scrollIntoView', 'Element.scrollIntoView');
            patch(HTMLElement.prototype, 'focus', 'HTMLElement.focus');
            patch(history, 'pushState', 'history.pushState');
            patch(history, 'replaceState', 'history.replaceState');

            document.addEventListener('focusin', function(e){
                akTrace('event:focusin', {
                    target: e.target && e.target.tagName,
                    id: e.target && e.target.id || '',
                    name: e.target && e.target.getAttribute && e.target.getAttribute('name') || ''
                });
            }, true);
            document.addEventListener('focusout', function(e){
                akTrace('event:focusout', {
                    target: e.target && e.target.tagName,
                    id: e.target && e.target.id || ''
                });
            }, true);

            if (window.MutationObserver) {
                var content = document.querySelector('.content');
                if (content) {
                    new MutationObserver(function(mutations){
                        var added=0, removed=0, text=0;
                        mutations.forEach(function(m){
                            added += m.addedNodes ? m.addedNodes.length : 0;
                            removed += m.removedNodes ? m.removedNodes.length : 0;
                            if (m.type === 'characterData') text++;
                        });
                        akTrace('mutation:.content', {records:mutations.length, added:added, removed:removed, text:text});
                    }).observe(content, {childList:true,subtree:true,characterData:true});
                }
            }

            if (window.ResizeObserver) {
                var root = document.documentElement;
                var body = document.body;
                var ro = new ResizeObserver(function(entries){
                    entries.forEach(function(entry){
                        akTrace('resize:' + (entry.target === root ? 'documentElement' : 'body'), {
                            height: Math.round(entry.contentRect.height * 100) / 100
                        });
                    });
                });
                if (root) ro.observe(root);
                if (body) ro.observe(body);
            }
        })();
        window.addEventListener('scroll', () => akTrace('window scroll'), {capture:true, passive:true});
        window.addEventListener('pageshow', e => akTrace('pageshow', {persisted:e.persisted}));
        window.addEventListener('pagehide', e => akTrace('pagehide', {persisted:e.persisted}));
        window.addEventListener('beforeunload', () => akTrace('beforeunload'));
        window.addEventListener('popstate', () => akTrace('popstate'));
        if ('onscrollend' in window) window.addEventListener('scrollend', () => akTrace('scrollend'), {passive:true});
    }

    function samePagePost(form) {
        if (!form || form.tagName !== 'FORM') return false;
        if (String(form.method || 'get').toLowerCase() !== 'post') return false;
        if (form.dataset.akNativePost === '1' || form.dataset.akAjaxPost === '0') return false;
        if (form.target && !['_self'].includes(String(form.target).toLowerCase())) return false;

        const currentContent = form.closest('.content');
        if (!currentContent) return false;

        try {
            const target = new URL(form.action || window.location.href, window.location.href);
            return target.origin === window.location.origin &&
                   target.pathname === window.location.pathname;
        } catch (e) {
            return false;
        }
    }

    function runFragmentScripts(container) {
        const scripts = Array.from(container.querySelectorAll('script'));
        scripts.forEach(function(oldScript){
            const src = oldScript.getAttribute('src');
            if (src) {
                const absolute = new URL(src, document.baseURI).href;
                if (Array.from(document.scripts).some(function(s){ return s.src === absolute; })) return;
                const script = document.createElement('script');
                Array.from(oldScript.attributes).forEach(function(attr){
                    script.setAttribute(attr.name, attr.value);
                });
                document.body.appendChild(script);
                return;
            }

            if (!oldScript.textContent.trim()) return;

            /*
             * Page modules commonly register initialization on DOMContentLoaded.
             * The new fragment is inserted after that event has already fired,
             * so execute those registrations immediately during fragment boot.
             */
            const originalDocumentAdd = document.addEventListener;
            const originalWindowAdd = window.addEventListener;
            const immediateAdd = function(original, target, type, listener, options){
                if ((target === document && type === 'DOMContentLoaded') ||
                    (target === window && type === 'load')) {
                    if (typeof listener === 'function') {
                        queueMicrotask(function(){
                            try { listener.call(target, new Event(type)); } catch (e) {}
                        });
                    }
                    return;
                }
                return original.call(target, type, listener, options);
            };

            document.addEventListener = function(type, listener, options){
                return immediateAdd(originalDocumentAdd, document, type, listener, options);
            };
            window.addEventListener = function(type, listener, options){
                return immediateAdd(originalWindowAdd, window, type, listener, options);
            };

            try {
                (new Function(oldScript.textContent)).call(window);
            } catch (e) {
                console.error('Ahl El Kheir fragment script failed:', e);
            } finally {
                document.addEventListener = originalDocumentAdd;
                window.addEventListener = originalWindowAdd;
            }
        });
    }

    async function renderResponse(response, fallbackUrl) {
        akTrace('renderResponse:start', {responseUrl:response.url || fallbackUrl, status:response.status});
        const finalUrl = new URL(response.url || fallbackUrl, window.location.href);

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        if (!contentType.includes('text/html')) {
            throw new Error('NON_HTML_RESPONSE');
        }

        const html = await response.text();
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const incoming = parsed.querySelector('.content');
        const current = document.querySelector('.content');

        if (!incoming || !current) {
            throw new Error('CONTENT_REGION_NOT_FOUND');
        }

        const scrollX = window.scrollX || 0;
        const scrollY = window.scrollY || window.pageYOffset || 0;
        akTrace('renderResponse:captured-scroll', {scrollX, scrollY});

        /*
         * Preserve the current document's scrollable height during the root
         * swap. Replacing the live .content root is a single DOM operation,
         * but if the response is shorter than the current content the browser
         * can still observe a sudden reduction in document height and clamp
         * scrollY before our explicit restore runs. Give the off-document
         * replacement a temporary minimum height equal to the live region's
         * rendered height, so the scroll range cannot collapse during the
         * swap.
         */
        const currentHeight = Math.ceil(current.getBoundingClientRect().height);
        const replacement = document.importNode(incoming, true);
        replacement.style.minHeight = currentHeight + 'px';

        /*
         * Preserve focus where possible. The submitted control normally
         * disappears with the old fragment, so remember a stable identity
         * before replacing the root.
         */
        const active = document.activeElement;
        let activeDescriptor = null;
        if (active && current.contains(active)) {
            if (active.id) {
                activeDescriptor = {type: 'id', value: active.id};
            } else {
                const name = active.getAttribute('name');
                if (name) {
                    activeDescriptor = {
                        type: 'name',
                        value: name,
                        tag: active.tagName
                    };
                }
            }
        }

        /*
         * The submit button is normally the focused element when this code
         * runs. Removing a focused element from a deeply scrolled document
         * can make the browser move the viewport as part of focus cleanup,
         * independently of scroll restoration. Explicitly blur it BEFORE the
         * DOM swap so the browser has no disappearing focused control to
         * reconcile during replacement.
         */
        akTrace('renderResponse:before-blur');
        if (active && typeof active.blur === 'function') {
            try { active.blur(); } catch (e) {}
        }

        /*
         * Keep the live .content element itself alive. Replacing that root
         * removes the DOM element that owns the entire visible page region;
         * browsers are then free to reconcile scroll/focus state against a
         * different scroll tree. The stable-root approach changes only its
         * children, so the page's scroll container and its identity never
         * disappear.
         *
         * Use replaceChildren() rather than remove/append loops so the live
         * root does not spend an observable layout interval with zero content.
         * Preserve any pre-existing inline min-height while the new fragment
         * is installed so the scroll range cannot collapse during the swap.
         */
        const originalMinHeight = current.style.minHeight;
        current.style.minHeight = currentHeight + 'px';
        const fragment = document.createDocumentFragment();
        while (replacement.firstChild) fragment.appendChild(replacement.firstChild);
        akTrace('renderResponse:before-content-children-replace');
        current.replaceChildren(fragment);
        akTrace('renderResponse:after-content-children-replace');

        if (parsed.title) document.title = parsed.title;
        if (finalUrl.href !== window.location.href) {
            history.replaceState(history.state, '', finalUrl.href);
        }

        akTrace('renderResponse:before-fragment-scripts');
        runFragmentScripts(replacement);
        akTrace('renderResponse:after-fragment-scripts');

        /*
         * Fragment initialization that was registered as DOMContentLoaded/load
         * is deliberately queued as a microtask by runFragmentScripts().
         * Restore scroll only after those queued initializers have completed.
         * Otherwise a fragment can mutate its rows/layout after scrollTo(),
         * letting scroll anchoring or scroll-range clamping move the viewport
         * again. This yields to the pending microtask queue without allowing
         * a rendering opportunity in between.
         */
        await Promise.resolve();
        akTrace('renderResponse:after-fragment-microtasks');

        akTrace('renderResponse:before-focus-restore');
        if (activeDescriptor) {
            let nextActive = null;
            if (activeDescriptor.type === 'id') {
                nextActive = replacement.querySelector('#' + CSS.escape(activeDescriptor.value));
            } else {
                nextActive = Array.from(
                    replacement.querySelectorAll(
                        activeDescriptor.tag + '[name="' +
                        CSS.escape(activeDescriptor.value) + '"]'
                    )
                )[0] || null;
            }
            if (nextActive && typeof nextActive.focus === 'function') {
                try {
                    nextActive.focus({preventScroll: true});
                } catch (e) {
                    try { nextActive.focus(); } catch (e2) {}
                }
            }
        }

        /*
         * The document never intentionally navigated, but fragment scripts
         * may alter layout. Restore the exact pre-submit viewport after the
         * complete fragment lifecycle. Remove the temporary height only after
         * the restore so it cannot participate in the swap-time clamp.
         *
         * If the new page is genuinely shorter than the previous viewport,
         * keep enough height for the requested scroll position rather than
         * allowing the browser to jump to the top.
         */
        akTrace('renderResponse:before-scrollTo');
        const requiredHeight = Math.ceil(scrollY + window.innerHeight);
        if (replacement.scrollHeight < requiredHeight) {
            replacement.style.minHeight = requiredHeight + 'px';
        }

        window.scrollTo({left: scrollX, top: scrollY, behavior: 'auto'});
        akTrace('renderResponse:after-scrollTo');
        if (AK_SCROLL_TRACE) {
            [0, 1, 16, 50, 150, 500].forEach(ms => setTimeout(() => akTrace('renderResponse:timer+'+ms), ms));
        }
    }

    async function refreshSamePage() {
        const url = window.location.href;
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-AK-Same-Page': '1' },
            cache: 'no-store'
        });
        await renderResponse(response, url);
    }

    window.AKSamePage = {
        refresh: refreshSamePage,
        renderResponse: renderResponse
    };

    /*
     * Capture submit before any page-level listener can invoke native
     * navigation. This is intentionally registered on window in capture
     * phase: the browser must never get a chance to start a document
     * navigation for a same-page POST.
     */
    window.addEventListener('submit', function(event){
        const form = event.target;
        if (!samePagePost(form)) return;
        if (event.defaultPrevented) return;
        if (form.dataset.akSubmitting === '1') return;

        akTrace('submit:before-preventDefault', {action:form.action || window.location.href});
        event.preventDefault();
        akTrace('submit:after-preventDefault');
        form.dataset.akSubmitting = '1';

        const submitter = event.submitter;
        if (submitter) submitter.disabled = true;

        const target = new URL(form.action || window.location.href, window.location.href);
        const formData = new FormData(form);
        if (submitter && submitter.name && !formData.has(submitter.name)) {
            formData.append(submitter.name, submitter.value || '');
        }

        akTrace('submit:before-fetch');
        fetch(target.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            redirect: 'follow',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-AK-Same-Page': '1'
            }
        }).then(function(response){
            akTrace('submit:fetch-response', {status:response.status, url:response.url});
            const finalUrl = new URL(response.url || target.href, window.location.href);

            /*
             * A redirect to another application page is a real navigation.
             * Only same-path responses are kept in-place.
             */
            if (finalUrl.origin !== window.location.origin ||
                finalUrl.pathname !== window.location.pathname) {
                window.location.assign(finalUrl.href);
                return null;
            }

            return renderResponse(response, target.href);
        }).catch(function(error){
            /*
             * The POST may already have reached the server. Never blindly
             * resubmit it on an error, because that could duplicate a
             * financial/HR operation. Restore the form and let the user retry.
             */
            console.error('Same-page POST failed:', error);
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'تعذر تنفيذ الإجراء',
                    text: 'تعذر تحديث الصفحة دون إعادة تحميلها. يمكنك المحاولة مرة أخرى.',
                    confirmButtonText: 'حسناً'
                });
            }
        }).finally(function(){
            delete form.dataset.akSubmitting;
            if (submitter) submitter.disabled = false;
        });
    });


})();

/* Prevent accidental mouse-wheel changes on every numeric input in the system.
 * Capture at window level so the browser's native number-input stepper cannot
 * receive the wheel action even if another page-level listener is present.
 */
window.addEventListener('wheel', function (event) {
    const target = event.target;
    if (
        event.cancelable &&
        target &&
        target.matches &&
        target.matches('input[type="number"]')
    ) {
        event.preventDefault();
        event.stopPropagation();
    }
}, { capture: true, passive: false });

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

    const searchPage = /\/modules\/search\/index\.php(?:$|[?#])/.test(window.location.href);
    if (searchPage) initSearchCenter();
});

function initSearchCenter() {
    const existingWelcome = document.querySelector('.welcome-section');
    const existingFilter = document.getElementById('filterForm');
    const content = document.querySelector('.content');
    const searchEntry = document.querySelector('.ak-header-search-btn[data-search-types]');
    if (!content || !existingWelcome || !searchEntry || document.getElementById('akSearchCenter')) return;

    const lang = document.documentElement.lang === 'en' ? 'en' : 'ar';
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q') || '';
    const requestedType = params.get('type') || '';
    const status = params.get('status') || '';
    const month = params.get('month') || '';
    const allowed = (searchEntry.dataset.searchTypes || '').split(',').map(v => v.trim()).filter(Boolean);
    const allowAll = allowed.length === 4;
    const text = lang === 'en' ? {
        title:'Search Center', subtitle:'Search system data from one place, then narrow the results with advanced filters.', placeholder:'Search by name, code, phone number, or any detail...', scope:'Search scope', all:'All data', families:'Families & Orphans', sponsors:'Sponsors', sponsorships:'Sponsorships', payments:'Monthly Payments', advanced:'Advanced filters', hideAdvanced:'Hide advanced filters', status:'Status', allStatuses:'All statuses', month:'Month', search:'Search', reset:'Reset', activeFilters:'Active filters', remove:'Remove'
    } : {
        title:'مركز البحث', subtitle:'ابحث في بيانات النظام من مكان واحد، ثم ضيّق النتائج باستخدام الفلاتر المتقدمة.', placeholder:'ابحث بالاسم، الكود، رقم الهاتف أو أي معلومة...', scope:'نطاق البحث', all:'كل البيانات', families:'الأسر والأيتام', sponsors:'الكفلاء', sponsorships:'الكفالات', payments:'الدفعات الشهرية', advanced:'خيارات متقدمة', hideAdvanced:'إخفاء الخيارات المتقدمة', status:'الحالة', allStatuses:'كل الحالات', month:'الشهر', search:'بحث', reset:'إعادة ضبط', activeFilters:'الفلاتر النشطة', remove:'إزالة'
    };
    const labels = {all:text.all, families:text.families, sponsors:text.sponsors, sponsorships:text.sponsorships, payments:text.payments};
    const statusMap = {
        families:[['pending','قيد الانتظار'],['active','نشطة'],['paused','موقوفة'],['completed','مكتملة'],['archived','مؤرشفة'],['inactive','غير نشطة'],['closed','مغلقة']],
        sponsors:[['active','نشط'],['inactive','غير نشط'],['suspended','موقوف'],['cancelled','ملغى']],
        sponsorships:[['active','نشطة'],['paused','موقوفة'],['completed','مكتملة'],['cancelled','ملغاة']],
        payments:[['draft','مسودة'],['pending_approval','بانتظار الاعتماد'],['approved','معتمدة'],['transferred','محوّلة'],['received','مستلمة'],['returned','مغلقة مع إرجاع'],['cancelled','ملغاة'],['voided','ملغاة (فسخ)']]
    };
    if (lang === 'en') Object.keys(statusMap).forEach(k => statusMap[k] = statusMap[k].map(x => [x[0], x[0].replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())]));
    const selected = allowed.includes(requestedType) ? requestedType : (allowAll ? 'all' : (allowed[0] || ''));

    const style = document.createElement('style');
    style.id='ak-search-center-style';
    style.textContent=`.ak-search-center{margin-bottom:24px}.ak-search-hero{background:#fff;border:1px solid #e3e8ef;border-radius:16px;box-shadow:0 4px 18px rgba(10,31,68,.06);overflow:hidden}.ak-search-hero-head{padding:22px 24px 12px}.ak-search-hero-head h2{margin:0 0 4px;color:var(--navy,#1b4d8f);font-weight:800;font-size:1.35rem}.ak-search-hero-head p{margin:0;color:#64748b;font-size:.9rem}.ak-search-main{padding:8px 24px 22px}.ak-search-input-wrap{display:flex;gap:10px;align-items:center}.ak-search-input{flex:1;min-width:0;height:48px;border:2px solid #dfe6ef;border-radius:11px;padding:8px 16px;font-family:inherit;font-size:.95rem;outline:0}.ak-search-input:focus{border-color:#1f4e8c;box-shadow:0 0 0 4px rgba(31,78,140,.1)}.ak-search-submit{height:48px;padding:8px 20px;border-radius:11px;font-weight:700;white-space:nowrap}.ak-search-scope{margin-top:14px}.ak-search-label{display:block;margin-bottom:7px;font-size:.78rem;font-weight:700;color:#475569}.ak-search-scopes{display:flex;gap:7px;flex-wrap:wrap}.ak-search-scope-btn{border:1px solid #d8e1ec;background:#fff;color:#475569;border-radius:9px;padding:7px 12px;font-size:.78rem;font-weight:600;cursor:pointer}.ak-search-scope-btn.active{background:#1f4e8c;color:#fff;border-color:#1f4e8c}.ak-search-advanced-toggle{margin-top:14px;border:0;background:transparent;color:#1f4e8c;padding:0;font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer}.ak-search-advanced{display:none;margin-top:14px;padding-top:15px;border-top:1px solid #e8edf3}.ak-search-advanced.open{display:block}.ak-search-filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.ak-search-field label{display:block;margin-bottom:5px;font-size:.75rem;font-weight:700;color:#475569}.ak-search-field select,.ak-search-field input{width:100%;height:38px;border:1px solid #d8e1ec;border-radius:8px;padding:5px 10px;font-family:inherit;font-size:.8rem;background:#fff}.ak-search-actions{display:flex;gap:8px;align-items:center;margin-top:13px}.ak-search-active{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:12px}.ak-search-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;background:#eef4fb;color:#1f4e8c;font-size:.7rem;font-weight:700}.ak-search-chip button{border:0;background:transparent;color:inherit;padding:0;cursor:pointer;font-size:.8rem}body.theme-dark .ak-search-hero{background:#2d3748;border-color:#4a5568;color:#e2e8f0}body.theme-dark .ak-search-hero-head p,body.theme-dark .ak-search-label,body.theme-dark .ak-search-field label{color:#cbd5e1}body.theme-dark .ak-search-input,body.theme-dark .ak-search-field select,body.theme-dark .ak-search-field input{background:#1a202c;color:#e2e8f0;border-color:#4a5568}body.theme-dark .ak-search-scope-btn{background:#2d3748;color:#e2e8f0;border-color:#4a5568}body.theme-dark .ak-search-scope-btn.active{background:#1f4e8c;border-color:#1f4e8c;color:#fff}body.theme-dark .ak-search-advanced{border-top-color:#4a5568}@media(max-width:767.98px){.ak-search-main,.ak-search-hero-head{padding-left:14px;padding-right:14px}.ak-search-input-wrap{flex-direction:column;align-items:stretch}.ak-search-submit{width:100%}.ak-search-filter-grid{grid-template-columns:1fr}}`;
    document.head.appendChild(style);

    const center=document.createElement('section');
    center.id='akSearchCenter'; center.className='ak-search-center fade-in';
    center.innerHTML=`<div class="ak-search-hero"><div class="ak-search-hero-head"><h2><i class="fas fa-search"></i> ${escapeHtml(text.title)}</h2><p>${escapeHtml(text.subtitle)}</p></div><div class="ak-search-main"><form id="akSearchForm" method="get" action=""><div class="ak-search-input-wrap"><input id="akSearchInput" class="ak-search-input" type="search" name="q" value="${escapeHtml(q)}" placeholder="${escapeHtml(text.placeholder)}" autocomplete="off"><button class="btn btn-primary ak-search-submit" type="submit"><i class="fas fa-search"></i> ${escapeHtml(text.search)}</button></div><div class="ak-search-scope"><span class="ak-search-label">${escapeHtml(text.scope)}</span><div class="ak-search-scopes" id="akSearchScopes"></div></div><button type="button" class="ak-search-advanced-toggle" id="akAdvancedToggle"><i class="fas fa-sliders"></i> <span>${escapeHtml(text.advanced)}</span></button><div class="ak-search-advanced" id="akAdvancedPanel"><div class="ak-search-filter-grid"><div class="ak-search-field" id="akStatusField"><label for="akSearchStatus">${escapeHtml(text.status)}</label><select id="akSearchStatus" name="status"><option value="">${escapeHtml(text.allStatuses)}</option></select></div><div class="ak-search-field" id="akMonthField"><label for="akSearchMonth">${escapeHtml(text.month)}</label><input id="akSearchMonth" type="month" name="month" value="${escapeHtml(month)}"></div></div><div class="ak-search-actions"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> ${escapeHtml(text.search)}</button><a href="?" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i> ${escapeHtml(text.reset)}</a></div></div><div class="ak-search-active" id="akActiveFilters"></div></form></div></div>`;
    content.insertBefore(center,existingWelcome); existingWelcome.style.display='none';
    if(existingFilter){const bar=existingFilter.closest('.filter-bar');if(bar)bar.style.display='none';}

    const scopes=document.getElementById('akSearchScopes'); const scopeTypes=allowed.slice(); if(allowAll)scopeTypes.unshift('all');
    scopeTypes.forEach(value=>{const b=document.createElement('button');b.type='button';b.className='ak-search-scope-btn'+(selected===value?' active':'');b.dataset.type=value;b.setAttribute('aria-pressed',selected===value?'true':'false');b.textContent=labels[value]||value;scopes.appendChild(b);});
    function updateFilters(t){const sel=document.getElementById('akSearchStatus'),sf=document.getElementById('akStatusField'),mf=document.getElementById('akMonthField');const list=statusMap[t]||[];sel.innerHTML='<option value="">'+escapeHtml(text.allStatuses)+'</option>';list.forEach(x=>{const o=document.createElement('option');o.value=x[0];o.textContent=x[1];if(status===x[0])o.selected=true;sel.appendChild(o);});sf.style.display=list.length?'':'none';mf.style.display=t==='payments'?'':'none';}
    scopes.addEventListener('click',e=>{const b=e.target.closest('.ak-search-scope-btn');if(!b)return;const u=new URL(window.location.href);u.searchParams.set('type',b.dataset.type);u.searchParams.delete('status');u.searchParams.set('page','1');if(b.dataset.type!=='payments')u.searchParams.delete('month');window.location.href=u.toString();});
    const toggle=document.getElementById('akAdvancedToggle'),panel=document.getElementById('akAdvancedPanel'),advLabel=toggle.querySelector('span');
    toggle.setAttribute('aria-controls','akAdvancedPanel');
    if(status||month){panel.classList.add('open');toggle.classList.add('open');toggle.setAttribute('aria-expanded','true');advLabel.textContent=text.hideAdvanced;} else {toggle.setAttribute('aria-expanded','false');}
    toggle.addEventListener('click',()=>{const open=panel.classList.toggle('open');toggle.classList.toggle('open',open);toggle.setAttribute('aria-expanded',open?'true':'false');advLabel.textContent=open?text.hideAdvanced:text.advanced;});
    updateFilters(selected);
    document.getElementById('akSearchForm').addEventListener('submit',function(){const t=document.createElement('input');t.type='hidden';t.name='type';t.value=document.querySelector('.ak-search-scope-btn.active')?.dataset.type||selected;this.appendChild(t);const pg=document.createElement('input');pg.type='hidden';pg.name='page';pg.value='1';this.appendChild(pg);});

    const active=document.getElementById('akActiveFilters');
    if(q||status||month){const l=document.createElement('span');l.style.cssText='font-size:.72rem;font-weight:700;color:#64748b';l.textContent=text.activeFilters+':';active.appendChild(l);if(q)addChip(q,'q');if(status)addChip((statusMap[selected]||[]).find(x=>x[0]===status)?.[1]||status,'status');if(month)addChip(month,'month');}
    function addChip(value,key){const c=document.createElement('span');c.className='ak-search-chip';c.innerHTML=escapeHtml(value)+' <button type="button" aria-label="'+escapeHtml(text.remove)+'">×</button>';c.querySelector('button').addEventListener('click',()=>{const u=new URL(window.location.href);u.searchParams.delete(key);u.searchParams.set('page','1');window.location.href=u.toString();});active.appendChild(c);}
}

function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
