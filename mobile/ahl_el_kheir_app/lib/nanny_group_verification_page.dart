import 'package:flutter/material.dart';

class NannyGroupVerificationPage extends StatefulWidget {
  const NannyGroupVerificationPage({
    super.key,
    required this.english,
    required this.loadData,
    required this.submitAction,
  });
  final bool english;
  final Future<Map<String, dynamic>> Function(String month, int? groupId) loadData;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;

  @override
  State<NannyGroupVerificationPage> createState() => _NannyGroupVerificationPageState();
}

class _NannyGroupVerificationPageState extends State<NannyGroupVerificationPage> {
  late final TextEditingController monthController;
  bool loading = true, saving = false;
  String? error;
  int? selectedGroupId;
  List<Map<String, dynamic>> groups = [], families = [];
  Map<String, dynamic>? selectedGroup;
  Map<String, dynamic> counts = {};
  final Set<int> selectedFamilyIds = {};

  bool get en => widget.english;
  String t(String ar, String english) => en ? english : ar;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    monthController = TextEditingController(text: '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}');
    load();
  }

  @override
  void dispose() {
    monthController.dispose();
    super.dispose();
  }

  Future<void> load() async {
    final month = monthController.text.trim();
    if (!RegExp(r'^\d{4}-(0[1-9]|1[0-2])$').hasMatch(month)) {
      setState(() => error = t('أدخل الشهر بصيغة YYYY-MM.', 'Enter the month as YYYY-MM.'));
      return;
    }
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadData(month, selectedGroupId);
      final rawGroups = data['groups'];
      final rawFamilies = data['families'];
      if (!mounted) return;
      setState(() {
        groups = rawGroups is List ? rawGroups.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
        families = rawFamilies is List ? rawFamilies.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
        selectedGroup = data['selected_group'] is Map ? Map<String, dynamic>.from(data['selected_group'] as Map) : null;
        counts = data['counts'] is Map ? Map<String, dynamic>.from(data['counts'] as Map) : {};
        if (selectedGroupId != null && selectedGroup == null) selectedGroupId = null;
        final validIds = families.map((e) => int.tryParse(e['id']?.toString() ?? '') ?? 0).toSet();
        selectedFamilyIds.removeWhere((id) => !validIds.contains(id));
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> performAction(Map<String, dynamic> body) async {
    if (selectedGroupId == null) return;
    setState(() { saving = true; error = null; });
    try {
      final result = await widget.submitAction({
        'group_id': selectedGroupId,
        'month': monthController.text.trim(),
        ...body,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(result['message']?.toString() ?? t('تم الحفظ.', 'Saved.')),
      ));
      selectedFamilyIds.clear();
      await load();
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> editFamily(Map<String, dynamic> family) async {
    bool orphan = family['is_orphan_verified'] == true;
    bool mother = family['is_mother_contact_verified'] == true;
    bool bank = family['is_bank_verified'] == true;
    final notes = TextEditingController(text: family['verification_notes']?.toString() ?? '');
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(builder: (context, setDialogState) => AlertDialog(
        title: Text(t('توثيق الأسرة', 'Verify family')),
        content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
          CheckboxListTile(value: orphan, onChanged: (v) => setDialogState(() => orphan = v ?? false), title: Text(t('التحقق من حالة اليتيم', 'Orphan eligibility checked')), contentPadding: EdgeInsets.zero),
          CheckboxListTile(value: mother, onChanged: (v) => setDialogState(() => mother = v ?? false), title: Text(t('التواصل مع الأم', 'Mother contacted')), contentPadding: EdgeInsets.zero),
          CheckboxListTile(value: bank, onChanged: (v) => setDialogState(() => bank = v ?? false), title: Text(t('التحقق من الحساب البنكي', 'Bank details checked')), contentPadding: EdgeInsets.zero),
          TextField(controller: notes, maxLines: 3, decoration: InputDecoration(labelText: t('ملاحظات', 'Notes'))),
        ])),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, {
            'is_orphan_verified': orphan,
            'is_mother_contact_verified': mother,
            'is_bank_verified': bank,
            'verification_notes': notes.text.trim(),
          }), child: Text(t('حفظ', 'Save'))),
        ],
      )),
    );
    notes.dispose();
    if (result != null) {
      await performAction({'action': 'verify_family', 'family_id': family['id'], ...result});
    }
  }

  Future<void> bulk(String operation) async {
    if (selectedFamilyIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('حدد أسرة واحدة على الأقل.', 'Select at least one family.'))));
      return;
    }
    await performAction({'action': 'bulk_verify', 'operation': operation, 'family_ids': selectedFamilyIds.toList()});
  }

  Future<void> submitGroup() async {
    final yes = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('إرسال المجموعة', 'Submit group')),
        content: Text(t('سيتم إرسال الأسر الموثقة بالكامل للمحاسبة. هل تريد المتابعة؟', 'Fully verified families will be sent to accounting. Continue?')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(t('إرسال', 'Submit'))),
        ],
      ),
    );
    if (yes == true) await performAction({'action': 'submit_group'});
  }

  int intValue(dynamic value) => int.tryParse(value?.toString() ?? '') ?? 0;
  String money(dynamic value) => (double.tryParse(value?.toString() ?? '') ?? 0).toStringAsFixed(2);
  String statusLabel(String status) {
    const ar = {'pending':'قيد التوثيق','submitted':'تم الإرسال','approved':'معتمد','transferred':'تم التحويل','closed':'مغلق','returned':'مُعاد'};
    const enLabels = {'pending':'Pending verification','submitted':'Submitted','approved':'Approved','transferred':'Transferred','closed':'Closed','returned':'Returned'};
    return (en ? enLabels : ar)[status] ?? status;
  }

  @override
  Widget build(BuildContext context) {
    final month = monthController.text.trim();
    return Scaffold(
      appBar: AppBar(title: Text(t('توثيق مجموعات الحاضنات', 'Nanny group verification'))),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(children: [
            Expanded(child: TextField(controller: monthController, keyboardType: TextInputType.datetime, decoration: InputDecoration(labelText: t('شهر الصرف (YYYY-MM)', 'Disbursement month (YYYY-MM)')))),
            const SizedBox(width: 8),
            IconButton(onPressed: loading || saving ? null : load, tooltip: t('تحديث', 'Refresh'), icon: const Icon(Icons.refresh_rounded)),
          ]),
        ),
        if (error != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4), child: Text(error!, style: const TextStyle(color: Colors.red))),
        if (selectedGroupId != null)
          Align(alignment: AlignmentDirectional.centerStart, child: TextButton.icon(
            onPressed: loading || saving ? null : () { setState(() { selectedGroupId = null; selectedFamilyIds.clear(); }); load(); },
            icon: const Icon(Icons.arrow_back_rounded), label: Text(t('العودة إلى المجموعات', 'Back to groups')),
          )),
        Expanded(child: loading
          ? const Center(child: CircularProgressIndicator())
          : selectedGroupId == null
            ? groups.isEmpty
              ? Center(child: Text(t('لا توجد مجموعات نشطة معينة لحسابك.', 'No active groups are assigned to your account.')))
              : ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: groups.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 6),
                  itemBuilder: (context, index) {
                    final group = groups[index];
                    return Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.groups_rounded)),
                      title: Text(group['group_name']?.toString() ?? ''),
                      subtitle: Text('${statusLabel(group['verification_status']?.toString() ?? '')}${group['description']?.toString().isNotEmpty == true ? '\n${group['description']}' : ''}'),
                      isThreeLine: group['description']?.toString().isNotEmpty == true,
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: () { setState(() { selectedGroupId = intValue(group['id']); selectedFamilyIds.clear(); }); load(); },
                    ));
                  },
                )
            : selectedGroup == null
              ? Center(child: Text(t('تعذر تحميل المجموعة.', 'Unable to load the group.')))
              : Column(children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(selectedGroup!['group_name']?.toString() ?? '', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                      Text('${statusLabel(selectedGroup!['verification_status']?.toString() ?? '')} · ${t('الشهر', 'Month')}: $month'),
                      const SizedBox(height: 6),
                      Wrap(spacing: 8, runSpacing: 4, children: [
                        Chip(label: Text('${t('موثقة', 'Verified')}: ${counts['verified_count'] ?? 0}')),
                        Chip(label: Text('${t('غير مكتملة', 'Incomplete')}: ${counts['unverified_count'] ?? 0}')),
                        Chip(label: Text('${t('المبلغ الموثق', 'Verified amount')}: ${money(counts['verified_amount'])}')),
                      ]),
                      if (selectedGroup!['can_bulk_verify'] == true)
                        Wrap(spacing: 8, runSpacing: 8, children: [
                          OutlinedButton(onPressed: saving ? null : () => bulk('orphan'), child: Text(t('توثيق الأيتام', 'Verify orphans'))),
                          OutlinedButton(onPressed: saving ? null : () => bulk('mother'), child: Text(t('توثيق التواصل', 'Verify contact'))),
                          OutlinedButton(onPressed: saving ? null : () => bulk('bank'), child: Text(t('توثيق البنوك', 'Verify banks'))),
                          FilledButton(onPressed: saving ? null : () => bulk('all'), child: Text(t('توثيق الكل', 'Verify all'))),
                        ]),
                      if (selectedGroup!['can_submit'] == true)
                        Padding(padding: const EdgeInsets.only(top: 8), child: FilledButton.icon(
                          onPressed: saving ? null : submitGroup,
                          icon: const Icon(Icons.send_rounded), label: Text(t('إرسال للمحاسبة', 'Submit to accounting')),
                        )),
                    ]),
                  ),
                  const Divider(height: 1),
                  Expanded(child: families.isEmpty
                    ? Center(child: Text(t('لا توجد أسر نشطة في هذه المجموعة.', 'No active families in this group.')))
                    : ListView.separated(
                        padding: const EdgeInsets.all(12),
                        itemCount: families.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 5),
                        itemBuilder: (context, index) {
                          final family = families[index];
                          final id = intValue(family['id']);
                          final complete = family['is_fully_verified'] == true;
                          final selectable = selectedGroup!['can_bulk_verify'] == true;
                          return Card(child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(children: [
                              if (selectable) Checkbox(value: selectedFamilyIds.contains(id), onChanged: (v) => setState(() { if (v == true) { selectedFamilyIds.add(id); } else { selectedFamilyIds.remove(id); } })),
                              Expanded(child: InkWell(
                                onTap: saving ? null : () => editFamily(family),
                                child: Padding(padding: const EdgeInsets.all(8), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Text('${family['family_code'] ?? ''} · ${family['mother_name'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                  const SizedBox(height: 4),
                                  Text('${t('المبلغ الشهري', 'Monthly amount')}: ${money(family['amount'])}'),
                                  Wrap(spacing: 5, children: [
                                    _CheckTag(label: t('اليتيم', 'Orphan'), checked: family['is_orphan_verified'] == true),
                                    _CheckTag(label: t('الأم', 'Mother'), checked: family['is_mother_contact_verified'] == true),
                                    _CheckTag(label: t('البنك', 'Bank'), checked: family['is_bank_verified'] == true),
                                  ]),
                                  Text(complete ? t('مكتملة', 'Complete') : t('تحتاج إلى استكمال التوثيق', 'Verification incomplete'), style: TextStyle(color: complete ? Colors.green.shade800 : Colors.orange.shade900, fontWeight: FontWeight.w600)),
                                ])),
                              )),
                              IconButton(onPressed: saving ? null : () => editFamily(family), tooltip: t('تعديل التوثيق', 'Edit verification'), icon: const Icon(Icons.edit_outlined)),
                            ]),
                          ));
                        },
                      )),
                ]),
        ),
      ]),
    );
  }
}

class _CheckTag extends StatelessWidget {
  const _CheckTag({required this.label, required this.checked});
  final String label;
  final bool checked;
  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(checked ? Icons.check_circle : Icons.radio_button_unchecked, size: 16, color: checked ? Colors.green : Colors.orange),
    label: Text(label),
    visualDensity: VisualDensity.compact,
    padding: EdgeInsets.zero,
  );
}
