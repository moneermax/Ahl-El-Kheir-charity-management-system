/* Financial Manager dashboard layout helpers. */
(function () {
    'use strict';

    function moveQuickStats() {
        var main = document.querySelector('main.container-fluid');
        if (!main) return;

        var cards = main.querySelectorAll('.fm-card');
        var quickStats = null;
        for (var i = 0; i < cards.length; i++) {
            var head = cards[i].querySelector('.fm-card-head');
            if (head && /Quick Statistics|إحصائيات سريعة/.test(head.textContent.trim())) {
                quickStats = cards[i];
                break;
            }
        }
        if (!quickStats) return;

        var treasuryGrid = main.querySelector('.grid-4');
        if (!treasuryGrid || treasuryGrid === quickStats || quickStats.contains(treasuryGrid)) return;

        main.insertBefore(quickStats, treasuryGrid);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', moveQuickStats);
    } else {
        moveQuickStats();
    }
})();
