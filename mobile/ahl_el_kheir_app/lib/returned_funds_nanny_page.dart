import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

class ReturnedFundsNannyPage extends StatefulWidget {
  const ReturnedFundsNannyPage({
    super.key,
    required this.english,
    required this.loadItems,
    required this.pickReceipt,
    required this.submitRedelivery,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadItems;
  final Future<PlatformFile?> Function() pickReceipt;
  final Future<Map<String, dynamic>> Function(int reissueId, PlatformFile receipt) submitRedelivery;

  @override
  State<ReturnedFundsNannyPage> createState() => _ReturnedFundsNannyPageState();
}

class _ReturnedFundsNannyPageState extends State<ReturnedFundsNannyPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  final Set<int> submitting = {};

  String text(String ar, String en) => widget.english ? en : ar;
  String amount(dynamic value) => (double.tryParse(value?.toString() ?? '') ?? 0).toStringAsFixed(2);

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await widget.loadItems();
      final raw = result['items'];
      final next = raw is List
          ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() => items = next);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> confirmRedelivery(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (id <= 0 || submitting.contains(id)) return;
    try {
      final receipt = await widget.pickReceipt();
      if (receipt == null || !mounted) return;
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(text('تأكيد إعادة التسليم', 'Confirm redelivery')),
          content: Text(text(
            'سيتم تأكيد تسليم مبلغ ${amount(item['amount'])} ج.س للأسرة ${item['mother_name'] ?? ''} بعد إرفاق الإيصال ${receipt.name}.',
            'This confirms delivery of ${amount(item['amount'])} SDG to ${item['mother_name'] ?? ''} using receipt ${receipt.name}.',
          )),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: Text(text('إلغاء', 'Cancel')),
            ),
            FilledButton(
              key: const ValueKey('returned-funds-confirm-submit'),
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: Text(text('تأكيد التسليم', 'Confirm redelivery')),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
      setState(() => submitting.add(id));
      await widget.submitRedelivery(id, receipt);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(text('تم تأكيد إعادة التسليم بنجاح.', 'Redelivery confirmed successfully.')),
      ));
      await load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => submitting.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(text('إعادة صرف المبالغ المرتجعة', 'Reissue returned funds')),
        actions: [
          IconButton(
            tooltip: text('تحديث', 'Refresh'),
            onPressed: loading ? null : load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Icon(Icons.info_outline_rounded, color: Color(0xFFB45309)),
                  const SizedBox(width: 12),
                  Expanded(child: Text(text(
                    'تظهر هنا المبالغ التي اعتمدها المحاسب لإعادة تسليمها للأسرة. لا يتم تسجيل مصروف جديد أو خصم من الصندوق إلا بعد رفع إيصال التسليم وتأكيده.',
                    'Only amounts authorized by accounting appear here. A new expense and cash deduction are recorded only after a receipt is uploaded and delivery is confirmed.',
                  ))),
                ]),
              ),
            ),
            const SizedBox(height: 8),
            if (loading)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(children: [
                    Text(error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: load,
                      icon: const Icon(Icons.refresh_rounded),
                      label: Text(text('إعادة المحاولة', 'Try again')),
                    ),
                  ]),
                ),
              )
            else if (items.isEmpty)
              Padding(
                padding: const EdgeInsets.all(28),
                child: Column(children: [
                  const Icon(Icons.check_circle_outline_rounded, size: 42, color: Color(0xFF16A34A)),
                  const SizedBox(height: 12),
                  Text(
                    text('لا توجد مبالغ معتمدة لإعادة التسليم حالياً.', 'There are no returned amounts authorized for redelivery.'),
                    textAlign: TextAlign.center,
                  ),
                ]),
              )
            else
              ...items.map((item) {
                final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
                final family = (item['mother_name']?.toString() ?? '').trim();
                final familyCode = (item['family_code']?.toString() ?? '').trim();
                final group = (item['group_name']?.toString() ?? '').trim();
                final month = item['month']?.toString() ?? '';
                final reason = (item['authorization_reason']?.toString() ?? '').trim();
                final busy = submitting.contains(id);
                return Card(
                  key: ValueKey('returned-funds-card-$id'),
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(family.isEmpty ? text('الأسرة', 'Family') : family,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                      if (familyCode.isNotEmpty) Text(familyCode, style: Theme.of(context).textTheme.bodySmall),
                      const SizedBox(height: 12),
                      _DetailRow(label: text('الشهر الأصلي', 'Original month'), value: month),
                      if (group.isNotEmpty) _DetailRow(label: text('المجموعة', 'Group'), value: group),
                      _DetailRow(label: text('المبلغ', 'Amount'), value: '${amount(item['amount'])} SDG'),
                      if (item['authorized_at'] != null && item['authorized_at'].toString().isNotEmpty)
                        _DetailRow(label: text('تاريخ الاعتماد', 'Authorized at'), value: item['authorized_at'].toString()),
                      if (reason.isNotEmpty) _DetailRow(label: text('سبب الاعتماد', 'Authorization reason'), value: reason),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          key: ValueKey('returned-funds-confirm-$id'),
                          onPressed: busy ? null : () => confirmRedelivery(item),
                          icon: busy
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.upload_file_rounded),
                          label: Text(busy
                              ? text('جارٍ الحفظ…', 'Submitting…')
                              : text('إرفاق الإيصال وتأكيد التسليم', 'Attach receipt and confirm delivery')),
                        ),
                      ),
                    ]),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 120, child: Text(label, style: Theme.of(context).textTheme.bodySmall)),
      const SizedBox(width: 8),
      Expanded(child: Text(value)),
    ]),
  );
}
