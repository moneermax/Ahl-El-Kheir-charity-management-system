import 'package:flutter/material.dart';

class AuditLogPage extends StatefulWidget {
  const AuditLogPage({
    super.key,
    required this.english,
    required this.loadOverview,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadOverview;

  @override
  State<AuditLogPage> createState() => _AuditLogPageState();
}

class _AuditLogPageState extends State<AuditLogPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> events = [];

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadOverview();
      final raw = data['recent_audit'];
      if (!mounted) return;
      setState(() {
        events = raw is List
            ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
            : <Map<String, dynamic>>[];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(
        title: Text(en ? 'Recent audit activity' : 'أحدث أنشطة التدقيق'),
        actions: [
          IconButton(
            tooltip: en ? 'Refresh' : 'تحديث',
            onPressed: loading ? null : refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              en
                  ? 'Latest six recorded events. The complete audit register is not yet available natively.'
                  : 'آخر ستة أحداث مسجلة. سجل التدقيق الكامل لم يُنفذ داخل التطبيق بعد.',
              style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
            ),
            const SizedBox(height: 14),
            if (loading && events.isEmpty)
              const Padding(
                padding: EdgeInsets.all(28),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (error != null && events.isEmpty)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Text(error!),
                      const SizedBox(height: 8),
                      FilledButton.icon(
                        onPressed: loading ? null : refresh,
                        icon: const Icon(Icons.refresh_rounded),
                        label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                      ),
                    ],
                  ),
                ),
              )
            else if (events.isEmpty)
              Padding(
                padding: const EdgeInsets.all(24),
                child: Text(en ? 'No recent audit events.' : 'لا توجد أحداث تدقيق حديثة.'),
              )
            else
              ...events.map((event) => Card(
                    margin: const EdgeInsets.only(bottom: 10),
                    child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.history_rounded)),
                      title: Text(event['action']?.toString().isNotEmpty == true
                          ? event['action'].toString()
                          : '—'),
                      subtitle: Padding(
                        padding: const EdgeInsets.only(top: 5),
                        child: Text(
                          "${event['username'] ?? '—'} · ${event['entity_type'] ?? '—'} #${event['entity_id'] ?? '—'}\n${event['created_at'] ?? ''}",
                        ),
                      ),
                      isThreeLine: true,
                    ),
                  )),
            if (error != null && events.isNotEmpty)
              Padding(
                padding: const EdgeInsets.all(8),
                child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
          ],
        ),
      ),
    );
  }
}
