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

    // Render the Sudan flag with CSS instead of the Unicode flag emoji.
    // This avoids browser/font-dependent rendering such as "SD".
    const FLAG_MARKER = '\ud83c\udde8\ud83c\udde9';
    const flagStyle = document.createElement('style');
    flagStyle.textContent = '.ak-sudan-flag{display:inline-block;width:1.35em;height:.9em;min-width:1.35em;vertical-align:-.12em;position:relative;overflow:hidden;border-radius:.08em;background:linear-gradient(to bottom,#d71920 0 33.333%,#fff 33.333% 66.666%,#000 66.666% 100%);box-shadow:0 0 0 1px rgba(0,0,0,.12);margin-inline-end:.3em}.ak-sudan-flag::before{content:"";position:absolute;inset:0 auto 0 0;width:42%;background:#087a3b;clip-path:polygon(0 0,100% 50%,0 100%)}';
    document.head.appendChild(flagStyle);

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
            if (!node.nodeValue || !node.nodeValue.includes(FLAG_MARKER)) return NodeFilter.FILTER_REJECT;
            const parent = node.parentElement;
            if (parent && /^(SCRIPT|STYLE|TEXTAREA)$/i.test(parent.tagName)) return NodeFilter.FILTER_REJECT;
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
});
