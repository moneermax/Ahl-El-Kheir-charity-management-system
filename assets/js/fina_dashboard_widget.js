document.addEventListener('DOMContentLoaded', function () {
    var main = document.querySelector('main.container-fluid');
    if (!main) return;

    var base = (window.APP_URL || '').replace(/\/$/, '/');
    if (!base) {
        var marker = '/modules/accounting/';
        var path = window.location.pathname;
        var markerPos = path.indexOf(marker);
        if (markerPos >= 0) base = window.location.origin + path.substring(0, markerPos + 1);
    }
    if (!base) return;

    fetch(base + 'modules/accounting/fina_dashboard_summary.php', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('request')); })
        .then(function (data) {
            if (!data || !data.ok) return;
            var p = data.pending || {count:0};
            var a = data.approved || {count:0,amount:0};
            var ret = data.returned || {count:0};
            var acc = data.account || {};
            var currencies = data.currencies || [];
            var statusLabels = { pending:'معلق', approved:'معتمد', returned:'مرتجع' };
            var currencyText = currencies.length ? currencies.map(function (row) {
                return row.currency_code + ': ' + Number(row.amount).toLocaleString() + ' (' + (statusLabels[row.status] || row.status) + ')';
            }).join(' — ') : 'لا توجد حركة مسجلة';
            var card = document.createElement('div');
            card.className = 'fm-card fina-dashboard-card';
            card.style.borderRight = '5px solid #6f42c1';
            card.innerHTML = '<style>.fina-dashboard-card .grid-4{grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}.fina-dashboard-card .stat-box{padding:10px 8px;border-radius:8px}.fina-dashboard-card .stat-value{font-size:1.25rem;margin:4px 0}.fina-dashboard-card .stat-label{font-size:.76rem;line-height:1.25}.fina-dashboard-card .stat-sub{font-size:.68rem;line-height:1.2}.fina-dashboard-card .fina-brand{display:flex;align-items:center;gap:10px}.fina-dashboard-card .fina-logo{width:52px;height:52px;object-fit:contain;border-radius:8px;background:#fff;border:1px solid #e9ecef;padding:3px}.fina-dashboard-card .fina-name-en{font-size:.72rem;color:#6c757d;font-weight:600;margin-top:1px}.fina-dashboard-card .fina-account-line{font-size:.82rem;color:#6c757d;margin-top:3px}@media(max-width:767px){.fina-dashboard-card .grid-4{grid-template-columns:repeat(2,minmax(0,1fr))}}</style>' +
                '<div class="fm-card-head"><div class="fina-brand"><img src="'+base+'assets/img/Feen_logo.jpeg" alt="منظمة فينا الخير — Feena Al-Khair" class="fina-logo"><div><span>منظمة فينا الخير</span><div class="fina-name-en">Feena Al-Khair</div><div class="fina-account-line">حساب الالتزام: 2300 — '+(acc.is_active ? 'الحساب نشط' : 'الحساب موقوف')+'</div></div></div><span class="badge-fm badge-blue">'+p.count+' بانتظار المراجعة</span></div>' +
                '<div class="fm-card-body">' +
                '<div class="grid-4">' +
                    '<div class="stat-box amber"><div class="stat-value">'+p.count+'</div><div class="stat-label">طلبات بانتظار المراجعة</div><div class="stat-sub">يجب مراجعتها من المدير المالي</div></div>' +
                    '<div class="stat-box green"><div class="stat-value">'+a.count+'</div><div class="stat-label">تحصيلات معتمدة</div><div class="stat-sub">لا تشمل إيرادات أهل الخير</div></div>' +
                    '<div class="stat-box red"><div class="stat-value">'+ret.count+'</div><div class="stat-label">تحصيلات مرتجعة</div><div class="stat-sub">مستبعدة من إجمالي المعتمد</div></div>' +
                    '<div class="stat-box purple"><div class="stat-value">'+Number(a.amount || 0).toLocaleString()+' SDG</div><div class="stat-label">إجمالي التحصيلات المعتمدة للتسوية</div><div class="stat-sub">التحصيلات المعتمدة فقط — المرتجعات مستبعدة</div></div>' +
                '</div>' +
                '<div class="small text-muted mt-2"><strong>الحركة حسب العملة:</strong> '+currencyText+'</div>' +
                '<div class="d-flex flex-wrap gap-2 mt-3">' +
                    '<a class="btn-fm btn-navy" href="'+base+'modules/accounting/fina_payment_review.php"><i class="fas fa-clipboard-check me-1"></i>مراجعة منظمة فينا الخير</a>' +
                    '<a class="btn-fm btn-ghost" href="'+base+'modules/accounting/fina_settlements.php"><i class="fas fa-money-bill-transfer me-1"></i>تسويات منظمة فينا الخير</a>' +
                    '<a class="btn-fm btn-ghost" href="'+base+'modules/accounting/fina_payment_history.php"><i class="fas fa-clock-rotate-left me-1"></i>سجل منظمة فينا الخير</a>' +
                    '<a class="btn-fm btn-ghost" href="'+base+'modules/accounting/fina_payment_report.php"><i class="fas fa-file-chart-column me-1"></i>تقرير منظمة فينا الخير</a>' +
                    '<a class="btn-fm btn-ghost" href="'+base+'modules/accounting/accounts.php"><i class="fas fa-scale-balanced me-1"></i>حساب 2300</a>' +
                '</div>' +
                '<div class="small text-muted mt-2">إجمالي المعتمد هنا هو مجموع التحصيلات ذات الحالة «معتمد» فقط؛ وهو يختلف عن الرصيد غير المسدد في شاشة التسويات. أموال منظمة فينا الخير مستقلة عن إيرادات الكفالات والرسوم الإدارية.</div>' +
                '</div>';
            var anchor = main.querySelector('.fm-top-layout');
            if (anchor) anchor.insertAdjacentElement('afterend', card);
            else main.appendChild(card);
        })
        .catch(function () {});
});
