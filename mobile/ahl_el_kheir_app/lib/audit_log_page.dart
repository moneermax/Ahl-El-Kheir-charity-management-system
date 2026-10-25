import 'package:flutter/material.dart';

class AuditLogPage extends StatefulWidget {
  const AuditLogPage({super.key, required this.english, required this.loadPage});
  final bool english;
  final Future<Map<String, dynamic>> Function(int page, String query) loadPage;
  @override
  State<AuditLogPage> createState() => _AuditLogPageState();
}

class _AuditLogPageState extends State<AuditLogPage> {
  final TextEditingController searchController = TextEditingController();
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> events = [];
  int page = 1, pages = 1, total = 0;
  String query = '';

  @override
  void initState() { super.initState(); refresh(resetPage: true); }
  @override
  void dispose() { searchController.dispose(); super.dispose(); }

  Future<void> refresh({bool resetPage = false}) async {
    if (resetPage) page = 1;
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadPage(page, query);
      final raw = data['items'];
      if (!mounted) return;
      setState(() {
        events = raw is List ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList() : <Map<String, dynamic>>[];
        total = int.tryParse(data['total']?.toString() ?? '') ?? events.length;
        page = int.tryParse(data['page']?.toString() ?? '') ?? page;
        pages = int.tryParse(data['pages']?.toString() ?? '') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(t('سجل التدقيق', 'Audit log')), actions: [
        IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : () => refresh(), icon: const Icon(Icons.refresh_rounded)),
      ]),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Text(t('سجل تدقيق للقراءة فقط، مع بحث وتقسيم إلى صفحات. لا تتوفر إجراءات حذف السجلات داخل التطبيق.',
                'Read-only audit history with search and pagination. Audit deletion is not available in the app.'),
              style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: TextField(
                controller: searchController,
                textInputAction: TextInputAction.search,
                onSubmitted: (value) { query = value.trim(); refresh(resetPage: true); },
                decoration: InputDecoration(labelText: t('بحث في الأحداث والمستخدمين', 'Search events and users'),
                  prefixIcon: const Icon(Icons.search_rounded), border: const OutlineInputBorder()),
              )),
              const SizedBox(width: 8),
              IconButton.filled(tooltip: t('بحث', 'Search'), onPressed: loading ? null : () {
                query = searchController.text.trim(); refresh(resetPage: true);
              }, icon: const Icon(Icons.search_rounded)),
            ]),
            const SizedBox(height: 8),
            Text(t('إجمالي السجلات: $total', 'Total records: $total'), style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 10),
            if (loading && events.isEmpty)
              const Padding(padding: EdgeInsets.all(28), child: Center(child: CircularProgressIndicator()))
            else if (error != null && events.isEmpty)
              Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(children: [
                Text(error!), const SizedBox(height: 8),
                FilledButton.icon(onPressed: loading ? null : () => refresh(), icon: const Icon(Icons.refresh_rounded), label: Text(t('إعادة المحاولة', 'Try again'))),
              ])))
            else if (events.isEmpty)
              Padding(padding: const EdgeInsets.all(24), child: Text(t('لا توجد سجلات مطابقة.', 'No matching audit records.')))
            else
              ...events.map((event) => Card(
                margin: const EdgeInsets.only(bottom: 10),
                child: ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.history_rounded)),
                  title: Text(event['action']?.toString().isNotEmpty == true ? event['action'].toString() : '—'),
                  subtitle: Padding(padding: const EdgeInsets.only(top: 5), child: Text(
                    "${event['user_name'] ?? event['username'] ?? '—'} · ${event['username'] ?? '—'}\n"
                    "${event['entity_type'] ?? '—'} #${event['entity_id'] ?? '—'} · ${event['created_at'] ?? ''}",
                  )),
                  isThreeLine: true,
                ),
              )),
            if (error != null && events.isNotEmpty) Padding(padding: const EdgeInsets.all(8), child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
            const SizedBox(height: 8),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              OutlinedButton.icon(onPressed: loading || page <= 1 ? null : () { setState(() => page--); refresh(); },
                icon: const Icon(Icons.chevron_left_rounded), label: Text(t('السابق', 'Previous'))),
              Text(t('صفحة $page من $pages', 'Page $page of $pages')),
              OutlinedButton.icon(onPressed: loading || page >= pages ? null : () { setState(() => page++); refresh(); },
                icon: const Icon(Icons.chevron_right_rounded), label: Text(t('التالي', 'Next'))),
            ]),
          ],
        ),
      ),
    );
  }
}
