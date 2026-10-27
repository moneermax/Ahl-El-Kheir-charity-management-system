import 'package:flutter/material.dart';

class OrdinaryTransactionReviewPage extends StatefulWidget {
  const OrdinaryTransactionReviewPage({
    super.key,
    required this.english,
    required this.loadQueue,
    required this.submitAction,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadQueue;
  final Future<void> Function(int transactionId, String action, String returnReason) submitAction;

  @override
  State<OrdinaryTransactionReviewPage> createState() => _OrdinaryTransactionReviewPageState();
}

class _OrdinaryTransactionReviewPageState extends State<OrdinaryTransactionReviewPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> pending = [];
  List<Map<String, dynamic>> history = [];
  final Set<int> submitting = {};
  final Map<int, TextEditingController> returnReasons = {};

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    for (final controller in returnReasons.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TextEditingController reasonController(int id) =>
      returnReasons.putIfAbsent(id, TextEditingController.new);

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadQueue();
      List<Map<String, dynamic>> parse(dynamic value) => value is List
          ? value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        pending = parse(result['pending']);
        history = parse(result['history']);
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String purpose(Map<String, dynamic> item) {
    final label = item['purpose_label']?.toString() ?? '';
    if (!widget.english) return label;
    const labels = {
      'كفالة شهرية': 'Monthly sponsorship',
      'رسوم دراسية': 'School fees',
      'علاج وأدوية': 'Medicine',
      'هدية/عيدية': 'Gift',
      'أخرى': 'Other',
      'رسوم إدارية': 'Administrative fee',
      'تبرع عام': 'General donation',
      'تبرع مشروع': 'Project donation',
    };
    return labels[label] ?? label;
  }

  String statusLabel(String status) => switch (status) {
        'posted' => t('مرحّلة', 'Posted'),
        'returned' => t('مُعادة للتعديل', 'Returned'),
        'cancelled' => t('ملغاة', 'Cancelled'),
        _ => status,
      };

  Future<void> submitTransaction(Map<String, dynamic> item, String action) async {
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (id < 1 || submitting.contains(id)) return;
    final reason = action == 'return' ? reasonController(id).text.trim() : '';
    if (action == 'return' && reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(t('اكتب سبب الإرجاع قبل إعادة الدفعة.', 'Enter a reason before returning the transaction.')),
      ));
      return;
    }
    final approving = action == 'approve';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approving ? t('اعتماد الدفعة؟', 'Approve transaction?') : t('إرجاع الدفعة؟', 'Return transaction?')),
        content: Text(approving
            ? t('سيتم ترحيل الدفعة وإنشاء القيد المحاسبي ضمن عملية واحدة.', 'The transaction and its accounting journal will be posted atomically.')
            : t('سيتم إرجاع الدفعة للمنشئ مع حفظ سبب الإرجاع.', 'The transaction will be returned to its creator with the reason recorded.')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(approving ? t('اعتماد وترحيل', 'Approve and post') : t('إرجاع للتعديل', 'Return for correction')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() { submitting.add(id); error = null; });
    try {
      await widget.submitAction(id, action, reason);
      if (!mounted) return;
      reasonController(id).clear();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(approving
            ? t('تم اعتماد الدفعة وترحيل القيد المحاسبي.', 'Transaction approved and journal posted.')
            : t('تم إرجاع الدفعة للمنشئ للتعديل.', 'Transaction returned to its creator for correction.')),
      ));
      await refresh();
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => submitting.remove(id));
    }
  }

  Widget detail(String label, String value) => Padding(
        padding: const EdgeInsets.only(bottom: 5),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 96, child: Text(label, style: Theme.of(context).textTheme.bodySmall)),
            Expanded(child: Text(value.isEmpty ? '—' : value)),
          ],
        ),
      );

  Widget pendingCard(Map<String, dynamic> item) {
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    final busy = submitting.contains(id);
    final amount = double.tryParse(item['amount']?.toString() ?? '') ?? 0;
    final code = item['transaction_code']?.toString() ?? '';
    final receipt = item['has_receipt'] == true || item['has_unified_receipt'] == true;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(child: Icon(Icons.fact_check_outlined, color: Theme.of(context).colorScheme.primary)),
                const SizedBox(width: 10),
                Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(code.isEmpty ? '—' : code, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                    Text(item['submitted_at']?.toString() ?? item['transaction_date']?.toString() ?? '—'),
                  ],
                )),
                Text('${amount.toStringAsFixed(2)} ${item['currency_code'] ?? ''}',
                  textAlign: TextAlign.end,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
              ],
            ),
            const SizedBox(height: 12),
            detail(t('المنشئ', 'Created by'), item['creator_name']?.toString() ?? ''),
            detail(t('الكفيل', 'Sponsor'), item['sponsor_name']?.toString() ?? ''),
            detail(t('الغرض', 'Purpose'), purpose(item)),
            if ((item['payment_period']?.toString() ?? '').isNotEmpty)
              detail(t('الفترة', 'Period'), item['payment_period'].toString()),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: Chip(
                avatar: Icon(receipt ? Icons.attach_file_rounded : Icons.insert_drive_file_outlined, size: 16),
                label: Text(receipt ? t('يوجد إيصال مرفق', 'Receipt attached') : t('لا يوجد إيصال', 'No receipt attached')),
              ),
            ),
            TextField(
              key: ValueKey('ordinary-return-reason-$id'),
              controller: reasonController(id),
              enabled: !busy,
              maxLength: 255,
              decoration: InputDecoration(
                labelText: t('سبب الإرجاع (إجباري عند الإرجاع)', 'Return reason (required to return)'),
                border: const OutlineInputBorder(),
                counterText: '',
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton.icon(
                  onPressed: busy ? null : () => submitTransaction(item, 'approve'),
                  icon: const Icon(Icons.check_circle_outline_rounded),
                  label: Text(t('اعتماد وترحيل', 'Approve and post')),
                ),
                OutlinedButton.icon(
                  onPressed: busy ? null : () => submitTransaction(item, 'return'),
                  icon: const Icon(Icons.reply_rounded),
                  label: Text(t('إرجاع للتعديل', 'Return for correction')),
                ),
                if (busy) const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget historyCard(Map<String, dynamic> item) {
    final amount = double.tryParse(item['amount']?.toString() ?? '') ?? 0;
    final status = item['status']?.toString() ?? '';
    final code = item['transaction_code']?.toString() ?? '';
    return Card(
      child: ListTile(
        leading: CircleAvatar(child: Icon(status == 'posted' ? Icons.check_rounded : Icons.reply_rounded)),
        title: Text(code.isEmpty ? '—' : code),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${purpose(item)} · ${amount.toStringAsFixed(2)} ${item['currency_code'] ?? ''}'),
              Text('${t('المنشئ', 'Creator')}: ${item['creator_name'] ?? '—'}'),
              Text('${t('المراجع', 'Reviewer')}: ${item['reviewer_name'] ?? '—'}'),
              Text(item['reviewed_at']?.toString() ?? '—'),
              if ((item['review_reason']?.toString() ?? '').isNotEmpty)
                Text('${t('سبب الإرجاع', 'Return reason')}: ${item['review_reason']}'),
            ],
          ),
        ),
        trailing: Chip(label: Text(statusLabel(status))),
        isThreeLine: true,
      ),
    );
  }

  Widget queueList(List<Map<String, dynamic>> rows, String emptyText, {required bool isPending}) =>
      RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(14),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (error != null)
              Card(child: ListTile(
                title: Text(error!),
                trailing: IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: refresh),
              )),
            if (loading) const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
            if (!loading && error == null && rows.isEmpty)
              Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(emptyText))),
            if (!loading && error == null)
              for (final row in rows) isPending ? pendingCard(row) : historyCard(row),
          ],
        ),
      );

  @override
  Widget build(BuildContext context) => DefaultTabController(
        length: 2,
        child: Scaffold(
          appBar: AppBar(
            title: Text(t('مراجعة الدفعات المالية', 'Financial transaction review')),
            bottom: TabBar(tabs: [
              Tab(text: '${t('بانتظار المراجعة', 'Pending')} (${pending.length})'),
              Tab(text: t('سجل المراجعات', 'Review history')),
            ]),
            actions: [
              IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
            ],
          ),
          body: TabBarView(children: [
            queueList(pending, t('لا توجد دفعات معلقة حالياً.', 'No transactions are waiting for review.'), isPending: true),
            queueList(history, t('لا يوجد سجل مراجعات بعد.', 'No review history yet.'), isPending: false),
          ]),
        ),
      );
}
