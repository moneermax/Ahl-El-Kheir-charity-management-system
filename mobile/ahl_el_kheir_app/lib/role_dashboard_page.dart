import 'package:flutter/material.dart';

class RoleDashboardPage extends StatefulWidget {
  const RoleDashboardPage({
    super.key,
    required this.english,
    required this.roleCode,
    required this.loadOverview,
    required this.onOpenTool,
  });

  final bool english;
  final String roleCode;
  final Future<Map<String, dynamic>> Function() loadOverview;
  final ValueChanged<String> onOpenTool;

  @override
  State<RoleDashboardPage> createState() => _RoleDashboardPageState();
}

class _RoleDashboardPageState extends State<RoleDashboardPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> metrics = [];

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void didUpdateWidget(covariant RoleDashboardPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.roleCode != widget.roleCode) refresh();
  }

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.loadOverview();
      final data = response['metrics'];
      if (!mounted) return;
      setState(() {
        metrics = data is List
            ? data.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
            : <Map<String, dynamic>>[];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String _formatValue(dynamic value) {
    final number = num.tryParse(value?.toString() ?? '');
    if (number == null) return value?.toString() ?? '0';
    if (number == number.roundToDouble()) {
      return number.toInt().toString().replaceAllMapped(
        RegExp(r'\B(?=(\d{3})+(?!\d))'),
        (match) => ',',
      );
    }
    return number.toStringAsFixed(2).replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (match) => ',',
    );
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    final attentionItems = _attentionItems();
    final isGeneralManager = const ['general_manager', 'gm'].contains(widget.roleCode);
    final scheme = Theme.of(context).colorScheme;
    const ink = Color(0xFF251B35);
    const muted = Color(0xFF756B84);
    const accents = [Color(0xFF5B21B6), Color(0xFFF59E0B), Color(0xFFDB2777), Color(0xFF7C3AED), Color(0xFFB45309), Color(0xFF9333EA)];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(en ? 'Dashboard overview' : 'مؤشرات لوحة التحكم',
                    style: const TextStyle(color: ink, fontSize: 16, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(en ? 'Live figures from your role dashboard' : 'بيانات مباشرة من لوحة دورك في النظام',
                    style: const TextStyle(color: muted, fontSize: 12)),
                ],
              ),
            ),
            IconButton(
              tooltip: en ? 'Refresh dashboard' : 'تحديث لوحة التحكم',
              onPressed: loading ? null : refresh,
              icon: const Icon(Icons.refresh_rounded),
            ),
            if (loading)
              const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
          ],
        ),
        const SizedBox(height: 12),
        if (error != null)
          Card(
            color: scheme.errorContainer,
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(en ? 'Dashboard data could not be loaded.' : 'تعذر تحميل بيانات لوحة التحكم.',
                    style: TextStyle(color: scheme.error, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 6),
                  Text(error!, style: const TextStyle(color: muted, fontSize: 11)),
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: TextButton.icon(
                      onPressed: loading ? null : refresh,
                      icon: const Icon(Icons.refresh_rounded),
                      label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        if (metrics.isEmpty && error == null && !loading)
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(en ? 'No dashboard metrics are available for this role.' : 'لا توجد مؤشرات متاحة لهذا الدور.',
              style: const TextStyle(color: muted)),
          ),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: metrics.length,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: 1.15,
          ),
          itemBuilder: (context, index) {
            final item = metrics[index];
            final accent = accents[index % accents.length];
            final label = item[en ? 'label_en' : 'label_ar']?.toString() ?? item['key']?.toString() ?? '';
            return Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: accent.withValues(alpha: .18)),
                boxShadow: [
                  BoxShadow(color: ink.withValues(alpha: .035), blurRadius: 12, offset: const Offset(0, 4)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.start,
                children: [
                  Text(label, maxLines: 2, overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: muted, fontSize: 11, height: 1.35, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 10),
                  Flexible(child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: AlignmentDirectional.centerStart,
                    child: Text(_formatValue(item['value']), maxLines: 1, overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: ink, fontSize: 22, fontWeight: FontWeight.w900)),
                  )),
                ],
              ),
            );
          },
        ),
        if (isGeneralManager && !loading && error == null) ...[
          const SizedBox(height: 22),
          Row(
            children: [
              const Icon(Icons.notifications_active_outlined, color: Color(0xFFB45309)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(en ? 'Needs your attention' : 'ما يحتاج انتباهك',
                  style: const TextStyle(color: ink, fontSize: 16, fontWeight: FontWeight.w900)),
              ),
              Text(attentionItems.length.toString(),
                style: const TextStyle(color: muted, fontSize: 12, fontWeight: FontWeight.w800)),
            ],
          ),
          const SizedBox(height: 10),
          if (attentionItems.isEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle_outline_rounded, color: Color(0xFF0F766E)),
                    const SizedBox(width: 10),
                    Expanded(child: Text(en
                      ? 'No attention items are currently reported.'
                      : 'لا توجد عناصر تتطلب المتابعة ضمن المؤشرات المتاحة حالياً.',
                      style: const TextStyle(color: muted, fontWeight: FontWeight.w700))),
                  ],
                ),
              ),
            ),
          for (final item in attentionItems)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: const Color(0xFFFFF7ED),
                  child: Icon(item.icon, color: const Color(0xFFB45309)),
                ),
                title: Text(en ? item.titleEn : item.titleAr,
                  style: const TextStyle(fontWeight: FontWeight.w800)),
                subtitle: Text(en ? item.subtitleEn : item.subtitleAr),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF3E8FF),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(_formatValue(item.count),
                        style: const TextStyle(color: Color(0xFF5B21B6), fontWeight: FontWeight.w900)),
                    ),
                    const SizedBox(width: 8),
                    const Icon(Icons.arrow_forward_ios_rounded, size: 14),
                  ],
                ),
                onTap: () => widget.onOpenTool(item.path),
              ),
            ),
        ],
      ],
    );
  }

  List<_AttentionItem> _attentionItems() {
    if (!const ['general_manager', 'gm'].contains(widget.roleCode)) return [];
    final values = <String, dynamic>{
      for (final metric in metrics)
        if (metric['key'] != null) metric['key'].toString(): metric['value'],
    };
    final items = <_AttentionItem>[];
    void addIfPositive(String key, String titleAr, String titleEn, String subtitleAr,
        String subtitleEn, String path, IconData icon) {
      final count = num.tryParse(values[key]?.toString() ?? '') ?? 0;
      if (count <= 0) return;
      items.add(_AttentionItem(titleAr, titleEn, subtitleAr, subtitleEn, path, icon, count));
    }

    // Use only counts returned by the authenticated role-dashboard API.
    addIfPositive('pending_families', 'أسر بانتظار الإجراء', 'Families awaiting action',
      'مراجعة الأسر التي تنتظر استكمال الإجراء', 'Review families awaiting the next action',
      'modules/reports/operational.php', Icons.hourglass_top_rounded);
    addIfPositive('uncovered_families', 'أسر بلا كفالة نشطة', 'Families without active sponsorship',
      'مراجعة الأسر التي تحتاج إلى تغطية', 'Review families that need sponsorship coverage',
      'modules/reports/orphaned_families.php', Icons.home_work_outlined);
    addIfPositive('paused_sponsorships', 'كفالات متوقفة', 'Paused sponsorships',
      'مراجعة حالة الكفالات المتوقفة', 'Review paused sponsorships',
      'modules/reports/sponsorship.php', Icons.pause_circle_outline_rounded);
    addIfPositive('open_winback', 'متابعات استعادة الكفلاء', 'Open sponsor win-back cases',
      'متابعة الحالات المفتوحة', 'Follow up on open cases',
      'modules/administration/winback.php', Icons.replay_rounded);
    addIfPositive('unassigned_letters', 'حروف غير مسندة للمشرفين', 'Unassigned supervisor letters',
      'مراجعة توزيع الحروف', 'Review supervisor letter assignments',
      'modules/reports/operational.php', Icons.assignment_late_outlined);
    addIfPositive('open_sponsor_requests', 'طلبات رعاية قيد المتابعة', 'Sponsor requests awaiting follow-up',
      'مراجعة الطلبات المفتوحة', 'Review open sponsor requests',
      'modules/sponsor_requests/index.php', Icons.mark_email_unread_outlined);
    return items;
  }
}

class _AttentionItem {
  const _AttentionItem(this.titleAr, this.titleEn, this.subtitleAr, this.subtitleEn,
      this.path, this.icon, this.count);

  final String titleAr;
  final String titleEn;
  final String subtitleAr;
  final String subtitleEn;
  final String path;
  final IconData icon;
  final num count;
}
