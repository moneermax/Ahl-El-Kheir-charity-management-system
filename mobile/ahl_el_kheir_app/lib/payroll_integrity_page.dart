import 'package:flutter/material.dart';

class PayrollIntegrityPage extends StatefulWidget {
  const PayrollIntegrityPage({super.key, required this.english, required this.loadChecks});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadChecks;

  @override
  State<PayrollIntegrityPage> createState() => _PayrollIntegrityPageState();
}

class _PayrollIntegrityPageState extends State<PayrollIntegrityPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> checks = [];
  int passed = 0;
  int failed = 0;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadChecks();
      if (!mounted) return;
      setState(() {
        checks = data['checks'] is List
            ? (data['checks'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
            : [];
        passed = int.tryParse(data['passed_checks']?.toString() ?? '') ?? 0;
        failed = int.tryParse(data['failed_checks']?.toString() ?? '') ?? 0;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF6F4EE),
    appBar: AppBar(
      title: Text(t('سلامة الرواتب', 'Payroll integrity')),
      actions: [IconButton(
        tooltip: t('إعادة الفحص', 'Run checks again'),
        onPressed: loading ? null : refresh,
        icon: const Icon(Icons.refresh_rounded),
      )],
    ),
    body: RefreshIndicator(
      onRefresh: refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          Card(child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(t('فحص تشخيصي للقراءة فقط', 'Read-only diagnostic'),
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text(t(
                'يفحص الربط بين مسيرات الرواتب والقيود المحاسبية دون تعديل أي سجل.',
                'Checks payroll-to-accounting consistency without changing any records.',
              )),
              const SizedBox(height: 14),
              Row(children: [
                Expanded(child: _IntegrityMetric(label: t('الفحوص السليمة', 'Passed'), value: passed, color: const Color(0xFF16804A))),
                const SizedBox(width: 8),
                Expanded(child: _IntegrityMetric(label: t('تحتاج معالجة', 'Needs attention'), value: failed, color: failed > 0 ? const Color(0xFFB42318) : const Color(0xFF16804A))),
              ]),
              if (loading) const Padding(padding: EdgeInsets.only(top: 12), child: LinearProgressIndicator()),
              if (error != null) ...[
                const SizedBox(height: 12),
                Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
                Align(alignment: AlignmentDirectional.centerStart,
                  child: TextButton(onPressed: loading ? null : refresh, child: Text(t('إعادة المحاولة', 'Retry')))),
              ],
            ]),
          )),
          for (final check in checks) ...[
            const SizedBox(height: 10),
            _IntegrityCheckCard(english: widget.english, check: check),
          ],
        ],
      ),
    ),
  );
}

class _IntegrityMetric extends StatelessWidget {
  const _IntegrityMetric({required this.label, required this.value, required this.color});
  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: color.withValues(alpha: .08), borderRadius: BorderRadius.circular(10)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 12)),
      const SizedBox(height: 4),
      Text(value.toString(), style: TextStyle(fontSize: 25, fontWeight: FontWeight.w800, color: color)),
    ]),
  );
}

class _IntegrityCheckCard extends StatelessWidget {
  const _IntegrityCheckCard({required this.english, required this.check});
  final bool english;
  final Map<String, dynamic> check;

  @override
  Widget build(BuildContext context) {
    final ok = check['ok'] == true;
    final rows = check['rows'] is List ? (check['rows'] as List).whereType<Map>().toList() : <Map>[];
    final count = int.tryParse(check['count']?.toString() ?? '') ?? 0;
    final title = check['title']?.toString() ?? '';
    final summary = check['summary']?.toString() ?? '';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
              decoration: BoxDecoration(
                color: ok ? const Color(0xFFDDF5E6) : const Color(0xFFFCE4E4),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(ok ? (english ? 'PASS' : 'سليم') : (english ? 'ISSUE' : 'مشكلة'),
                style: TextStyle(color: ok ? const Color(0xFF12663A) : const Color(0xFFB42318), fontWeight: FontWeight.w800, fontSize: 11)),
            ),
          ]),
          const SizedBox(height: 6),
          Text(summary, style: const TextStyle(color: Color(0xFF667085), fontSize: 12)),
          if (count > 0) ...[
            const SizedBox(height: 10),
            Text(english ? '$count record(s)' : '$count سجل', style: const TextStyle(fontWeight: FontWeight.w800)),
            if (check['rows_truncated'] == true)
              Text(english ? 'Showing the first 100 records.' : 'يتم عرض أول 100 سجل فقط.', style: const TextStyle(fontSize: 11)),
            for (final row in rows) ...[
              const Divider(height: 16),
              for (final entry in row.entries)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    SizedBox(width: 105, child: Text(entry.key, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
                    const SizedBox(width: 6),
                    Expanded(child: SelectableText(entry.value?.toString() ?? 'NULL', style: const TextStyle(fontSize: 11))),
                  ]),
                ),
            ],
          ],
        ]),
      ),
    );
  }
}
