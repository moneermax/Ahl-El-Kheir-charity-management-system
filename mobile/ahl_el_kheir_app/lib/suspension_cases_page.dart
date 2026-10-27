import 'package:flutter/material.dart';

class SuspensionCasesPage extends StatefulWidget {
  const SuspensionCasesPage({
    super.key,
    required this.english,
    required this.canLift,
    required this.loadCases,
    required this.liftCase,
    required this.loadOptions,
    required this.createCase,
  });

  final bool english;
  final bool canLift;
  final Future<Map<String, dynamic>> Function() loadCases;
  final Future<Map<String, dynamic>> Function(int caseId) liftCase;
  final Future<Map<String, dynamic>> Function(String query) loadOptions;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> values) createCase;

  @override
  State<SuspensionCasesPage> createState() => _SuspensionCasesPageState();
}

class _SuspensionCasesPageState extends State<SuspensionCasesPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  int? liftingId;
  bool canCreate = false;
  bool creating = false;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  String t(String ar, String en) => widget.english ? en : ar;

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadCases();
      final raw = data['items'];
      if (!mounted) return;
      setState(() {
        items = raw is List
            ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
            : <Map<String, dynamic>>[];
        canCreate = data['can_create'] == true;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String reasonLabel(String reason) {
    final labels = widget.english
        ? const {
            'age_out': 'Reached legal age',
            'false_info': 'Incorrect application information',
            'other_support': 'Other relative support',
            'other': 'Other reason',
          }
        : const {
            'age_out': 'بلوغ السن القانوني',
            'false_info': 'معلومات غير صحيحة عند التقديم',
            'other_support': 'وجود معيل آخر من الأقارب',
            'other': 'سبب آخر',
          };
    return labels[reason] ?? reason;
  }

  Future<void> lift(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (!widget.canLift || id < 1 || liftingId != null || item['status'] != 'active') return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('رفع التعليق؟', 'Lift suspension?')),
        content: Text(t(
          'سيتم رفع حالة التعليق واستئناف الكفالات المرتبطة بهذه الحالة.',
          'This lifts the case and resumes sponsorships linked to this suspension.',
        )),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('إلغاء', 'Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('تأكيد الرفع', 'Confirm lift')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() {
      liftingId = id;
      error = null;
    });
    try {
      await widget.liftCase(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('تم رفع التعليق واستئناف الكفالات المرتبطة.', 'Suspension lifted and linked sponsorships resumed.'))),
      );
      await refresh();
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => liftingId = null);
    }
  }


  Future<void> createSuspension() async {
    if (!canCreate || creating || !mounted) return;
    String queryText = '';
    List<Map<String, dynamic>> options = [];
    bool searching = false;
    String? searchError;
    Future<void> search(StateSetter setDialogState) async {
      final query = queryText.trim();
      if (query.length < 2) {
        setDialogState(() => searchError = t('أدخل حرفين على الأقل للبحث.', 'Enter at least two characters to search.'));
        return;
      }
      setDialogState(() { searching = true; searchError = null; });
      try {
        final result = await widget.loadOptions(query);
        final raw = result['options'];
        setDialogState(() => options = raw is List ? raw.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList() : <Map<String, dynamic>>[]);
      } catch (e) {
        setDialogState(() => searchError = e.toString());
      } finally {
        setDialogState(() => searching = false);
      }
    }
    final selected = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          scrollable: true,
          title: Text(t('تعليق جديد', 'New suspension')),
          content: SizedBox(width: 460, child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(t('ابحث عن الأسرة أو اليتيم. تظهر النتائج وفق صلاحيات حسابك.', 'Search for a family or child. Results are scoped to your account permissions.')),
            const SizedBox(height: 12),
            TextField(key: const ValueKey('suspension-search'), onChanged: (value) => queryText = value, onSubmitted: (_) => search(setDialogState),
              decoration: InputDecoration(labelText: t('اسم أو كود الأسرة أو اسم اليتيم', 'Family name/code or child name'), border: const OutlineInputBorder())),
            Align(alignment: AlignmentDirectional.centerEnd, child: TextButton.icon(onPressed: searching ? null : () => search(setDialogState),
              icon: const Icon(Icons.search_rounded), label: Text(t('بحث', 'Search')))),
            if (searchError != null) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(searchError!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
            if (searching) const LinearProgressIndicator(),
            if (!searching && options.isEmpty) Padding(padding: const EdgeInsets.all(10), child: Text(t('لا توجد نتائج. أدخل عبارة بحث أخرى.', 'No results. Try another search.'))),
            if (options.isNotEmpty) SizedBox(height: 180, child: ListView.builder(shrinkWrap: true, itemCount: options.length, itemBuilder: (context, index) {
              final option = options[index];
              final isChild = option['entity_type'] == 'child';
              final code = option['family_code']?.toString() ?? '';
              final subtitle = isChild ? t('يتيم · الأسرة: ', 'Child · Family: ') + code + ' · ' + (option['mother_name']?.toString() ?? '') : t('أسرة', 'Family') + ' · ' + code;
              return ListTile(
                key: ValueKey('suspension-option-' + (option['entity_type']?.toString() ?? '') + '-' + (option['entity_id']?.toString() ?? '')),
                dense: true, leading: Icon(isChild ? Icons.child_care_rounded : Icons.family_restroom_rounded),
                title: Text(option['entity_name']?.toString() ?? ''), subtitle: Text(subtitle),
                onTap: () => Navigator.pop(dialogContext, option),
              );
            })),
          ])),
          actions: [TextButton(onPressed: searching ? null : () => Navigator.pop(dialogContext), child: Text(t('إلغاء', 'Cancel')))],
        ),
      ),
    );
    if (selected == null || !mounted) return;
    String notesText = '';
    String reason = 'other';
    final details = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(builder: (context, setDialogState) => AlertDialog(
        scrollable: true,
        title: Text(t('تفاصيل التعليق', 'Suspension details')),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text((selected['entity_type'] == 'child' ? t('اليتيم: ', 'Child: ') : t('الأسرة: ', 'Family: ')) + (selected['entity_name']?.toString() ?? '') + ' · ' + (selected['family_code']?.toString() ?? '')),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(key: const ValueKey('suspension-reason'), initialValue: reason, isExpanded: true,
            decoration: InputDecoration(labelText: t('سبب التعليق', 'Suspension reason'), border: const OutlineInputBorder()),
            items: [
              DropdownMenuItem(value: 'age_out', child: Text(t('بلوغ السن القانوني', 'Reached legal age'))),
              DropdownMenuItem(value: 'false_info', child: Text(t('معلومات غير صحيحة عند التقديم', 'Incorrect application information'))),
              DropdownMenuItem(value: 'other_support', child: Text(t('وجود معيل آخر من الأقارب', 'Other relative support'))),
              DropdownMenuItem(value: 'other', child: Text(t('سبب آخر', 'Other reason'))),
            ],
            onChanged: (value) { if (value != null) setDialogState(() => reason = value); }),
          const SizedBox(height: 12),
          TextField(key: const ValueKey('suspension-notes'), onChanged: (value) => notesText = value, maxLines: 3, decoration: InputDecoration(labelText: t('ملاحظات', 'Notes'), border: const OutlineInputBorder())),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, {'reason': reason, 'notes': notesText.trim()}), child: Text(t('متابعة', 'Continue'))),
        ],
      )),
    );
    if (details == null || !mounted) return;
    final confirmed = await showDialog<bool>(context: context, builder: (dialogContext) => AlertDialog(
      title: Text(t('تأكيد التعليق؟', 'Confirm suspension?')),
      content: Text(t('سيتم تسجيل الحالة وإيقاف الكفالات النشطة المرتبطة بالسجل المحدد.', 'This records the suspension and pauses active sponsorships linked to the selected record.')),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(t('إلغاء', 'Cancel'))),
        FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(t('تنفيذ التعليق', 'Suspend'))),
      ],
    ));
    if (confirmed != true || !mounted) return;
    setState(() { creating = true; error = null; });
    try {
      await widget.createCase({'entity_type': selected['entity_type'], 'entity_id': selected['entity_id'], 'reason': details['reason'], 'notes': details['notes']});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم تنفيذ التعليق وإيقاف الكفالات المرتبطة.', 'Suspension created and linked sponsorships paused.'))));
      await refresh();
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => creating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(t('حالات تعليق الكفالات', 'Sponsorship suspensions')),
        actions: [
          if (canCreate) IconButton(tooltip: t('تعليق جديد', 'New suspension'), onPressed: loading || creating ? null : createSuspension, icon: const Icon(Icons.add_circle_outline_rounded)),
          IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading || creating ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null && items.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
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
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    children: [
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF2FF),
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.pause_circle_outline_rounded, color: Color(0xFF1F4E8C), size: 28),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(t('سجل التعليقات', 'Suspension register'),
                                    style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0A1F44))),
                                  const SizedBox(height: 3),
                                  Text(t('آخر ${items.length} حالة · النشطة أولاً', '${items.length} cases · active cases first'),
                                    style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (error != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
                        ),
                      const SizedBox(height: 10),
                      if (items.isEmpty)
                        Padding(
                          padding: const EdgeInsets.all(28),
                          child: Text(t('لا توجد حالات تعليق مسجلة ضمن نطاق حسابك.', 'No suspension cases are available in your account scope.'),
                            textAlign: TextAlign.center),
                        ),
                      for (final item in items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      CircleAvatar(
                                        backgroundColor: item['status'] == 'active' ? const Color(0xFFFFE8E8) : const Color(0xFFE5F6EF),
                                        child: Icon(
                                          item['entity_type'] == 'child' ? Icons.child_care_rounded : Icons.family_restroom_rounded,
                                          color: item['status'] == 'active' ? const Color(0xFFB42318) : const Color(0xFF16805D),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(item['entity_name']?.toString().isNotEmpty == true ? item['entity_name'].toString() : '—',
                                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: Color(0xFF0A1F44))),
                                            const SizedBox(height: 3),
                                            Text(
                                              (item['entity_type'] == 'child' ? t('طفل', 'Child') : t('أسرة', 'Family')) +
                                              ' · ' + (item['family_code']?.toString() ?? '—') +
                                              ' · #' + (item['id']?.toString() ?? '—'),
                                              style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                            ),
                                          ],
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                                        decoration: BoxDecoration(
                                          color: item['status'] == 'active' ? const Color(0xFFFFE8E8) : const Color(0xFFE5F6EF),
                                          borderRadius: BorderRadius.circular(20),
                                        ),
                                        child: Text(
                                          item['status'] == 'active' ? t('نشط', 'Active') : t('مرفوع', 'Lifted'),
                                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800,
                                            color: item['status'] == 'active' ? const Color(0xFFB42318) : const Color(0xFF16805D)),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  Text(reasonLabel(item['reason']?.toString() ?? ''),
                                    style: const TextStyle(fontWeight: FontWeight.w700)),
                                  if ((item['notes']?.toString() ?? '').isNotEmpty) ...[
                                    const SizedBox(height: 5),
                                    Text(item['notes'].toString(), style: const TextStyle(color: Color(0xFF64748B))),
                                  ],
                                  const SizedBox(height: 8),
                                  Text(t('تاريخ التعليق: ', 'Suspended: ') + (item['suspended_at']?.toString() ?? '—'),
                                    style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                  Text(t('بواسطة: ', 'By: ') + ((item['suspended_by_name']?.toString().isNotEmpty == true) ? item['suspended_by_name'].toString() : '—'),
                                    style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                  if (item['status'] != 'active')
                                    Text(t('رُفع بواسطة: ', 'Lifted by: ') + ((item['lifted_by_name']?.toString().isNotEmpty == true) ? item['lifted_by_name'].toString() : '—'),
                                      style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                  if (widget.canLift && item['status'] == 'active') ...[
                                    const SizedBox(height: 10),
                                    Align(
                                      alignment: AlignmentDirectional.centerEnd,
                                      child: FilledButton.icon(
                                        onPressed: liftingId == int.tryParse(item['id']?.toString() ?? '') ? null : () => lift(item),
                                        icon: liftingId == int.tryParse(item['id']?.toString() ?? '')
                                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                            : const Icon(Icons.lock_open_rounded, size: 18),
                                        label: Text(t('رفع التعليق', 'Lift suspension')),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
