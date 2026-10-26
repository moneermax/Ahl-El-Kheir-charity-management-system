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
  });

  final bool english;
  final String roleCode;
  final Future<PlatformFile?> Function()? pickReceipt;
  final Future<Map<String, dynamic>> Function(int itemId, PlatformFile receipt)? submitItemConfirmation;
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
                    Text(t('سجل للقراءة فقط', 'Read-only register'),
                        style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    Text(t('متابعة الدفعات وحالاتها دون تنفيذ أي إجراء مالي من التطبيق.',
                        'Review batches and statuses without performing financial actions.')),
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
  });

  final bool english;
  final int batchId;
  final String roleCode;
  final Future<PlatformFile?> Function()? pickReceipt;
  final Future<Map<String, dynamic>> Function(int itemId, PlatformFile receipt)? submitItemConfirmation;
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
            if (widget.roleCode != 'nanny') Text(t('عرض فقط — إجراءات الدفعات غير متاحة لهذا الدور بعد.', 'Read-only — batch actions are not yet available for this role.'), style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}
