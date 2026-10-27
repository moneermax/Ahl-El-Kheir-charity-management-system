import 'package:flutter/material.dart';

class SalaryAdvanceDashboardPage extends StatefulWidget {
  const SalaryAdvanceDashboardPage({
    super.key,
    required this.english,
    required this.loadDashboard,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadDashboard;

  @override
  State<SalaryAdvanceDashboardPage> createState() => _SalaryAdvanceDashboardPageState();
}

class _SalaryAdvanceDashboardPageState extends State<SalaryAdvanceDashboardPage> {
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
      final result = await widget.loadDashboard();
      if (!mounted) return;
      setState(() {
        data = result;
        loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = e.toString();
        loading = false;
      });
    }
  }

  int count(String key) => int.tryParse('${(data['counts'] as Map?)?[key] ?? 0}') ?? 0;

  String statusLabel(String status) {
    const ar = {
      'disbursed': 'تم الصرف',
      'settled': 'تمت التسوية',
      'rejected': 'مرفوض',
      'cancelled': 'ملغى',
    };
    const en = {
      'disbursed': 'Disbursed',
      'settled': 'Settled',
      'rejected': 'Rejected',
      'cancelled': 'Cancelled',
    };
    return (widget.english ? en[status] : ar[status]) ?? status;
  }

  @override
  Widget build(BuildContext context) {
    final history = (data['history'] as List? ?? const [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();

    return Scaffold(
      appBar: AppBar(
        title: Text(t('لوحة سلف الرواتب', 'Salary-advance dashboard')),
        actions: [
          IconButton(
            tooltip: t('تحديث', 'Refresh'),
            onPressed: loading ? null : refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: loading && data.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : error != null && data.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, size: 42),
                        const SizedBox(height: 12),
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        FilledButton(onPressed: refresh, child: Text(t('إعادة المحاولة', 'Retry'))),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: refresh,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (error != null)
                        Card(
                          child: ListTile(
                            leading: const Icon(Icons.error_outline_rounded),
                            title: Text(t('تعذر التحديث', 'Refresh failed')),
                            subtitle: Text(error!),
                          ),
                        ),
                      Text(t('ملخص الطلبات', 'Request summary'), style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 10),
                      GridView.count(
                        crossAxisCount: 2,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisSpacing: 10,
                        mainAxisSpacing: 10,
                        childAspectRatio: 1.75,
                        children: [
                          _MetricCard(label: t('بانتظار مراجعة FM', 'Pending FM review'), value: count('fm_pending')),
                          _MetricCard(label: t('بانتظار المحاسبة', 'Pending accounting'), value: count('accounting_pending')),
                          _MetricCard(label: t('تم صرفها', 'Disbursed'), value: count('disbursed')),
                          _MetricCard(label: t('تمت تسويتها', 'Settled'), value: count('settled')),
                        ],
                      ),
                      const SizedBox(height: 18),
                      Card(
                        child: ListTile(
                          leading: const Icon(Icons.info_outline_rounded),
                          title: Text(t('عرض للمتابعة فقط', 'Read-only overview')),
                          subtitle: Text(t(
                            'إدارة السياسة ومعالجة الطلبات وإعادة الجدولة ليست متاحة داخل التطبيق بعد. لم تتم إضافة روابط تفتح صفحات خارج التطبيق.',
                            'Policy management, request processing and rescheduling are not available in-app yet. This screen does not open external pages.',
                          )),
                        ),
                      ),
                      const SizedBox(height: 18),
                      Text(t('سجل الطلبات المعالجة والسابقة', 'Processed and previous requests'), style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 8),
                      if (history.isEmpty)
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(18),
                            child: Text(t('لا توجد طلبات سابقة في السجل.', 'No previous requests in the history.')),
                          ),
                        )
                      else
                        for (final row in history)
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          '${row['request_no'] ?? '—'}',
                                          style: const TextStyle(fontWeight: FontWeight.w800),
                                        ),
                                      ),
                                      Chip(label: Text(statusLabel('${row['status'] ?? ''}'))),
                                    ],
                                  ),
                                  Text('${row['employee_name'] ?? '—'}'),
                                  Text('${row['employee_code'] ?? ''}', style: Theme.of(context).textTheme.bodySmall),
                                  const SizedBox(height: 8),
                                  _DetailLine(label: t('المبلغ المعتمد', 'Approved amount'), value: '${(double.tryParse('${row['approved_amount'] ?? 0}') ?? 0).toStringAsFixed(2)} SDG'),
                                  _DetailLine(label: t('الرصيد المتبقي', 'Outstanding balance'), value: '${(double.tryParse('${row['outstanding_balance'] ?? 0}') ?? 0).toStringAsFixed(2)} SDG'),
                                  _DetailLine(label: t('تاريخ المعالجة', 'Processed date'), value: '${row['disbursed_at'] ?? row['settled_at'] ?? '—'}'),
                                ],
                              ),
                            ),
                          ),
                    ],
                  ),
                ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value});
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('$value', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text(label, textAlign: TextAlign.center),
            ],
          ),
        ),
      );
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: Text(label)),
            const SizedBox(width: 12),
            Flexible(child: Text(value, textAlign: TextAlign.end)),
          ],
        ),
      );
}
