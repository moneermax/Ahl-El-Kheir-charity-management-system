import 'package:flutter/material.dart';
class FamilyDetailPage extends StatefulWidget {
 const FamilyDetailPage({super.key,required this.familyId,required this.english,required this.loadDetail});
 final int familyId;final bool english;final Future<Map<String,dynamic>> Function(int id) loadDetail;
 @override State<FamilyDetailPage> createState()=>_FamilyDetailPageState();
}
class _FamilyDetailPageState extends State<FamilyDetailPage>{
 bool loading=true;String? error;Map<String,dynamic> family={};List<Map<String,dynamic>> children=[],ships=[];
 @override void initState(){super.initState();load();}
 Future<void> load() async {
  setState(() { loading = true; error = null; });
  try {
   final data = await widget.loadDetail(widget.familyId);
   if (!mounted) return;
   final rawChildren = data['children'];
   final rawShips = data['sponsorships'];
   setState(() {
    family = Map<String, dynamic>.from(data['family'] as Map);
    children = rawChildren is List ? rawChildren.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : <Map<String, dynamic>>[];
    ships = rawShips is List ? rawShips.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : <Map<String, dynamic>>[];
   });
  } catch (e) {
   if (mounted) setState(() => error = e.toString());
  } finally {
   if (mounted) setState(() => loading = false);
  }
 }
 String status(String s){final m=widget.english?const {'active':'Active','pending':'Pending','paused':'Paused','completed':'Completed','archived':'Archived','inactive':'Inactive','closed':'Closed'}:const {'active':'نشطة','pending':'قيد المراجعة','paused':'موقوفة','completed':'مكتملة','archived':'مؤرشفة','inactive':'غير نشطة','closed':'مغلقة'};return m[s]??s;}
 String match(String s){final m=widget.english?const {'matched':'Sponsored','waiting_list':'Waiting list','lost_sponsor':'Lost sponsor','unmatched':'Not sponsored'}:const {'matched':'مكفول','waiting_list':'في الانتظار','lost_sponsor':'فقد كفيله','unmatched':'غير مكفول'};return m[s]??s;}
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(appBar:AppBar(title:Text(en?'Family details':'تفاصيل الأسرة')),body:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Text(error!,textAlign:TextAlign.center)):RefreshIndicator(onRefresh:load,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),children:[
 Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
 Text(family['mother_name']?.toString()??'',style:Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight:FontWeight.w800)),
 Text(family['family_code']?.toString()??'',style:const TextStyle(color:Color(0xFF64748B))),
 const SizedBox(height:8),Chip(label:Text(status(family['status']?.toString()??''))),
 _Line(label:en?'Phone':'الهاتف',value:family['mother_phone']?.toString()??''),
 _Line(label:en?'Alternate phone':'هاتف بديل',value:family['mother_alt_phone']?.toString()??''),
 _Line(label:en?'Registration number':'الرقم التسجيلي',value:family['registration_number']?.toString()??''),
 _Line(label:en?'City / district':'المدينة / الحي',value:family['city']?.toString()??''),
 _Line(label:en?'Address':'العنوان',value:family['address']?.toString()??''),
 _Line(label:en?'Mother marital status':'الحالة الاجتماعية للأم',value:family['mother_marital_status']?.toString()??''),
 _Line(label:en?'Father name':'اسم الأب',value:family['father_name']?.toString()??''),
 _Line(label:en?'Father death date':'تاريخ وفاة الأب',value:family['father_death_date']?.toString()??''),
 _Line(label:en?'Father death cause':'سبب وفاة الأب',value:family['father_death_cause']?.toString()??''),
 _Line(label:en?'Monthly need':'الاحتياج الشهري',value:(double.tryParse(family['monthly_need_amount']?.toString()??'0')??0).toStringAsFixed(2)),
 if((family['notes']?.toString()??'').isNotEmpty) _Line(label:en?'Notes':'ملاحظات',value:family['notes'].toString()),
 _Line(label:en?'Nanny':'المشرفة',value:family['nanny_name']?.toString()??'—'),
 _Line(label:en?'Supervisor':'المشرف',value:family['supervisor_name']?.toString()??'—'),
 _Line(label:en?'Monthly commitment':'الالتزام الشهري',value:(double.tryParse(family['monthly_commitment']?.toString()??'0')??0).toStringAsFixed(2)),
 ]))),
 Padding(padding:const EdgeInsets.fromLTRB(4,14,4,6),child:Text((en?'Children':'الأطفال')+' ('+children.length.toString()+')',style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800))),
 if(children.isEmpty)Padding(padding:const EdgeInsets.all(16),child:Text(en?'No children recorded.':'لا يوجد أطفال مسجلون.'))
 else ...children.map((c) => Card(child: ListTile(
  leading: CircleAvatar(child: Icon(c['is_critical'] == true ? Icons.priority_high_rounded : Icons.child_care_rounded)),
  title: Text(c['child_name']?.toString() ?? ''),
  subtitle: Text((c['birth_date']?.toString() ?? '') + '\n' + match(c['match_status']?.toString() ?? 'unmatched')),
  isThreeLine: true,
  trailing: c['is_critical'] == true ? Chip(label: Text(en ? 'Critical' : 'حالة حرجة')) : null,
))),
 Padding(padding:const EdgeInsets.fromLTRB(4,14,4,6),child:Text(en?'Sponsorships':'الكفالات',style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800))),
 if(ships.isEmpty)Padding(padding:const EdgeInsets.all(16),child:Text(en?'No sponsorships recorded.':'لا توجد كفالات مسجلة.'))
 else ...ships.map((s) => Card(child: ListTile(
  title: Text(s['sponsor_name']?.toString() ?? ''),
  subtitle: Text((s['sponsor_code']?.toString() ?? '') + '\n' + (s['child_name']?.toString() ?? '') + '\n' +
    (en ? 'Monthly: ' : 'المبلغ الشهري: ') +
    (double.tryParse(s['monthly_amount']?.toString() ?? '0') ?? 0).toStringAsFixed(2) + ' · ' +
    (s['start_date']?.toString() ?? '')),
  isThreeLine: true,
  trailing: Chip(label: Text(status(s['status']?.toString() ?? ''))),
))),
 ])));}
}
class _Line extends StatelessWidget{const _Line({required this.label,required this.value});final String label,value;@override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.symmetric(vertical:3),child:Row(children:[Expanded(child:Text(label,style:const TextStyle(color:Color(0xFF64748B)))),const SizedBox(width:8),Flexible(child:Text(value,textAlign:TextAlign.end,style:const TextStyle(fontWeight:FontWeight.w700)))]));}
