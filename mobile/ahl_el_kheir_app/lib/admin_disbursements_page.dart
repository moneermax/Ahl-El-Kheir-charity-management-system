import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

class AdminDisbursementsPage extends StatefulWidget {
  const AdminDisbursementsPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.loadDetail,
    this.roleCode = 'admin',
    this.pickReceipt,
    this.submitItemConfirmation,
    this.loadEligibleGroups,
    this.createBatches,
    this.pickTransferReceipt,
    this.submitTransfer,
    this.pickReturnReceipt,
    this.submitReturn,
  });

  final bool english;
  final String roleCode;
  final Future<PlatformFile?> Function()? pickReceipt;
  final Future<Map<String, dynamic>> Function(int itemId, PlatformFile receipt)? submitItemConfirmation;
  final Future<Map<String, dynamic>> Function(String month)? loadEligibleGroups;
  final Future<Map<String, dynamic>> Function(String month, List<int> groupIds)? createBatches;
  final Future<PlatformFile?> Function()? pickTransferReceipt;
  final Future<Map<String, dynamic>> Function(int batchId, PlatformFile receipt)? submitTransfer;
  final Future<PlatformFile?> Function()? pickReturnReceipt;
  final Future<Map<String, dynamic>> Function(int batchId, String reasonType, String reasonDetails, PlatformFile receipt)? submitReturn;
  final Future<Map<String, dynamic>> Function({
    required String month,
    required String status,
    required String query,
    required int page,
  }) loadPage;
  final Future<Map<String, dynamic>> Function(int id, int page) loadDetail;

  @override
  State<AdminDisbursementsPage> createState() => _AdminDisbursementsPageState();
}

class _AdminDisbursementsPageState extends State<AdminDisbursementsPage> {
  final monthController = TextEditingController();
  final queryController = TextEditingController();
  String status = 'all';
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  int page = 1;
  int pages = 1;
  int total = 0;

  String t(String ar, String en) => widget.english ? en : ar;
  String money(dynamic value) {
    final amount = num.tryParse(value?.toString() ?? '') ?? 0;
    return amount.toStringAsFixed(2).replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (match) => ',',
    );
  }

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    monthController.dispose();
    queryController.dispose();
    super.dispose();
  }

  Future<void> refresh({int requestedPage = 1}) async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.loadPage(
        month: monthController.text.trim(),
        status: status,
        query: queryController.text.trim(),
        page: requestedPage,
      );
      final rawItems = response['items'];
      final pagination = response['pagination'];
      if (!mounted) return;
      setState(() {
        items = rawItems is List
            ? rawItems.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList()
            : [];
        page = pagination is Map ? int.tryParse(pagination['page']?.toString() ?? '') ?? 1 : 1;
        pages = pagination is Map ? int.tryParse(pagination['pages']?.toString() ?? '') ?? 1 : 1;
        total = pagination is Map ? int.tryParse(pagination['total']?.toString() ?? '') ?? items.length : items.length;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String statusLabel(String value) => switch (value) {
    'draft' => t('مسودة', 'Draft'),
    'pending_approval' => t('بانتظار الاعتماد', 'Pending approval'),
    'approved' => t('معتمد', 'Approved'),
    'transferred' => t('محوّل', 'Transferred'),
    'received' => t('مستلم', 'Received'),
    'returned' => t('مرتجع', 'Returned'),
    'voided' => t('مبطل', 'Voided'),
    'cancelled' => t('ملغى', 'Cancelled'),
    _ => value,
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(t('التحويلات الشهرية', 'Monthly disbursements')),
        actions: [
          IconButton(
            onPressed: loading ? null : () => refresh(requestedPage: page),
            tooltip: t('تحديث', 'Refresh'),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => refresh(requestedPage: page),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(t('سجل التحويلات الشهرية', 'Monthly disbursement register'),
                        style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    Text(t('متابعة الدفعات وتنفيذ الإجراءات المتاحة حسب صلاحية الدور.',
                        'Review batches and use the actions available to your role.')),
                    if (['admin', 'financial_manager', 'general_manager', 'accountant_staff'].contains(widget.roleCode)
                        && widget.loadEligibleGroups != null && widget.createBatches != null) ...[
                      const SizedBox(height: 10),
                      OutlinedButton.icon(
                        key: const Key('disbursements-create-batches'),
                        onPressed: loading ? null : () async {
                          final created = await Navigator.of(context).push<bool>(MaterialPageRoute<bool>(
                            builder: (_) => DisbursementBatchCreatePage(
                              english: widget.english,
                              loadEligibleGroups: widget.loadEligibleGroups!,
                              createBatches: widget.createBatches!,
                            ),
                          ));
                          if (created == true && mounted) await refresh();
                        },
                        icon: const Icon(Icons.playlist_add_rounded),
                        label: Text(t('إنشاء دفعات الشهر', 'Create monthly batches')),
                      ),
                    ],
                    const SizedBox(height: 12),
                    TextField(
                      key: const Key('disbursements-month-filter'),
                      controller: monthController,
                      decoration: InputDecoration(
                        labelText: t('الشهر (YYYY-MM، اختياري)', 'Month (YYYY-MM, optional)'),
                        border: const OutlineInputBorder(),
                      ),
                      textDirection: TextDirection.ltr,
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      key: const Key('disbursements-status-filter'),
                      initialValue: status,
                      decoration: InputDecoration(
                        labelText: t('الحالة', 'Status'),
                        border: const OutlineInputBorder(),
                      ),
                      items: [
                        DropdownMenuItem(value: 'all', child: Text(t('كل الحالات', 'All statuses'))),
                        for (final value in ['draft', 'pending_approval', 'approved', 'transferred', 'received', 'returned', 'voided', 'cancelled'])
                          DropdownMenuItem(value: value, child: Text(statusLabel(value))),
                      ],
                      onChanged: loading ? null : (value) {
                        if (value != null) setState(() => status = value);
                      },
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      key: const Key('disbursements-search-filter'),
                      controller: queryController,
                      decoration: InputDecoration(
                        labelText: t('بحث باسم المربية أو المجموعة أو رقم الدفعة', 'Search nanny, group or batch ID'),
                        border: const OutlineInputBorder(),
                        prefixIcon: const Icon(Icons.search_rounded),
                      ),
                      onSubmitted: (_) => refresh(),
                    ),
                    const SizedBox(height: 10),
                    FilledButton.icon(
                      onPressed: loading ? null : () => refresh(),
                      icon: const Icon(Icons.filter_alt_rounded),
                      label: Text(t('تطبيق الفلاتر', 'Apply filters')),
                    ),
                  ],
                ),
              ),
            ),
            if (loading) const LinearProgressIndicator(),
            if (error != null)
              Card(
                color: Theme.of(context).colorScheme.errorContainer,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(error!),
                ),
              ),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                t('إجمالي النتائج: $total', 'Results: $total'),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            if (!loading && error == null && items.isEmpty)
              Card(child: Padding(
                padding: const EdgeInsets.all(18),
                child: Text(t('لا توجد دفعات مطابقة للفلاتر.', 'No batches match these filters.')),
              )),
            for (final batch in items)
              Card(
                key: ValueKey('disbursement-batch-${batch['id']}'),
                margin: const EdgeInsets.only(bottom: 10),
                child: InkWell(
                  onTap: () {
                    final id = int.tryParse(batch['id']?.toString() ?? '');
                    if (id == null || id < 1) return;
                    Navigator.of(context).push(MaterialPageRoute<void>(
                      builder: (_) => AdminDisbursementDetailPage(
                        english: widget.english,
                        batchId: id,
                        roleCode: widget.roleCode,
                        loadDetail: widget.loadDetail,
                        pickReceipt: widget.pickReceipt,
                        submitItemConfirmation: widget.submitItemConfirmation,
                        pickTransferReceipt: widget.pickTransferReceipt,
                        submitTransfer: widget.submitTransfer,
                        pickReturnReceipt: widget.pickReturnReceipt,
                        submitReturn: widget.submitReturn,
                      ),
                    ));
                  },
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(
                              '${t('دفعة', 'Batch')} #${batch['id'] ?? '—'} · ${batch['month'] ?? '—'}',
                              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                            )),
                            const SizedBox(width: 8),
                            Flexible(child: Chip(label: Text(statusLabel(batch['status']?.toString() ?? '')))),
                          ],
                        ),
                        Text(batch['group_name']?.toString().trim().isNotEmpty == true
                            ? batch['group_name'].toString()
                            : t('مجموعة غير محددة', 'Unassigned group')),
                        const SizedBox(height: 4),
                        Text('${t('المربية', 'Nanny')}: ${batch['nanny_name'] ?? '—'}'),
                        const SizedBox(height: 8),
                        Wrap(spacing: 12, runSpacing: 6, children: [
                          Text('${t('الإجمالي', 'Total')}: ${money(batch['total_amount'])}'),
                          Text('${t('الأسر', 'Families')}: ${batch['items_total'] ?? 0}'),
                          Text('${t('تم التأكيد', 'Paid')}: ${batch['items_paid'] ?? 0}'),
                          Text('${t('معلق', 'Pending')}: ${batch['items_pending'] ?? 0}'),
                        ]),
                        const Align(
                          alignment: AlignmentDirectional.centerEnd,
                          child: Icon(Icons.chevron_right_rounded),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            if (pages > 1)
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  OutlinedButton(
                    onPressed: loading || page <= 1 ? null : () => refresh(requestedPage: page - 1),
                    child: Text(t('السابق', 'Previous')),
                  ),
                  Text('${t('صفحة', 'Page')} $page / $pages'),
                  OutlinedButton(
                    onPressed: loading || page >= pages ? null : () => refresh(requestedPage: page + 1),
                    child: Text(t('التالي', 'Next')),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class AdminDisbursementDetailPage extends StatefulWidget {
  const AdminDisbursementDetailPage({
    super.key,
    required this.english,
    required this.batchId,
    required this.loadDetail,
    this.roleCode = 'admin',
    this.pickReceipt,
    this.submitItemConfirmation,
    this.pickTransferReceipt,
    this.submitTransfer,
    this.pickReturnReceipt,
    this.submitReturn,
  });

  final bool english;
  final int batchId;
  final String roleCode;
  final Future<PlatformFile?> Function()? pickReceipt;
  final Future<Map<String, dynamic>> Function(int itemId, PlatformFile receipt)? submitItemConfirmation;
  final Future<PlatformFile?> Function()? pickTransferReceipt;
  final Future<Map<String, dynamic>> Function(int batchId, PlatformFile receipt)? submitTransfer;
  final Future<PlatformFile?> Function()? pickReturnReceipt;
  final Future<Map<String, dynamic>> Function(int batchId, String reasonType, String reasonDetails, PlatformFile receipt)? submitReturn;
  final Future<Map<String, dynamic>> Function(int id, int page) loadDetail;

  @override
  State<AdminDisbursementDetailPage> createState() => _AdminDisbursementDetailPageState();
}

class _AdminDisbursementDetailPageState extends State<AdminDisbursementDetailPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> batch = {};
  List<Map<String, dynamic>> items = [];
  int page = 1;
  int pages = 1;
  final Map<int, PlatformFile> selectedReceipts = {};
  final Set<int> busyItems = {};
  PlatformFile? selectedTransferReceipt;
  bool transferBusy = false;
  final TextEditingController returnDetailsController = TextEditingController();
  String returnReasonType = 'انقطاع الاتصال مع الأم';
  PlatformFile? selectedReturnReceipt;
  bool returnBusy = false;

  String t(String ar, String en) => widget.english ? en : ar;
  String money(dynamic value) {
    final amount = num.tryParse(value?.toString() ?? '') ?? 0;
    return amount.toStringAsFixed(2).replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (match) => ',',
    );
  }

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    returnDetailsController.dispose();
    super.dispose();
  }

  Future<void> pickReturnReceiptFile() async {
    final picker = widget.pickReturnReceipt;
    if (picker == null) return;
    try {
      final file = await picker();
      if (!mounted || file == null) return;
      setState(() => selectedReturnReceipt = file);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> closeWithReturn() async {
    final receipt = selectedReturnReceipt;
    final submit = widget.submitReturn;
    if (receipt == null || submit == null || returnBusy) return;
    if (returnReasonType == 'سبب آخر' && returnDetailsController.text.trim().isEmpty) {
      setState(() => error = t('اكتب تفاصيل سبب الإرجاع.', 'Enter details for the selected return reason.'));
      return;
    }
    setState(() { returnBusy = true; error = null; });
    try {
      final result = await submit(widget.batchId, returnReasonType, returnDetailsController.text.trim(), receipt);
      if (!mounted) return;
      setState(() => selectedReturnReceipt = null);
      final returnedAmount = result['returned_amount'];
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(t('تم إقفال الدفعة وإرجاع ${money(returnedAmount)} إلى الصندوق.', 'Batch closed; ${money(returnedAmount)} returned to cash.')),
      ));
      await refresh(requestedPage: page);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => returnBusy = false);
    }
  }

  Future<void> refresh({int requestedPage = 1}) async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.loadDetail(widget.batchId, requestedPage);
      final rawBatch = response['batch'];
      final rawItems = response['items'];
      final pagination = response['items_pagination'];
      if (!mounted) return;
      setState(() {
        batch = rawBatch is Map ? Map<String, dynamic>.from(rawBatch) : {};
        items = rawItems is List ? rawItems.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList() : [];
        page = pagination is Map ? int.tryParse(pagination['page']?.toString() ?? '') ?? 1 : 1;
        pages = pagination is Map ? int.tryParse(pagination['pages']?.toString() ?? '') ?? 1 : 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> pickReceiptForItem(int itemId) async {
    final picker = widget.pickReceipt;
    if (picker == null) return;
    try {
      final file = await picker();
      if (!mounted || file == null) return;
      setState(() => selectedReceipts[itemId] = file);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> confirmItem(int itemId) async {
    final file = selectedReceipts[itemId];
    final submit = widget.submitItemConfirmation;
    if (file == null || submit == null || busyItems.contains(itemId)) return;
    setState(() => busyItems.add(itemId));
    try {
      await submit(itemId, file);
      if (!mounted) return;
      setState(() => selectedReceipts.remove(itemId));
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم تأكيد استلام الأسرة وإرفاق الإيصال.', 'Family receipt confirmed successfully.'))));
      await refresh(requestedPage: page);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => busyItems.remove(itemId));
    }
  }

  Future<void> pickTransferReceiptFile() async {
    final picker = widget.pickTransferReceipt;
    if (picker == null) return;
    try {
      final file = await picker();
      if (!mounted || file == null) return;
      setState(() => selectedTransferReceipt = file);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> transferBatch() async {
    final receipt = selectedTransferReceipt;
    final submit = widget.submitTransfer;
    if (receipt == null || submit == null || transferBusy) return;
    setState(() => transferBusy = true);
    try {
      await submit(widget.batchId, receipt);
      if (!mounted) return;
      setState(() => selectedTransferReceipt = null);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم التحويل وترحيل القيد المحاسبي.', 'Transfer completed and accounting journal posted.'))));
      await refresh(requestedPage: page);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => transferBusy = false);
    }
  }

  String itemStatus(String value) => switch (value) {
    'paid' => t('تم الصرف', 'Paid'),
    'pending' => t('معلق', 'Pending'),
    'returned' => t('مرتجع', 'Returned'),
    _ => value,
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('${t('تفاصيل الدفعة', 'Batch details')} #${widget.batchId}')),
      body: RefreshIndicator(
        onRefresh: () => refresh(requestedPage: page),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (loading) const LinearProgressIndicator(),
            if (error != null) Card(
              color: Theme.of(context).colorScheme.errorContainer,
              child: Padding(padding: const EdgeInsets.all(12), child: Text(error!)),
            ),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${t('الشهر', 'Month')}: ${batch['month'] ?? '—'}'),
                  Text('${t('الحالة', 'Status')}: ${batch['status'] ?? '—'}'),
                  Text('${t('المجموعة', 'Group')}: ${batch['group_name'] ?? '—'}'),
                  Text('${t('المربية', 'Nanny')}: ${batch['nanny_name'] ?? '—'}'),
                  const Divider(),
                  Text('${t('إجمالي الدفعة', 'Batch total')}: ${money(batch['total_amount'])}', style: const TextStyle(fontWeight: FontWeight.w900)),
                  Text('${t('الأسر', 'Families')}: ${batch['items_total'] ?? 0}'),
                  Text('${t('تم الصرف', 'Paid')}: ${batch['items_paid'] ?? 0} · ${money(batch['paid_amount'])}'),
                  Text('${t('معلق', 'Pending')}: ${batch['items_pending'] ?? 0} · ${money(batch['pending_amount'])}'),
                  Text('${t('مرتجع', 'Returned')}: ${batch['items_returned'] ?? 0} · ${money(batch['returned_amount'])}'),
                  if (['admin', 'financial_manager', 'general_manager'].contains(widget.roleCode)
                      && batch['status']?.toString() == 'pending_approval'
                      && widget.pickTransferReceipt != null && widget.submitTransfer != null) ...[
                    const Divider(height: 24),
                    OutlinedButton.icon(
                      key: const Key('disbursement-pick-transfer-receipt'),
                      onPressed: transferBusy ? null : pickTransferReceiptFile,
                      icon: const Icon(Icons.attach_file_rounded),
                      label: Text(selectedTransferReceipt?.name ?? t('اختيار إيصال التحويل (إجباري)', 'Choose transfer receipt (required)')),
                    ),
                    const SizedBox(height: 8),
                    FilledButton.icon(
                      key: const Key('disbursement-submit-transfer'),
                      onPressed: selectedTransferReceipt != null && !transferBusy ? transferBatch : null,
                      icon: transferBusy
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.account_balance_wallet_rounded),
                      label: Text(t('تأكيد التحويل وترحيل القيد', 'Transfer and post accounting')),
                    ),
                  ],
                  if (widget.roleCode == 'nanny'
                      && batch['status']?.toString() == 'transferred'
                      && (int.tryParse(batch['items_pending']?.toString() ?? '') ?? 0) > 0
                      && widget.pickReturnReceipt != null && widget.submitReturn != null) ...[
                    const Divider(height: 24),
                    Text(t('إرجاع المبلغ غير المصروف', 'Return outstanding amount'),
                        style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      key: const Key('disbursement-return-reason'),
                      initialValue: returnReasonType,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: t('سبب الإرجاع', 'Return reason'), border: const OutlineInputBorder()),
                      items: [
                        DropdownMenuItem(value: 'انقطاع الاتصال مع الأم', child: Text(t('انقطاع الاتصال مع الأم', 'Lost contact with mother'))),
                        DropdownMenuItem(value: 'تعذر الوصول للأسرة', child: Text(t('تعذر الوصول للأسرة', 'Unable to reach family'))),
                        DropdownMenuItem(value: 'رفض استلام المبلغ', child: Text(t('رفض استلام المبلغ', 'Family refused payment'))),
                        DropdownMenuItem(value: 'تغيير بيانات الحساب البنكي', child: Text(t('تغيير بيانات الحساب البنكي', 'Bank details changed'))),
                        DropdownMenuItem(value: 'انتقال الأسرة', child: Text(t('انتقال الأسرة', 'Family relocated'))),
                        DropdownMenuItem(value: 'وفاة المستفيد', child: Text(t('وفاة المستفيد', 'Beneficiary deceased'))),
                        DropdownMenuItem(value: 'إيقاف الكفالة', child: Text(t('إيقاف الكفالة', 'Sponsorship stopped'))),
                        DropdownMenuItem(value: 'سبب آخر', child: Text(t('سبب آخر', 'Other'))),
                      ],
                      onChanged: returnBusy ? null : (value) {
                        if (value != null) setState(() { returnReasonType = value; error = null; });
                      },
                    ),
                    if (returnReasonType == 'سبب آخر') ...[
                      const SizedBox(height: 8),
                      TextField(
                        key: const Key('disbursement-return-reason-details'),
                        controller: returnDetailsController,
                        minLines: 2,
                        maxLines: 4,
                        decoration: InputDecoration(labelText: t('تفاصيل السبب (إجباري)', 'Reason details (required)'), border: const OutlineInputBorder()),
                        onChanged: (_) { if (error != null) setState(() => error = null); },
                      ),
                    ],
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      key: const Key('disbursement-pick-return-receipt'),
                      onPressed: returnBusy ? null : pickReturnReceiptFile,
                      icon: const Icon(Icons.attach_file_rounded),
                      label: Text(selectedReturnReceipt?.name ?? t('اختيار إيصال الإرجاع (إجباري)', 'Choose return receipt (required)')),
                    ),
                    const SizedBox(height: 8),
                    FilledButton.icon(
                      key: const Key('disbursement-submit-return'),
                      onPressed: selectedReturnReceipt != null && !returnBusy
                          && (returnReasonType != 'سبب آخر' || returnDetailsController.text.trim().isNotEmpty)
                          ? closeWithReturn : null,
                      icon: returnBusy
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.assignment_return_rounded),
                      label: Text(t('إقفال الدفعة وإرجاع المتبقي', 'Close batch and return remaining funds')),
                    ),
                  ],
                ]),
              ),
            ),
            const SizedBox(height: 8),
            Text(t('تفاصيل الأسر', 'Family items'), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900)),
            for (final item in items)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(item['mother_name']?.toString().trim().isNotEmpty == true
                          ? item['mother_name'].toString()
                          : (item['family_code']?.toString() ?? t('أسرة', 'Family'))),
                      subtitle: Text('${item['family_code'] ?? ''} · ${t('الأطفال', 'Children')}: ${item['children_count'] ?? 0} · ${itemStatus(item['status']?.toString() ?? '')}'),
                      trailing: Text(money(item['amount']), style: const TextStyle(fontWeight: FontWeight.w800)),
                    ),
                    if (widget.roleCode == 'nanny'
                        && ['transferred', 'received'].contains(batch['status']?.toString())
                        && item['status']?.toString() == 'pending'
                        && widget.pickReceipt != null
                        && widget.submitItemConfirmation != null) ...[
                      OutlinedButton.icon(
                        key: Key('disbursement-pick-receipt-${item['id']}'),
                        onPressed: busyItems.contains(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                            ? null
                            : () => pickReceiptForItem(int.tryParse(item['id']?.toString() ?? '') ?? 0),
                        icon: const Icon(Icons.attach_file_rounded),
                        label: Text(selectedReceipts[int.tryParse(item['id']?.toString() ?? '') ?? 0]?.name
                            ?? t('اختيار إيصال الاستلام', 'Choose receipt file')),
                      ),
                      const SizedBox(height: 6),
                      FilledButton.icon(
                        key: Key('disbursement-confirm-item-${item['id']}'),
                        onPressed: selectedReceipts.containsKey(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                            && !busyItems.contains(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                            ? () => confirmItem(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                            : null,
                        icon: busyItems.contains(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.check_circle_outline_rounded),
                        label: Text(t('تأكيد الاستلام', 'Confirm receipt')),
                      ),
                    ],
                  ]),
                ),
              ),
            if (!loading && items.isEmpty && error == null)
              Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(t('لا توجد تفاصيل لهذه الدفعة.', 'No family items for this batch.')))),
            if (pages > 1)
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                OutlinedButton(onPressed: loading || page <= 1 ? null : () => refresh(requestedPage: page - 1), child: Text(t('السابق', 'Previous'))),
                Text('${t('صفحة', 'Page')} $page / $pages'),
                OutlinedButton(onPressed: loading || page >= pages ? null : () => refresh(requestedPage: page + 1), child: Text(t('التالي', 'Next'))),
              ]),
            const SizedBox(height: 12),
            if (widget.roleCode != 'nanny') Text(t('لا تتوفر إجراءات أخرى للدفعة في هذه الشاشة حالياً.', 'Other batch actions are not available on this screen yet.'), style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class DisbursementBatchCreatePage extends StatefulWidget {
  const DisbursementBatchCreatePage({super.key, required this.english, required this.loadEligibleGroups, required this.createBatches});
  final bool english;
  final Future<Map<String, dynamic>> Function(String month) loadEligibleGroups;
  final Future<Map<String, dynamic>> Function(String month, List<int> groupIds) createBatches;
  @override
  State<DisbursementBatchCreatePage> createState() => _DisbursementBatchCreatePageState();
}

class _DisbursementBatchCreatePageState extends State<DisbursementBatchCreatePage> {
  late final TextEditingController monthController;
  List<Map<String, dynamic>> groups = [];
  final Set<int> selectedGroupIds = {};
  bool loading = true;
  bool submitting = false;
  String? error;
  String? loadedMonth;
  String t(String ar, String en) => widget.english ? en : ar;
  String money(dynamic value) {
    final amount = num.tryParse(value?.toString() ?? '') ?? 0;
    return amount.toStringAsFixed(2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (m) => ',');
  }
  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    monthController = TextEditingController(text: '${now.year}-${now.month.toString().padLeft(2, '0')}');
    loadGroups();
  }
  @override
  void dispose() { monthController.dispose(); super.dispose(); }
  Future<void> loadGroups() async {
    final month = monthController.text.trim();
    if (!RegExp(r'^\d{4}-(0[1-9]|1[0-2])$').hasMatch(month)) {
      setState(() => error = t('أدخل الشهر بصيغة YYYY-MM.', 'Enter the month as YYYY-MM.'));
      return;
    }
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.loadEligibleGroups(month);
      final raw = response['eligible_groups'];
      if (!mounted) return;
      setState(() {
        groups = raw is List ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
        loadedMonth = response['eligible_month']?.toString() ?? month;
        selectedGroupIds.clear();
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
  Future<void> createSelected() async {
    final month = monthController.text.trim();
    if (!RegExp(r'^\d{4}-(0[1-9]|1[0-2])$').hasMatch(month)) {
      setState(() => error = t('أدخل الشهر بصيغة YYYY-MM.', 'Enter the month as YYYY-MM.'));
      return;
    }
    if (loadedMonth != month) {
      await loadGroups();
      if (!mounted || loadedMonth != month) return;
    }
    if (selectedGroupIds.isEmpty) {
      setState(() => error = t('اختر مجموعة واحدة على الأقل.', 'Select at least one group.'));
      return;
    }
    setState(() { submitting = true; error = null; });
    try {
      final response = await widget.createBatches(month, selectedGroupIds.toList()..sort());
      if (!mounted) return;
      final created = int.tryParse(response['created_count']?.toString() ?? '') ?? 0;
      final skipped = int.tryParse(response['skipped_count']?.toString() ?? '') ?? 0;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(t('نتيجة إنشاء الدفعات', 'Batch creation result')),
          content: Text(t('تم إنشاء $created دفعة وتخطي $skipped مجموعة.', 'Created $created batch(es) and skipped $skipped group(s).')),
          actions: [TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('متابعة', 'Continue')))],
        ),
      );
      if (!mounted) return;
      Navigator.pop(context, created > 0);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(t('إنشاء دفعات الشهر', 'Create monthly batches'))),
    body: RefreshIndicator(
      onRefresh: loadGroups,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(t('المجموعات المؤهلة', 'Eligible groups'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text(t('تظهر المجموعات التي لديها تحقق مكتمل للأسرة لهذا الشهر. تُنشأ الدفعة بانتظار الاعتماد فقط.', 'Only groups with fully verified families for this month are shown. Created batches remain pending approval.')),
              const SizedBox(height: 12),
              TextField(key: const Key('disbursement-create-month'), controller: monthController, textDirection: TextDirection.ltr,
                decoration: InputDecoration(labelText: t('الشهر (YYYY-MM)', 'Month (YYYY-MM)'), border: const OutlineInputBorder())),
              const SizedBox(height: 8),
              OutlinedButton.icon(onPressed: loading || submitting ? null : loadGroups, icon: const Icon(Icons.refresh_rounded), label: Text(t('تحميل المجموعات', 'Load eligible groups'))),
              if (loadedMonth != null) Text(t('شهر المجموعات: $loadedMonth', 'Groups month: $loadedMonth')),
              if (loading) const LinearProgressIndicator(),
              if (error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(error!, style: const TextStyle(color: Color(0xFFB42318)))),
            ]),
          )),
          if (!loading && error == null && groups.isEmpty) Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(t('لا توجد مجموعات مؤهلة لهذا الشهر.', 'No eligible groups for this month.')))),
          for (final group in groups)
            Card(child: CheckboxListTile(
              key: Key('disbursement-group-${group['id']}'),
              value: selectedGroupIds.contains(int.tryParse(group['id']?.toString() ?? '') ?? 0),
              onChanged: loading || submitting ? null : (checked) {
                final id = int.tryParse(group['id']?.toString() ?? '') ?? 0;
                if (id < 1) return;
                setState(() {
                  if (checked == true) selectedGroupIds.add(id);
                  else selectedGroupIds.remove(id);
                  error = null;
                });
              },
              title: Text(group['group_name']?.toString() ?? t('مجموعة', 'Group'), style: const TextStyle(fontWeight: FontWeight.w800)),
              subtitle: Text('${t('المربية', 'Nanny')}: ${group['nanny_name'] ?? '—'} · ${t('الأطفال', 'Children')}: ${group['orphan_count'] ?? 0} · ${t('المتوقع', 'Expected')}: ${money(group['expected_amount'])}'),
              controlAffinity: ListTileControlAffinity.leading,
            )),
          const SizedBox(height: 12),
          FilledButton.icon(key: const Key('disbursement-create-submit'), onPressed: loading || submitting ? null : createSelected,
            icon: submitting ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.playlist_add_check_rounded),
            label: Text(t('إنشاء الدفعات المحددة', 'Create selected batches'))),
        ],
      ),
    ),
  );
}
