import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:pdfrx/pdfrx.dart';

class PayrollManagementPage extends StatefulWidget {
  const PayrollManagementPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.submitAction,
    required this.pickProof,
    required this.uploadProof,
  });
  final bool english;
  final Future<Map<String, dynamic>> Function(int month, int year) loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;
  final Future<PlatformFile?> Function() pickProof;
  final Future<Map<String, dynamic>> Function(int payrollId, int month, int year, PlatformFile proof, bool legacyEvidence) uploadProof;
  final Future<Map<String, dynamic>> Function(int payrollId) loadProof;
  @override
  State<PayrollManagementPage> createState() => _PayrollManagementPageState();
}

class _PayrollManagementPageState extends State<PayrollManagementPage> {
  late final TextEditingController monthController;
  late final TextEditingController yearController;
  bool loading = true, saving = false;
  String? error;
  Map<String, dynamic> data = {};
  final Map<int, PlatformFile> selectedProofs = {};
  final Map<int, int> rowAccounts = {};
  int? selectedDefaultAccount;
  String t(String ar, String en) => widget.english ? en : ar;
  int get month => int.tryParse(monthController.text.trim()) ?? 0;
  int get year => int.tryParse(yearController.text.trim()) ?? 0;
  List<Map<String, dynamic>> get rows => (data['rows'] is List) ? (data['rows'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
  List<Map<String, dynamic>> get accounts => (data['active_payment_accounts'] is List) ? (data['active_payment_accounts'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
  Map<String, dynamic> get summary => data['summary'] is Map ? Map<String, dynamic>.from(data['summary'] as Map) : {};

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    monthController = TextEditingController(text: now.month.toString());
    yearController = TextEditingController(text: now.year.toString());
    refresh();
  }
  @override
  void dispose() { monthController.dispose(); yearController.dispose(); super.dispose(); }

  Future<void> refresh() async {
    if (month < 1 || month > 12 || year < 2020 || year > 2100) {
      setState(() { error = t('أدخل شهراً من 1 إلى 12 وسنة بين 2020 و2100.', 'Enter month 1–12 and year 2020–2100.'); loading = false; });
      return;
    }
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.loadPage(month, year);
      if (!mounted) return;
      setState(() {
        data = response;
        final setting = response['default_payment_account'];
        selectedDefaultAccount = setting is Map ? int.tryParse(setting['default_payment_account_id']?.toString() ?? '') : null;
        rowAccounts.clear();
        for (final row in rows) {
          final id = int.tryParse(row['id']?.toString() ?? '') ?? 0;
          final accountId = int.tryParse(row['payment_account_id']?.toString() ?? '') ?? 0;
          if (id > 0 && accountId > 0) rowAccounts[id] = accountId;
        }
        selectedProofs.removeWhere((id, file) => !rows.any((row) => int.tryParse(row['id']?.toString() ?? '') == id));
      });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }

  Future<void> runAction(Map<String, dynamic> body, {String? success}) async {
    setState(() { saving = true; error = null; });
    try {
      await widget.submitAction({...body, 'month': month, 'year': year});
      selectedProofs.clear();
      await refresh();
      if (mounted && success != null) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(success)));
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => saving = false); }
  }

  Future<bool> confirmDialog(String title, String message) async =>
      await showDialog<bool>(context: context, builder: (context) => AlertDialog(
        title: Text(title), content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(t('تأكيد', 'Confirm'))),
        ],
      )) ?? false;

  Future<void> generate() async {
    if (await confirmDialog(t('إنشاء المسيرات', 'Generate payroll'), t('سيتم إنشاء المسيرات المؤهلة وتحديث المسودات فقط؛ لن يتم الاعتماد أو الصرف.', 'Eligible payroll rows will be generated and drafts refreshed only. Nothing will be approved or paid.'))) {
      await runAction({'action': 'generate'}, success: t('تمت معالجة إنشاء المسيرات.', 'Payroll generation completed.'));
    }
  }
  Future<void> approveAll() async {
    if (await confirmDialog(t('اعتماد المسودات', 'Approve drafts'), t('سيتم اعتماد جميع المسودات للفترة المحددة فقط دون صرفها.', 'All drafts in this period will be approved, not paid.'))) {
      await runAction({'action': 'approve_all'}, success: t('تم اعتماد المسودات.', 'Drafts approved.'));
    }
  }

  Future<void> editDraft(Map<String, dynamic> row) async {
    final id = int.tryParse(row['id']?.toString() ?? '') ?? 0;
    final a = TextEditingController(text: (row['allowances'] ?? 0).toString());
    final o = TextEditingController(text: (row['overtime'] ?? 0).toString());
    final d = TextEditingController(text: (row['deductions'] ?? 0).toString());
    final key = GlobalKey<FormState>();
    final values = await showDialog<Map<String, String>>(context: context, builder: (context) => AlertDialog(
      title: Text(t('تعديل مسودة الراتب', 'Edit payroll draft')),
      content: Form(key: key, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        for (final item in [(t('البدلات', 'Allowances'), a), (t('العمل الإضافي', 'Overtime'), o), (t('الخصومات', 'Deductions'), d)])
          TextFormField(controller: item.$2, keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: item.$1),
            validator: (v) { final n = double.tryParse(v?.trim() ?? ''); return n == null || !n.isFinite || n < 0 ? t('أدخل مبلغاً غير سالب.', 'Enter a non-negative amount.') : null; }),
      ]))),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(t('إلغاء', 'Cancel'))),
        FilledButton(onPressed: () { if (key.currentState?.validate() ?? false) Navigator.pop(context, {'allowances': a.text.trim(), 'overtime': o.text.trim(), 'deductions': d.text.trim()}); }, child: Text(t('حفظ', 'Save'))),
      ],
    ));
    a.dispose(); o.dispose(); d.dispose();
    if (values != null && mounted) await runAction({'action': 'update_amounts', 'payroll_id': id, ...values}, success: t('حُفظت المسودة وأعيد احتساب الخصومات.', 'Draft saved and deductions recalculated.'));
  }

  Future<void> pickProof(int id) async {
    try { final file = await widget.pickProof(); if (file != null && mounted) setState(() => selectedProofs[id] = file); }
    catch (e) { if (mounted) setState(() => error = e.toString()); }
  }
  Future<void> uploadProof(Map<String, dynamic> row, bool legacy) async {
    final id = int.tryParse(row['id']?.toString() ?? '') ?? 0;
    final file = selectedProofs[id];
    if (id <= 0 || file == null) return;
    setState(() { saving = true; error = null; });
    try {
      await widget.uploadProof(id, month, year, file, legacy);
      selectedProofs.remove(id);
      await refresh();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(legacy
        ? t('أُرفق الدليل التاريخي دون تغيير الدفع أو المحاسبة.', 'Legacy evidence attached without changing payment or accounting.')
        : t('حُفظ الإثبات فقط؛ لم يتم تسجيل الدفع.', 'Proof saved only; payment was not recorded.'))));
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => saving = false); }
  }

  Future<void> viewProof(int id) async {
    try {
      final result = await widget.loadProof(id);
      final mime = (result['mime'] ?? '').toString().toLowerCase();
      final value = result['bytes'];
      if (value is! Uint8List) throw StateError(t('تعذر قراءة ملف الإثبات.', 'Could not read payroll proof.'));
      if (!mime.startsWith('image/') && mime != 'application/pdf') throw StateError(t('نوع ملف الإثبات غير مدعوم.', 'Unsupported payroll proof type.'));
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(t('إثبات تحويل الراتب', 'Payroll transfer proof')),
          content: SizedBox(
            width: 520,
            height: mime.startsWith('image/') ? 500 : 620,
            child: mime.startsWith('image/')
                ? InteractiveViewer(child: Image.memory(value, fit: BoxFit.contain))
                : PdfViewer.data(value, sourceName: (result['filename'] ?? 'payroll-proof.pdf').toString()),
          ),
          actions: [TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('إغلاق', 'Close')))],
        ),
      );
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  String money(dynamic value) {
    final n = num.tryParse(value?.toString() ?? '') ?? 0;
    return n.toStringAsFixed(2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (m) => ',');
  }
  String statusLabel(String value) => switch (value) {
    'draft' => t('مسودة', 'Draft'), 'approved' => t('معتمد', 'Approved'), 'paid' => t('مصروف', 'Paid'), _ => value,
  };
  String proofLabel(String? state) => switch (state) {
    'prepared' => t('الإثبات جاهز — الدفع غير مؤكد', 'Proof prepared — payment not confirmed'),
    'confirmed' => t('التحويل مؤكد', 'Transfer confirmed'),
    'legacy_evidence' => t('دليل تاريخي', 'Legacy evidence'),
    _ => t('لا يوجد إثبات', 'No proof'),
  };
  String accountLabel(Map<String, dynamic> a) => (a['code']?.toString() ?? '') + ' · ' + ((widget.english ? a['name_en'] : a['name_ar'])?.toString() ?? '');

  @override
  Widget build(BuildContext context) {
    final transferReady = data['transfer_schema_available'] == true;
    return Scaffold(
      appBar: AppBar(title: Text(t('إدارة الرواتب', 'Payroll management')), actions: [
        IconButton(onPressed: loading || saving ? null : refresh, icon: const Icon(Icons.refresh_rounded), tooltip: t('تحديث', 'Refresh')),
      ]),
      body: RefreshIndicator(onRefresh: refresh, child: ListView(padding: const EdgeInsets.all(14), children: [
        Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(t('فترة الرواتب', 'Payroll period'), style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(child: TextField(controller: monthController, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: t('الشهر', 'Month'), border: const OutlineInputBorder()), textDirection: TextDirection.ltr)),
            const SizedBox(width: 8),
            Expanded(child: TextField(controller: yearController, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: t('السنة', 'Year'), border: const OutlineInputBorder()), textDirection: TextDirection.ltr)),
            const SizedBox(width: 8),
            FilledButton(onPressed: loading || saving ? null : refresh, child: Text(t('عرض', 'Load'))),
          ]),
          const SizedBox(height: 8),
          Text(t('الفترة: ', 'Period: ') + (data['period']?.toString() ?? '--')),
          Text(t('السجلات: ', 'Rows: ') + (summary['count']?.toString() ?? '0') + ' · ' + t('مسودات: ', 'Drafts: ') + (summary['draft']?.toString() ?? '0') + ' · ' + t('معتمدة: ', 'Approved: ') + (summary['approved']?.toString() ?? '0') + ' · ' + t('مصروفة: ', 'Paid: ') + (summary['paid']?.toString() ?? '0')),
          Text(t('صافي الرواتب: ', 'Net payroll: ') + money(summary['net']) + ' · ' + t('الحوافز: ', 'Incentives: ') + money(summary['incentives'])),
          if (data['active_payroll_policy'] is Map) Text(t('السياسة الفعالة: V', 'Effective policy: V') + ((data['active_payroll_policy'] as Map)['version_no']?.toString() ?? '') + ' · ' + ((data['active_payroll_policy'] as Map)['effective_from']?.toString() ?? '')),
          const SizedBox(height: 8),
          FilledButton.icon(onPressed: loading || saving ? null : generate, icon: const Icon(Icons.playlist_add_rounded), label: Text(t('إنشاء / تحديث المسودات', 'Generate / refresh drafts'))),
          OutlinedButton.icon(onPressed: loading || saving || (int.tryParse(summary['draft']?.toString() ?? '0') ?? 0) == 0 ? null : approveAll, icon: const Icon(Icons.fact_check_outlined), label: Text(t('اعتماد كل المسودات', 'Approve all drafts'))),
        ]))),
        if (transferReady) Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(t('حساب التحويل الافتراضي للفترة', 'Period default payment account'), style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          DropdownButtonFormField<int>(
            key: ValueKey('payroll-default-account-' + (data['period']?.toString() ?? '')),
            initialValue: accounts.any((a) => int.tryParse(a['id']?.toString() ?? '') == selectedDefaultAccount) ? selectedDefaultAccount : null,
            decoration: InputDecoration(labelText: t('الحساب النشط', 'Active account'), border: const OutlineInputBorder()),
            items: accounts.map((a) => DropdownMenuItem<int>(value: int.tryParse(a['id']?.toString() ?? ''), child: Text(accountLabel(a), overflow: TextOverflow.ellipsis))).toList(),
            onChanged: loading || saving ? null : (v) => setState(() => selectedDefaultAccount = v),
          ),
          const SizedBox(height: 8),
          OutlinedButton(onPressed: loading || saving || selectedDefaultAccount == null ? null : () => runAction({'action': 'save_period_payment_account', 'default_payment_account_id': selectedDefaultAccount}, success: t('تم حفظ الحساب الافتراضي.', 'Default account saved.')), child: Text(t('حفظ الحساب الافتراضي', 'Save default account'))),
        ]))),
        if (!transferReady && !loading) Card(color: Theme.of(context).colorScheme.errorContainer, child: Padding(padding: const EdgeInsets.all(12), child: Text(t('ترحيل إثباتات التحويل غير مطبق؛ إجراءات التحويل معطلة.', 'Payroll transfer-proof migration is not applied; transfer actions are disabled.')))),
        if (loading || saving) const LinearProgressIndicator(),
        if (error != null) Card(color: Theme.of(context).colorScheme.errorContainer, child: Padding(padding: const EdgeInsets.all(12), child: Text(error!))),
        if (!loading && rows.isEmpty) Padding(padding: const EdgeInsets.all(24), child: Center(child: Text(t('لا توجد مسيرات لهذه الفترة.', 'No payroll rows for this period.')))),
        for (final row in rows) _payrollCard(row, transferReady),
      ])),
    );
  }

  Widget _payrollCard(Map<String, dynamic> row, bool transferReady) {
    final id = int.tryParse(row['id']?.toString() ?? '') ?? 0;
    final status = row['status']?.toString() ?? '';
    final proofState = row['payment_proof_state']?.toString();
    final hasProof = row['has_payment_proof'] == true;
    final selected = selectedProofs[id];
    final currentAccount = rowAccounts[id];
    final canAccount = transferReady && status == 'approved' && !hasProof;
    final canNewProof = transferReady && status == 'approved' && !hasProof;
    final canLegacyProof = transferReady && status == 'paid' && !hasProof;
    final canConfirm = transferReady && status == 'approved' && proofState == 'prepared';
    return Card(key: Key('payroll-row-' + id.toString()), margin: const EdgeInsets.only(bottom: 10), child: Padding(padding: const EdgeInsets.all(13), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        const Icon(Icons.badge_outlined), const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(row['employee_name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          Text(row['employee_code']?.toString() ?? '', style: Theme.of(context).textTheme.bodySmall),
        ])),
        Chip(label: Text(statusLabel(status))),
      ]),
      const Divider(),
      Wrap(spacing: 12, runSpacing: 8, children: [
        Text(t('الأساسي: ', 'Basic: ') + money(row['basic_salary'])),
        Text(t('البدلات: ', 'Allowances: ') + money(row['allowances'])),
        Text(t('الإضافي: ', 'Overtime: ') + money(row['overtime'])),
        Text(t('الخصومات: ', 'Deductions: ') + money(row['deductions'])),
        Text(t('خصم الحضور: ', 'Attendance deduction: ') + money(row['attendance_deduction'])),
        Text(t('خصم السلفة: ', 'Advance deduction: ') + money(row['salary_advance_deduction'])),
        Text(t('الحوافز: ', 'Incentives: ') + money(row['incentive_amount'])),
        Text(t('الصافي: ', 'Net: ') + money(row['net_salary']), style: const TextStyle(fontWeight: FontWeight.w800)),
      ]),
      const SizedBox(height: 8),
      Text(t('المحاسبة: ', 'Accounting: ') + (row['accounting_status']?.toString() ?? 'none') + ' · ' + t('القيد: ', 'Journal: ') + (row['accounting_entry_id']?.toString() ?? '--')),
      if (status == 'draft') ...[
        OutlinedButton.icon(key: Key('payroll-edit-' + id.toString()), onPressed: saving ? null : () => editDraft(row), icon: const Icon(Icons.edit_outlined), label: Text(t('تعديل المكونات', 'Edit components'))),
        FilledButton.icon(key: Key('payroll-approve-' + id.toString()), onPressed: saving ? null : () async {
          if (await confirmDialog(t('اعتماد المسير', 'Approve payroll'), t('سيتم اعتماد هذا المسير فقط دون صرفه.', 'Only this payroll row will be approved; it will not be paid.'))) {
            await runAction({'action': 'approve', 'payroll_id': id}, success: t('تم اعتماد المسير.', 'Payroll approved.'));
          }
        }, icon: const Icon(Icons.check_circle_outline), label: Text(t('اعتماد فقط', 'Approve only'))),
      ],
      if (transferReady && status == 'approved') ...[
        Text(t('حساب التحويل: ', 'Payment account: ') + (row['payment_account_code']?.toString() ?? t('غير محدد', 'Not set'))),
        if (canAccount && accounts.isNotEmpty) ...[
          DropdownButtonFormField<int>(
            key: ValueKey('payroll-row-account-' + id.toString() + '-' + (currentAccount?.toString() ?? '0')),
            initialValue: accounts.any((a) => int.tryParse(a['id']?.toString() ?? '') == currentAccount) ? currentAccount : null,
            decoration: InputDecoration(labelText: t('حساب فردي', 'Employee account'), border: const OutlineInputBorder()),
            items: accounts.map((a) => DropdownMenuItem<int>(value: int.tryParse(a['id']?.toString() ?? ''), child: Text(accountLabel(a), overflow: TextOverflow.ellipsis))).toList(),
            onChanged: saving ? null : (v) { if (v != null) setState(() => rowAccounts[id] = v); },
          ),
          OutlinedButton(onPressed: saving || currentAccount == null ? null : () => runAction({'action': 'set_row_payment_account', 'payroll_id': id, 'payment_account_id': currentAccount}, success: t('تم حفظ حساب الموظف.', 'Employee account saved.')), child: Text(t('حفظ حساب الموظف', 'Save employee account'))),
        ],
      ],
      if (hasProof) ...[
        Text(proofLabel(proofState), style: const TextStyle(fontWeight: FontWeight.w700)),
        if ((row['payment_proof_filename']?.toString() ?? '').isNotEmpty) Text(row['payment_proof_filename'].toString(), maxLines: 2, overflow: TextOverflow.ellipsis),
        OutlinedButton.icon(key: Key('payroll-view-proof-' + id.toString()), onPressed: saving ? null : () => viewProof(id), icon: const Icon(Icons.visibility_outlined), label: Text(t('عرض الإثبات', 'View proof'))),
        if ((row['payment_proof_uploaded_at']?.toString() ?? '').isNotEmpty) Text(t('تاريخ الرفع: ', 'Uploaded: ') + row['payment_proof_uploaded_at'].toString()),
      ],
      if (canNewProof || canLegacyProof) ...[
        OutlinedButton.icon(key: Key('payroll-pick-proof-' + id.toString()), onPressed: saving ? null : () => pickProof(id), icon: const Icon(Icons.attach_file_rounded), label: Text(t('اختيار إثبات PDF/JPG/PNG', 'Choose PDF/JPG/PNG proof'))),
        if (selected != null) Text(selected.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        FilledButton.icon(key: Key('payroll-upload-proof-' + id.toString()), onPressed: saving || selected == null ? null : () => uploadProof(row, canLegacyProof), icon: const Icon(Icons.upload_file_rounded), label: Text(canLegacyProof ? t('إرفاق دليل تاريخي فقط', 'Attach legacy evidence only') : t('رفع الإثبات دون تأكيد الدفع', 'Upload proof without confirming payment'))),
      ],
      if (canConfirm) FilledButton.icon(key: Key('payroll-confirm-transfer-' + id.toString()), onPressed: saving ? null : () async {
        if (await confirmDialog(t('تأكيد تنفيذ التحويل', 'Confirm transfer execution'), t('استخدم هذا الإجراء بعد تنفيذ التحويل فعلياً والتحقق من الإثبات. سيُسجل الصرف ويُرحل القيد.', 'Use only after the transfer was actually executed and proof checked. This records payment and posts accounting.'))) {
          await runAction({'action': 'confirm_transfer', 'payroll_id': id}, success: t('تم تأكيد التحويل وترحيل المحاسبة.', 'Transfer confirmed and accounting posted.'));
        }
      }, icon: const Icon(Icons.account_balance_rounded), label: Text(t('تأكيد التحويل وترحيل المحاسبة', 'Confirm transfer and post accounting'))),
      if (status == 'paid' && proofState == 'legacy_evidence') Text(t('الدليل التاريخي لا ينشئ عملية صرف جديدة.', 'Legacy evidence does not create a new payment.')),
    ])));
  }
}
