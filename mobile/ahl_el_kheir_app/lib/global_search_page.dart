import 'package:flutter/material.dart';

typedef GlobalSearchLoader = Future<Map<String, dynamic>> Function(String type, String query, int page);
typedef GlobalSearchOpenResult = void Function(String type, Map<String, dynamic> item);

class GlobalSearchPage extends StatefulWidget {
  const GlobalSearchPage({super.key, required this.roleCode, required this.english, required this.loadSearch, required this.onOpenResult});
  final String roleCode;
  final bool english;
  final GlobalSearchLoader loadSearch;
  final GlobalSearchOpenResult onOpenResult;
  @override State<GlobalSearchPage> createState() => _GlobalSearchPageState();
}

class _GlobalSearchPageState extends State<GlobalSearchPage> {
  final queryController = TextEditingController();
  String selectedType = '';
  bool loading = false;
  String? error;
  List<Map<String, dynamic>> items = [];
  int total = 0, page = 1, pages = 1;
  String t(String ar, String en) => widget.english ? en : ar;

  String get role => switch (widget.roleCode) {
    'sudo' || 'super_admin' => 'admin',
    'gm' => 'general_manager',
    'vgm' => 'vice_general_manager',
    _ => widget.roleCode,
  };

  List<String> get types {
    final r = role;
    final result = <String>[];
    if (['admin','vice_general_manager','general_manager','supervisor','nanny'].contains(r)) result.add('families');
    if (['admin','vice_general_manager','general_manager','supervisor'].contains(r)) result.add('sponsors');
    if (['admin','vice_general_manager','general_manager','supervisor','accountant'].contains(r)) result.add('sponsorships');
    return result;
  }

  @override void initState() { super.initState(); if (types.isNotEmpty) selectedType = types.first; }
  @override void dispose() { queryController.dispose(); super.dispose(); }

  String typeLabel(String type) => switch(type) {
    'families' => t('الأسر والأيتام','Families & children'),
    'sponsors' => t('الكفلاء','Sponsors'),
    'sponsorships' => t('الكفالات','Sponsorships'),
    _ => type,
  };

  Future<void> search({int targetPage = 1}) async {
    if (selectedType.isEmpty) return;
    setState(() { loading = true; error = null; page = targetPage; });
    try {
      final response = await widget.loadSearch(selectedType, queryController.text.trim(), targetPage);
      if (!mounted) return;
      final raw = response['data'];
      final data = raw is Map ? Map<String,dynamic>.from(raw) : <String,dynamic>{};
      final list = data['items'];
      setState(() {
        items = list is List ? list.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList() : <Map<String,dynamic>>[];
        total = int.tryParse(data['total']?.toString() ?? '0') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? targetPage.toString()) ?? targetPage;
        pages = int.tryParse(data['pages']?.toString() ?? '1') ?? 1;
      });
    } catch(e) {
      if (mounted) setState(() { error = e.toString(); items = []; total = 0; pages = 1; });
    } finally { if (mounted) setState(()=>loading=false); }
  }

  String statusLabel(String value) {
    const ar = {'active':'نشط / نشطة','pending':'قيد الانتظار','paused':'موقوف','completed':'مكتمل','inactive':'غير نشط','suspended':'موقوف','cancelled':'ملغى'};
    const en = {'active':'Active','pending':'Pending','paused':'Paused','completed':'Completed','inactive':'Inactive','suspended':'Suspended','cancelled':'Cancelled'};
    return (widget.english ? en : ar)[value] ?? value;
  }

  String titleFor(Map<String,dynamic> x) => switch(selectedType) {
    'families' => (x['mother_name']?.toString() ?? '') + ' · ' + (x['family_code']?.toString() ?? ''),
    'sponsors' => (x['full_name']?.toString() ?? '') + ' · ' + (x['sponsor_code']?.toString() ?? ''),
    _ => x['sponsorship_code']?.toString() ?? '',
  };

  String subtitleFor(Map<String,dynamic> x) => switch(selectedType) {
    'families' => (x['mother_phone']?.toString() ?? '') + ' · ' + t('عدد الأطفال: ','Children: ') + (x['children_count']?.toString() ?? '0') + ' · ' + statusLabel(x['status']?.toString() ?? ''),
    'sponsors' => (x['phone']?.toString() ?? '') + ' · ' + t('الكفالات النشطة: ','Active sponsorships: ') + (x['active_sponsorship_count']?.toString() ?? '0') + ' · ' + statusLabel(x['status']?.toString() ?? ''),
    _ => (x['sponsor_name']?.toString() ?? '') + ' · ' + (x['child_name']?.toString() ?? '') + ' · ' + (x['family_code']?.toString() ?? '') + ' · ' + (double.tryParse(x['monthly_amount']?.toString() ?? '0') ?? 0).toStringAsFixed(2) + ' SDG · ' + statusLabel(x['status']?.toString() ?? ''),
  };

  @override Widget build(BuildContext context) {
    final available = types;
    return Scaffold(
      appBar: AppBar(title: Text(t('البحث الشامل','Global search'))),
      body: available.isEmpty
        ? Center(child: Padding(padding: const EdgeInsets.all(28), child: Column(mainAxisSize:MainAxisSize.min, children:[
            const Icon(Icons.search_off_rounded,size:48), const SizedBox(height:14),
            Text(t('لا تتوفر فئات بحث أصلية لهذا الدور حتى الآن.','No native search categories are available for this role yet.'),textAlign:TextAlign.center),
            const SizedBox(height:8),
            Text(t('لن يتم فتح الموقع أو أي صفحة خارجية.','The website and external pages will not be opened.'),textAlign:TextAlign.center,style:Theme.of(context).textTheme.bodySmall),
          ])))
        : Column(children:[
          Padding(padding:const EdgeInsets.fromLTRB(16,16,16,8),child:DropdownButtonFormField<String>(
            initialValue:selectedType,decoration:InputDecoration(labelText:t('نطاق البحث','Search in')),
            items:available.map((v)=>DropdownMenuItem(value:v,child:Text(typeLabel(v)))).toList(),
            onChanged: loading ? null : (v) { if (v != null) setState(() { selectedType = v; items = []; total = 0; page = 1; error = null; }); },
          )),
          Padding(padding:const EdgeInsets.fromLTRB(16,4,16,8),child:TextField(
            controller:queryController,textInputAction:TextInputAction.search,onSubmitted:(_)=>search(),
            decoration:InputDecoration(labelText:t('عبارة البحث','Search term'),hintText:t('الاسم أو الرقم أو الهاتف','Name, code or phone'),prefixIcon:const Icon(Icons.search_rounded),
              suffixIcon: IconButton(tooltip: t('مسح','Clear'), onPressed: () { queryController.clear(); setState(() { items = []; total = 0; page = 1; error = null; }); }, icon: const Icon(Icons.close_rounded)),
            ),
          )),
          Padding(padding:const EdgeInsets.symmetric(horizontal:16,vertical:4),child:SizedBox(width:double.infinity,child:FilledButton.icon(onPressed:loading?null:()=>search(),icon:const Icon(Icons.search_rounded),label:Text(t('بحث','Search'))))),
          Padding(padding:const EdgeInsets.symmetric(horizontal:16,vertical:8),child:Align(alignment:AlignmentDirectional.centerStart,child:Text(t('النتائج: ','Results: ')+total.toString(),style:Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight:FontWeight.w800)))),
          Expanded(child:loading?const Center(child:CircularProgressIndicator()):error!=null
            ? Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),const SizedBox(height:12),OutlinedButton(onPressed:()=>search(targetPage:page),child:Text(t('إعادة المحاولة','Try again')))])))
            : items.isEmpty?Center(child:Padding(padding:const EdgeInsets.all(24),child:Text(t('أدخل عبارة بحث لعرض النتائج.','Enter a search term to see results.'),textAlign:TextAlign.center)))
            : RefreshIndicator(onRefresh:()=>search(targetPage:page),child:ListView.separated(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.fromLTRB(16,4,16,16),itemCount:items.length,separatorBuilder:(_,__)=>const SizedBox(height:8),itemBuilder:(context,index){
              final item=items[index];
              return Card(child:ListTile(
                leading:CircleAvatar(child:Icon(switch(selectedType){'families'=>Icons.family_restroom_rounded,'sponsors'=>Icons.volunteer_activism_rounded,_=>Icons.handshake_rounded})),
                title:Text(titleFor(item),maxLines:2,overflow:TextOverflow.ellipsis),
                subtitle:Text(subtitleFor(item),maxLines:3,overflow:TextOverflow.ellipsis),
                trailing:const Icon(Icons.chevron_right_rounded),
                onTap:()=>widget.onOpenResult(selectedType,item),
              ));
            })),
          ),
          if(!loading&&error==null&&pages>1) SafeArea(top:false,child:Padding(padding:const EdgeInsets.all(12),child:Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[
            OutlinedButton(onPressed:page>1?()=>search(targetPage:page-1):null,child:Text(t('السابق','Previous'))),
            Text(t('صفحة ','Page ')+page.toString()+t(' من ',' of ')+pages.toString()),
            OutlinedButton(onPressed:page<pages?()=>search(targetPage:page+1):null,child:Text(t('التالي','Next'))),
          ]))),
          Padding(padding:const EdgeInsets.fromLTRB(16,0,16,14),child:Text(t('البحث في الدفعات الشهرية ومستندات المشاريع المؤرشفة ما زال قيد التنفيذ داخل التطبيق.','Search for monthly disbursements and archived project documents is still being implemented natively.'),textAlign:TextAlign.center,style:Theme.of(context).textTheme.bodySmall)),
        ]),
    );
  }
}
