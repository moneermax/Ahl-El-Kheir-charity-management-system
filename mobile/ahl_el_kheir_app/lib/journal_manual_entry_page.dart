import 'package:flutter/material.dart';

class JournalManualEntryPage extends StatefulWidget {
  const JournalManualEntryPage({
    super.key,
    required this.english,
    required this.loadAccounts,
    required this.submitEntry,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadAccounts;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitEntry;

  @override
  State<JournalManualEntryPage> createState() => _JournalManualEntryPageState();
}

class _JournalManualEntryPageState extends State<JournalManualEntryPage> {
  final formKey = GlobalKey<FormState>();
  final description = TextEditingController();
  final lines = <_ManualJournalLine>[_ManualJournalLine(), _ManualJournalLine()];
  List<Map<String, dynamic>> accounts = [];
  bool loading = true, saving = false;
  String? error;
  late String entryDate;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    entryDate = '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    loadAccounts();
  }

  @override
  void dispose() {
    description.dispose();
    for (final line in lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> loadAccounts() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadAccounts();
      if (!mounted) return;
      setState(() {
        accounts = result['accounts'] is List
            ? (result['accounts'] as List).whereType<Map>().map((a) => Map<String, dynamic>.from(a)).toList()
            : <Map<String, dynamic>>[];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> chooseDate() async {
    final current = DateTime.tryParse(entryDate) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked == null || !mounted) return;
    setState(() => entryDate = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}');
  }

  int cents(String value) {
    final normalized = value.trim().replaceAll(',', '');
    if (normalized.isEmpty) return 0;
    return ((double.tryParse(normalized) ?? 0) * 100).round();
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    final selected = <int>[];
    var debitTotal = 0, creditTotal = 0;
    for (final line in lines) {
      final debitText = line.debit.text.trim();
      final creditText = line.credit.text.trim();
      if (line.accountId == null && debitText.isEmpty && creditText.isEmpty) continue;
      if (line.accountId == null) {
        setState(() => error = t('اختر حساباً لكل سطر مستخدم.', 'Choose an account for every used line.'));
        return;
      }
      if (selected.contains(line.accountId)) {
        setState(() => error = t('لا يجوز تكرار الحساب في القيد نفسه.', 'An account cannot be repeated in the same entry.'));
        return;
      }
      selected.add(line.accountId!);
      final debit = cents(debitText), credit = cents(creditText);
      if (debit < 0 || credit < 0 || (debit > 0 && credit > 0) || (debit == 0 && credit == 0)) {
        setState(() => error = t('كل سطر يجب أن يحتوي مبلغاً موجباً في جانب واحد فقط.', 'Each line must have a positive amount on exactly one side.'));
        return;
      }
      debitTotal += debit;
      creditTotal += credit;
    }
    if (selected.length < 2) {
      setState(() => error = t('يجب أن يحتوي القيد على سطرين على الأقل.', 'An entry needs at least two lines.'));
      return;
    }
    if (debitTotal <= 0 || debitTotal != creditTotal) {
      setState(() => error = t('إجمالي المدين يجب أن يساوي إجمالي الدائن.', 'Total debit must equal total credit.'));
      return;
    }

    setState(() { saving = true; error = null; });
    try {
      final payloadLines = <Map<String, dynamic>>[];
      for (final line in lines) {
        if (line.accountId == null && line.debit.text.trim().isEmpty && line.credit.text.trim().isEmpty) continue;
        payloadLines.add({
          'account_id': line.accountId,
          'debit': line.debit.text.trim(),
          'credit': line.credit.text.trim(),
          'description': line.description.text.trim(),
        });
      }
      await widget.submitEntry({
        'action': 'create_manual_entry',
        'entry_date': entryDate,
        'description': description.text.trim(),
        'lines': payloadLines,
      });
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  String accountLabel(Map<String, dynamic> account) {
    final code = account['code']?.toString() ?? '';
    final name = widget.english && (account['name_en']?.toString() ?? '').isNotEmpty
        ? account['name_en'].toString()
        : account['name_ar']?.toString() ?? '';
    return '$code · $name';
  }

  Widget lineCard(int index) {
    final line = lines[index];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(child: Text(t('السطر', 'Line') + ' ${index + 1}', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
                if (lines.length > 2) IconButton(
                  tooltip: t('حذف السطر', 'Remove line'),
                  onPressed: saving ? null : () => setState(() { final removed = lines.removeAt(index); removed.dispose(); }),
                  icon: const Icon(Icons.remove_circle_outline_rounded),
                ),
              ],
            ),
            DropdownButtonFormField<int>(
              initialValue: line.accountId,
              isExpanded: true,
              decoration: InputDecoration(labelText: t('الحساب', 'Account')),
              items: accounts.map((account) => DropdownMenuItem<int>(
                value: int.tryParse(account['id']?.toString() ?? ''),
                child: Text(accountLabel(account), overflow: TextOverflow.ellipsis),
              )).where((item) => item.value != null).toList(),
              onChanged: saving ? null : (value) => setState(() => line.accountId = value),
              validator: (value) => value == null ? t('اختر حساباً.', 'Choose an account.') : null,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: TextFormField(
                  controller: line.debit,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(labelText: t('مدين', 'Debit'), hintText: '0.00'),
                )),
                const SizedBox(width: 10),
                Expanded(child: TextFormField(
                  controller: line.credit,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(labelText: t('دائن', 'Credit'), hintText: '0.00'),
                )),
              ],
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.description,
              maxLength: 255,
              decoration: InputDecoration(labelText: t('بيان السطر (اختياري)', 'Line description (optional)')),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(t('قيد محاسبي يدوي', 'New manual journal entry'))),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (error != null) ...[
                  _JournalFormMessage(text: error!),
                  const SizedBox(height: 10),
                ],
                if (accounts.isEmpty)
                  _JournalFormMessage(text: t('لا توجد حسابات نشطة متاحة لإنشاء القيد.', 'No active accounts are available for a journal entry.')),
                Form(
                  key: formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              OutlinedButton.icon(onPressed: saving ? null : chooseDate, icon: const Icon(Icons.calendar_month_rounded), label: Text('${t('تاريخ القيد', 'Entry date')}: $entryDate')),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: description,
                                maxLength: 255,
                                decoration: InputDecoration(labelText: t('وصف القيد', 'Entry description')),
                                validator: (value) => (value ?? '').trim().isEmpty ? t('وصف القيد مطلوب.', 'An entry description is required.') : null,
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(t('أسطر القيد', 'Journal lines'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      for (var i = 0; i < lines.length; i++) lineCard(i),
                      OutlinedButton.icon(
                        onPressed: saving || lines.length >= 20 ? null : () => setState(() => lines.add(_ManualJournalLine())),
                        icon: const Icon(Icons.add_rounded),
                        label: Text(t('إضافة سطر', 'Add line')),
                      ),
                      const SizedBox(height: 12),
                      FilledButton.icon(
                        onPressed: saving || accounts.isEmpty ? null : save,
                        icon: saving
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.save_rounded),
                        label: Text(t('حفظ وترحيل القيد', 'Save and post entry')),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}

class _ManualJournalLine {
  int? accountId;
  final debit = TextEditingController();
  final credit = TextEditingController();
  final description = TextEditingController();

  void dispose() {
    debit.dispose();
    credit.dispose();
    description.dispose();
  }
}

class _JournalFormMessage extends StatelessWidget {
  const _JournalFormMessage({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: s.error.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: s.error.withValues(alpha: .22)),
      ),
      child: Text(text, style: TextStyle(color: s.error)),
    );
  }
}
