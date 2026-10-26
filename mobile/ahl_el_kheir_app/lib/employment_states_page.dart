import 'package:flutter/material.dart';

class EmploymentStatesPage extends StatefulWidget {
  const EmploymentStatesPage({super.key, required this.english, required this.loadPage});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadPage;
  @override
  State<EmploymentStatesPage> createState() => _EmploymentStatesPageState();
}
class _EmploymentStatesPageState extends State<EmploymentStatesPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> metrics = {};
  List<Map<String, dynamic>> states = [];
  List<Map<String, dynamic>> employees = [];
  String t(String ar, String en) => widget.english ? en : ar;
  @override
  void initState() { super.initState(); refresh(); }
  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadPage();
      if (!mounted) return;
      setState(() {
        metrics = data['metrics'] is Map ? Map<String, dynamic>.from(data['metrics'] as Map) : {};
        states = data['states'] is List ? (data['states'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
        employees = data['employees'] is List ? (data['employees'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
      });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  String legacyLabel(dynamic value) {
    final status = value?.toString() ?? '';
    const ar = {'active':'نشط','suspended':'موقوف مؤقتاً','on_leave':'في إجازة','terminated':'منهي الخدمة'};
    const en = {'active':'Active','suspended':'Temporarily suspended','on_leave':'On leave','terminated':'Terminated'};
    return (widget.english ? en : ar)[status] ?? (status.isEmpty ? '—' : status);
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(title: Text(t('الحالة الوظيفية','Employment states')), actions: [IconButton(tooltip: t('تحديث','Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded))]),
    body: RefreshIndicator(onRefresh: refresh, child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
      Card(child: Padding(padding: const EdgeInsets.all(15), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(t('مرجع دورة حياة الموظف — قراءة فقط','Employee lifecycle reference — read only'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        const SizedBox(height: 5),
        Text(t('يعرض الحالة الحالية وسجلها دون تغيير أي بيانات.','Displays current employment state and history without changing data.')),
        if (loading) const Padding(padding: EdgeInsets.only(top: 10), child: LinearProgressIndicator()),
        if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          _StateMetric(label: t('الحالات المعرفة','Defined states'), value: metrics['defined_states'] ?? states.length),
          _StateMetric(label: t('الموظفون المرتبطون','Employees linked'), value: metrics['linked_employees'] ?? 0),
          _StateMetric(label: t('سجلات التاريخ الحالية','Current history rows'), value: metrics['current_history_rows'] ?? 0),
        ]),
        const SizedBox(height: 12),
        Text(t('الحالات المتاحة','Available states'), style: const TextStyle(fontWeight: FontWeight.w800)),
        for (final state in states)
          ListTile(dense: true, contentPadding: EdgeInsets.zero, title: Text((widget.english ? state['name_en'] : state['name_ar'])?.toString() ?? state['code']?.toString() ?? ''), subtitle: Text('${state['code'] ?? ''} · ${state['category'] ?? ''}')),
      ]))),
      if (!loading && error == null && employees.isEmpty) Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(t('لا توجد سجلات موظفين.','No employee records found.')))),
      for (final e in employees) ...[
        const SizedBox(height: 8),
        Card(child: Padding(padding: const EdgeInsets.all(13), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('${e['full_name'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          Text((e['employee_code'] ?? '').toString(), style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12)),
          const Divider(),
          _StateRow(label: t('الحالة الوظيفية','Employment state'), value: (widget.english ? e['state_name_en'] : e['state_name'])?.toString() ?? '—'),
          _StateRow(label: t('رمز الحالة','State code'), value: e['state_code']?.toString() ?? '—'),
          _StateRow(label: t('الحالة القديمة','Legacy status'), value: legacyLabel(e['status'])),
          _StateRow(label: t('بداية السجل','Effective from'), value: (e['history_from'] ?? e['employment_state_changed_at'] ?? '—').toString()),
          _StateRow(label: t('سبب السجل','History reason'), value: e['history_reason']?.toString() ?? '—'),
        ]))),
      ],
    ])),
  );
}
class _StateMetric extends StatelessWidget {
  const _StateMetric({required this.label, required this.value});
  final String label;
  final dynamic value;
  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 115),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(10)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 11)), const SizedBox(height: 4),
      Text(value.toString(), style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Theme.of(context).colorScheme.onPrimaryContainer)),
    ]),
  );
}
class _StateRow extends StatelessWidget {
  const _StateRow({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 3), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
    SizedBox(width: 125, child: Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
    const SizedBox(width: 7), Expanded(child: SelectableText(value, style: const TextStyle(fontSize: 12))),
  ]));
}
