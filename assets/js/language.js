/* Ahl El Kheir — client-side i18n runtime.
 * Server-side PHP remains the source of truth for initial HTML.
 * This file handles UI inserted later by JavaScript/AJAX and UI attributes.
 * It never translates arbitrary database content unless the complete value is
 * explicitly present in the UI dictionary.
 */
(function (window, document) {
    'use strict';

    var raw = window.AK_TRANSLATIONS || {};
    var dictionary = {};

    function normalize(value) {
        return String(value == null ? '' : value)
            .replace(/\u00a0|\u202f/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    Object.keys(raw).forEach(function (key) {
        dictionary[key] = raw[key];
        var normalized = normalize(key);
        if (normalized && !Object.prototype.hasOwnProperty.call(dictionary, normalized)) {
            dictionary[normalized] = raw[key];
        }
    });

    function translate(value) {
        if (window.AK_LANG !== 'en') return value;
        var key = String(value == null ? '' : value);
        if (Object.prototype.hasOwnProperty.call(dictionary, key)) return dictionary[key];
        var normalized = normalize(key);
        if (Object.prototype.hasOwnProperty.call(dictionary, normalized)) return dictionary[normalized];
        return value;
    }

    function shouldSkip(element) {
        if (!element || element.nodeType !== 1) return true;
        if (element.closest && element.closest('[data-ak-no-i18n],script,style,pre,code,textarea')) return true;
        if (element.matches && element.matches('input[type="text"],input[type="search"],input[type="email"],input[type="tel"],input[type="number"]')) return true;
        return false;
    }

    function translateElement(root) {
        if (window.AK_LANG !== 'en' || !root) return;
        var walker = document.createTreeWalker(
            root.nodeType === 9 ? root : root,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function (node) {
                    var parent = node.parentElement;
                    if (!parent || shouldSkip(parent)) return NodeFilter.FILTER_REJECT;
                    if (!normalize(node.nodeValue)) return NodeFilter.FILTER_REJECT;
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            var translated = translate(node.nodeValue);
            if (translated !== node.nodeValue) node.nodeValue = translated;
        });

        var attrs = root.querySelectorAll ? root.querySelectorAll('[placeholder],[title],[aria-label],[aria-description],[data-bs-title],[alt],[data-confirm],[data-reassign-confirm]') : [];
        Array.prototype.forEach.call(attrs, translateAttributes);
        if (root.nodeType === 1) translateAttributes(root);
    }

    function translateAttributes(element) {
        if (!element || shouldSkip(element)) return;
        ['placeholder', 'title', 'aria-label', 'aria-description', 'data-bs-title', 'alt', 'data-confirm', 'data-reassign-confirm'].forEach(function (name) {
            if (!element.hasAttribute(name)) return;
            var value = element.getAttribute(name);
            var translated = translate(value);
            if (translated !== value) element.setAttribute(name, translated);
        });
    }

    /*
     * Language links are generated server-side, but older pages can contain
     * an existing ?lang= value. Appending another lang parameter is unsafe:
     * PHP uses the last duplicate value while URLSearchParams.get() uses the
     * first one. That made the toggle appear to work only after navigating to
     * another page. Always replace every existing lang parameter with exactly
     * one target value, and set the cookie immediately before navigation.
     */
    function prepareLanguageUrl(href, target) {
        try {
            var url = new URL(href, window.location.href);
            url.searchParams.delete('lang');
            url.searchParams.set('lang', target);
            return url.toString();
        } catch (e) {
            return href;
        }
    }

    function switchLanguage(target, href) {
        if (target !== 'ar' && target !== 'en') return;

        var destination = prepareLanguageUrl(href || window.location.href, target);

        /* Keep the language state synchronized even if navigation is handled
         * by browser history/cache before the next PHP request. */
        document.cookie = 'ak_lang=' + encodeURIComponent(target) + '; Max-Age=31536000; Path=/; SameSite=Lax';

        /* Replace the current URL rather than leaving duplicate lang params. */
        window.location.assign(destination);
    }

    function normalizeLanguageLinks() {
        var links = document.querySelectorAll('a[href*="lang="]');
        Array.prototype.forEach.call(links, function (link) {
            try {
                var url = new URL(link.href, window.location.href);
                var params = url.searchParams.getAll('lang');
                if (!params.length) return;

                var target = params[params.length - 1];
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

                var target = window.AK_LANG === 'en' ? 'ar' : 'en';
                switchLanguage(target, link.href);
            });
        });
    }

    function fixBootstrapDirection() {
        var links = document.querySelectorAll('link[rel="stylesheet"]');

        Array.prototype.forEach.call(links, function (link) {
            if (window.AK_LANG === 'en') {
                if (link.href.indexOf('bootstrap.rtl') !== -1) {
                    link.href = link.href.replace('bootstrap.rtl', 'bootstrap');
                }
            } else {
                if (link.href.indexOf('bootstrap.min.css') !== -1 && link.href.indexOf('bootstrap.rtl') === -1) {
                    link.href = link.href.replace('bootstrap.min.css', 'bootstrap.rtl.min.css');
                }
            }
        });

        document.documentElement.dir = window.AK_LANG === 'en' ? 'ltr' : 'rtl';
        document.documentElement.lang = window.AK_LANG === 'en' ? 'en' : 'ar';
    }

    window.AKLang = {
        t: translate,
        refresh: function (root) { translateElement(root || document.body); },
        switchTo: function (target) {
            switchLanguage(target, window.location.href);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        normalizeLanguageLinks();
        bindLanguageToggle();
        fixBootstrapDirection();

        if (window.AK_LANG !== 'en') return;
        translateElement(document.body);

        if (!window.MutationObserver) return;
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                    if (node.nodeType === 1) translateElement(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})(window, document);
