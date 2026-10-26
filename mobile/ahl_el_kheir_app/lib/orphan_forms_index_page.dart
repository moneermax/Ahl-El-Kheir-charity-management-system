import 'package:flutter/material.dart';

class OrphanFormsIndexPage extends StatefulWidget {
  const OrphanFormsIndexPage({super.key, required this.english, required this.loadForms, required this.openForm});
  final bool english;
  final Future<Map<String, dynamic>> Function(String query) loadForms;
  final Future<void> Function(int childId) openForm;
  @override
  State<OrphanFormsIndexPage> createState() => _OrphanFormsIndexPageState();
}
class _OrphanFormsIndexPageState extends State<OrphanFormsIndexPage> {
  final queryController = TextEditingController();
  bool loading = true;
  String? error;
  bool canEdit = false;
  List<Map<String, dynamic>> children = [];
  @override
  void initState() { super.initState(); refresh(); }
  @override
  void dispose() { queryController.dispose(); super.dispose(); }
  String t(String ar, String en) => widget.english ? en : ar;
  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadForms(queryController.text.trim());
      if (!mounted) return;
      setState(() {
        children = data['children'] is List ? (data['children'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
        canEdit = data['can_edit'] == true;
      });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF6F4EE),
    appBar: AppBar(title: Text(t('استمارات الأيتام','Orphan forms')), actions: [IconButton(tooltip: t('تحديث','Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded))]),
    body: RefreshIndicator(onRefresh: refresh, child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
      Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(t('بحث وفتح الاستمارات','Search and open forms'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
        const SizedBox(height: 5),
        Text(t('تُطبق صلاحيات الدور ونطاق الأسر على الخادم. النتائج محدودة بأحدث 200 سجل.','Server-side role and family scope apply. Results are limited to the latest 200 records.')),
        const SizedBox(height: 12),
        TextField(controller: queryController, onSubmitted: (_) => refresh(), decoration: InputDecoration(
          labelText: t('اسم اليتيم أو الأم أو كود الأسرة أو رقم الاستمارة','Child, mother, family code or form serial'),
          border: const OutlineInputBorder(), prefixIcon: const Icon(Icons.search_rounded),
        )),
        const SizedBox(height: 8),
        FilledButton.icon(onPressed: loading ? null : refresh, icon: const Icon(Icons.search_rounded), label: Text(t('بحث','Search'))),
        if (loading) const Padding(padding: EdgeInsets.only(top: 10), child: LinearProgressIndicator()),
        if (error != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(error!, style: const TextStyle(color: Color(0xFFB42318)))),
        if (!loading && error == null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(t('النتائج: ','Results: ') + children.length.toString() + (canEdit ? t(' · قابل للتعديل حسب الصلاحية',' · editing permitted by role') : t(' · عرض فقط',' · read-only')))),
      ]))),
      if (!loading && error == null && children.isEmpty) Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(t('لا توجد استمارات مطابقة.','No matching forms found.')))),
      for (final child in children) ...[
        const SizedBox(height: 8),
        Card(child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => widget.openForm(int.tryParse(child['id']?.toString() ?? '') ?? 0),
          child: Padding(padding: const EdgeInsets.all(14), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            CircleAvatar(backgroundColor: const Color(0xFFE9F2EB), child: const Icon(Icons.assignment_outlined, color: Color(0xFF1E6B4E))),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(child['child_name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              const SizedBox(height: 4),
              Text(t('الأسرة: ','Family: ') + (child['family_code']?.toString() ?? '—') + ' · ' + t('الأم: ','Mother: ') + (child['mother_name']?.toString() ?? '—')),
              Text(t('رقم الاستمارة: ','Form: ') + (child['form_serial']?.toString() ?? '—') + ' · ' + t('الحالة: ','Status: ') + (child['family_status']?.toString() ?? '—'), style: const TextStyle(fontSize: 12, color: Color(0xFF667085))),
            ])),
            Icon(canEdit ? Icons.edit_note_rounded : Icons.visibility_outlined, color: const Color(0xFF1E6B4E)),
          ])),
        )),
      ],
    ])),
  );
}
