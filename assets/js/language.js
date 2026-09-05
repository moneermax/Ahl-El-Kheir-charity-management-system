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
    function exactCompatibilityText(value) {
        if (window.AK_LANG !== 'en') return null;
        var source = String(value == null ? '' : value);
        var normalized = source.replace(/[\u00a0\u202f]/g, ' ').replace(/\s+/g, ' ').trim();
        if (!normalized || !Object.prototype.hasOwnProperty.call(dictionary, normalized)) return null;
        var translated = String(dictionary[normalized]);
        return translated === normalized ? null : translated;
    }
    function refreshExactText(root) {
        if (window.AK_LANG !== 'en' || !root || !document.createTreeWalker) return;
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentElement; if (!parent) return NodeFilter.FILTER_REJECT;
                var tag = parent.tagName;
                if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'SELECT' || tag === 'OPTION' || tag === 'PRE' || tag === 'CODE') return NodeFilter.FILTER_REJECT;
                return node.nodeValue && node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });
        var nodes = []; while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            var translated = exactCompatibilityText(node.nodeValue); if (translated === null) return;
            var leading = (node.nodeValue.match(/^\s*/) || [''])[0], trailing = (node.nodeValue.match(/\s*$/) || [''])[0];
            node.nodeValue = leading + translated + trailing;
        });
    }
    function refresh(root) {
        root = root || document;
        var elements = root.querySelectorAll ? root.querySelectorAll('[data-i18n]') : [];
        Array.prototype.forEach.call(elements, function (element) { var key = element.getAttribute('data-i18n'); if (key) element.textContent = translate(key); });
        var attributeElements = root.querySelectorAll ? root.querySelectorAll('[data-i18n-attr]') : [];
        Array.prototype.forEach.call(attributeElements, function (element) {
            var spec = element.getAttribute('data-i18n-attr'); if (!spec) return;
            spec.split(';').forEach(function (entry) { var parts = entry.split(':'); if (parts.length < 2) return; var attribute = parts.shift().trim(), key = parts.join(':').trim(); if (attribute && key) element.setAttribute(attribute, translate(key)); });
        });
        refreshExactText(root);
    }
    function prepareLanguageUrl(href, target) { try { var url = new URL(href, window.location.href); url.searchParams.delete('lang'); url.searchParams.set('lang', target); return url.toString(); } catch (e) { return href; } }
    function switchLanguage(target, href) { if (target !== 'ar' && target !== 'en') return; var destination = prepareLanguageUrl(href || window.location.href, target); document.cookie = 'ak_lang=' + encodeURIComponent(target) + '; Max-Age=31536000; Path=/; SameSite=Lax'; window.location.assign(destination); }
    function normalizeLanguageLinks() {
        var links = document.querySelectorAll('a[href*="lang="]');
        Array.prototype.forEach.call(links, function (link) { try { var url = new URL(link.href, window.location.href), values = url.searchParams.getAll('lang'); if (!values.length) return; var target = values[values.length - 1]; if (target !== 'ar' && target !== 'en') return; url.searchParams.delete('lang'); url.searchParams.set('lang', target); link.href = url.toString(); } catch (e) {} });
    }
    function bindLanguageToggle() {
        var links = document.querySelectorAll('a[href*="lang="]');
        Array.prototype.forEach.call(links, function (link) { if (link.dataset.akLangBound === '1') return; link.dataset.akLangBound = '1'; link.addEventListener('click', function (event) { event.preventDefault(); switchLanguage(window.AK_LANG === 'en' ? 'ar' : 'en', link.href); }); });
    }
    function fixBootstrapDirection() {
        var links = document.querySelectorAll('link[rel="stylesheet"]');
        Array.prototype.forEach.call(links, function (link) { if (window.AK_LANG === 'en' && link.href.indexOf('bootstrap.rtl') !== -1) link.href = link.href.replace('bootstrap.rtl', 'bootstrap'); else if (window.AK_LANG === 'ar' && link.href.indexOf('bootstrap.min.css') !== -1 && link.href.indexOf('bootstrap.rtl') === -1) link.href = link.href.replace('bootstrap.min.css', 'bootstrap.rtl.min.css'); });
        document.documentElement.dir = window.AK_LANG === 'en' ? 'ltr' : 'rtl'; document.documentElement.lang = window.AK_LANG === 'en' ? 'en' : 'ar';
    }
    window.AKLang = { t: translate, refresh: refresh, switchTo: function (target) { switchLanguage(target, window.location.href); } };
    document.addEventListener('DOMContentLoaded', function () {
        normalizeLanguageLinks(); bindLanguageToggle(); fixBootstrapDirection(); refresh(document);
        if (!window.MutationObserver) return;
        var observer = new MutationObserver(function (mutations) { mutations.forEach(function (mutation) { Array.prototype.forEach.call(mutation.addedNodes, function (node) { if (node.nodeType === 1) refresh(node); }); }); });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})(window, document);