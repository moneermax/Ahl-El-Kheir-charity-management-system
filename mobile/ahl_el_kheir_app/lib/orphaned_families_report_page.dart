import 'package:flutter/material.dart';

class OrphanedFamiliesReportPage extends StatefulWidget {
  const OrphanedFamiliesReportPage({super.key, required this.english, required this.loadReport, required this.openFamily});
  final bool english;
  final Future<Map<String, dynamic>> Function(String city, String status) loadReport;
  final void Function(int familyId) openFamily;
  @override
  State<OrphanedFamiliesReportPage> createState() => _OrphanedFamiliesReportPageState();
}

class _OrphanedFamiliesReportPageState extends State<OrphanedFamiliesReportPage> {
  bool loading = true;
  String? error;
  String city = '';
  String status = 'active';
  List<String> cities = [];
  List<Map<String, dynamic>> items = [];
  int total = 0;
  int activeChildren = 0;
  String t(String ar, String en) => widget.english ? en : ar;
  @override
  void initState() { super.initState(); refresh(); }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadReport(city, status);
      final rawItems = data['items'];
      final rawCities = data['cities'];
      if (!mounted) return;
      setState(() {
        items = rawItems is List ? rawItems.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList() : [];
        cities = rawCities is List ? rawCities.map((v) => v.toString()).toList() : [];
        total = int.tryParse(data['total']?.toString() ?? '') ?? items.length;
        activeChildren = int.tryParse(data['active_children_waiting']?.toString() ?? '') ?? 0;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String statusLabel(String value) => widget.english
      ? (value == 'active' ? 'Active' : value == 'pending' ? 'Pending' : 'All')
      : (value == 'active' ? 'نشطة' : value == 'pending' ? 'معلقة' : 'الكل');

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(t('تقرير الأسر غير المكفولة', 'Uncovered families report')), actions: [
        IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
      ]),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Text(t('الأسر ذات الأطفال النشطين دون أي كفالة نشطة مرتبطة. يعتمد التقرير على علاقة family_children الفعلية.',
                'Families with active children and no active linked sponsorship. The report uses the actual family_children relationship.'),
              style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: DropdownButtonFormField<String>(
                initialValue: cities.contains(city) || city.isEmpty ? city : '',
                decoration: InputDecoration(labelText: t('المدينة', 'City'), border: const OutlineInputBorder()),
                items: [
                  DropdownMenuItem(value: '', child: Text(t('كل المدن', 'All cities'))),
                  ...cities.map((value) => DropdownMenuItem(value: value, child: Text(value))),
                ],
                onChanged: loading ? null : (value) { setState(() => city = value ?? ''); refresh(); },
              )),
              const SizedBox(width: 10),
              Expanded(child: DropdownButtonFormField<String>(
                initialValue: status,
                decoration: InputDecoration(labelText: t('الحالة', 'Status'), border: const OutlineInputBorder()),
                items: [
                  DropdownMenuItem(value: 'active', child: Text(statusLabel('active'))),
                  DropdownMenuItem(value: 'pending', child: Text(statusLabel('pending'))),
                  DropdownMenuItem(value: 'all', child: Text(statusLabel('all'))),
                ],
                onChanged: loading ? null : (value) { setState(() => status = value ?? 'active'); refresh(); },
              )),
            ]),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: _Summary(title: t('الأسر غير المكفولة', 'Uncovered families'), value: total.toString(), icon: Icons.family_restroom_rounded)),
              const SizedBox(width: 10),
              Expanded(child: _Summary(title: t('الأطفال النشطون', 'Active children'), value: activeChildren.toString(), icon: Icons.child_care_rounded)),
            ]),
            if (error != null) Card(color: Theme.of(context).colorScheme.errorContainer, child: Padding(padding: const EdgeInsets.all(12), child: Text(error!))),
            if (loading && items.isEmpty)
              const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator()))
            else if (items.isEmpty)
              Padding(padding: const EdgeInsets.all(24), child: Text(t('لا توجد أسر مطابقة للفلاتر.', 'No families match these filters.')))
            else
              ...items.map((item) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.family_restroom_rounded)),
                  title: Text(item['mother_name']?.toString().isNotEmpty == true ? item['mother_name'].toString() : '—'),
                  subtitle: Text(
                    (item['family_code']?.toString() ?? '—') + ' · ' + (item['city']?.toString() ?? '—') + '\n' +
                    t('الأطفال النشطون', 'Active children') + ': ' + (item['active_children']?.toString() ?? '0') + ' / ' + (item['actual_children_count']?.toString() ?? '0') + '\n' +
                    t('الاحتياج الشهري', 'Monthly need') + ': ' + (item['monthly_need_amount']?.toString() ?? '0'),
                  ),
                  isThreeLine: true,
                  trailing: IconButton(
                    tooltip: t('عرض الأسرة', 'View family'),
                    onPressed: () {
                      final id = int.tryParse(item['id']?.toString() ?? '');
                      if (id != null && id > 0) widget.openFamily(id);
                    },
                    icon: const Icon(Icons.open_in_new_rounded),
                  ),
                ),
              )),
          ],
        ),
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.title, required this.value, required this.icon});
  final String title;
  final String value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Card(child: Padding(
    padding: const EdgeInsets.all(12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: Theme.of(context).colorScheme.primary),
      const SizedBox(height: 8),
      Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
      Text(title, style: Theme.of(context).textTheme.bodySmall),
    ]),
  ));
}
