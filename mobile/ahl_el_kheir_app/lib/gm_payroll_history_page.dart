import 'package:flutter/material.dart';

class GmPayrollHistoryPage extends StatefulWidget {
  const GmPayrollHistoryPage({super.key, required this.english, required this.loadHistory, required this.openReport});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadHistory;
  final void Function(int month, int year) openReport;
  @override
  State<GmPayrollHistoryPage> createState() => _GmPayrollHistoryPageState();
}
class _GmPayrollHistoryPageState extends State<GmPayrollHistoryPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> periods = [];
  String t(String ar, String en) => widget.english ? en : ar;
  @override
  void initState() { super.initState(); refresh(); }
  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadHistory();
      if (mounted) setState(() => periods = data['periods'] is List ? (data['periods'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : []);
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  String periodName(Map<String, dynamic> item) {
    final month = int.tryParse(item['month']?.toString() ?? '') ?? 0;
    final year = item['year']?.toString() ?? '';
    const ar = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    const en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    final names = widget.english ? en : ar;
    return (month > 0 && month <= 12 ? names[month - 1] : item['period']?.toString() ?? '') + ' ' + year;
  }
  String money(dynamic v) => (double.tryParse(v?.toString() ?? '') ?? 0).toStringAsFixed(2);
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF6F4EE),
    appBar: AppBar(title: Text(t('سجل فترات الرواتب','Payroll period history')), actions: [IconButton(tooltip: t('تحديث','Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded))]),
    body: RefreshIndicator(onRefresh: refresh, child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(t('سجل للعرض فقط','Read-only history'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Text(t('يكتمل الصرف عند اكتمال الترحيل المحاسبي وإثبات الصرف وإشعار كشف الموظف.','Completion requires accounting posting, accepted payment proof and employee statement notification.')),
        if (loading) const Padding(padding: EdgeInsets.only(top: 12), child: LinearProgressIndicator()),
        if (error != null) Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
      ]))),
      if (!loading && error == null && periods.isEmpty) Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(t('لا توجد فترات صرف مسجلة.','No paid payroll periods recorded.')))),
      for (final item in periods) ...[
        const SizedBox(height: 10),
        Card(child: InkWell(onTap: () => widget.openReport(int.tryParse(item['month']?.toString() ?? '') ?? 0, int.tryParse(item['year']?.toString() ?? '') ?? 0), child: Padding(padding: const EdgeInsets.all(15), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [Expanded(child: Text(periodName(item), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))), Text(item['complete'] == true ? t('مكتملة','Complete') : t('غير مكتملة','Incomplete'), style: TextStyle(fontWeight: FontWeight.w800, color: item['complete'] == true ? const Color(0xFF16804A) : const Color(0xFFB42318)))]),
          const SizedBox(height: 8),
          Wrap(spacing: 12, runSpacing: 8, children: [
            Text(t('المصروف لهم: ','Paid: ') + '${item['paid_count'] ?? 0}'),
            Text(t('المؤهلون: ','Eligible: ') + '${item['eligible_count'] ?? 0}'),
            Text(t('صافي الرواتب: ','Net: ') + money(item['net'])),
            Text(t('الحوافز: ','Incentives: ') + money(item['incentives'])),
            Text(t('الإجمالي: ','Total: ') + money(item['total']), style: const TextStyle(fontWeight: FontWeight.w800)),
          ]),
          const SizedBox(height: 8),
          Align(alignment: AlignmentDirectional.centerEnd, child: Text(t('عرض الكشف ←','Open report →'), style: const TextStyle(color: Color(0xFF1E6B4E), fontWeight: FontWeight.w800))),
        ])))),
      ],
    ])),
  );
}
