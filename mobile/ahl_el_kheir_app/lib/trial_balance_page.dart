import 'package:flutter/material.dart';

class TrialBalancePage extends StatefulWidget {
  const TrialBalancePage({
    super.key,
    required this.english,
    required this.loadReport,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(String from, String to) loadReport;

  @override
  State<TrialBalancePage> createState() => _TrialBalancePageState();
}

class _TrialBalancePageState extends State<TrialBalancePage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _data = {};
  DateTime? _fromDate;
  DateTime? _toDate;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    _load();
  }

  String _date(DateTime? value) {
    if (value == null) return '';
    return '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
  }

  Future<void> _load() async {
    DateTime? from = _fromDate;
    DateTime? to = _toDate;
    if (from != null && to != null && from.isAfter(to)) {
      final earlier = to;
      to = from;
      from = earlier;
    }
    setState(() {
      _fromDate = from;
      _toDate = to;
      _loading = true;
      _error = null;
    });
    try {
      final result = await widget.loadReport(_date(from), _date(to));
      if (!mounted) return;
      setState(() {
        _data = result;
        _loading = false;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        _error = exception.toString();
        _loading = false;
      });
    }
  }

  Future<void> _chooseDate(bool chooseFrom) async {
    final initial = (chooseFrom ? _fromDate : _toDate) ??
        (chooseFrom ? _toDate : _fromDate) ??
        DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (chooseFrom) {
        _fromDate = selected;
      } else {
        _toDate = selected;
      }
    });
  }

  Map<String, dynamic> get _totals {
    final value = _data['totals'];
    return value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
  }

  List<Map<String, dynamic>> get _accounts {
    final value = _data['accounts'];
    if (value is! List) return [];
    return value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList();
  }

  double _number(dynamic value) => value is num ? value.toDouble() : double.tryParse(value?.toString() ?? '') ?? 0;

  String _money(dynamic value) => _number(value).toStringAsFixed(2);

  Widget _metric(String label, dynamic amount, {Color? color}) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 8),
            Text(
              _money(amount),
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: color,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  String _typeLabel(String type) {
    const ar = {
      'asset': 'أصول',
      'liability': 'التزامات',
      'equity': 'حقوق ملكية',
      'revenue': 'إيرادات',
      'expense': 'مصروفات',
    };
    const en = {
      'asset': 'Assets',
      'liability': 'Liabilities',
      'equity': 'Equity',
      'revenue': 'Revenue',
      'expense': 'Expenses',
    };
    return (widget.english ? en[type] : ar[type]) ?? type;
  }

  @override
  Widget build(BuildContext context) {
    final accounts = _accounts;
    final totals = _totals;
    final balanced = totals['is_balanced'] == true;

    return Scaffold(
      appBar: AppBar(
        title: Text(t('ميزان المراجعة', 'Trial balance')),
        actions: [
          IconButton(
            tooltip: t('تحديث', 'Refresh'),
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: _loading && _data.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _data.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, size: 42),
                        const SizedBox(height: 12),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        FilledButton(onPressed: _load, child: Text(t('إعادة المحاولة', 'Retry'))),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_loading) const LinearProgressIndicator(),
                      if (_error != null)
                        Card(
                          child: ListTile(
                            leading: const Icon(Icons.error_outline_rounded),
                            title: Text(t('تعذر التحديث', 'Refresh failed')),
                            subtitle: Text(_error!),
                          ),
                        ),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(t('الفترة المحاسبية', 'Accounting period'), style: Theme.of(context).textTheme.titleMedium),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  OutlinedButton.icon(
                                    onPressed: _loading ? null : () => _chooseDate(true),
                                    icon: const Icon(Icons.calendar_today_outlined),
                                    label: Text('${t('من تاريخ', 'From date')}: ${_date(_fromDate).isEmpty ? t('كل التواريخ', 'All dates') : _date(_fromDate)}'),
                                  ),
                                  OutlinedButton.icon(
                                    onPressed: _loading ? null : () => _chooseDate(false),
                                    icon: const Icon(Icons.event_outlined),
                                    label: Text('${t('إلى تاريخ', 'To date')}: ${_date(_toDate).isEmpty ? t('كل التواريخ', 'All dates') : _date(_toDate)}'),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  FilledButton.icon(
                                    onPressed: _loading ? null : _load,
                                    icon: const Icon(Icons.filter_alt_outlined),
                                    label: Text(t('تطبيق الفلتر', 'Apply filters')),
                                  ),
                                  OutlinedButton(
                                    onPressed: _loading || (_fromDate == null && _toDate == null)
                                        ? null
                                        : () {
                                            setState(() {
                                              _fromDate = null;
                                              _toDate = null;
                                            });
                                            _load();
                                          },
                                    child: Text(t('مسح التواريخ', 'Clear dates')),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(t('إجماليات القيود المُرحّلة', 'Posted journal totals'), style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 4),
                      Text(t('يعرض التقرير القيود المُرحّلة فقط، ولا يُجري أي تغييرات محاسبية.', 'Only posted journal entries are included. This report does not change accounting data.')),
                      const SizedBox(height: 8),
                      GridView.count(
                        crossAxisCount: 2,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisSpacing: 8,
                        mainAxisSpacing: 8,
                        childAspectRatio: 1.8,
                        children: [
                          _metric(t('إجمالي المدين', 'Total debit'), totals['total_debit']),
                          _metric(t('إجمالي الدائن', 'Total credit'), totals['total_credit']),
                          _metric(t('الفرق', 'Difference'), totals['difference'], color: balanced ? Colors.green : Theme.of(context).colorScheme.error),
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(t('حالة الميزان', 'Balance status'), style: Theme.of(context).textTheme.bodySmall),
                                  const SizedBox(height: 8),
                                  Text(
                                    balanced ? t('متوازن', 'Balanced') : t('غير متوازن', 'Unbalanced'),
                                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          color: balanced ? Colors.green : Theme.of(context).colorScheme.error,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('الحسابات ذات الحركة', 'Accounts with posted activity'),
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 8),
                      if (accounts.isEmpty)
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(18),
                            child: Text(t('لا توجد حركات مُرحّلة ضمن الفترة المحددة.', 'No posted activity exists for this period.')),
                          ),
                        )
                      else
                        for (final account in accounts)
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          '${account['name_ar'] ?? '—'}',
                                          style: const TextStyle(fontWeight: FontWeight.w800),
                                        ),
                                      ),
                                      Chip(label: Text('${account['code'] ?? '—'}')),
                                    ],
                                  ),
                                  if ('${account['name_en'] ?? ''}'.trim().isNotEmpty)
                                    Text('${account['name_en']}', style: Theme.of(context).textTheme.bodySmall),
                                  Text(_typeLabel('${account['account_type'] ?? ''}'), style: Theme.of(context).textTheme.bodySmall),
                                  const Divider(height: 18),
                                  Row(
                                    children: [
                                      Expanded(child: Text(t('مدين', 'Debit'))),
                                      Text(_money(account['debit_total']), style: const TextStyle(fontWeight: FontWeight.w700)),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      Expanded(child: Text(t('دائن', 'Credit'))),
                                      Text(_money(account['credit_total']), style: const TextStyle(fontWeight: FontWeight.w700)),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                      const SizedBox(height: 8),
                      Text(
                        t('عدد القيود: ', 'Journal entries: ') + '${totals['journal_count'] ?? 0}' +
                            t('  •  عدد البنود: ', '  •  Lines: ') + '${totals['line_count'] ?? 0}',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
    );
  }
}
