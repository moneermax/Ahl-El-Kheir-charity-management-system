import 'package:flutter/material.dart';
import 'journal_manual_entry_page.dart';

class JournalPage extends StatefulWidget {
  const JournalPage({
    super.key,
    required this.english,
    required this.loadEntries,
    required this.loadEntry,
    required this.loadAccounts,
    required this.submitManualEntry,
    required this.submitVoid,
    required this.roleCode,
    required this.currentUserId,
  });

  final bool english;
  final String roleCode;
  final int currentUserId;
  final Future<Map<String, dynamic>> Function({required String from, required String to}) loadEntries;
  final Future<Map<String, dynamic>> Function(int id) loadEntry;
  final Future<Map<String, dynamic>> Function() loadAccounts;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitManualEntry;
  final Future<Map<String, dynamic>> Function(int id, String reason) submitVoid;

  @override
  State<JournalPage> createState() => _JournalPageState();
}

class _JournalPageState extends State<JournalPage> {
  bool loading = true;
  String? error;
  String fromDate = '';
  String toDate = '';
  List<Map<String, dynamic>> entries = [];

  String t(String ar, String en) => widget.english ? en : ar;
  bool get canManage => ['admin', 'sudo', 'super_admin', 'financial_manager', 'fm'].contains(widget.roleCode);

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadEntries(from: fromDate, to: toDate);
      if (!mounted) return;
      setState(() {
        entries = result['entries'] is List
            ? (result['entries'] as List).whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList()
            : <Map<String, dynamic>>[];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> createEntry() async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute<bool>(
      builder: (_) => JournalManualEntryPage(
        english: widget.english,
        loadAccounts: widget.loadAccounts,
        submitEntry: widget.submitManualEntry,
      ),
    ));
    if (saved == true && mounted) await refresh();
  }

  Future<void> chooseDate({required bool start}) async {
    final current = DateTime.tryParse(start ? fromDate : toDate) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked == null || !mounted) return;
    final value = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    setState(() {
      if (start) {
        fromDate = value;
      } else {
        toDate = value;
      }
    });
  }

  Future<void> openEntry(Map<String, dynamic> entry) async {
    final id = int.tryParse(entry['id']?.toString() ?? '');
    if (id == null || id < 1) return;
    await Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => JournalEntryDetailPage(
        english: widget.english,
        entryId: id,
        loadEntry: widget.loadEntry,
        roleCode: widget.roleCode,
        currentUserId: widget.currentUserId,
        submitVoid: widget.submitVoid,
      ),
    ));
  }

  Widget dateFilter({required bool start}) {
    final value = start ? fromDate : toDate;
    return Expanded(
      child: OutlinedButton.icon(
        onPressed: () => chooseDate(start: start),
        icon: const Icon(Icons.calendar_month_rounded),
        label: Text(
          value.isEmpty
              ? (start ? t('من تاريخ', 'From date') : t('إلى تاريخ', 'To date'))
              : value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    );
  }

  Widget entryCard(Map<String, dynamic> entry) {
    final s = Theme.of(context).colorScheme;
    final isBalanced = entry['is_balanced'] == true;
    final isVoided = (entry['voided_at']?.toString() ?? '').isNotEmpty || entry['status'] == 'voided';
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => openEntry(entry),
        child: Padding(
          padding: const EdgeInsets.all(15),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(color: s.primaryContainer, borderRadius: BorderRadius.circular(14)),
                    child: Icon(Icons.menu_book_rounded, color: s.primary),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(entry['entry_code']?.toString() ?? '—', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 3),
                        Text(entry['description']?.toString() ?? '', maxLines: 2, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 4),
                        Text('${entry['entry_date'] ?? '—'} · ${entry['reference_type'] ?? t('يدوي', 'Manual')}', style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _JournalPill(
                    label: t('مدين', 'Debit'),
                    value: (entry['total_debit'] ?? 0).toString(),
                    color: s.primary,
                  ),
                  _JournalPill(
                    label: t('دائن', 'Credit'),
                    value: (entry['total_credit'] ?? 0).toString(),
                    color: s.tertiary,
                  ),
                  _JournalPill(
                    label: isVoided ? t('مُبطل', 'Voided') : entry['status'] == 'posted' ? t('مرحّل', 'Posted') : (entry['status']?.toString() ?? '—'),
                    value: isBalanced ? t('متوازن', 'Balanced') : t('تحقق من التوازن', 'Check balance'),
                    color: isVoided ? s.error : isBalanced ? s.primary : s.error,
                  ),
                ],
              ),
              if ((entry['creator']?.toString() ?? '').isNotEmpty) ...[
                const SizedBox(height: 8),
                Text('${t('أنشأه', 'Created by')}: ${entry['creator']}', style: Theme.of(context).textTheme.bodySmall),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('اليومية المحاسبية', 'Accounting journal')),
        actions: [
          if (canManage) IconButton(tooltip: t('قيد جديد', 'New entry'), onPressed: loading ? null : createEntry, icon: const Icon(Icons.add_rounded)),
          IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(t('تصفية القيود بالتاريخ', 'Filter entries by date'), style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  Row(children: [dateFilter(start: true), const SizedBox(width: 8), dateFilter(start: false)]),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(child: FilledButton.icon(onPressed: loading ? null : refresh, icon: const Icon(Icons.filter_alt_rounded), label: Text(t('تطبيق', 'Apply')))),
                      const SizedBox(width: 8),
                      OutlinedButton(
                        onPressed: loading ? null : () { setState(() { fromDate = ''; toDate = ''; }); refresh(); },
                        child: Text(t('مسح', 'Clear')),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            _JournalMessage(text: error!, isError: true),
          ],
          const SizedBox(height: 12),
          if (loading)
            const Padding(padding: EdgeInsets.all(28), child: Center(child: CircularProgressIndicator()))
          else if (entries.isEmpty)
            _JournalMessage(text: t('لا توجد قيود ضمن النطاق المحدد.', 'No journal entries match the selected date range.'), isError: false)
          else ...[
            Text(t('القيود', 'Entries') + ' · ${entries.length}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            for (final entry in entries) entryCard(entry),
          ],
          const SizedBox(height: 24),
          Text(t('يعرض التطبيق القيود للقراءة والمراجعة. لا يغيّر فتح القيد أي بيانات محاسبية.', 'Entries are shown for reading and review. Opening an entry does not change accounting data.'), style: Theme.of(context).textTheme.bodySmall?.copyWith(color: s.onSurfaceVariant)),
        ],
      ),
    );
  }
}

class JournalEntryDetailPage extends StatefulWidget {
  const JournalEntryDetailPage({super.key, required this.english, required this.entryId, required this.loadEntry, required this.roleCode, required this.currentUserId, required this.submitVoid});
  final bool english;
  final int entryId;
  final String roleCode;
  final int currentUserId;
  final Future<Map<String, dynamic>> Function(int id) loadEntry;
  final Future<Map<String, dynamic>> Function(int id, String reason) submitVoid;

  @override
  State<JournalEntryDetailPage> createState() => _JournalEntryDetailPageState();
}

class _JournalEntryDetailPageState extends State<JournalEntryDetailPage> {
  bool loading = true, voiding = false;
  String? error, notice;
  Map<String, dynamic>? entry;
  List<Map<String, dynamic>> lines = [];
  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() { super.initState(); load(); }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadEntry(widget.entryId);
      if (!mounted) return;
      setState(() {
        entry = result['entry'] is Map ? Map<String, dynamic>.from(result['entry'] as Map) : null;
        lines = result['lines'] is List ? (result['lines'] as List).whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList() : <Map<String, dynamic>>[];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  bool get canVoid {
    final current = entry;
    if (current == null || !['admin', 'sudo', 'super_admin', 'financial_manager', 'fm'].contains(widget.roleCode)) return false;
    const automatedTypes = {'transaction', 'transaction_void', 'disbursement', 'disbursement_return', 'disbursement_void', 'item_return', 'voucher', 'voucher_void', 'payroll', 'manual_void'};
    final referenceType = current['reference_type']?.toString() ?? '';
    final voidedAt = current['voided_at']?.toString() ?? '';
    final creatorId = int.tryParse(current['created_by']?.toString() ?? '');
    return current['status'] == 'posted' && voidedAt.isEmpty && !automatedTypes.contains(referenceType) && creatorId != widget.currentUserId;
  }

  Future<void> voidEntry() async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('إبطال القيد اليدوي', 'Void manual journal entry')),
        content: TextField(controller: controller, maxLength: 255, decoration: InputDecoration(labelText: t('سبب الإبطال', 'Reason for voiding'))),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()), child: Text(t('تأكيد الإبطال', 'Confirm void'))),
        ],
      ),
    );
    controller.dispose();
    if (reason == null) return;
    setState(() { voiding = true; error = null; notice = null; });
    try {
      await widget.submitVoid(widget.entryId, reason);
      if (mounted) {
        setState(() => notice = t('تم إبطال القيد وإنشاء قيد عكسي متوازن.', 'The entry was voided and a balanced reversal was created.'));
        await load();
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => voiding = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    final current = entry;
    final balanced = current?['is_balanced'] == true;
    final isVoided = (current?['voided_at']?.toString() ?? '').isNotEmpty || current?['status'] == 'voided';
    return Scaffold(
      appBar: AppBar(title: Text(current?['entry_code']?.toString() ?? t('تفاصيل القيد', 'Journal entry details'))),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Padding(padding: const EdgeInsets.all(18), child: _JournalMessage(text: error!, isError: true))
              : current == null
                  ? _JournalMessage(text: t('القيد غير موجود.', 'Journal entry not found.'), isError: true)
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(18),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(current['description']?.toString() ?? '', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                                const SizedBox(height: 8),
                                Text('${current['entry_date'] ?? '—'} · ${current['reference_type'] ?? t('يدوي', 'Manual')} · ${current['creator'] ?? '—'}'),
                                const SizedBox(height: 12),
                                _JournalPill(label: t('مدين', 'Debit'), value: (current['total_debit'] ?? 0).toString(), color: s.primary),
                                const SizedBox(height: 8),
                                _JournalPill(label: t('دائن', 'Credit'), value: (current['total_credit'] ?? 0).toString(), color: s.tertiary),
                                const SizedBox(height: 8),
                                _JournalMessage(
                                  text: balanced
                                      ? t('القيد متوازن.', 'The entry is balanced.')
                                      : t('القيد غير متوازن أو يحتوي على أسطر غير كافية.', 'The entry is unbalanced or has too few lines.'),
                                  isError: !balanced,
                                ),
                                if (isVoided) ...[
                                  const SizedBox(height: 8),
                                  _JournalMessage(
                                    text: '${t('تم الإبطال', 'Voided')}: ${current['void_reason'] ?? '—'}',
                                    isError: true,
                                  ),
                                ],
                                if (notice != null) ...[
                                  const SizedBox(height: 8),
                                  _JournalMessage(text: notice!, isError: false),
                                ],
                                if (canVoid) ...[
                                  const SizedBox(height: 12),
                                  FilledButton.icon(
                                    onPressed: voiding ? null : voidEntry,
                                    icon: voiding ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.gpp_bad_outlined),
                                    label: Text(t('إبطال القيد', 'Void entry')),
                                    style: FilledButton.styleFrom(backgroundColor: s.error),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        Text(t('سطور القيد', 'Journal lines'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 8),
                        for (final line in lines)
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text('${line['account_code'] ?? '—'} · ${widget.english && (line['account_name_en']?.toString() ?? '').isNotEmpty ? line['account_name_en'] : (line['account_name_ar'] ?? '—')}', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                                  if ((line['description']?.toString() ?? '').isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text(line['description'].toString())),
                                  const SizedBox(height: 8),
                                  Row(
                                    children: [
                                      Expanded(child: Text(t('مدين', 'Debit') + ': ${line['debit'] ?? 0}')),
                                      Expanded(child: Text(t('دائن', 'Credit') + ': ${line['credit'] ?? 0}')),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
    );
  }
}

class _JournalPill extends StatelessWidget {
  const _JournalPill({required this.label, required this.value, required this.color});
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    decoration: BoxDecoration(color: color.withValues(alpha: .08), borderRadius: BorderRadius.circular(12)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('$label: ', style: TextStyle(color: color, fontWeight: FontWeight.w700)),
        Flexible(child: Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w800), overflow: TextOverflow.ellipsis)),
      ],
    ),
  );
}

class _JournalMessage extends StatelessWidget {
  const _JournalMessage({required this.text, required this.isError});
  final String text;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    final color = isError ? s.error : s.primary;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: .22)),
      ),
      child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
    );
  }
}
