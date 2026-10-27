import 'package:flutter/material.dart';

class FinaReportPage extends StatefulWidget {
  const FinaReportPage({
    super.key,
    required this.english,
    required this.loadReport,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(String from, String to) loadReport;

  @override
  State<FinaReportPage> createState() => _FinaReportPageState();
}

class _FinaReportPageState extends State<FinaReportPage> {
  late final TextEditingController fromController;
  late final TextEditingController toController;
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};

  String t(String ar, String en) => widget.english ? en : ar;
  List<Map<String, dynamic>> rows(String key) {
    final value = data[key];
    return value is List
        ? value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
        : <Map<String, dynamic>>[];
  }

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    fromController = TextEditingController(text: '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-01');
    toController = TextEditingController(text: now.toIso8601String().substring(0, 10));
    refresh();
  }

  @override
  void dispose() {
    fromController.dispose();
    toController.dispose();
    super.dispose();
  }

  Future<void> chooseDate(TextEditingController controller) async {
    final current = DateTime.tryParse(controller.text) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
      helpText: t('اختر التاريخ', 'Choose date'),
    );
    if (picked == null || !mounted) return;
    controller.text = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    setState(() {});
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadReport(fromController.text.trim(), toController.text.trim());
      if (!mounted) return;
      setState(() => data = result);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String amount(dynamic value) {
    final parsed = value is num ? value.toDouble() : double.tryParse(value?.toString() ?? '') ?? 0;
    return parsed.toStringAsFixed(2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (m) => ',');
  }

  int count(dynamic value) => value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

  Widget metric(String title, String value, IconData icon, Color color) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(children: [
        Container(
          width: 42, height: 42,
          decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: color),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(fontSize: 12, color: Color(0xFF647067))),
          const SizedBox(height: 4),
          Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
        ])),
      ]),
    ),
  );

  String methodLabel(String value) => switch (value) {
    'cash' => t('نقدي', 'Cash'),
    'bank_transfer' => t('تحويل بنكي', 'Bank transfer'),
    'credit_card' => t('بطاقة', 'Card'),
    'mobile' => t('محفظة إلكترونية', 'Mobile wallet'),
    'other' => t('أخرى', 'Other'),
    _ => value,
  };

  String sourceLabel(String value) => switch (value) {
    'sponsor' => t('كفيل من أهل الخير', 'Ahl El Kheir sponsor'),
    'person' => t('شخص خارجي', 'External person'),
    'organization' => t('منظمة / جهة', 'Organization'),
    'other' => t('أخرى', 'Other'),
    _ => value,
  };

  Widget section(String title, List<Widget> children) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        const SizedBox(height: 10),
        if (children.isEmpty) Text(t('لا توجد بيانات لهذه الفترة.', 'No data for this period.')),
        ...children,
      ]),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final reviewed = data['reviewed'] is Map ? Map<String, dynamic>.from(data['reviewed'] as Map) : <String, dynamic>{};
    final currency = data['currency_code']?.toString() ?? '';
    final summary = rows('summary');
    final methods = rows('methods');
    final sources = rows('sources');
    return Scaffold(
      backgroundColor: const Color(0xFFF6F4EE),
      appBar: AppBar(
        title: Text(t('تقرير تحصيلات فينا الخير', 'Fina collection report')),
        actions: [IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded))],
      ),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(t('تقرير تحليلي لفترة محددة، وليس سجلاً تفصيلياً للمعاملات.', 'A date-range analysis, not a transaction-level register.')),
              if (data['supervisor_own_only'] == true)
                Padding(padding: const EdgeInsets.only(top: 6), child: Text(t('يعرض التقرير التحصيلات التي سجلتها بنفسك فقط.', 'This report includes only collections you registered.'), style: const TextStyle(fontWeight: FontWeight.w700))),
              const SizedBox(height: 14),
              Row(children: [
                Expanded(child: OutlinedButton.icon(onPressed: loading ? null : () => chooseDate(fromController), icon: const Icon(Icons.calendar_today_rounded), label: Text(t('من: ', 'From: ') + fromController.text))),
                const SizedBox(width: 8),
                Expanded(child: OutlinedButton.icon(onPressed: loading ? null : () => chooseDate(toController), icon: const Icon(Icons.event_rounded), label: Text(t('إلى: ', 'To: ') + toController.text))),
              ]),
              const SizedBox(height: 8),
              FilledButton.icon(onPressed: loading ? null : refresh, icon: const Icon(Icons.filter_alt_rounded), label: Text(t('عرض التقرير', 'Run report'))),
            ]))),
            if (loading) const Padding(padding: EdgeInsets.all(12), child: Center(child: CircularProgressIndicator())),
            if (error != null) Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(children: [
              Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
              TextButton(onPressed: loading ? null : refresh, child: Text(t('إعادة المحاولة', 'Retry'))),
            ]))),
            if (!loading && error == null) ...[
              Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(
                t('الفترة: ', 'Period: ') + (data['from']?.toString() ?? fromController.text) + ' — ' + (data['to']?.toString() ?? toController.text) +
                '\n' + t('إجمالي السجلات: ', 'Total records: ') + count(reviewed['total_count']).toString() +
                '\n' + t('إجمالي المبلغ المعتمد: ', 'Approved total: ') + amount(reviewed['approved_amount']) + ' ' + currency,
              ))),
              metric(t('معتمد', 'Approved'), count(reviewed['approved_count']).toString() + ' · ' + amount(reviewed['approved_amount']) + ' ' + currency, Icons.check_circle_outline_rounded, const Color(0xFF237A4B)),
              metric(t('مرتجع', 'Returned'), count(reviewed['returned_count']).toString() + ' · ' + amount(reviewed['returned_amount']) + ' ' + currency, Icons.assignment_return_outlined, const Color(0xFFB42318)),
              for (final item in summary) section(
                t('ملخص العملة ', 'Currency summary ') + (item['currency_code']?.toString() ?? ''),
                [
                  Text(t('إجمالي القيمة: ', 'Total value: ') + amount(item['total_amount'])),
                  Text(t('معلق: ', 'Pending: ') + count(item['pending_count']).toString() + ' · ' + amount(item['pending_amount'])),
                  Text(t('معتمد: ', 'Approved: ') + count(item['approved_count']).toString() + ' · ' + amount(item['approved_amount'])),
                  Text(t('مرتجع: ', 'Returned: ') + count(item['returned_count']).toString() + ' · ' + amount(item['returned_amount'])),
                ],
              ),
              section(t('التوزيع حسب طريقة الدفع', 'By payment method'), [
                for (final item in methods) ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(methodLabel(item['payment_method']?.toString() ?? '')),
                  subtitle: Text(t('السجلات: ', 'Records: ') + count(item['total_count']).toString() +
                    ' · ' + t('معلق: ', 'Pending: ') + count(item['pending_count']).toString() +
                    ' · ' + t('معتمد: ', 'Approved: ') + count(item['approved_count']).toString() +
                    ' · ' + t('مرتجع: ', 'Returned: ') + count(item['returned_count']).toString()),
                  trailing: Text(amount(item['total_amount']) + ' ' + currency, textAlign: TextAlign.end),
                ),
              ]),
              section(t('التوزيع حسب مصدر الأموال', 'By funding source'), [
                for (final item in sources) ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(sourceLabel(item['source_type']?.toString() ?? '')),
                  subtitle: Text(t('السجلات: ', 'Records: ') + count(item['total_count']).toString() +
                    ' · ' + t('معتمد: ', 'Approved: ') + count(item['approved_count']).toString() +
                    ' · ' + t('مرتجع: ', 'Returned: ') + count(item['returned_count']).toString()),
                  trailing: Text(amount(item['total_amount']) + ' ' + currency, textAlign: TextAlign.end),
                ),
              ]),
            ],
          ],
        ),
      ),
    );
  }
}
