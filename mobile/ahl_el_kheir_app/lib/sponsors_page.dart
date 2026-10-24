import 'package:flutter/material.dart';

class SponsorsPage extends StatefulWidget {
  const SponsorsPage({super.key, required this.english, required this.loadSponsors});
  final bool english;
  final Future<Map<String, dynamic>> Function(String query, String status, String linkStatus, int page) loadSponsors;

  @override
  State<SponsorsPage> createState() => _SponsorsPageState();
}

class _SponsorsPageState extends State<SponsorsPage> {
  final search = TextEditingController();
  bool loading = true;
  String? error;
  String status = '', link = '';
  int page = 1, pages = 1, total = 0;
  List<Map<String, dynamic>> items = [];

  @override
  void initState() { super.initState(); load(); }
  @override
  void dispose() { search.dispose(); super.dispose(); }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadSponsors(search.text.trim(), status, link, page);
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
      ? const {'active':'Active','inactive':'Inactive','suspended':'Suspended','cancelled':'Cancelled'}
      : const {'active':'نشط','inactive':'غير نشط','suspended':'موقوف','cancelled':'ملغي'};
    return labels[value] ?? value;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Sponsors' : 'الكفلاء')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Row(children: [
            Expanded(child: TextField(
              controller: search,
              onSubmitted: (_) { setState(() => page = 1); load(); },
              decoration: InputDecoration(
                hintText: en ? 'Search name, code or phone' : 'بحث بالاسم أو الكود أو الهاتف',
                prefixIcon: const Icon(Icons.search),
              ),
            )),
            IconButton(onPressed: () { setState(() => page = 1); load(); }, icon: const Icon(Icons.search)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(children: [
            Expanded(child: DropdownButtonFormField<String>(
              initialValue: status,
              decoration: InputDecoration(labelText: en ? 'Status' : 'الحالة'),
              items: [
                DropdownMenuItem(value: '', child: Text(en ? 'All' : 'الكل')),
                DropdownMenuItem(value: 'active', child: Text(en ? 'Active' : 'نشط')),
                DropdownMenuItem(value: 'inactive', child: Text(en ? 'Inactive' : 'غير نشط')),
                DropdownMenuItem(value: 'suspended', child: Text(en ? 'Suspended' : 'موقوف')),
                DropdownMenuItem(value: 'cancelled', child: Text(en ? 'Cancelled' : 'ملغي')),
              ],
              onChanged: (value) {
                if (value != null) { setState(() { status = value; page = 1; }); load(); }
              },
            )),
            const SizedBox(width: 8),
            Expanded(child: DropdownButtonFormField<String>(
              initialValue: link,
              decoration: InputDecoration(labelText: en ? 'Sponsorship' : 'الكفالة'),
              items: [
                DropdownMenuItem(value: '', child: Text(en ? 'All' : 'الكل')),
                DropdownMenuItem(value: 'linked', child: Text(en ? 'Linked' : 'مرتبط')),
                DropdownMenuItem(value: 'unlinked', child: Text(en ? 'Unlinked' : 'غير مرتبط')),
              ],
              onChanged: (value) {
                if (value != null) { setState(() { link = value; page = 1; }); load(); }
              },
            )),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.all(12),
          child: Align(alignment: AlignmentDirectional.centerStart, child: Text((en ? 'Sponsors: ' : 'عدد الكفلاء: ') + total.toString())),
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
                    ? ListView(children: [Padding(padding: const EdgeInsets.all(24), child: Text(en ? 'No sponsors found.' : 'لا يوجد كفلاء', textAlign: TextAlign.center))])
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(12),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 4),
                        itemBuilder: (context, index) {
                          final sponsor = items[index];
                          final count = int.tryParse(sponsor['active_sponsorship_count']?.toString() ?? '0') ?? 0;
                          final phone = sponsor['phone']?.toString() ?? '';
                          return Card(child: ListTile(
                            leading: const CircleAvatar(child: Icon(Icons.volunteer_activism_rounded)),
                            title: Text(sponsor['full_name']?.toString() ?? ''),
                            subtitle: Text(
                              (sponsor['sponsor_code']?.toString() ?? '') + '\n' +
                              (phone.isNotEmpty ? phone : (en ? 'No phone' : 'لا يوجد هاتف')) + '\n' +
                              (en ? 'Active sponsorships: ' : 'الكفالات النشطة: ') + count.toString(),
                            ),
                            isThreeLine: true,
                            trailing: Chip(label: Text(statusLabel(sponsor['status']?.toString() ?? ''))),
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
