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

        var attrs = root.querySelectorAll ? root.querySelectorAll('[placeholder],[title],[aria-label],[aria-description],[data-bs-title],[alt]') : [];
        Array.prototype.forEach.call(attrs, translateAttributes);
        if (root.nodeType === 1) translateAttributes(root);
    }

    function translateAttributes(element) {
        if (!element || shouldSkip(element)) return;
        ['placeholder', 'title', 'aria-label', 'aria-description', 'data-bs-title', 'alt'].forEach(function (name) {
            if (!element.hasAttribute(name)) return;
            var value = element.getAttribute(name);
            var translated = translate(value);
            if (translated !== value) element.setAttribute(name, translated);
        });
    }

    window.AKLang = {
        t: translate,
        refresh: function (root) { translateElement(root || document.body); }
    };

    document.addEventListener('DOMContentLoaded', function () {
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
