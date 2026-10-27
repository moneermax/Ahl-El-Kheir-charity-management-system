import 'package:flutter/material.dart';

class SalaryAdvanceWaiverPage extends StatefulWidget {
  const SalaryAdvanceWaiverPage({
    super.key,
    required this.english,
    required this.roleCode,
    required this.loadPage,
    required this.submitAction,
  });

  final bool english;
  final String roleCode;
  final Future<Map<String, dynamic>> Function(String effectiveMonth) loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;

  @override
  State<SalaryAdvanceWaiverPage> createState() => _SalaryAdvanceWaiverPageState();
}

class _SalaryAdvanceWaiverPageState extends State<SalaryAdvanceWaiverPage> {
  bool loading = true;
  bool busy = false;
  String? error;
  Map<String, dynamic> data = {};
  final monthController = TextEditingController();
  final reasonController = TextEditingController();
  String decisionType = 'blanket';
  int? employeeId;
  int? refundAccountId;
  int? expenseAccountId;

  String t(String ar, String en) => widget.english ? en : ar;
  String str(dynamic value) => value == null || value.toString().isEmpty ? '—' : value.toString();
  int number(dynamic value) => value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
  double amount(dynamic value) => value is num ? value.toDouble() : double.tryParse(value?.toString() ?? '') ?? 0;
  bool get canFM => data['can_fm_prepare'] == true;
  bool get canGM => data['can_gm_review'] == true;
  bool get tablesReady => data['tables_ready'] == true;

  List<Map<String, dynamic>> rows(String key) {
    final value = data[key];
    if (value is! List) return [];
    return value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList();
  }

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    monthController.text = '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-01';
    refresh();
  }

  @override
  void dispose() {
    monthController.dispose();
    reasonController.dispose();
    super.dispose();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final value = await widget.loadPage(monthController.text.trim());
      if (!mounted) return;
      final employees = value['employees'] is List ? value['employees'] as List : const [];
      final refunds = value['refund_accounts'] is List ? value['refund_accounts'] as List : const [];
      final expenses = value['expense_accounts'] is List ? value['expense_accounts'] as List : const [];
      setState(() {
        data = value;
        if (employees.whereType<Map>().every((row) => number(row['id']) != employeeId)) employeeId = null;
        if (refunds.whereType<Map>().every((row) => number(row['id']) != refundAccountId)) refundAccountId = refunds.isEmpty ? null : number(refunds.first['id']);
        if (expenses.whereType<Map>().every((row) => number(row['id']) != expenseAccountId)) expenseAccountId = null;
        loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { error = e.toString(); loading = false; });
    }
  }

  Future<void> act(Map<String, dynamic> body) async {
    if (busy) return;
    setState(() { busy = true; error = null; });
    try {
      await widget.submitAction(body);
      if (!mounted) return;
      reasonController.clear();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم حفظ الإجراء.', 'Action saved.'))));
      await refresh();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Theme.of(context).colorScheme.error),
      );
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> prepare() async {
    final month = monthController.text.trim();
    if (!RegExp(r'^\d{4}-\d{2}-01$').hasMatch(month)) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('أدخل تاريخ أول الشهر بصيغة YYYY-MM-01.', 'Enter the first day of the month as YYYY-MM-01.'))));
      return;
    }
    if (reasonController.text.trim().isEmpty || (decisionType == 'individual' && (employeeId == null || employeeId! <= 0)) || refundAccountId == null || expenseAccountId == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('أكمل الموظف والحسابات والسبب المطلوب.', 'Complete the employee, accounts, and reason fields.'))));
      return;
    }
    await act({
      'action': 'prepare',
      'decision_type': decisionType,
      'effective_month': month,
      if (decisionType == 'individual') 'employee_id': employeeId,
      'refund_account_id': refundAccountId,
      'waiver_expense_account_id': expenseAccountId,
      'reason': reasonController.text.trim(),
    });
  }

  Future<void> review(int decisionId, bool approve) async {
    String reason = '';
    if (!approve) {
      final controller = TextEditingController();
      try {
        final result = await showDialog<String>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: Text(t('سبب رفض القرار', 'Reason for rejection')),
            content: TextField(
              controller: controller,
              minLines: 2,
              maxLines: 4,
              maxLength: 2000,
              decoration: InputDecoration(labelText: t('سبب الرفض (مطلوب)', 'Rejection reason (required)')),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('إلغاء', 'Cancel'))),
              FilledButton(onPressed: () {
                if (controller.text.trim().isNotEmpty) Navigator.pop(dialogContext, controller.text.trim());
              }, child: Text(t('رفض القرار', 'Reject decision'))),
            ],
          ),
        );
        if (result == null || result.trim().isEmpty) return;
        reason = result.trim();
      } finally {
        controller.dispose();
      }
    } else {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(t('اعتماد قرار الإعفاء؟', 'Approve waiver decision?')),
          content: Text(t('سيعود القرار إلى المدير المالي للتنفيذ. الاعتماد لا ينشئ قيداً محاسبياً.', 'The decision returns to the Financial Manager for execution. Approval itself does not create an accounting entry.')),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(t('إلغاء', 'Cancel'))),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(t('اعتماد', 'Approve'))),
          ],
        ),
      );
      if (confirmed != true) return;
    }
    await act({'action': approve ? 'approve' : 'reject', 'decision_id': decisionId, 'reason': reason});
  }

  Future<void> execute(int decisionId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('تنفيذ الإعفاء نهائياً؟', 'Execute waiver permanently?')),
        content: Text(t(
          'سينفذ النظام رد الخصومات المؤهلة، والإعفاء المحاسبي، وإلغاء الطلبات غير المصروفة المشمولة. ستبقى القيود والسجلات التاريخية محفوظة.',
          'This executes eligible deduction refunds, the accounting waiver, and cancellation of covered undistributed requests. Historical journals and records remain preserved.',
        )),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(t('تنفيذ نهائي', 'Execute'))),
        ],
      ),
    );
    if (confirmed == true) await act({'action': 'execute', 'decision_id': decisionId});
  }

  String statusLabel(String status) => ({
    'pending_gm': t('بانتظار اعتماد المدير العام', 'Pending GM approval'),
    'approved_by_gm': t('معتمد — بانتظار التنفيذ المالي', 'Approved — pending FM execution'),
    'rejected_by_gm': t('مرفوض', 'Rejected'),
    'executed': t('تم التنفيذ', 'Executed'),
  })[status] ?? status;

  Widget sectionTitle(String title) => Padding(
    padding: const EdgeInsets.only(bottom: 10, top: 4),
    child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
  );

  Widget summaryLine(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 5),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 125, child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700))),
      Expanded(child: Text(value)),
    ]),
  );

  Widget decisionCard(Map<String, dynamic> decision) {
    final id = number(decision['id']);
    final status = str(decision['status']);
    final items = rows('items').where((row) => number(row['decision_id']) == id).toList();
    final isPending = status == 'pending_gm';
    final isApproved = status == 'approved_by_gm';
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: Text(str(decision['decision_no']), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(width: 8),
            Flexible(child: Chip(label: Text(statusLabel(status), textAlign: TextAlign.center))),
          ]),
          const SizedBox(height: 8),
          summaryLine(t('نوع القرار', 'Decision type'), str(decision['decision_type']) == 'blanket' ? t('جماعي', 'Blanket') : t('فردي', 'Individual')),
          summaryLine(t('شهر السريان', 'Effective month'), str(decision['effective_month'])),
          summaryLine(t('أعده', 'Prepared by'), str(decision['preparer_name'] ?? decision['creator_name'])),
          summaryLine(t('عدد البنود', 'Items'), str(decision['item_count'])),
          summaryLine(t('رد الخصومات', 'Refund preview'), '${amount(decision['refund_preview']).toStringAsFixed(2)} SDG'),
          summaryLine(t('المبلغ المشمول بالإعفاء', 'Waiver amount'), '${amount(decision['waiver_preview']).toStringAsFixed(2)} SDG'),
          if (str(decision['reason']) != '—') ...[
            const SizedBox(height: 4),
            Text(str(decision['reason'])),
          ],
          if (items.isNotEmpty) ...[
            const Divider(height: 22),
            sectionTitle(t('السلف المشمولة', 'Covered advances')),
            for (final item in items)
              Container(
                margin: const EdgeInsets.only(bottom: 7),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${str(item['employee_code'])} — ${str(item['employee_name'])}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('${str(item['request_no'])} · ${str(item['previous_request_status'])}'),
                  Text('${t('الرصيد', 'Balance')}: ${amount(item['balance_before']).toStringAsFixed(2)} · ${t('خصم الشهر', 'Month deduction')}: ${amount(item['current_period_repayment']).toStringAsFixed(2)}'),
                ]),
              ),
          ],
          if (canGM && isPending) ...[
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: OutlinedButton.icon(
                onPressed: busy ? null : () => review(id, false),
                icon: const Icon(Icons.close_rounded),
                label: Text(t('رفض', 'Reject')),
              )),
              const SizedBox(width: 10),
              Expanded(child: FilledButton.icon(
                onPressed: busy ? null : () => review(id, true),
                icon: const Icon(Icons.check_rounded),
                label: Text(t('اعتماد', 'Approve')),
              )),
            ]),
          ],
          if (canFM && isApproved) ...[
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: busy ? null : () => execute(id),
              icon: const Icon(Icons.account_balance_rounded),
              label: Text(t('تنفيذ مالي نهائي', 'Execute financial decision')),
            ),
          ],
        ]),
      ),
    );
  }

  Widget preparationForm() {
    final employees = rows('employees');
    final refunds = rows('refund_accounts');
    final expenses = rows('expense_accounts');
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          sectionTitle(t('تجهيز قرار إعفاء جديد', 'Prepare a new waiver decision')),
          TextField(
            controller: monthController,
            decoration: InputDecoration(
              labelText: t('شهر السريان (اليوم الأول)', 'Effective month (first day)'),
              hintText: 'YYYY-MM-01',
              helperText: t('يجب أن ينتهي التاريخ بـ -01.', 'Date must end with -01.'),
            ),
            onSubmitted: (_) => refresh(),
          ),
          Align(alignment: AlignmentDirectional.centerEnd, child: TextButton.icon(
            onPressed: loading || busy ? null : refresh,
            icon: const Icon(Icons.refresh_rounded),
            label: Text(t('تحديث السلف للشهر', 'Refresh advances for month')),
          )),
          DropdownButtonFormField<String>(
            initialValue: decisionType,
            decoration: InputDecoration(labelText: t('نوع القرار', 'Decision type')),
            items: [
              DropdownMenuItem(value: 'blanket', child: Text(t('جماعي — جميع السلف القائمة', 'Blanket — all eligible advances'))),
              DropdownMenuItem(value: 'individual', child: Text(t('فردي — موظف محدد', 'Individual — one employee'))),
            ],
            onChanged: busy ? null : (value) => setState(() => decisionType = value ?? 'blanket'),
          ),
          if (decisionType == 'individual') ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: employeeId,
              decoration: InputDecoration(labelText: t('الموظف', 'Employee')),
              items: employees.map((row) => DropdownMenuItem<int>(
                value: number(row['id']),
                child: Text('${str(row['employee_code'])} — ${str(row['full_name'])}', overflow: TextOverflow.ellipsis),
              )).toList(),
              onChanged: busy ? null : (value) => setState(() => employeeId = value),
            ),
          ],
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: refunds.any((row) => number(row['id']) == refundAccountId) ? refundAccountId : null,
            decoration: InputDecoration(labelText: t('حساب رد الخصم', 'Refund source account')),
            items: refunds.map((row) => DropdownMenuItem<int>(
              value: number(row['id']),
              child: Text('${str(row['code'])} — ${str(widget.english ? row['name_en'] : row['name_ar'])}', overflow: TextOverflow.ellipsis),
            )).toList(),
            onChanged: busy ? null : (value) => setState(() => refundAccountId = value),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: expenses.any((row) => number(row['id']) == expenseAccountId) ? expenseAccountId : null,
            decoration: InputDecoration(labelText: t('حساب مصروف الإعفاء', 'Waiver expense account')),
            items: expenses.map((row) => DropdownMenuItem<int>(
              value: number(row['id']),
              child: Text('${str(row['code'])} — ${str(widget.english ? row['name_en'] : row['name_ar'])}', overflow: TextOverflow.ellipsis),
            )).toList(),
            onChanged: busy ? null : (value) => setState(() => expenseAccountId = value),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: reasonController,
            minLines: 2,
            maxLines: 4,
            maxLength: 2000,
            decoration: InputDecoration(labelText: t('سبب قرار الإعفاء (مطلوب)', 'Waiver decision reason (required)')),
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: busy || loading || employees.isEmpty && decisionType == 'individual' || refunds.isEmpty || expenses.isEmpty ? null : prepare,
            icon: const Icon(Icons.send_rounded),
            label: Text(t('إرسال إلى المدير العام', 'Submit to General Manager')),
          ),
          if (employees.isEmpty) Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(t('لا توجد سلف مؤهلة حالياً للشهر المحدد.', 'No eligible advances for the selected month.'), style: Theme.of(context).textTheme.bodySmall),
          ),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final decisions = rows('decisions');
    return Scaffold(
      appBar: AppBar(
        title: Text(t('إعفاء سلف الرواتب', 'Salary Advance Waiver')),
        actions: [IconButton(onPressed: loading || busy ? null : refresh, tooltip: t('تحديث', 'Refresh'), icon: const Icon(Icons.refresh_rounded))],
      ),
      body: loading && data.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [Color(0xFF5B21B6), Color(0xFF7C3AED)]),
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Text(
                    t(
                      'إجراءات المدير العام والمالي تتم وفق الصلاحيات الحالية. الاعتماد لا يرحّل قيوداً؛ التنفيذ المالي وحده ينفذ القرار المعتمد.',
                      'General Manager and Financial Manager actions follow existing permissions. Approval creates no journals; only FM execution applies an approved decision.',
                    ),
                    style: const TextStyle(color: Colors.white, height: 1.5),
                  ),
                ),
                if (error != null) ...[
                  const SizedBox(height: 12),
                  Card(child: Padding(padding: const EdgeInsets.all(12), child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)))),
                ],
                if (!tablesReady) ...[
                  const SizedBox(height: 12),
                  Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(t('ميزة الإعفاء غير مفعلة في قاعدة البيانات الحالية. لم يتم إجراء أي تغيير.', 'The waiver feature is not enabled in this database. No changes were made.')))),
                ] else ...[
                  if (canFM) ...[
                    preparationForm(),
                    const SizedBox(height: 18),
                  ],
                  sectionTitle(canGM ? t('قرارات بانتظار المراجعة', 'Decisions awaiting review') : t('قرارات الإعفاء والمتابعة', 'Waiver decisions')),
                  if (decisions.isEmpty)
                    Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(t('لا توجد قرارات في هذه المرحلة.', 'No decisions in this stage.'))))
                  else
                    for (final decision in decisions) decisionCard(decision),
                ],
              ],
            ),
    );
  }
}
