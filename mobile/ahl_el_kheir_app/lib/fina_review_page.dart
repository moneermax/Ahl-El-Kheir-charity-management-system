import 'dart:typed_data';
import 'package:flutter/material.dart';

class FinaReviewPage extends StatefulWidget {
  const FinaReviewPage({
    super.key,
    required this.english,
    required this.loadReview,
    required this.submitAction,
    required this.loadReceipt,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadReview;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic>) submitAction;
  final Future<Map<String, dynamic>> Function(int) loadReceipt;

  @override
  State<FinaReviewPage> createState() => _FinaReviewPageState();
}

class _FinaReviewPageState extends State<FinaReviewPage> {
  bool loading = true;
  bool acting = false;
  bool canReview = false;
  int currentUserId = 0;
  String? error;
  List<Map<String, dynamic>> pending = [];
  List<Map<String, dynamic>> history = [];

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadReview();
      if (!mounted) return;
      setState(() {
        canReview = result['can_review'] == true;
        currentUserId = int.tryParse((result['current_user_id'] ?? 0).toString()) ?? 0;
        pending = _rows(result['pending']);
        history = _rows(result['history']);
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  List<Map<String, dynamic>> _rows(dynamic value) => value is List
      ? value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList()
      : [];

  String _source(Map<String, dynamic> row) {
    if (row['source_type'] == 'sponsor') {
      final code = (row['sponsor_code'] ?? '').toString();
      final name = (row['sponsor_name'] ?? '').toString();
      return [code, name].where((part) => part.isNotEmpty).join(' — ');
    }
    final name = (row['source_name'] ?? '').toString();
    final type = row['source_type'] == 'organization'
        ? t('منظمة / جهة', 'Organization')
        : row['source_type'] == 'person'
            ? t('شخص خارجي', 'External person')
            : t('أخرى', 'Other');
    return type + ' — ' + name;
  }

  Future<void> _viewReceipt(int collectionId) async {
    try {
      final result = await widget.loadReceipt(collectionId);
      if (!mounted) return;
      final mime = (result['mime'] ?? '').toString();
      final value = result['bytes'];
      if (value is! Uint8List) throw StateError('Invalid receipt response');
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(t('إيصال التحصيل', 'Collection receipt')),
          content: SizedBox(
            width: 520,
            child: mime.startsWith('image/')
                ? InteractiveViewer(
                    child: Image.memory(value, fit: BoxFit.contain),
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.picture_as_pdf_outlined, size: 56),
                      const SizedBox(height: 12),
                      Text(t(
                        'تم استرجاع ملف PDF بأمان، لكن معاينة PDF داخل التطبيق لم تُنفذ بعد.',
                        'The PDF was retrieved securely, but in-app PDF preview is not implemented yet.',
                      )),
                    ],
                  ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('إغلاق', 'Close'))),
          ],
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<void> _act(Map<String, dynamic> row, String action) async {
    final id = int.tryParse((row['id'] ?? 0).toString()) ?? 0;
    if (id <= 0 || acting) return;
    String? note;
    if (action == 'return') {
      final controller = TextEditingController();
      note = await showDialog<String>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(t('سبب إرجاع التحصيل', 'Reason for returning collection')),
          content: TextField(
            controller: controller,
            autofocus: true,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: t('سبب الإرجاع مطلوب', 'Return reason is required'),
              border: const OutlineInputBorder(),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('إلغاء', 'Cancel'))),
            FilledButton(
              onPressed: () {
                final value = controller.text.trim();
                if (value.isNotEmpty) Navigator.pop(dialogContext, value);
              },
              child: Text(t('إرجاع', 'Return')),
            ),
          ],
        ),
      );
      controller.dispose();
      if (note == null || note.trim().isEmpty) return;
    } else {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(t('اعتماد التحصيل؟', 'Approve collection?')),
          content: Text(t(
            'سيتم اعتماد التحصيل وترحيله بالقيد المحاسبي المعتمد لفينا الخير.',
            'This approves the collection and posts it using the existing Fina accounting workflow.',
          )),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(t('إلغاء', 'Cancel'))),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(t('اعتماد', 'Approve'))),
          ],
        ),
      );
      if (confirmed != true) return;
    }

    setState(() { acting = true; error = null; });
    try {
      final payload = <String, dynamic>{'id': id, 'action': action};
      if (note != null) payload['return_note'] = note;
      await widget.submitAction(payload);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(action == 'approve'
            ? t('تم اعتماد التحصيل وترحيله.', 'Collection approved and posted.')
            : t('تم إرجاع التحصيل.', 'Collection returned.')),
      ));
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => acting = false);
    }
  }

  Widget _collectionCard(Map<String, dynamic> row, {required bool isHistory}) {
    final id = int.tryParse((row['id'] ?? 0).toString()) ?? 0;
    final creatorId = int.tryParse((row['created_by'] ?? 0).toString()) ?? 0;
    final own = creatorId == currentUserId;
    final status = (row['status'] ?? 'pending').toString();
    final amount = (row['amount'] ?? '').toString();
    final currency = (row['currency_code'] ?? '').toString();
    final date = (row['collection_date'] ?? '').toString();
    final method = (row['payment_method'] ?? '').toString();
    final receipt = (row['receipt_path'] ?? '').toString().isNotEmpty;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: Text('#' + id.toString() + ' — ' + _source(row),
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
              const SizedBox(width: 8),
              Text(amount + ' ' + currency, style: const TextStyle(fontWeight: FontWeight.w800)),
            ]),
            const SizedBox(height: 8),
            Text(t('التاريخ: ', 'Date: ') + date + '   •   ' + t('طريقة الدفع: ', 'Method: ') + method),
            if ((row['purpose_note'] ?? '').toString().isNotEmpty)
              Padding(padding: const EdgeInsets.only(top: 5),
                child: Text(t('الغرض: ', 'Purpose: ') + row['purpose_note'].toString())),
            if ((row['creator_name'] ?? '').toString().isNotEmpty)
              Padding(padding: const EdgeInsets.only(top: 5),
                child: Text(t('المنشئ: ', 'Created by: ') + row['creator_name'].toString())),
            Padding(
              padding: const EdgeInsets.only(top: 5),
              child: receipt
                  ? Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: OutlinedButton.icon(
                        onPressed: acting ? null : () => _viewReceipt(id),
                        icon: const Icon(Icons.attach_file),
                        label: Text(t('عرض الإيصال', 'View receipt')),
                      ),
                    )
                  : Text(t('لا يوجد إيصال مرفق.', 'No receipt attached.')),
            ),
            if (isHistory) ...[
              const Divider(height: 18),
              Text(status == 'approved'
                  ? t('الحالة: معتمد', 'Status: Approved')
                  : t('الحالة: مرتجع', 'Status: Returned')),
              if ((row['reviewer_name'] ?? '').toString().isNotEmpty)
                Text(t('المراجع: ', 'Reviewed by: ') + row['reviewer_name'].toString()),
              if (row['accounting_journal_id'] != null)
                Text(t('القيد المحاسبي: #', 'Journal entry: #') + row['accounting_journal_id'].toString()),
              if ((row['return_note'] ?? '').toString().isNotEmpty)
                Text(t('سبب الإرجاع: ', 'Return reason: ') + row['return_note'].toString()),
            ] else if (canReview) ...[
              const SizedBox(height: 10),
              if (own)
                Text(t('لا يمكنك مراجعة تحصيل سجلته بنفسك.', 'You cannot review a collection you created.'),
                    style: TextStyle(color: Theme.of(context).colorScheme.error))
              else
                Row(children: [
                  Expanded(child: OutlinedButton.icon(
                    onPressed: acting ? null : () => _act(row, 'return'),
                    icon: const Icon(Icons.undo),
                    label: Text(t('إرجاع', 'Return')),
                  )),
                  const SizedBox(width: 10),
                  Expanded(child: FilledButton.icon(
                    onPressed: acting ? null : () => _act(row, 'approve'),
                    icon: const Icon(Icons.check_circle_outline),
                    label: Text(t('اعتماد وترحيل', 'Approve & post')),
                  )),
                ]),
            ],
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('مراجعة تحصيلات فينا الخير', 'Fina collection review')),
      actions: [IconButton(onPressed: loading || acting ? null : refresh, icon: const Icon(Icons.refresh))],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: refresh,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (error != null)
                  Card(color: Theme.of(context).colorScheme.errorContainer,
                    child: Padding(padding: const EdgeInsets.all(12), child: Text(error!))),
                Text(t('طلبات بانتظار المراجعة', 'Pending collections'),
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                if (pending.isEmpty) Text(t('لا توجد تحصيلات معلقة.', 'No pending collections.')),
                for (final row in pending) _collectionCard(row, isHistory: false),
                const SizedBox(height: 16),
                Text(t('السجل الأخير', 'Recent history'),
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                if (history.isEmpty) Text(t('لا يوجد سجل سابق.', 'No previous history.')),
                for (final row in history) _collectionCard(row, isHistory: true),
              ],
            ),
          ),
  );
}
