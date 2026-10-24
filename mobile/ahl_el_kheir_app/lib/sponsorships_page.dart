import 'package:flutter/material.dart';

class SponsorshipsPage extends StatefulWidget {
  const SponsorshipsPage({super.key, required this.english, required this.loadSponsorships});
  final bool english;
  final Future<Map<String, dynamic>> Function(String query, String type, String status, int page) loadSponsorships;
  @override State<SponsorshipsPage> createState() => _SponsorshipsPageState();
}
class _SponsorshipsPageState extends State<SponsorshipsPage> {
  final search = TextEditingController();
  bool loading = true;
  String? error;
  String type = 'all', status = '';
  int page = 1, pages = 1, total = 0, activeCount = 0;
  double activeMonthly = 0;
  List<Map<String, dynamic>> items = [];
  @override void initState() { super.initState(); load(); }
  @override void dispose() { search.dispose(); super.dispose(); }
  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadSponsorships(search.text.trim(), type, status, page);
      if (!mounted) return;
      final raw = data['items'];
      setState(() {
        items = raw is List ? raw.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : <Map<String, dynamic>>[];
        total = int.tryParse(data['total']?.toString() ?? '0') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '1') ?? 1;
        activeCount = int.tryParse(data['active_count']?.toString() ?? '0') ?? 0;
        activeMonthly = double.tryParse(data['active_monthly_total']?.toString() ?? '0') ?? 0;
      });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  String statusLabel(String s) {
    final m = widget.english ? const {'active':'Active','paused':'Paused','completed':'Completed','cancelled':'Cancelled'} : const {'active':'نشطة','paused':'موقوفة','completed':'مكتملة','cancelled':'ملغاة'};
    return m[s] ?? s;
  }
  String typeLabel(String s) {
    final m = widget.english ? const {'all':'All fields','sponsor':'Sponsor','orphan':'Child','family':'Family','code':'Codes'} : const {'all':'كل الحقول','sponsor':'الكفيل','orphan':'الطفل','family':'الأسرة','code':'الأكواد'};
    return m[s] ?? s;
  }
  @override Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(appBar: AppBar(title: Text(en ? 'Sponsorships' : 'الكفالات')), body: Column(children: [
      Padding(padding: const EdgeInsets.fromLTRB(12,12,12,4), child: Row(children: [
        Expanded(child: TextField(controller: search, onSubmitted: (_) { setState(() => page = 1); load(); }, decoration: InputDecoration(hintText: en ? 'Search sponsor, child, family or code' : 'بحث بالكفيل أو الطفل أو الأسرة أو الكود', prefixIcon: const Icon(Icons.search)))),
        IconButton(onPressed: () { setState(() => page = 1); load(); }, icon: const Icon(Icons.search)),
      ])),
      Padding(padding: const EdgeInsets.symmetric(horizontal:12), child: Row(children: [
        Expanded(child: DropdownButtonFormField<String>(initialValue:type, decoration:InputDecoration(labelText:en?'Search field':'مجال البحث'), items:['all','sponsor','orphan','family','code'].map((v)=>DropdownMenuItem(value:v,child:Text(typeLabel(v)))).toList(), onChanged:(v){if(v!=null){setState((){type=v;page=1;});load();}})),
        const SizedBox(width:8),
        Expanded(child: DropdownButtonFormField<String>(initialValue:status, decoration:InputDecoration(labelText:en?'Status':'الحالة'), items:[DropdownMenuItem(value:'',child:Text(en?'All':'الكل')),DropdownMenuItem(value:'active',child:Text(en?'Active':'نشطة')),DropdownMenuItem(value:'paused',child:Text(en?'Paused':'موقوفة')),DropdownMenuItem(value:'completed',child:Text(en?'Completed':'مكتملة')),DropdownMenuItem(value:'cancelled',child:Text(en?'Cancelled':'ملغاة'))], onChanged:(v){if(v!=null){setState((){status=v;page=1;});load();}})),
      ])),
      Padding(padding:const EdgeInsets.all(12), child:Wrap(spacing:12,runSpacing:6,children:[Text((en?'Records: ':'عدد الكفالات: ')+total.toString()),Text((en?'Active: ':'النشطة: ')+activeCount.toString()),Text((en?'Active monthly: ':'الإجمالي الشهري النشط: ')+activeMonthly.toStringAsFixed(2)+' SDG')])),
      Expanded(child: loading ? const Center(child:CircularProgressIndicator()) : error != null ? Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),OutlinedButton(onPressed:load,child:Text(en?'Try again':'إعادة المحاولة'))]))) : RefreshIndicator(onRefresh:load,child:items.isEmpty ? ListView(children:[Padding(padding:const EdgeInsets.all(24),child:Text(en?'No sponsorships found.':'لا توجد كفالات',textAlign:TextAlign.center))]) : ListView.separated(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),itemCount:items.length,separatorBuilder:(_,__)=>const SizedBox(height:4),itemBuilder:(ctx,i){
        final s=items[i];
        return Card(
          child: ListTile(
            leading: CircleAvatar(child: Icon(s['status'] == 'active' ? Icons.favorite_rounded : Icons.volunteer_activism_rounded)),
            title: Text(s['sponsorship_code']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800)),
            subtitle: Text(
              (s['sponsor_name']?.toString() ?? '') + ' (' + (s['sponsor_code']?.toString() ?? '') + ')\n' +
              (s['child_name']?.toString() ?? '') + '\n' +
              (s['mother_name']?.toString() ?? '') + ' (' + (s['family_code']?.toString() ?? '') + ')\n' +
              (en ? 'Monthly: ' : 'المبلغ الشهري: ') +
              (double.tryParse(s['monthly_amount']?.toString() ?? '0') ?? 0).toStringAsFixed(2) + ' SDG · ' +
              (s['start_date']?.toString() ?? ''),
            ),
            isThreeLine: true,
            trailing: Chip(label: Text(statusLabel(s['status']?.toString() ?? ''))),
          ),
        );
      }))),
      if(!loading && error==null && pages>1) SafeArea(top:false,child:Padding(padding:const EdgeInsets.all(10),child:Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[OutlinedButton(onPressed:page>1?(){setState(()=>page--);load();}:null,child:Text(en?'Previous':'السابق')),Text(en?'Page '+page.toString()+' of '+pages.toString():'صفحة '+page.toString()+' من '+pages.toString()),OutlinedButton(onPressed:page<pages?(){setState(()=>page++);load();}:null,child:Text(en?'Next':'التالي'))]))),
    ]));
  }
}