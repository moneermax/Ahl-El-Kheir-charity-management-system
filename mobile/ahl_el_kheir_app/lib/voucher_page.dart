import 'package:flutter/material.dart';

typedef LoadVouchers = Future<Map<String, dynamic>> Function(String type);

class VoucherPage extends StatefulWidget {
  const VoucherPage({super.key, required this.english, required this.loadVouchers});
  final bool english;
  final LoadVouchers loadVouchers;

  @override
  State<VoucherPage> createState() => _VoucherPageState();
}

class _VoucherPageState extends State<VoucherPage> {
  String _type = '';
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _vouchers = [];
  List<Map<String, dynamic>> _cashAccounts = [];
  bool _canIssue = false;
  bool _canVoid = false;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final response = await widget.loadVouchers(_type);
      final data = response['data'];
      if (data is! Map) throw const FormatException('Invalid voucher response');
      final rawVouchers = data['vouchers'];
      final rawAccounts = data['cash_accounts'];
      if (rawVouchers is! List || rawAccounts is! List) {
        throw const FormatException('Missing voucher data');
      }
      if (!mounted) return;
      setState(() {
        _vouchers = rawVouchers.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList();
        _cashAccounts = rawAccounts.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList();
        _canIssue = data['can_issue'] == true;
        _canVoid = data['can_void'] == true;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  String _money(dynamic value) {
    final amount = double.tryParse(value?.toString() ?? '') ?? 0;
    return amount.toStringAsFixed(2);
  }

  String _date(dynamic value) {
    final raw = value?.toString() ?? '';
    if (raw.length >= 10) return raw.substring(0, 10);
    return raw.isEmpty ? '—' : raw;
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('سندات القبض والصرف', 'Receipt & payment vouchers')),
        actions: [IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: _loading ? null : _load, icon: const Icon(Icons.refresh_rounded))],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text(t('أرصدة النقدية والبنوك والمحافظ', 'Cash, bank and wallet balances'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  if (_cashAccounts.isEmpty && !_loading)
                    Text(t('لا توجد حسابات نقدية متاحة.', 'No cash accounts are available.')),
                  for (final account in _cashAccounts)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      child: Row(children: [
                        Expanded(child: Text('${account['code'] ?? ''} · ${account['name_ar'] ?? ''}')),
                        const SizedBox(width: 12),
                        Text(_money(account['balance']), style: const TextStyle(fontWeight: FontWeight.w800, fontFeatures: [])),
                      ]),
                    ),
                ]),
              ),
            ),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: '', label: Text(t('الكل', 'All')), icon: const Icon(Icons.receipt_long_outlined)),
                ButtonSegment(value: 'receipt', label: Text(t('قبض', 'Receipts')), icon: const Icon(Icons.call_received_rounded)),
                ButtonSegment(value: 'payment', label: Text(t('صرف', 'Payments')), icon: const Icon(Icons.call_made_rounded)),
              ],
              selected: {_type},
              onSelectionChanged: _loading ? null : (selected) {
                setState(() => _type = selected.first);
                _load();
              },
            ),
            const SizedBox(height: 10),
            Text(
              t('عرض للقراءة فقط. إنشاء السندات وإبطالها غير متاحين في هذه النسخة من التطبيق.',
                 'Read-only register. Voucher creation and voiding are not available in this app version.'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
            ),
            if (_canIssue || _canVoid) ...[
              const SizedBox(height: 4),
              Text(t('تُحفظ صلاحيات الإنشاء والإبطال على الخادم، لكن واجهتهما غير منفذة هنا.',
                     'Issue and void permissions remain server-controlled; their native actions are not implemented here.'),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant)),
            ],
            const SizedBox(height: 8),
            if (_loading) const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())),
            if (!_loading && _error != null)
              Card(child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  const Icon(Icons.cloud_off_rounded, size: 36),
                  const SizedBox(height: 8),
                  Text(t('تعذر تحميل السندات', 'Could not load vouchers'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 6),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 10),
                  FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh), label: Text(t('إعادة المحاولة', 'Retry'))),
                ]),
              )),
            if (!_loading && _error == null && _vouchers.isEmpty)
              Padding(padding: const EdgeInsets.all(28), child: Column(children: [
                const Icon(Icons.receipt_long_outlined, size: 40),
                const SizedBox(height: 8),
                Text(t('لا توجد سندات مطابقة.', 'No matching vouchers.')),
              ])),
            for (final voucher in _vouchers) _voucherCard(voucher, colors),
            if (!_loading && _error == null && _vouchers.isNotEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(t('يُعرض أحدث ${_vouchers.length} سند كحد أقصى 300 من الخادم.',
                              'Showing ${_vouchers.length} most recent vouchers; server limit is 300.'),
                  textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
              ),
          ],
        ),
      ),
    );
  }

  Widget _voucherCard(Map<String, dynamic> voucher, ColorScheme colors) {
    final isReceipt = voucher['voucher_type']?.toString() == 'receipt';
    final status = voucher['status']?.toString() ?? '';
    final statusLabel = switch (status) {
      'posted' => t('مُرحّل', 'Posted'),
      'voided' => t('مُبطل', 'Voided'),
      'draft' => t('مسودة', 'Draft'),
      _ => status.isEmpty ? '—' : status,
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            CircleAvatar(
              backgroundColor: isReceipt ? colors.primaryContainer : colors.tertiaryContainer,
              child: Icon(isReceipt ? Icons.call_received_rounded : Icons.call_made_rounded,
                color: isReceipt ? colors.onPrimaryContainer : colors.onTertiaryContainer),
            ),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(voucher['voucher_no']?.toString() ?? '—',
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
              Text(isReceipt ? t('سند قبض', 'Receipt voucher') : t('سند صرف', 'Payment voucher')),
              Text(_date(voucher['voucher_date']), style: Theme.of(context).textTheme.bodySmall),
            ])),
            const SizedBox(width: 8),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text(_money(voucher['amount']), style: TextStyle(fontWeight: FontWeight.w900, color: colors.primary, fontSize: 16)),
              Text(statusLabel, style: Theme.of(context).textTheme.labelSmall),
            ]),
          ]),
          const Divider(height: 22),
          _detail(t('الجهة', 'Party'), voucher['party_name']),
          _detail(t('حساب النقدية', 'Cash account'), '${voucher['cash_code'] ?? ''} · ${voucher['cash_name'] ?? ''}'),
          _detail(t('الحساب المقابل', 'Counter-account'), '${voucher['other_code'] ?? ''} · ${voucher['other_name'] ?? ''}'),
          if ((voucher['description']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('البيان', 'Description'), voucher['description']),
          if ((voucher['reference_number']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('رقم المرجع', 'Reference'), voucher['reference_number']),
          if ((voucher['entry_code']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('القيد المحاسبي', 'Journal entry'), voucher['entry_code']),
          if ((voucher['creator']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('أنشأه', 'Created by'), voucher['creator']),
        ]),
      ),
    );
  }

  Widget _detail(String label, dynamic value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 112, child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700))),
      const SizedBox(width: 8),
      Expanded(child: Text((value?.toString() ?? '').trim().isEmpty ? '—' : value.toString())),
    ]),
  );
}
