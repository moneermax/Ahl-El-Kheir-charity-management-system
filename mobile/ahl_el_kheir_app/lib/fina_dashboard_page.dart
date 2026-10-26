import 'package:flutter/material.dart';

class FinaDashboardPage extends StatefulWidget {
  const FinaDashboardPage({super.key, required this.english, required this.loadDashboard, required this.onOpenReview});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadDashboard;
  final VoidCallback onOpenReview;

  @override
  State<FinaDashboardPage> createState() => _FinaDashboardPageState();
}

class _FinaDashboardPageState extends State<FinaDashboardPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.loadDashboard();
      if (mounted) setState(() => data = response);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Widget metric(String title, String value, IconData icon, Color color) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(children: [
        Container(width: 42, height: 42, decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(12)), child: Icon(icon, color: color)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(fontSize: 12, color: Color(0xFF647067))),
          const SizedBox(height: 4),
          Text(value, style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800)),
        ])),
      ]),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final account = data['liability_account'] is Map ? Map<String, dynamic>.from(data['liability_account'] as Map) : <String, dynamic>{};
    final canReview = data['can_view_review'] == true;
    final configured = account['configured'] == true;
    return Scaffold(
      backgroundColor: const Color(0xFFF6F4EE),
      appBar: AppBar(
        title: Text(t('لوحة فينا الخير', 'Fina Al-Khair dashboard')),
        actions: [IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded))],
      ),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(t('منظمة فينا الخير', 'Fina Al-Khair Organization'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text(t('ملخص مستقل لتحصيلات فينا الخير. التسجيل لا ينشئ قيداً محاسبياً؛ الاعتماد المالي هو الذي يرحّل القيد.', 'A standalone Fina collection summary. Registration does not post an accounting entry; finance approval performs posting.')),
            ]))),
            if (loading) const Padding(padding: EdgeInsets.all(12), child: LinearProgressIndicator()),
            if (error != null) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(children: [
              Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
              TextButton(onPressed: refresh, child: Text(t('إعادة المحاولة', 'Retry'))),
            ]))),
            if (!loading && error == null) ...[
              metric(t('بانتظار المراجعة', 'Pending review'), (data['pending_count'] ?? 0).toString(), Icons.hourglass_top_rounded, const Color(0xFFB7791F)),
              metric(t('التحصيلات المعتمدة', 'Approved collections'), (data['approved_count'] ?? 0).toString(), Icons.check_circle_outline_rounded, const Color(0xFF237A4B)),
              metric(t('التحصيلات المرتجعة', 'Returned collections'), (data['returned_count'] ?? 0).toString(), Icons.assignment_return_outlined, const Color(0xFFB42318)),
              metric(t('إجمالي المعتمد', 'Approved amount'), (data['approved_amount'] ?? 0).toString() + ' ' + (data['currency_code']?.toString() ?? ''), Icons.account_balance_wallet_outlined, const Color(0xFF6F42C1)),
              const SizedBox(height: 8),
              Card(child: ListTile(
                leading: Icon(configured && account['active'] == true ? Icons.verified_rounded : Icons.warning_amber_rounded, color: configured && account['active'] == true ? const Color(0xFF237A4B) : const Color(0xFFB7791F)),
                title: Text(t('حساب الالتزام', 'Liability account') + ' ' + (account['code']?.toString() ?? '2300')),
                subtitle: Text(configured && account['active'] == true
                  ? t('الحساب مهيأ ونشط.', 'Account is configured and active.')
                  : t('الحساب غير مهيأ أو غير نشط. يلزم مراجعة الإعداد المحاسبي.', 'Account is missing or inactive; accounting setup needs review.')),
              )),
              if (canReview) ...[
                const SizedBox(height: 8),
                FilledButton.icon(onPressed: widget.onOpenReview, icon: const Icon(Icons.fact_check_outlined), label: Text(t('مراجعة التحصيلات', 'Review collections'))),
              ],
            ],
          ],
        ),
      ),
    );
  }
}
