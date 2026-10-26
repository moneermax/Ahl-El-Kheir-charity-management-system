import 'package:flutter/material.dart';

class GmPayrollReportPage extends StatefulWidget {
  const GmPayrollReportPage({super.key, required this.english, required this.month, required this.year, required this.loadReport});
  final bool english;
  final int month;
  final int year;
  final Future<Map<String, dynamic>> Function(int month, int year) loadReport;
  @override
  State<GmPayrollReportPage> createState() => _GmPayrollReportPageState();
}
class _GmPayrollReportPageState extends State<GmPayrollReportPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> report = {};
  String t(String ar, String en) => widget.english ? en : ar;
  String money(dynamic v) => (double.tryParse(v?.toString() ?? '') ?? 0).toStringAsFixed(2);
  @override
  void initState() { super.initState(); refresh(); }
  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try { final data = await widget.loadReport(widget.month, widget.year); if (mounted) setState(() => report = data); }
    catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  @override
  Widget build(BuildContext context) {
    final employees = report['employees'] is List ? (report['employees'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : <Map<String, dynamic>>[];
    return Scaffold(
      backgroundColor: const Color(0xFFF6F4EE),
      appBar: AppBar(title: Text(t('كشف صرف الرواتب','Payroll payment report')), actions: [IconButton(tooltip: t('تحديث','Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded))]),
      body: RefreshIndicator(onRefresh: refresh, child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
        Card(child: Padding(padding: const EdgeInsets.all(15), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text((report['period'] ?? '${widget.year}-${widget.month.toString().padLeft(2,'0')}').toString(), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
          const SizedBox(height: 5),
          Text(t('كشف قراءة فقط؛ لا ينفذ اعتماداً أو صرفاً أو تعديلاً.','Read-only report; no approval, payment or edits are possible.')),
          if (loading) const Padding(padding: EdgeInsets.only(top: 12), child: LinearProgressIndicator()),
          if (error != null) Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
          if (!loading && error == null) ...[
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              _Metric(label: t('المؤهلون','Eligible'), value: '${report['eligible_count'] ?? 0}'),
              _Metric(label: t('المكتمل صرفهم','Completed'), value: '${report['paid_count'] ?? 0}'),
              _Metric(label: t('صافي الرواتب','Net salaries'), value: money(report['net'])),
              _Metric(label: t('الحوافز','Incentives'), value: money(report['incentives'])),
              _Metric(label: t('الإجمالي','Total'), value: money(report['total'])),
            ]),
          ],
        ]))),
        if (!loading && error == null) Card(color: report['complete'] == true ? const Color(0xFFE4F6E9) : const Color(0xFFFFF3CD), child: Padding(padding: const EdgeInsets.all(13), child: Text(report['complete'] == true ? t('اكتمل الصرف والترحيل وإثبات الصرف وإشعار الموظف لكل المؤهلين.','Posting, proof and employee notification are complete for all eligible staff.') : t('الفترة غير مكتملة. راجع حالة كل موظف أدناه.','The period is incomplete. Review each employee below.')))),
        if (!loading && error == null && employees.isEmpty) Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(t('لا يوجد موظفون مؤهلون لهذه الفترة.','No eligible employees for this period.')))),
        for (final e in employees) ...[
          const SizedBox(height: 9),
          Card(child: Padding(padding: const EdgeInsets.all(13), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('${e['full_name'] ?? ''} (${e['employee_code'] ?? ''})', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            const SizedBox(height: 5),
            Text(e['completion_paid'] == true ? t('مكتمل','Complete') : t('غير مكتمل','Incomplete'), style: TextStyle(fontWeight: FontWeight.w800, color: e['completion_paid'] == true ? const Color(0xFF16804A) : const Color(0xFFB42318))),
            const Divider(),
            for (final pair in <List<String>>[
              [t('الراتب الأساسي','Basic salary'), money(e['basic_salary'] ?? e['effective_basic_salary'])],
              [t('البدلات','Allowances'), money(e['allowances'])],
              [t('الإضافي','Overtime'), money(e['overtime'])],
              [t('الخصومات','Deductions'), money(e['deductions'])],
              [t('خصم الحضور','Attendance deduction'), money(e['attendance_deduction'])],
              [t('خصم السلفة','Salary advance deduction'), money(e['salary_advance_deduction'])],
              [t('صافي الراتب','Net salary'), money(e['net_salary'])],
              [t('الحوافز','Incentives'), money(e['incentive_amount'])],
              [t('تاريخ الصرف','Payment date'), (e['payment_date'] ?? '—').toString()],
              [t('قيد اليومية','Journal entry'), (e['accounting_entry_id'] ?? '—').toString()],
              [t('إثبات الصرف','Payment proof'), (e['proof_state'] ?? '—').toString()],
              [t('إشعار كشف الموظف','Statement notification'), e['completion_employee_notification'] == true ? t('مسجل','Recorded') : t('غير مسجل','Not recorded')],
            ])
              Padding(padding: const EdgeInsets.symmetric(vertical: 2), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                SizedBox(width: 130, child: Text(pair[0], style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
                const SizedBox(width: 6), Expanded(child: SelectableText(pair[1], style: const TextStyle(fontSize: 12))),
              ])),
          ]))),
        ],
      ])),
    );
  }
}
class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 120),
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(color: const Color(0xFFE9F2EB), borderRadius: BorderRadius.circular(10)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 11)), const SizedBox(height: 4),
      Text(value, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF15583F))),
    ]),
  );
}
