import 'package:flutter/material.dart';

class AvailableFamiliesPage extends StatefulWidget {
  const AvailableFamiliesPage({
    super.key,
    required this.english,
    required this.loadFamilies,
    required this.openFamily,
  });
  final bool english;
  final Future<Map<String, dynamic>> Function() loadFamilies;
  final void Function(int familyId) openFamily;
  @override
  State<AvailableFamiliesPage> createState() => _AvailableFamiliesPageState();
}

class _AvailableFamiliesPageState extends State<AvailableFamiliesPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadFamilies();
      final raw = result['items'];
      if (!mounted) return;
      setState(() {
        items = raw is List ? raw.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : <Map<String, dynamic>>[];
        loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { error = e.toString(); loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(t('الأسر المتاحة للكفالة', 'Families available for sponsorship'))),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              t('الأسر النشطة أو قيد الإجراء التي لا توجد لها كفالة نشطة حالياً.',
                 'Active or pending families without an active sponsorship.'),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 12),
            Card(child: ListTile(
              leading: const Icon(Icons.home_work_outlined),
              title: Text(t('قائمة الأسر المتاحة حالياً', 'Currently available families')),
              trailing: Text('${items.length}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            )),
            if (loading) const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
            if (error != null) Card(child: ListTile(
              title: Text(t('تعذر تحميل الأسر', 'Could not load families')),
              subtitle: Text(error!),
              trailing: IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
            )),
            if (!loading && error == null && items.isEmpty)
              Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(t('لا توجد أسر متاحة حالياً.', 'No families are currently available.')))),
            for (final family in items)
              Card(child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.family_restroom_outlined)),
                title: Text((family['mother_name'] ?? '').toString(), style: const TextStyle(fontWeight: FontWeight.w800)),
                subtitle: Text(
                  '${family['family_code'] ?? ''}${(family['city'] ?? '').toString().isNotEmpty ? ' • ${family['city']}' : ''}\\n'
                  '${t('الأطفال','Children')}: ${family['children_count'] ?? 0} • '
                  '${t('الاحتياج الشهري','Monthly need')}: ${(double.tryParse('${family['monthly_need_amount'] ?? 0}') ?? 0).toStringAsFixed(0)}',
                ),
                isThreeLine: true,
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  final id = int.tryParse('${family['id'] ?? ''}');
                  if (id != null && id > 0) widget.openFamily(id);
                },
              )),
          ],
        ),
      ),
    );
  }
}
