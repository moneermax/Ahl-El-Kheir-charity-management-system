import 'package:flutter/material.dart';

class AdminDatabasePage extends StatefulWidget {
  const AdminDatabasePage({super.key, required this.english, required this.loadPage});
  final bool english;
  final Future<Map<String, dynamic>> Function(String? table) loadPage;

  @override
  State<AdminDatabasePage> createState() => _AdminDatabasePageState();
}

class _AdminDatabasePageState extends State<AdminDatabasePage> {
  bool loading = true;
  String? error;
  String? selectedTable;
  List<Map<String, dynamic>> tables = [];
  List<Map<String, dynamic>> columns = [];
  List<Map<String, dynamic>> rows = [];

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh({String? table}) async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadPage(table ?? selectedTable);
      if (!mounted) return;
      setState(() {
        tables = data['tables'] is List
            ? (data['tables'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
            : [];
        selectedTable = data['selected_table']?.toString();
        columns = data['columns'] is List
            ? (data['columns'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
            : [];
        rows = data['rows'] is List
            ? (data['rows'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
            : [];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF6F4EE),
    appBar: AppBar(
      title: Text(t('متصفح قاعدة البيانات', 'Database browser')),
      actions: [IconButton(
        tooltip: t('تحديث', 'Refresh'),
        onPressed: loading ? null : () => refresh(),
        icon: const Icon(Icons.refresh_rounded),
      )],
    ),
    body: RefreshIndicator(
      onRefresh: () => refresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(t('متصفح قراءة فقط', 'Read-only browser'),
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text(t(
                'يعرض الجداول وحقولها وعينة تصل إلى 50 سجلاً. لا توجد عمليات تعديل أو حذف أو تغيير للمخطط.',
                'Shows tables, columns, and a sample of up to 50 rows. No data changes or schema operations are available.',
              )),
              const SizedBox(height: 12),
              Text(t('الجداول المتاحة: ', 'Available tables: ') + tables.length.toString(),
                style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                key: ValueKey<String?>(selectedTable),
                initialValue: selectedTable,
                isExpanded: true,
                decoration: InputDecoration(
                  border: const OutlineInputBorder(),
                  labelText: t('اختر جدولاً', 'Select a table'),
                ),
                items: tables.map((item) {
                  final name = item['name']?.toString() ?? '';
                  final count = item['count']?.toString() ?? '0';
                  return DropdownMenuItem<String>(value: name, child: Text(name + ' (' + count + ')', overflow: TextOverflow.ellipsis));
                }).toList(),
                onChanged: loading ? null : (value) { if (value != null) refresh(table: value); },
              ),
              if (loading) const Padding(padding: EdgeInsets.only(top: 12), child: LinearProgressIndicator()),
              if (error != null) ...[
                const SizedBox(height: 12),
                Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
                Align(alignment: AlignmentDirectional.centerStart,
                  child: TextButton(onPressed: () => refresh(), child: Text(t('إعادة المحاولة', 'Retry')))),
              ],
            ]),
          )),
          if (selectedTable != null) ...[
            const SizedBox(height: 12),
            _DatabaseSection(title: t('هيكل الجدول', 'Table schema') + ': ' + selectedTable!, child: Column(
              children: columns.map((column) => ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text(column['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text([
                  column['type']?.toString() ?? '',
                  t('يسمح بقيمة فارغة', 'Nullable') + ': ' + (column['nullable']?.toString() ?? ''),
                  if ((column['key']?.toString() ?? '').isNotEmpty) 'Key: ' + column['key'].toString(),
                  if ((column['extra']?.toString() ?? '').isNotEmpty) column['extra'].toString(),
                ].join(' · ')),
              )).toList(),
            )),
            const SizedBox(height: 12),
            _DatabaseSection(title: t('عينة من السجلات', 'Sample rows') + ' (' + rows.length.toString() + '/50)', child: rows.isEmpty
              ? Text(t('لا توجد سجلات في هذا الجدول.', 'This table has no rows.'))
              : Column(children: [
                for (var index = 0; index < rows.length; index++) ...[
                  if (index > 0) const Divider(height: 18),
                  Text(t('سجل', 'Row') + ' ' + (index + 1).toString(),
                    style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF1E6B4E))),
                  const SizedBox(height: 6),
                  for (final entry in rows[index].entries)
                    Padding(padding: const EdgeInsets.symmetric(vertical: 3), child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(width: 112, child: Text(entry.key, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
                        const SizedBox(width: 8),
                        Expanded(child: SelectableText(entry.value?.toString() ?? 'NULL', style: const TextStyle(fontSize: 11))),
                      ],
                    )),
                ],
              ]),
            ),
          ],
        ],
      ),
    ),
  );
}

class _DatabaseSection extends StatelessWidget {
  const _DatabaseSection({required this.title, required this.child});
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(child: Padding(
    padding: const EdgeInsets.all(16),
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
      const SizedBox(height: 10),
      child,
    ]),
  ));
}
