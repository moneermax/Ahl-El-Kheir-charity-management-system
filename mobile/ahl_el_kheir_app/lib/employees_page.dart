import 'package:flutter/material.dart';

class EmployeesPage extends StatefulWidget {
  const EmployeesPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.openDetail,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(String query, int page) loadPage;
  final void Function(Map<String, dynamic> employee) openDetail;

  @override
  State<EmployeesPage> createState() => _EmployeesPageState();
}

class _EmployeesPageState extends State<EmployeesPage> {
  final search = TextEditingController();
  bool loading = true;
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
      final data = await widget.loadPage(search.text.trim(), page);
      final raw = data['items'];
      final next = raw is List
          ? raw.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
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

  String stateLabel(Map<String, dynamic> item) {
    final en = widget.english;
    final category = item['employment_state_category']?.toString() ?? '';
    if (category == 'working') return en ? 'Working' : 'على رأس العمل';
    final value = en ? item['employment_state_code'] : item['employment_state_name'];
    final label = value?.toString() ?? '';
    return label.isEmpty ? (en ? 'Not specified' : 'غير محدد') : label;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Employees' : 'الموظفون')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: search,
              onSubmitted: (_) {
                setState(() => page = 1);
                load();
              },
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search_rounded),
                hintText: en
                    ? 'Search name, employee code or position'
                    : 'بحث بالاسم أو الرقم الوظيفي أو المسمى',
                suffixIcon: IconButton(
                  onPressed: () {
                    search.clear();
                    setState(() => page = 1);
                    load();
                  },
                  icon: const Icon(Icons.clear_rounded),
                  tooltip: en ? 'Clear search' : 'مسح البحث',
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(16, 0, 16, 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(en ? 'Employees: $total' : 'عدد الموظفين: $total'),
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
                    : items.isEmpty
                        ? Center(
                            child: Text(en ? 'No employees found.' : 'لا يوجد موظفون مطابقون.'),
                          )
                        : RefreshIndicator(
                            onRefresh: load,
                            child: ListView.separated(
                              physics: const AlwaysScrollableScrollPhysics(),
                              padding: const EdgeInsets.all(12),
                              itemCount: items.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (context, index) {
                                final item = items[index];
                                final department = (en
                                        ? item['department_name_en']
                                        : item['department_name_ar'])
                                    ?.toString() ?? '';
                                final code = item['employee_code']?.toString() ?? '';
                                final position = item['position']?.toString() ?? '';
                                final subtitle = [
                                  if (code.isNotEmpty) code,
                                  if (position.isNotEmpty) position,
                                  if (department.isNotEmpty) department,
                                ].join(' • ');
                                return Card(
                                  child: ListTile(
                                    leading: const CircleAvatar(
                                      child: Icon(Icons.badge_outlined),
                                    ),
                                    title: Text(item['full_name']?.toString() ?? ''),
                                    subtitle: Text(subtitle),
                                    isThreeLine: subtitle.contains(' • '),
                                    onTap: () => widget.openDetail(item),
                                    trailing: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      crossAxisAlignment: CrossAxisAlignment.end,
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Text(
                                          stateLabel(item),
                                          style: Theme.of(context).textTheme.labelSmall,
                                        ),
                                        if ((item['hire_date']?.toString() ?? '').isNotEmpty)
                                          Text(
                                            item['hire_date'].toString(),
                                            style: Theme.of(context).textTheme.labelSmall,
                                          ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
          if (!loading && error == null && pages > 1)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(en ? 'Page $page of $pages' : 'الصفحة $page من $pages'),
                  Row(
                    children: [
                      IconButton(
                        onPressed: page > 1
                            ? () {
                                setState(() => page--);
                                load();
                              }
                            : null,
                        icon: const Icon(Icons.chevron_left_rounded),
                        tooltip: en ? 'Previous page' : 'الصفحة السابقة',
                      ),
                      IconButton(
                        onPressed: page < pages
                            ? () {
                                setState(() => page++);
                                load();
                              }
                            : null,
                        icon: const Icon(Icons.chevron_right_rounded),
                        tooltip: en ? 'Next page' : 'الصفحة التالية',
                      ),
                    ],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
