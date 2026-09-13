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

        var treasuryGrid = main.querySelector('.fm-top-right > .grid-4');
        if (!treasuryGrid || quickStats === treasuryGrid || quickStats.contains(treasuryGrid)) return;

        main.insertBefore(quickStats, treasuryGrid);
    }

    function formatAmount(value) {
        return Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function prepareTreasuryRow(treasuryGrid) {
        treasuryGrid.style.gridTemplateColumns = 'repeat(5, minmax(0, 1fr))';
        treasuryGrid.style.gap = '10px';

        var treasuryCards = treasuryGrid.querySelectorAll('.stat-box');
        for (var i = 0; i < treasuryCards.length; i++) {
            treasuryCards[i].style.minWidth = '0';
            treasuryCards[i].style.padding = '14px 10px';
        }
    }

    function buildTreasuryAdminFeeCard(value) {
        var card = document.createElement('div');
        card.className = 'stat-box purple';
        card.id = 'fm-treasury-admin-fee';
        card.style.minWidth = '0';
        card.style.padding = '14px 10px';
        card.innerHTML = '' +
            '<div class="stat-label" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">💼 الرسوم الإدارية المحصلة</div>' +
            '<div class="stat-value" data-admin-fee-value style="font-size:1.55rem;white-space:nowrap">' + (value === null ? '…' : formatAmount(value)) + '</div>' +
            '<div class="stat-sub" style="font-size:.72rem;white-space:nowrap">حساب 4200 · SDG</div>';
        return card;
    }

    function buildLedgerKpiCard(data) {
        var card = document.createElement('div');
        card.className = 'fm-card';
        card.id = 'fm-accounting-kpis';
        card.style.borderRight = '5px solid #17a2b8';

        card.innerHTML = '' +
            '<div class="fm-card-head">' +
                '<span>📊 مؤشرات الكفالات والرسوم الإدارية — من دفتر الأستاذ المرحّل</span>' +
                '<a href="' + data.report_url + '" class="btn-fm btn-ghost" style="background:#fff;color:#1b4d8f;font-size:.8rem">تقرير الرسوم الإدارية</a>' +
            '</div>' +
            '<div class="fm-card-body">' +
                '<div class="grid-4" style="margin-bottom:15px">' +
                    '<div class="stat-box amber"><div class="stat-label">📅 رسوم إدارية هذا الشهر</div><div class="stat-value" data-kpi="admin_fees_month"></div><div class="stat-sub" data-kpi-month></div></div>' +
                    '<div class="stat-box blue"><div class="stat-label">💰 إجمالي تحصيل الكفالات</div><div class="stat-value" data-kpi="sponsorship_gross"></div><div class="stat-sub">إجمالي المدين في حسابات التحصيل · SDG</div></div>' +
                    '<div class="stat-box green"><div class="stat-label">📈 صافي إيرادات الكفالات</div><div class="stat-value" data-kpi="sponsorship_net"></div><div class="stat-sub">4100 — إيرادات الكفالات · SDG</div></div>' +
                '</div>' +
                '<div style="border:1px solid #e9ecef;border-radius:10px;padding:15px;background:#f8f9fa">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap">' +
                        '<div><strong>معادلة تحصيل الكفالات</strong><div class="stat-sub">إجمالي التحصيل = صافي إيرادات الكفالات + الرسوم الإدارية</div></div>' +
                        '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-weight:700">' +
                            '<span data-kpi="gross_formula"></span><span>−</span><span data-kpi="net_formula"></span><span>−</span><span data-kpi="fees_formula"></span><span>=</span><span data-kpi="reconciliation" style="font-size:1.15rem"></span>' +
                        '</div>' +
                    '</div>' +
                    '<div style="margin-top:8px;color:#666;font-size:.8rem" data-kpi="reconciliation_label"></div>' +
                '</div>' +
            '</div>';

        card.querySelector('[data-kpi="admin_fees_month"]').textContent = formatAmount(data.admin_fees_month) + ' SDG';
        card.querySelector('[data-kpi-month]').textContent = 'الشهر ' + data.month + ' · حساب 4200';
        card.querySelector('[data-kpi="sponsorship_gross"]').textContent = formatAmount(data.sponsorship_gross) + ' SDG';
        card.querySelector('[data-kpi="sponsorship_net"]').textContent = formatAmount(data.sponsorship_net) + ' SDG';
        card.querySelector('[data-kpi="gross_formula"]').textContent = formatAmount(data.sponsorship_gross) + ' SDG';
        card.querySelector('[data-kpi="net_formula"]').textContent = formatAmount(data.sponsorship_net) + ' SDG';
        card.querySelector('[data-kpi="fees_formula"]').textContent = formatAmount(data.sponsorship_admin_fees) + ' SDG';
        card.querySelector('[data-kpi="reconciliation"]').textContent = formatAmount(data.sponsorship_reconciliation) + ' SDG';
        card.querySelector('[data-kpi="reconciliation_label"]').textContent = Math.abs(Number(data.sponsorship_reconciliation || 0)) < 0.01
            ? '✓ القيد متطابق ومتوازن: لا يوجد فرق بين إجمالي التحصيل ومكوناته.'
            : '⚠ يوجد فرق في المطابقة ويجب مراجعته محاسبيًا.';

        return card;
    }

    function updateMonthlyFlowCard(data) {
        var main = document.querySelector('main.container-fluid');
        if (!main) return;

        var cards = main.querySelectorAll('.fm-card');
        var incomeCard = null;
        var expenseCard = null;

        for (var i = 0; i < cards.length; i++) {
            var head = cards[i].querySelector('.fm-card-head');
            if (!head) continue;
            var text = head.textContent || '';
            if (!incomeCard && /Monthly Income|الدخل الشهري/.test(text)) incomeCard = cards[i];
            if (!expenseCard && /Monthly Expenses|المصروفات الشهرية/.test(text)) expenseCard = cards[i];
        }

        if (!incomeCard || !expenseCard) return;

        function setFlowValues(card, values, labels) {
            var rows = card.querySelectorAll('.flow-row');
            for (var j = 0; j < rows.length; j++) {
                var label = rows[j].querySelector('.flow-label');
                var value = rows[j].querySelector('.flow-value');
                if (label && labels[j]) label.textContent = labels[j];
                if (value && values[j] !== undefined) value.textContent = formatAmount(values[j]);
            }
        }

        // Revenue is credited; expenses are debited. Display the same ledger truth used by the KPI endpoint.
        setFlowValues(incomeCard,
            [data.monthly_income_total, data.monthly_income_sponsorship, data.monthly_income_admin_fee],
            ['إجمالي الإيرادات', '💰 إيرادات الكفالات — حساب 4100', '💼 الرسوم الإدارية — حساب 4200']);

        setFlowValues(expenseCard,
            [data.monthly_expense_total, data.monthly_expense_programs, data.monthly_expense_salaries],
            ['إجمالي المصروفات', '🧾 مصروفات البرامج والمساعدات — حساب 5100', '👥 الرواتب والأجور — حساب 5200']);
    }

    function loadAccountingKpis() {
        var main = document.querySelector('main.container-fluid');
        if (!main) return;

        var treasuryGrid = main.querySelector('.fm-top-right > .grid-4');
        if (!treasuryGrid) return;

        // The fifth card is structural and must appear even if the KPI endpoint fails.
        prepareTreasuryRow(treasuryGrid);
        var adminFeeCard = document.getElementById('fm-treasury-admin-fee');
        if (!adminFeeCard) {
            adminFeeCard = buildTreasuryAdminFeeCard(null);
            treasuryGrid.appendChild(adminFeeCard);
        }

        var endpoint = new URL('fm_dashboard_kpis.php', window.location.href).toString();
        fetch(endpoint, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('KPI request failed: ' + response.status);
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.ok) throw new Error(data && data.message ? data.message : 'KPI response failed');

                var valueNode = adminFeeCard.querySelector('[data-admin-fee-value]');
                if (valueNode) valueNode.textContent = formatAmount(data.admin_fees_total);

                updateMonthlyFlowCard(data);

                if (!document.getElementById('fm-accounting-kpis')) {
                    var card = buildLedgerKpiCard(data);
                    treasuryGrid.parentNode.insertBefore(card, treasuryGrid.nextSibling);
                }
            })
            .catch(function (error) {
                var valueNode = adminFeeCard.querySelector('[data-admin-fee-value]');
                if (valueNode) valueNode.textContent = '—';
                adminFeeCard.title = 'تعذر تحميل إجمالي الرسوم الإدارية من دفتر الأستاذ: ' + error.message;
                console.error('FM accounting KPI load failed:', error);
            });
    }

    function init() {
        moveQuickStats();
        loadAccountingKpis();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
