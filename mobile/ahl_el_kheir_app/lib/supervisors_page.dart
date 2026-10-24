import 'package:flutter/material.dart';

class SupervisorsPage extends StatefulWidget {
  const SupervisorsPage({
    super.key,
    required this.english,
    required this.loadPage,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(String query, bool showArchived, int page) loadPage;

  @override
  State<SupervisorsPage> createState() => _SupervisorsPageState();
}

class _SupervisorsPageState extends State<SupervisorsPage> {
  final search = TextEditingController();
  bool loading = true;
  bool showArchived = false;
  String? error;
  List<Map<String, dynamic>> items = [];
  int total = 0, page = 1, pages = 1;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadPage(search.text.trim(), showArchived, page);
      final raw = data['items'];
      final next = raw is List
          ? raw.whereType<Map>().map((value) => Map<String, dynamic>.from(value)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        items = next;
        total = int.tryParse(data['total']?.toString() ?? '') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String statusLabel(String value) {
    final labels = widget.english
        ? const {'active': 'Active', 'suspended': 'Suspended', 'on_leave': 'On leave', 'returning': 'Returning', 'departed': 'Departed', 'archived': 'Archived'}
        : const {'active': 'نشط', 'suspended': 'موقوف', 'on_leave': 'في إجازة', 'returning': 'قيد العودة', 'departed': 'غادر العمل', 'archived': 'مؤرشف'};
    return labels[value] ?? value;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Supervisors' : 'المشرفون')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: TextField(
              controller: search,
              onSubmitted: (_) {
                setState(() => page = 1);
                load();
              },
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search_rounded),
                hintText: en ? 'Search name, username or phone' : 'بحث بالاسم أو اسم المستخدم أو الهاتف',
                suffixIcon: IconButton(
                  onPressed: () {
                    search.clear();
                    setState(() => page = 1);
                    load();
                  },
                  tooltip: en ? 'Clear search' : 'مسح البحث',
                  icon: const Icon(Icons.clear_rounded),
                ),
              ),
            ),
          ),
          SwitchListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 16),
            title: Text(en ? 'Include departed and archived' : 'إظهار من غادروا والمؤرشفين'),
            value: showArchived,
            onChanged: (value) {
              setState(() {
                showArchived = value;
                page = 1;
              });
              load();
            },
          ),
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(16, 0, 16, 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(en ? 'Supervisors: $total' : 'عدد المشرفين: $total'),
            ),
          ),
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : error != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(error!, textAlign: TextAlign.center),
                              const SizedBox(height: 12),
                              OutlinedButton.icon(
                                onPressed: load,
                                icon: const Icon(Icons.refresh_rounded),
                                label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: load,
                        child: items.isEmpty
                            ? ListView(
                                physics: const AlwaysScrollableScrollPhysics(),
                                children: [
                                  Padding(
                                    padding: const EdgeInsets.all(28),
                                    child: Text(en ? 'No supervisors found.' : 'لا يوجد مشرفون مطابقون.', textAlign: TextAlign.center),
                                  ),
                                ],
                              )
                            : ListView.separated(
                                physics: const AlwaysScrollableScrollPhysics(),
                                padding: const EdgeInsets.all(12),
                                itemCount: items.length + (pages > 1 ? 1 : 0),
                                separatorBuilder: (_, __) => const SizedBox(height: 6),
                                itemBuilder: (context, index) {
                                  if (index == items.length) {
                                    return Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        IconButton(onPressed: page > 1 ? () { setState(() => page--); load(); } : null, icon: const Icon(Icons.chevron_left_rounded)),
                                        Text(en ? 'Page $page of $pages' : 'صفحة $page من $pages'),
                                        IconButton(onPressed: page < pages ? () { setState(() => page++); load(); } : null, icon: const Icon(Icons.chevron_right_rounded)),
                                      ],
                                    );
                                  }
                                  final item = items[index];
                                  final letters = item['letters']?.toString() ?? '';
                                  final phone = item['phone']?.toString() ?? '';
                                  final email = item['email']?.toString() ?? '';
                                  final status = item['supervisor_status']?.toString() ?? '';
                                  final subtitle = [
                                    if (phone.isNotEmpty) phone,
                                    if (email.isNotEmpty) email,
                                    if (letters.isNotEmpty) '${en ? 'Letters' : 'الحروف'}: $letters',
                                  ].join(' • ');
                                  return Card(
                                    child: ListTile(
                                      leading: const CircleAvatar(child: Icon(Icons.supervisor_account_rounded)),
                                      title: Text(item['full_name']?.toString() ?? ''),
                                      subtitle: Text(subtitle.isEmpty ? '@${item['username'] ?? ''}' : '@${item['username'] ?? ''}\n$subtitle'),
                                      isThreeLine: subtitle.isNotEmpty,
                                      trailing: Text(statusLabel(status), textAlign: TextAlign.end),
                                    ),
                                  );
                                },
                              ),
                      ),
          ),
        ],
      ),
    );
  }
}
