import 'package:flutter/material.dart';

class SponsorRequestsPage extends StatefulWidget {
  const SponsorRequestsPage({super.key, required this.english, required this.loadRequests});
  final bool english;
  final Future<Map<String, dynamic>> Function(String query, String status, int page) loadRequests;
  @override
  State<SponsorRequestsPage> createState() => _SponsorRequestsPageState();
}

class _SponsorRequestsPageState extends State<SponsorRequestsPage> {
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
      final data = await widget.loadRequests(search.text.trim(), status, page);
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
      ? const {'new':'New','contacted':'Contacted','converted':'Converted','lost':'Lost'}
      : const {'new':'جديد','contacted':'تم التواصل','converted':'تم التحويل','lost':'مفقود'};
    return labels[value] ?? value;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Sponsor requests' : 'طلبات الرعاية')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Row(children: [
            Expanded(child: TextField(
              controller: search,
              onSubmitted: (_) { setState(() => page = 1); load(); },
              decoration: InputDecoration(hintText: en ? 'Search name, phone or source' : 'بحث بالاسم أو الهاتف أو المصدر', prefixIcon: const Icon(Icons.search)),
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
              DropdownMenuItem(value: 'new', child: Text(en ? 'New' : 'جديد')),
              DropdownMenuItem(value: 'contacted', child: Text(en ? 'Contacted' : 'تم التواصل')),
              DropdownMenuItem(value: 'converted', child: Text(en ? 'Converted' : 'تم التحويل')),
              DropdownMenuItem(value: 'lost', child: Text(en ? 'Lost' : 'مفقود')),
            ],
            onChanged: (value) { if (value != null) { setState(() { status = value; page = 1; }); load(); } },
          ),
        ),
        Padding(padding: const EdgeInsets.all(12), child: Align(alignment: AlignmentDirectional.centerStart, child: Text((en ? 'Requests: ' : 'عدد الطلبات: ') + total.toString()))),
        Expanded(
          child: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(error!, textAlign: TextAlign.center),
                  OutlinedButton(onPressed: load, child: Text(en ? 'Try again' : 'إعادة المحاولة')),
                ])))
              : RefreshIndicator(
                  onRefresh: load,
                  child: items.isEmpty
                    ? ListView(children: [Padding(padding: const EdgeInsets.all(24), child: Text(en ? 'No requests found.' : 'لا توجد طلبات', textAlign: TextAlign.center))])
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(12),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 4),
                        itemBuilder: (context, index) {
                          final request = items[index];
                          final gender = request['gender']?.toString() ?? '';
                          final genderLabel = en ? (gender == 'male' ? 'Male' : gender == 'female' ? 'Female' : gender) : (gender == 'male' ? 'ذكر' : gender == 'female' ? 'أنثى' : gender);
                          final phone = request['phone']?.toString() ?? '';
                          final source = request['source']?.toString() ?? '';
                          final created = request['created_at']?.toString() ?? '';
                          final notes = request['notes']?.toString() ?? '';
                          return Card(child: ListTile(
                            leading: const CircleAvatar(child: Icon(Icons.campaign_outlined)),
                            title: Text(request['sponsor_name']?.toString() ?? ''),
                            subtitle: Text([
                              if (phone.isNotEmpty) phone,
                              if (genderLabel.isNotEmpty) genderLabel,
                              if (source.isNotEmpty) (en ? 'Source: ' : 'المصدر: ') + source,
                              if (created.isNotEmpty) created,
                              if (notes.isNotEmpty) notes,
                            ].join('\n')),
                            isThreeLine: true,
                            trailing: Chip(label: Text(statusLabel(request['status']?.toString() ?? ''))),
                          ));
                        },
                      ),
                ),
        ),
        if (!loading && error == null && pages > 1)
          SafeArea(top: false, child: Padding(padding: const EdgeInsets.all(10), child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              OutlinedButton(onPressed: page > 1 ? () { setState(() => page--); load(); } : null, child: Text(en ? 'Previous' : 'السابق')),
              Text(en ? 'Page $page of $pages' : 'صفحة $page من $pages'),
              OutlinedButton(onPressed: page < pages ? () { setState(() => page++); load(); } : null, child: Text(en ? 'Next' : 'التالي')),
            ],
          ))),
      ]),
    );
  }
}
