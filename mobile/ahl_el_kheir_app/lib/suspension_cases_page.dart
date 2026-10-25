import 'package:flutter/material.dart';

class SuspensionCasesPage extends StatefulWidget {
  const SuspensionCasesPage({
    super.key,
    required this.english,
    required this.canLift,
    required this.loadCases,
    required this.liftCase,
  });

  final bool english;
  final bool canLift;
  final Future<Map<String, dynamic>> Function() loadCases;
  final Future<Map<String, dynamic>> Function(int caseId) liftCase;

  @override
  State<SuspensionCasesPage> createState() => _SuspensionCasesPageState();
}

class _SuspensionCasesPageState extends State<SuspensionCasesPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  int? liftingId;

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

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('حالات تعليق الكفالات', 'Sponsorship suspensions')),
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
