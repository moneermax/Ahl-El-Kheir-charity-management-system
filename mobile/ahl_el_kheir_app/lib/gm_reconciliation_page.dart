import 'package:flutter/material.dart';

class GmReconciliationPage extends StatefulWidget {
  const GmReconciliationPage({
    super.key,
    required this.english,
    required this.loadPage,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadPage;

  @override
  State<GmReconciliationPage> createState() => _GmReconciliationPageState();
}

class _GmReconciliationPageState extends State<GmReconciliationPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await widget.loadPage();
      if (!mounted) return;
      setState(() {
        data = result;
        loading = false;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        error = exception.toString();
        loading = false;
      });
    }
  }

  Map<String, dynamic> get summary =>
      data['summary'] is Map<String, dynamic>
          ? data['summary'] as Map<String, dynamic>
          : <String, dynamic>{};

  List<Map<String, dynamic>> rows(String key) {
    final value = data[key];
    if (value is! List) return [];
    return value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList();
  }

  String money(dynamic value) {
    final amount = value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0;
    return amount.toStringAsFixed(0);
  }

  String value(dynamic value) {
    if (value == null || value.toString().isEmpty) return '—';
    if (value is num && value is! int) return value.toStringAsFixed(0);
    return value.toString();
  }

  Widget _metric(String label, dynamic amount, IconData icon, Color color) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: color.withValues(alpha: 0.12),
              child: Icon(icon, color: color),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 4),
                  Text(
                    money(amount),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: color,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _section({
    required String title,
    required String keyName,
    required String emptyText,
    required String titleField,
    required List<String> fields,
  }) {
    final items = rows(keyName);
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.only(bottom: 12),
      child: ExpansionTile(
        initiallyExpanded: false,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        subtitle: Text(t('عدد السجلات: ', 'Records: ') + items.length.toString()),
        children: [
          if (items.isEmpty)
            Padding(
              padding: const EdgeInsets.all(18),
              child: Text(emptyText, textAlign: TextAlign.center),
            ),
          for (final item in items)
            ListTile(
              dense: true,
              title: Text(value(item[titleField]), style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(fields.map((field) {
                final label = _fieldLabel(field);
                final fieldValue = field == 'total_amount' ||
                        field == 'amount' ||
                        field == 'inflow' ||
                        field == 'outflow' ||
                        field == 'cashnet' ||
                        field == 'voided_total'
                    ? money(item[field])
                    : value(item[field]);
                return '$label: $fieldValue';
              }).join('  •  ')),
              isThreeLine: fields.length > 2,
            ),
        ],
      ),
    );
  }

  String _fieldLabel(String field) => ({
    'month': t('الشهر', 'Month'),
    'status': t('الحالة', 'Status'),
    'created_at': t('تاريخ الإنشاء', 'Created'),
    'total_amount': t('الإجمالي', 'Total'),
    'nanny_name': t('الحاضنة', 'Nanny'),
    'item_count': t('البنود', 'Items'),
    'open_item_count': t('البنود المفتوحة', 'Open items'),
    'amount': t('المبلغ', 'Amount'),
    'return_reason': t('سبب الإرجاع', 'Return reason'),
    'returned_by': t('أكّد الإرجاع', 'Returned by'),
    'returned_at': t('تاريخ الإرجاع', 'Returned at'),
    'reversal_journal_id': t('القيد العكسي', 'Reversal journal'),
    'inflow': t('الوارد', 'Inflow'),
    'outflow': t('الصادر', 'Outflow'),
    'cashnet': t('صافي النقد', 'Cash net'),
    'difference': t('الفرق', 'Difference'),
    'count': t('عدد الدفعات', 'Batches'),
    'voided_total': t('إجمالي المُبطَل', 'Voided total'),
    'void_reason': t('سبب الإبطال', 'Void reason'),
    'voided_by': t('بواسطة', 'Voided by'),
    'voided_at': t('تاريخ الإبطال', 'Voided at'),
  })[field] ?? field;

  @override
  Widget build(BuildContext context) {
    final inflow = summary['total_inflow'];
    final outflow = summary['total_outflow'];
    final cashNet = summary['total_cash_net'];
    final difference = summary['difference'];

    return Scaffold(
      appBar: AppBar(
        title: Text(t('المصالحة المالية', 'Financial reconciliation')),
        actions: [
          IconButton(
            tooltip: t('تحديث', 'Refresh'),
            onPressed: loading ? null : refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.cloud_off_rounded, size: 42),
                        const SizedBox(height: 12),
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: refresh,
                          icon: const Icon(Icons.refresh_rounded),
                          label: Text(t('إعادة المحاولة', 'Try again')),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: refresh,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        t('ملخص دفتر الأستاذ المُرحّل', 'Posted ledger summary'),
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      _metric(t('إجمالي الوارد', 'Total inflow'), inflow, Icons.south_west_rounded, Colors.teal),
                      _metric(t('إجمالي الصادر', 'Total outflow'), outflow, Icons.north_east_rounded, Colors.deepOrange),
                      _metric(t('صافي حركة النقد', 'Net cash movement'), cashNet, Icons.account_balance_wallet_outlined, Colors.indigo),
                      _metric(t('الفرق: الوارد − الصادر', 'Difference: inflow − outflow'), difference, Icons.balance_rounded, Colors.deepPurple),
                      const SizedBox(height: 12),
                      _section(
                        title: t('الدفعات المفتوحة — الأقدم أولاً', 'Open batches — oldest first'),
                        keyName: 'open_batches',
                        emptyText: t('لا توجد دفعات مفتوحة.', 'There are no open batches.'),
                        titleField: 'month',
                        fields: ['status', 'created_at', 'nanny_name', 'total_amount', 'open_item_count', 'item_count'],
                      ),
                      _section(
                        title: t('سجل المبالغ المرتجعة', 'Returned funds'),
                        keyName: 'returns',
                        emptyText: t('لا توجد إرجاعات مسجلة.', 'No returns are recorded.'),
                        titleField: 'month',
                        fields: ['amount', 'nanny_name', 'return_reason', 'returned_by', 'returned_at', 'reversal_journal_id'],
                      ),
                      _section(
                        title: t('المصالحة الشهرية من القيود المُرحّلة', 'Monthly posted-ledger reconciliation'),
                        keyName: 'monthly_ledger',
                        emptyText: t('لا توجد قيود مُرحّلة.', 'No posted ledger entries were found.'),
                        titleField: 'month',
                        fields: ['inflow', 'outflow', 'cashnet'],
                      ),
                      _section(
                        title: t('ملخص الدفعات الشهرية', 'Monthly disbursement totals'),
                        keyName: 'monthly_disbursements',
                        emptyText: t('لا توجد دفعات شهرية.', 'No monthly disbursements were found.'),
                        titleField: 'month',
                        fields: ['count', 'total_amount', 'voided_total'],
                      ),
                      _section(
                        title: t('سجل إبطال الدفعات', 'Voided batches'),
                        keyName: 'voided_batches',
                        emptyText: t('لا توجد دفعات مُبطلة.', 'No voided batches were found.'),
                        titleField: 'month',
                        fields: ['total_amount', 'void_reason', 'voided_by', 'voided_at', 'reversal_journal_id'],
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Text(
                          t('عرض للقراءة فقط. لا يتم تعديل القيود أو الدفعات من هذه الشاشة.',
                              'Read-only view. This screen does not modify ledger entries or disbursement batches.'),
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}
