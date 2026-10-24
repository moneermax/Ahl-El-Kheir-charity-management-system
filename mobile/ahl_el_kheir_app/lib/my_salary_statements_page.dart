import 'package:flutter/material.dart';

class MySalaryStatementsPage extends StatefulWidget {
  const MySalaryStatementsPage({super.key, required this.english, required this.loadPage});

  final bool english;
  final Future<Map<String, dynamic>> Function(int page) loadPage;

  @override
  State<MySalaryStatementsPage> createState() => _MySalaryStatementsPageState();
}

class _MySalaryStatementsPageState extends State<MySalaryStatementsPage> {
  bool loading = true;
  bool employeeLinked = true;
  String? error;
  int page = 1, pages = 1, total = 0;
  List<Map<String, dynamic>> items = [];

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadPage(page);
      if (!mounted) return;
      final raw = data['items'];
      setState(() {
        employeeLinked = data['employee_linked'] != false;
        items = raw is List
            ? raw.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
            : <Map<String, dynamic>>[];
        total = int.tryParse(data['total']?.toString() ?? '0') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '1') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String period(Map<String, dynamic> item) {
    const ar = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
      'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    const en = ['', 'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'];
    final month = int.tryParse(item['month']?.toString() ?? '') ?? 0;
    final year = item['year']?.toString() ?? '';
    final names = widget.english ? en : ar;
    return (month > 0 && month < names.length ? names[month] : month.toString()) + ' ' + year;
  }

  String money(dynamic value, String currency) =>
      (double.tryParse(value?.toString() ?? '') ?? 0).toStringAsFixed(2) + ' ' + currency;

  Widget amountLine(String label, dynamic value, String currency) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(children: [
      Expanded(child: Text(label)),
      const SizedBox(width: 12),
      Text(money(value, currency), style: const TextStyle(fontWeight: FontWeight.w700)),
    ]),
  );

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'My salary statements' : 'كشوف رواتبي')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Center(child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Text(error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: load,
                      icon: const Icon(Icons.refresh),
                      label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                    ),
                  ]),
                ))
              : !employeeLinked
                  ? Center(child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        en ? 'No employee profile is linked to this account. Please contact HR.'
                           : 'لا يوجد ملف موظف مرتبط بهذا الحساب. يرجى التواصل مع الموارد البشرية.',
                        textAlign: TextAlign.center,
                      ),
                    ))
                  : Column(children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                        child: Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: Text((en ? 'Statements: ' : 'عدد الكشوف: ') + total.toString()),
                        ),
                      ),
                      Expanded(child: RefreshIndicator(
                        onRefresh: load,
                        child: items.isEmpty
                            ? ListView(children: [Padding(
                                padding: const EdgeInsets.all(24),
                                child: Text(
                                  en ? 'No paid and posted salary statements are available.'
                                     : 'لا توجد كشوف رواتب مصروفة ومرحّلة محاسبياً.',
                                  textAlign: TextAlign.center,
                                ),
                              )])
                            : ListView.separated(
                                physics: const AlwaysScrollableScrollPhysics(),
                                padding: const EdgeInsets.all(12),
                                itemCount: items.length,
                                separatorBuilder: (_, __) => const SizedBox(height: 6),
                                itemBuilder: (context, index) {
                                  final item = items[index];
                                  final currency = item['salary_currency']?.toString() ?? 'SDG';
                                  return Card(child: ExpansionTile(
                                    leading: const CircleAvatar(child: Icon(Icons.receipt_long_outlined)),
                                    title: Text(period(item), style: const TextStyle(fontWeight: FontWeight.w800)),
                                    subtitle: Text((item['employee_code']?.toString() ?? '') +
                                      (en ? ' · Paid' : ' · مصروف')),
                                    trailing: Text(money(item['net_salary'], currency),
                                      style: const TextStyle(fontWeight: FontWeight.w800)),
                                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                                    children: [
                                      amountLine(en ? 'Basic salary' : 'الراتب الأساسي', item['basic_salary'], currency),
                                      amountLine(en ? 'Allowances' : 'البدلات', item['allowances'], currency),
                                      amountLine(en ? 'Overtime' : 'العمل الإضافي', item['overtime'], currency),
                                      amountLine(en ? 'Deductions' : 'الخصومات', item['deductions'], currency),
                                      amountLine(en ? 'Attendance deduction' : 'خصم الحضور', item['attendance_deduction'], currency),
                                      amountLine(en ? 'Salary advance deduction' : 'خصم السلف', item['salary_advance_deduction'], currency),
                                      amountLine(en ? 'Incentives' : 'الحوافز', item['incentive_amount'], currency),
                                      amountLine(en ? 'Net salary' : 'صافي الراتب', item['net_salary'], currency),
                                      if ((item['payment_date']?.toString() ?? '').isNotEmpty)
                                        Padding(
                                          padding: const EdgeInsets.only(top: 8),
                                          child: Align(
                                            alignment: AlignmentDirectional.centerStart,
                                            child: Text((en ? 'Payment date: ' : 'تاريخ الصرف: ') + item['payment_date'].toString()),
                                          ),
                                        ),
                                    ],
                                  ));
                                },
                              ),
                      )),
                      if (pages > 1)
                        SafeArea(top: false, child: Padding(
                          padding: const EdgeInsets.all(10),
                          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                            OutlinedButton(
                              onPressed: page > 1 ? () { setState(() => page--); load(); } : null,
                              child: Text(en ? 'Previous' : 'السابق'),
                            ),
                            Text(en ? 'Page $page of $pages' : 'صفحة $page من $pages'),
                            OutlinedButton(
                              onPressed: page < pages ? () { setState(() => page++); load(); } : null,
                              child: Text(en ? 'Next' : 'التالي'),
                            ),
                          ]),
                        )),
                    ]),
    );
  }
}
