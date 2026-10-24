import 'package:flutter/material.dart';

class NannyPreVerificationPage extends StatefulWidget {
  const NannyPreVerificationPage({super.key, required this.english, required this.loadData, required this.submitAction});
  final bool english;
  final Future<Map<String, dynamic>> Function(String month) loadData;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;
  @override
  State<NannyPreVerificationPage> createState() => _NannyPreVerificationPageState();
}

class _NannyPreVerificationPageState extends State<NannyPreVerificationPage> {
  late final TextEditingController month;
  bool loading = true, saving = false;
  String? error;
  List<Map<String, dynamic>> families = [];
  Map<String, dynamic> counts = {};
  final Set<int> selected = {};
  bool get en => widget.english;
  String t(String ar, String english) => en ? english : ar;
  int num(dynamic v) => int.tryParse(v?.toString() ?? '') ?? 0;

  @override
  void initState() {
    super.initState();
    final n = DateTime.now();
    month = TextEditingController(text: '${n.year.toString().padLeft(4, '0')}-${n.month.toString().padLeft(2, '0')}');
    load();
  }
  @override
  void dispose() { month.dispose(); super.dispose(); }

  Future<void> load() async {
    if (!RegExp(r'^\d{4}-(0[1-9]|1[0-2])$').hasMatch(month.text.trim())) {
      setState(() => error = t('أدخل الشهر بصيغة YYYY-MM.', 'Enter month as YYYY-MM.'));
      return;
    }
    setState(() { loading = true; error = null; });
    try {
      final d = await widget.loadData(month.text.trim());
      if (!mounted) return;
      setState(() {
        final raw = d['items'];
        families = raw is List ? raw.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : [];
        counts = d['counts'] is Map ? Map<String, dynamic>.from(d['counts'] as Map) : {};
        selected.removeWhere((id) => !families.any((f) => num(f['id']) == id));
      });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }

  Future<void> act(Map<String, dynamic> body) async {
    setState(() { saving = true; error = null; });
    try {
      final d = await widget.submitAction({'month': month.text.trim(), ...body});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(d['message']?.toString() ?? t('تم الحفظ.', 'Saved.'))));
      selected.clear();
      await load();
    } catch (e) {
      if (mounted) { setState(() => error = e.toString()); ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()))); }
    } finally { if (mounted) setState(() => saving = false); }
  }

  Future<void> edit(Map<String, dynamic> f) async {
    bool orphan = f['is_orphan_verified'] == true, mother = f['is_mother_contact_verified'] == true, bank = f['is_bank_verified'] == true;
    final notes = TextEditingController(text: f['verification_notes']?.toString() ?? '');
    final result = await showDialog<Map<String, dynamic>>(context: context, builder: (dc) => StatefulBuilder(builder: (context, ds) => AlertDialog(
      title: Text(t('التوثيق المسبق للأسرة', 'Preliminary family verification')),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        CheckboxListTile(value: orphan, onChanged: (v) => ds(() => orphan = v ?? false), title: Text(t('التحقق من حالة اليتيم', 'Orphan eligibility checked'))),
        CheckboxListTile(value: mother, onChanged: (v) => ds(() => mother = v ?? false), title: Text(t('التواصل مع الأم', 'Mother contacted'))),
        CheckboxListTile(value: bank, onChanged: (v) => ds(() => bank = v ?? false), title: Text(t('التحقق من الحساب البنكي', 'Bank details checked'))),
        TextField(controller: notes, maxLines: 3, decoration: InputDecoration(labelText: t('ملاحظات', 'Notes'))),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dc), child: Text(t('إلغاء', 'Cancel'))),
        FilledButton(onPressed: () => Navigator.pop(dc, {'is_orphan_verified': orphan, 'is_mother_contact_verified': mother, 'is_bank_verified': bank, 'verification_notes': notes.text.trim()}), child: Text(t('حفظ', 'Save'))),
      ],
    )));
    notes.dispose();
    if (result != null) await act({'action': 'verify_family', 'family_id': f['id'], ...result});
  }

  Future<void> bulk(String op) async {
    if (selected.isEmpty) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('حدد أسرة واحدة على الأقل.', 'Select at least one family.')))); return; }
    await act({'action': 'bulk_verify', 'operation': op, 'family_ids': selected.toList()});
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(t('التحقق المسبق للأسر', 'Preliminary family verification'))),
    body: Column(children: [
      Padding(padding: const EdgeInsets.all(12), child: Row(children: [
        Expanded(child: TextField(controller: month, keyboardType: TextInputType.datetime, decoration: InputDecoration(labelText: t('شهر الصرف (YYYY-MM)', 'Disbursement month (YYYY-MM)')))),
        IconButton(onPressed: loading || saving ? null : load, icon: const Icon(Icons.refresh_rounded)),
      ])),
      Padding(padding: const EdgeInsets.symmetric(horizontal: 14), child: Text(t(
        'خطوة تمهيدية فقط؛ تُحفظ التوثيقات للشهر نفسه وتظهر في توثيق المجموعة. تشمل جميع الأسر النشطة ضمن مجموعاتك النشطة بغض النظر عن حالة المجموعة.',
        'Preliminary only. Checks are shared with group verification for the same month and include active families in your active groups regardless of group status.'))),
      if (error != null) Padding(padding: const EdgeInsets.all(8), child: Text(error!, style: const TextStyle(color: Colors.red))),
      if (!loading) Padding(padding: const EdgeInsets.all(8), child: Column(children: [
        Wrap(spacing: 5, children: [
          Chip(label: Text('${t('الأسر', 'Families')}: ${counts['total'] ?? 0}')),
          Chip(label: Text('${t('مكتملة', 'Complete')}: ${counts['verified'] ?? 0}')),
          Chip(label: Text('${t('بدأت', 'In progress')}: ${counts['partial'] ?? 0}')),
          Chip(label: Text('${t('لم تبدأ', 'Not started')}: ${counts['not_started'] ?? 0}')),
        ]),
        Wrap(spacing: 5, runSpacing: 4, children: [
          OutlinedButton(onPressed: saving ? null : () => bulk('orphan'), child: Text(t('توثيق الأيتام', 'Verify orphans'))),
          OutlinedButton(onPressed: saving ? null : () => bulk('mother'), child: Text(t('توثيق التواصل', 'Verify contact'))),
          OutlinedButton(onPressed: saving ? null : () => bulk('bank'), child: Text(t('توثيق البنوك', 'Verify banks'))),
          FilledButton(onPressed: saving ? null : () => bulk('all'), child: Text(t('توثيق الكل', 'Verify all'))),
        ]),
      ])),
      Expanded(child: loading ? const Center(child: CircularProgressIndicator()) : families.isEmpty
        ? Center(child: Text(t('لا توجد أسر متاحة للتوثيق المسبق حالياً.', 'No families available for preliminary verification.')))
        : ListView.builder(padding: const EdgeInsets.all(10), itemCount: families.length, itemBuilder: (context, i) {
          final f = families[i], id = num(f['id']);
          final complete = f['verification_status'] == 'verified';
          return Card(child: Row(children: [
            Checkbox(value: selected.contains(id), onChanged: saving ? null : (v) => setState(() { if (v == true) selected.add(id); else selected.remove(id); })),
            Expanded(child: InkWell(onTap: saving ? null : () => edit(f), child: Padding(padding: const EdgeInsets.all(8), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${f['family_code'] ?? ''} · ${f['mother_name'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.bold)),
              if ((f['group_names']?.toString() ?? '').isNotEmpty) Text('${t('المجموعات', 'Groups')}: ${f['group_names']}'),
              Text('${t('التوثيق', 'Verification')}: ${f['verified_checks'] ?? 0}/3'),
              Text(complete ? t('مكتملة', 'Complete') : t('غير مكتملة', 'Incomplete'), style: TextStyle(color: complete ? Colors.green : Colors.orange)),
            ])))),
            IconButton(onPressed: saving ? null : () => edit(f), icon: const Icon(Icons.edit_outlined)),
          ]));
        })),
    ]),
  );
}
