import 'package:flutter/material.dart';

class RoleDashboardPage extends StatefulWidget {
  const RoleDashboardPage({
    super.key,
    required this.english,
    required this.roleCode,
    required this.loadOverview,
  });

  final bool english;
  final String roleCode;
  final Future<Map<String, dynamic>> Function() loadOverview;

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
    const ink = Color(0xFF0A1F44);
    const muted = Color(0xFF64748B);
    const accents = [
      Color(0xFF2E63A8),
      Color(0xFF218C74),
      Color(0xFF8764B8),
      Color(0xFFC56B32),
      Color(0xFF168AAD),
      Color(0xFFB5475A),
    ];
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
            color: const Color(0xFFFFF2F0),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(en ? 'Dashboard data could not be loaded.' : 'تعذر تحميل بيانات لوحة التحكم.',
                    style: const TextStyle(color: Color(0xFFB42318), fontWeight: FontWeight.w700)),
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
            childAspectRatio: 1.55,
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
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(label, maxLines: 2, overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: muted, fontSize: 11, height: 1.35, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  Text(_formatValue(item['value']), maxLines: 1, overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: ink, fontSize: 22, fontWeight: FontWeight.w900)),
                ],
              ),
            );
          },
        ),
      ],
    );
  }
}
