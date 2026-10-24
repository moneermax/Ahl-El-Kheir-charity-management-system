import 'package:flutter/material.dart';

class FamiliesPage extends StatefulWidget {
  const FamiliesPage({super.key, required this.english, required this.loadFamilies});
  final bool english;
  final Future<Map<String, dynamic>> Function(String query, String status, int page) loadFamilies;

  @override
  State<FamiliesPage> createState() => _FamiliesPageState();
}

class _FamiliesPageState extends State<FamiliesPage> {
  final search = TextEditingController();
  bool loading = true;
  String? error;
  String status = '';
  int page = 1, pages = 1, total = 0;
  List<Map<String, dynamic>> items = [];

  @override
  void initState() { super.initState(); load(); }
  @override
  void dispose() { search.dispose(); super.dispose(); }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadFamilies(search.text.trim(), status, page);
      if (!mounted) return;
      final raw = data['items'];
      setState(() {
        items = raw is List ? raw.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList() : <Map<String, dynamic>>[];
        total = int.tryParse(data['total']?.toString() ?? '0') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '1') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String statusLabel(String value) {
    final labels = widget.english
      ? const {'active':'Active','pending':'Pending','paused':'Paused','completed':'Completed','archived':'Archived','inactive':'Inactive','closed':'Closed'}
      : const {'active':'نشطة','pending':'قيد المراجعة','paused':'موقوفة','completed':'مكتملة','archived':'مؤرشفة','inactive':'غير نشطة','closed':'مغلقة'};
    return labels[value] ?? value;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Families' : 'الأسر')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Row(children: [
            Expanded(child: TextField(
              controller: search,
              onSubmitted: (_) { setState(() => page = 1); load(); },
              decoration: InputDecoration(
                hintText: en ? 'Search family, mother or phone' : 'بحث بكود الأسرة أو الأم أو الهاتف',
                prefixIcon: const Icon(Icons.search),
              ),
            )),
            IconButton(onPressed: () { setState(() => page = 1); load(); }, icon: const Icon(Icons.search)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: DropdownButtonFormField<String>(
            initialValue: status,
            decoration: InputDecoration(labelText: en ? 'Status' : 'الحالة'),
            items: [
              DropdownMenuItem(value: '', child: Text(en ? 'All' : 'الكل')),
              DropdownMenuItem(value: 'active', child: Text(en ? 'Active' : 'نشطة')),
              DropdownMenuItem(value: 'pending', child: Text(en ? 'Pending' : 'قيد المراجعة')),
              DropdownMenuItem(value: 'paused', child: Text(en ? 'Paused' : 'موقوفة')),
              DropdownMenuItem(value: 'completed', child: Text(en ? 'Completed' : 'مكتملة')),
              DropdownMenuItem(value: 'archived', child: Text(en ? 'Archived' : 'مؤرشفة')),
              DropdownMenuItem(value: 'inactive', child: Text(en ? 'Inactive' : 'غير نشطة')),
              DropdownMenuItem(value: 'closed', child: Text(en ? 'Closed' : 'مغلقة')),
            ],
            onChanged: (value) {
              if (value != null) { setState(() { status = value; page = 1; }); load(); }
            },
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(12),
          child: Align(alignment: AlignmentDirectional.centerStart, child: Text((en ? 'Families: ' : 'عدد الأسر: ') + total.toString())),
        ),
        Expanded(
          child: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
              ? Center(child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Text(error!, textAlign: TextAlign.center),
                    OutlinedButton(onPressed: load, child: Text(en ? 'Try again' : 'إعادة المحاولة')),
                  ]),
                ))
              : RefreshIndicator(
                  onRefresh: load,
                  child: items.isEmpty
                    ? ListView(children: [Padding(padding: const EdgeInsets.all(24), child: Text(en ? 'No families found.' : 'لا توجد أسر', textAlign: TextAlign.center))])
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(12),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 4),
                        itemBuilder: (context, index) {
                          final family = items[index];
                          final phone = family['mother_phone']?.toString() ?? '';
                          final monthly = double.tryParse(family['monthly_commitment']?.toString() ?? '0') ?? 0;
                          return Card(child: ListTile(
                            leading: const CircleAvatar(child: Icon(Icons.family_restroom_rounded)),
                            title: Text(family['mother_name']?.toString() ?? ''),
                            subtitle: Text(
                              (family['family_code']?.toString() ?? '') + '\n' +
                              (phone.isNotEmpty ? phone : (en ? 'No phone' : 'لا يوجد هاتف')) + '\n' +
                              (en ? 'Children: ' : 'الأطفال: ') + (family['children_count']?.toString() ?? '0') + ' · ' +
                              (en ? 'Active sponsorships: ' : 'الكفالات النشطة: ') + (family['active_sponsorships']?.toString() ?? '0') + '\n' +
                              (en ? 'Monthly commitment: ' : 'الالتزام الشهري: ') + monthly.toStringAsFixed(2),
                            ),
                            isThreeLine: true,
                            trailing: Chip(label: Text(statusLabel(family['status']?.toString() ?? ''))),
                          ));
                        },
                      ),
                ),
        ),
        if (!loading && error == null && pages > 1)
          SafeArea(top: false, child: Padding(
            padding: const EdgeInsets.all(10),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              OutlinedButton(onPressed: page > 1 ? () { setState(() => page--); load(); } : null, child: Text(en ? 'Previous' : 'السابق')),
              Text(en ? 'Page ' + page.toString() + ' of ' + pages.toString() : 'صفحة ' + page.toString() + ' من ' + pages.toString()),
              OutlinedButton(onPressed: page < pages ? () { setState(() => page++); load(); } : null, child: Text(en ? 'Next' : 'التالي')),
            ]),
          )),
      ]),
    );
  }
}
