import 'package:flutter/material.dart';

class ReportsPage extends StatefulWidget {
  const ReportsPage({super.key, required this.english, required this.loadOverview, required this.openReport});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadOverview;
  final void Function(String key) openReport;
  @override
  State<ReportsPage> createState() => _ReportsPageState();
}

class _ReportsPageState extends State<ReportsPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> stats = {};
  List<Map<String, dynamic>> reports = [];
  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() { super.initState(); refresh(); }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadOverview();
      if (!mounted) return;
      final rawStats = data['stats'];
      final rawReports = data['reports'];
      setState(() {
        stats = rawStats is Map ? Map<String, dynamic>.from(rawStats) : {};
        reports = rawReports is List ? rawReports.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList() : [];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  int metric(String key) => int.tryParse(stats[key]?.toString() ?? '') ?? 0;
  String amount(String key) => (double.tryParse(stats[key]?.toString() ?? '') ?? 0).toStringAsFixed(2);

  String reportLabel(String key, String fallback) {
    if (!widget.english) return fallback;
    return ({
      'financial': 'Financial reports',
      'sponsorship': 'Sponsorship reports',
      'operational': 'Operational reports',
      'hr': 'HR reports',
      'orphaned': 'Uncovered families report',
      'reconciliation': 'Reconciliation report',
      'sponsor_monthly': 'Monthly sponsor report',
      'lost_contact': 'Unreachable families report',
      'confirmed_disbursements': 'Confirmed disbursements',
      'my_financial': 'My financial report',
    })[key] ?? fallback;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(t('مركز التقارير', 'Reports center')), actions: [
        IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
      ]),
      body: loading && stats.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: refresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  Text(t('مؤشرات عامة وفق صلاحيات التقارير الحالية. التقارير غير المنفذة داخل التطبيق لا تفتح موقعاً خارجياً.',
                      'Overview metrics follow your existing report permissions. Reports not implemented natively do not open an external site.'),
                    style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
                  const SizedBox(height: 14),
                  if (error != null) Card(color: Theme.of(context).colorScheme.errorContainer, child: Padding(padding: const EdgeInsets.all(12), child: Text(error!))),
                  GridView.count(
                    crossAxisCount: MediaQuery.sizeOf(context).width > 600 ? 3 : 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 1.45,
                    children: [
                      _Metric(title: t('الرعاة النشطون', 'Active sponsors'), value: metric('sponsors').toString(), icon: Icons.volunteer_activism_rounded),
                      _Metric(title: t('الأسر النشطة', 'Active families'), value: metric('families').toString(), icon: Icons.family_restroom_rounded),
                      _Metric(title: t('الكفالات النشطة', 'Active sponsorships'), value: metric('active_sponsorships').toString(), icon: Icons.favorite_rounded),
                      _Metric(title: t('الالتزام الشهري', 'Monthly commitment'), value: amount('monthly_commitment'), icon: Icons.payments_rounded),
                      _Metric(title: t('أسر بلا كفالة نشطة', 'Families without active sponsorship'), value: metric('orphaned_families').toString(), icon: Icons.people_alt_outlined),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Text(t('التقارير المتاحة حسب الدور', 'Reports available for your role'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  ...reports.map((report) {
                    final key = report['key']?.toString() ?? '';
                    final native = report['native'] == true || ['financial', 'sponsorship', 'operational', 'hr'].contains(key);
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(child: Icon(native ? Icons.phone_android_rounded : Icons.insert_chart_outlined_rounded)),
                        title: Text(reportLabel(key, report['label']?.toString() ?? key)),
                        subtitle: Text(widget.english
                            ? (native ? 'Available in the app' : 'Not implemented natively yet')
                            : (native ? 'متاح داخل التطبيق' : 'غير منفذ داخل التطبيق بعد')),
                        trailing: native ? const Icon(Icons.chevron_right_rounded) : const Icon(Icons.lock_clock_rounded),
                        onTap: native ? () => widget.openReport(key) : null,
                      ),
                    );
                  }),
                ],
              ),
            ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.title, required this.value, required this.icon});
  final String title;
  final String value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Icon(icon, size: 22, color: Theme.of(context).colorScheme.primary),
        Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
        Text(title, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall),
      ]),
    ),
  );
}
