import 'package:flutter/material.dart';

class AdminDashboardPage extends StatefulWidget {
  const AdminDashboardPage({
    super.key,
    required this.english,
    required this.fullName,
    required this.loadOverview,
    required this.onOpenTool,
    required this.onOpenAgeAlerts,
  });

  final bool english;
  final String fullName;
  final Future<Map<String, dynamic>> Function() loadOverview;
  final void Function(String tool) onOpenTool;
  final VoidCallback onOpenAgeAlerts;

  @override
  State<AdminDashboardPage> createState() => _AdminDashboardPageState();
}

class _AdminDashboardPageState extends State<AdminDashboardPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> stats = {};
  List<Map<String, dynamic>> audit = [];
  Map<String, dynamic> server = {};

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.loadOverview();
      if (!mounted) return;
      final rawStats = response['stats'];
      final rawAudit = response['recent_audit'];
      final rawServer = response['server'];
      setState(() {
        stats = rawStats is Map ? Map<String, dynamic>.from(rawStats) : {};
        audit = rawAudit is List
            ? rawAudit.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
            : [];
        server = rawServer is Map ? Map<String, dynamic>.from(rawServer) : {};
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String t(String ar, String en) => widget.english ? en : ar;

  int metric(String key) => int.tryParse(stats[key]?.toString() ?? '') ?? 0;

  @override
  Widget build(BuildContext context) {
    const ink = Color(0xFF172033);
    const muted = Color(0xFF758198);
    const canvas = Color(0xFFF5F7FC);
    const violet = Color(0xFF7057D9);
    const cyan = Color(0xFF21B6B0);
    const orange = Color(0xFFFF9F43);
    const rose = Color(0xFFE75A7C);

    return Scaffold(
      backgroundColor: canvas,
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 30),
          children: [
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(28),
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFF171C35), Color(0xFF373B76), Color(0xFF7358D7)],
                ),
                boxShadow: [
                  BoxShadow(color: violet.withValues(alpha: .20), blurRadius: 28, offset: const Offset(0, 12)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 46,
                        height: 46,
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .13), borderRadius: BorderRadius.circular(15)),
                        child: const Icon(Icons.admin_panel_settings_rounded, color: Colors.white, size: 27),
                      ),
                      const SizedBox(width: 12),
                      Expanded(child: Text(t('لوحة تحكم النظام', 'SYSTEM CONTROL CENTER'),
                        style: const TextStyle(color: Color(0xFFB9B5FF), fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 1.1))),
                      IconButton(
                        tooltip: t('تحديث البيانات', 'Refresh data'),
                        onPressed: refresh,
                        color: Colors.white,
                        icon: const Icon(Icons.refresh_rounded),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Text(t('مرحباً بعودتك', 'Welcome back'),
                    style: const TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  Text(widget.fullName, style: const TextStyle(color: Colors.white, fontSize: 23, height: 1.25, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 10),
                  Text(t('إدارة المستخدمين والبيانات والمالية وأدوات حماية النظام من مساحة واحدة.',
                    'Manage accounts, core records, finance and system protection from one workspace.'),
                    style: const TextStyle(color: Color(0xFFDADCF4), fontSize: 12, height: 1.7)),
                  const SizedBox(height: 16),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    _StatusPill(label: t('قاعدة البيانات', 'Database'), value: server['database_connected'] == true ? t('متصلة', 'Connected') : t('غير متاحة', 'Unavailable'), good: server['database_connected'] == true),
                    _StatusPill(label: 'PHP', value: server['php_version']?.toString() ?? '—', good: true),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 22),
            Row(children: [
              Expanded(child: Text(t('نظرة عامة', 'SYSTEM AT A GLANCE'), style: const TextStyle(color: ink, fontSize: 15, fontWeight: FontWeight.w900, letterSpacing: .4))),
              if (loading) const SizedBox(width: 15, height: 15, child: CircularProgressIndicator(strokeWidth: 2)),
            ]),
            const SizedBox(height: 12),
            if (error != null)
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: const Color(0xFFFFF0F0), borderRadius: BorderRadius.circular(16)),
                child: Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
              ),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.32,
              children: [
                _MetricCard(label: t('المستخدمون', 'Users'), value: metric('users'), icon: Icons.groups_rounded, color: violet),
                _MetricCard(label: t('الكفلاء', 'Sponsors'), value: metric('sponsors'), icon: Icons.volunteer_activism_rounded, color: orange),
                _MetricCard(label: t('الأسر', 'Families'), value: metric('families'), icon: Icons.home_work_rounded, color: cyan),
                _MetricCard(label: t('الكفالات', 'Sponsorships'), value: metric('sponsorships'), icon: Icons.handshake_rounded, color: rose),
              ],
            ),
            const SizedBox(height: 24),
            _SectionHeading(title: t('إدارة النظام', 'SYSTEM ADMINISTRATION'), subtitle: t('الحسابات والبنية الأساسية', 'Accounts and organization setup'), icon: Icons.tune_rounded, color: violet),
            const SizedBox(height: 10),
            _ToolTile(icon: Icons.manage_accounts_rounded, color: violet, title: t('المستخدمون', 'User accounts'), description: t('إنشاء الحسابات وإدارة المستخدمين', 'Create and manage user accounts'), tag: t('إدارة', 'ADMIN'), onTap: () => widget.onOpenTool('users')),
            _ToolTile(icon: Icons.apartment_rounded, color: violet, title: t('الأقسام', 'Departments'), description: t('إدارة أقسام المنظمة', 'Manage organization departments'), tag: t('إدارة', 'ADMIN'), onTap: () => widget.onOpenTool('departments')),
            _ToolTile(icon: Icons.settings_rounded, color: violet, title: t('إعدادات النظام', 'System settings'), description: t('بيانات المنظمة والإعدادات العامة', 'Organization profile and global settings'), tag: t('إدارة', 'ADMIN'), onTap: () => widget.onOpenTool('settings')),
            const SizedBox(height: 22),
            _SectionHeading(title: t('البيانات الأساسية', 'CORE RECORDS'), subtitle: t('السجلات التشغيلية الرئيسية', 'Primary operational records'), icon: Icons.hub_rounded, color: cyan),
            const SizedBox(height: 10),
            _ToolTile(icon: Icons.volunteer_activism_rounded, color: orange, title: t('الكفلاء', 'Sponsors'), description: t('سجل الكفلاء وبياناتهم', 'Sponsor directory and records'), onTap: () => widget.onOpenTool('sponsors')),
            _ToolTile(icon: Icons.family_restroom_rounded, color: cyan, title: t('الأسر', 'Families'), description: t('سجل الأسر والبيانات المرتبطة بها', 'Family records and linked information'), onTap: () => widget.onOpenTool('families')),
            _ToolTile(icon: Icons.handshake_rounded, color: rose, title: t('الكفالات', 'Sponsorships'), description: t('سجل الكفالات وعلاقاتها', 'Sponsorship records and relationships'), onTap: () => widget.onOpenTool('sponsorships')),
            _ToolTile(icon: Icons.query_stats_rounded, color: cyan, title: t('التقارير', 'Reports'), description: t('مركز التقارير الموحد', 'Unified reporting center'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('reports')),
            const SizedBox(height: 22),
            _SectionHeading(title: t('التشغيل والأفراد', 'OPERATIONS & PEOPLE'), subtitle: t('شاشات الهاتف المتاحة للإدارة', 'Native mobile workflows available to administrators'), icon: Icons.workspaces_rounded, color: violet),
            const SizedBox(height: 10),
            _ToolTile(icon: Icons.work_rounded, color: violet, title: t('المشروعات', 'Projects'), description: t('متابعة المشروعات والتقدم والمعالم', 'Review projects, progress and milestones'), onTap: () => widget.onOpenTool('projects')),
            _ToolTile(icon: Icons.badge_rounded, color: violet, title: t('الموظفون', 'Employees'), description: t('دليل الموظفين وتفاصيل ملفاتهم', 'Employee directory and profile details'), onTap: () => widget.onOpenTool('employees')),
            _ToolTile(icon: Icons.fact_check_rounded, color: cyan, title: t('الحضور والانصراف', 'Attendance'), description: t('مراجعة سجلات الحضور حسب التاريخ', 'Review attendance by date'), onTap: () => widget.onOpenTool('attendance')),
            _ToolTile(icon: Icons.supervisor_account_rounded, color: cyan, title: t('المشرفون', 'Supervisors'), description: t('عرض المشرفين والتكليفات والحالة', 'Review supervisors, assignments and status'), onTap: () => widget.onOpenTool('supervisors')),
            _ToolTile(icon: Icons.campaign_rounded, color: orange, title: t('طلبات الرعاية', 'Sponsor requests'), description: t('مراجعة طلبات الرعاية وحالاتها', 'Review sponsor leads and statuses'), onTap: () => widget.onOpenTool('sponsor_requests')),
            _ToolTile(icon: Icons.rule_folder_rounded, color: orange, title: t('طابور المراجعة المالية', 'Financial review queue'), description: t('مراجعة التحصيلات والقرارات المالية', 'Review pending collections and finance decisions'), onTap: () => widget.onOpenTool('fm_review_queue')),
            const SizedBox(height: 22),
            _SectionHeading(title: t('المالية', 'FINANCE'), subtitle: t('أدوات المتابعة المالية المتاحة للمدير', 'Financial oversight tools'), icon: Icons.account_balance_wallet_rounded, color: orange),
            const SizedBox(height: 10),
            _ToolTile(icon: Icons.dashboard_rounded, color: orange, title: t('لوحة المالية', 'Finance dashboard'), description: t('ملخص ومؤشرات الإدارة المالية', 'Finance management overview'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('finance_dashboard')),
            _ToolTile(icon: Icons.account_tree_rounded, color: orange, title: t('دليل الحسابات', 'Chart of accounts'), description: t('مراجعة الحسابات المحاسبية', 'Review accounting accounts'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('accounts')),
            _ToolTile(icon: Icons.receipt_long_rounded, color: orange, title: t('سجل المعاملات', 'Transaction ledger'), description: t('متابعة سجل المعاملات المالية', 'Review financial transaction records'), onTap: () => widget.onOpenTool('transactions')),
            _ToolTile(icon: Icons.payments_rounded, color: orange, title: t('التحويلات الشهرية', 'Monthly disbursements'), description: t('متابعة دفعات الأسر الشهرية', 'Review monthly family payments'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('disbursements')),
            _ToolTile(icon: Icons.price_check_rounded, color: orange, title: t('تحصيل فينا الخير', 'Fina Al-Khair collection'), description: t('فتح مسار تحصيل فينا الخير', 'Open Fina Al-Khair collection workflow'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('fina_collection')),
            const SizedBox(height: 22),
            _SectionHeading(title: t('الحماية والصيانة', 'PROTECTION & MAINTENANCE'), subtitle: t('أدوات حساسة للنظام', 'Sensitive system controls'), icon: Icons.shield_rounded, color: rose),
            const SizedBox(height: 10),
            _ToolTile(icon: Icons.backup_rounded, color: rose, title: t('النسخ الاحتياطي', 'Database backup'), description: t('إنشاء وإدارة نسخ قاعدة البيانات', 'Create and manage database backups'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('backup')),
            _ToolTile(icon: Icons.storage_rounded, color: rose, title: t('إدارة قاعدة البيانات', 'Database tools'), description: t('أدوات إدارة قاعدة البيانات', 'Database administration tools'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('database')),
            _ToolTile(icon: Icons.fact_check_rounded, color: rose, title: t('سجل التدقيق', 'Audit log'), description: t('مراجعة الأحداث الإدارية المسجلة', 'Review recorded administrative events'), tag: t('ويب', 'WEB'), onTap: () => widget.onOpenTool('audit')),
            const SizedBox(height: 22),
            _SectionHeading(title: t('آخر أنشطة التدقيق', 'RECENT AUDIT ACTIVITY'), subtitle: t('آخر ستة أحداث مسجلة', 'Latest six recorded events'), icon: Icons.history_rounded, color: violet),
            const SizedBox(height: 10),
            if (audit.isEmpty)
              Container(padding: const EdgeInsets.all(18), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)), child: Text(t('لا توجد أحداث حديثة أو تعذر تحميلها.', 'No recent events, or they could not be loaded.'), style: const TextStyle(color: muted)))
            else
              ...audit.map((item) => Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFE7EAF2))),
                child: Row(children: [
                  Container(width: 38, height: 38, decoration: BoxDecoration(color: const Color(0xFFEDE9FF), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.receipt_long_rounded, color: violet)),
                  const SizedBox(width: 11),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(item['action']?.toString() ?? '—', style: const TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 13)),
                    const SizedBox(height: 3),
                    Text('${item['username'] ?? '—'} · ${item['entity_type'] ?? '—'} #${item['entity_id'] ?? '—'}', style: const TextStyle(color: muted, fontSize: 11)),
                    const SizedBox(height: 2),
                    Text(item['created_at']?.toString() ?? '', style: const TextStyle(color: muted, fontSize: 10)),
                  ])),
                ]),
              )),
            const SizedBox(height: 16),
            _ToolTile(icon: Icons.hourglass_bottom_rounded, color: orange, title: t('تنبيه بلوغ السن القانوني', 'Legal-age alerts'), description: t('مراجعة الأطفال ضمن نافذة التنبيه', 'Review children in the alert window'), onTap: widget.onOpenAgeAlerts),
            const SizedBox(height: 12),
            Center(child: Text(t('لوحة مخصصة لحساب المدير / مطور النظام', 'Dedicated administrator and system-developer workspace'), style: const TextStyle(color: muted, fontSize: 11))),
          ],
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.value, required this.good});
  final String label;
  final String value;
  final bool good;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
    decoration: BoxDecoration(color: Colors.white.withValues(alpha: .10), borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.white.withValues(alpha: .12))),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(good ? Icons.check_circle_rounded : Icons.error_outline_rounded, color: good ? const Color(0xFF7EE3B2) : const Color(0xFFFFC3C3), size: 14),
      const SizedBox(width: 6),
      Text('$label: $value', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
    ]),
  );
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value, required this.icon, required this.color});
  final String label;
  final int value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), border: Border.all(color: const Color(0xFFE7EAF2)), boxShadow: [BoxShadow(color: color.withValues(alpha: .06), blurRadius: 14, offset: const Offset(0, 5))]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: color.withValues(alpha: .11), borderRadius: BorderRadius.circular(12)), child: Icon(icon, color: color, size: 19)),
        const Spacer(),
        Icon(Icons.north_east_rounded, color: color.withValues(alpha: .65), size: 16),
      ]),
      const Spacer(),
      Text(value.toString(), style: const TextStyle(color: Color(0xFF172033), fontSize: 24, fontWeight: FontWeight.w900)),
      const SizedBox(height: 3),
      Text(label, style: const TextStyle(color: Color(0xFF758198), fontSize: 12, fontWeight: FontWeight.w700)),
    ]),
  );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle, required this.icon, required this.color});
  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Row(children: [
    Container(width: 42, height: 42, decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(14)), child: Icon(icon, color: color)),
    const SizedBox(width: 11),
    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: const TextStyle(color: Color(0xFF172033), fontSize: 14, fontWeight: FontWeight.w900)),
      const SizedBox(height: 2),
      Text(subtitle, style: const TextStyle(color: Color(0xFF758198), fontSize: 11, height: 1.4)),
    ])),
  ]);
}

class _ToolTile extends StatelessWidget {
  const _ToolTile({required this.icon, required this.color, required this.title, required this.description, required this.onTap, this.tag});
  final IconData icon;
  final Color color;
  final String title;
  final String description;
  final String? tag;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(17), border: Border.all(color: const Color(0xFFE7EAF2))),
    child: ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      leading: Container(width: 42, height: 42, decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(13)), child: Icon(icon, color: color)),
      title: Text(title, style: const TextStyle(color: Color(0xFF172033), fontWeight: FontWeight.w800, fontSize: 13)),
      subtitle: Padding(padding: const EdgeInsets.only(top: 3), child: Text(description, style: const TextStyle(color: Color(0xFF758198), fontSize: 11, height: 1.4))),
      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
        if (tag != null) Container(padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4), decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(7)), child: Text(tag!, style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w900))),
        const SizedBox(width: 6),
        const Icon(Icons.chevron_right_rounded, color: Color(0xFF9AA3B5)),
      ]),
    ),
  );
}
