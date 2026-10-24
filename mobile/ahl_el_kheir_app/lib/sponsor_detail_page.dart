import 'package:flutter/material.dart';
class SponsorDetailPage extends StatefulWidget {
 const SponsorDetailPage({super.key,required this.sponsorId,required this.english,required this.loadDetail});
 final int sponsorId;final bool english;final Future<Map<String,dynamic>> Function(int id) loadDetail;
 @override State<SponsorDetailPage> createState()=>_SponsorDetailPageState();
}
class _SponsorDetailPageState extends State<SponsorDetailPage>{
 bool loading=true;String? error;Map<String,dynamic> sponsor={};List<Map<String,dynamic>> ships=[];
 @override void initState(){super.initState();load();}
 Future<void> load() async {
  setState(() { loading = true; error = null; });
  try {
   final data = await widget.loadDetail(widget.sponsorId);
   if (!mounted) return;
   final raw = data['sponsorships'];
   setState(() {
    sponsor = Map<String, dynamic>.from(data['sponsor'] as Map);
    ships = raw is List ? raw.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : <Map<String, dynamic>>[];
   });
  } catch (e) {
   if (mounted) setState(() => error = e.toString());
  } finally {
   if (mounted) setState(() => loading = false);
  }
 }
 String status(String s){final m=widget.english?const {'active':'Active','inactive':'Inactive','suspended':'Suspended','cancelled':'Cancelled'}:const {'active':'نشط','inactive':'غير نشط','suspended':'موقوف','cancelled':'ملغي'};return m[s]??s;}
 String gender(String s){final m=widget.english?const {'male':'Male','female':'Female'}:const {'male':'ذكر','female':'أنثى'};return m[s]??s;}
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(appBar:AppBar(title:Text(en?'Sponsor details':'تفاصيل الكفيل')),body:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Text(error!,textAlign:TextAlign.center)):RefreshIndicator(onRefresh:load,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),children:[
 Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
 Text(sponsor['full_name']?.toString()??'',style:Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight:FontWeight.w800)),
 Text(sponsor['sponsor_code']?.toString()??'',style:const TextStyle(color:Color(0xFF64748B))),
 const SizedBox(height:10),Chip(label:Text(status(sponsor['status']?.toString()??''))),
 _Line(label:en?'Phone':'الهاتف',value:sponsor['phone']?.toString()??''),
 _Line(label:en?'Gender':'الجنس',value:gender(sponsor['gender']?.toString()??'')),
 _Line(label:en?'First letter':'الحرف',value:sponsor['letter']?.toString()??''),
 _Line(label:en?'Supervisor':'المشرف',value:sponsor['supervisor_name']?.toString()??'—'),
 _Line(label:en?'Acquisition source':'مصدر الاستقطاب',value:sponsor['acquisition_source']?.toString()??''),
 _Line(label:en?'Brought by':'تم الاستقطاب بواسطة',value:sponsor['brought_by_name']?.toString()??''),
 _Line(label:en?'Active sponsored children':'الأيتام المكفولون',value:sponsor['active_orphan_count']?.toString()??'0'),
 ]))),
 Padding(padding:const EdgeInsets.fromLTRB(4,14,4,6),child:Text(en?'Sponsorships':'الكفالات',style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800))),
 if(ships.isEmpty)Padding(padding:const EdgeInsets.all(18),child:Text(en?'No sponsorships found.':'لا توجد كفالات.'))
 else ...ships.map((s)=>Card(child:ListTile(title:Text(s['child_name']?.toString()??''),subtitle:Text((s['sponsorship_code']?.toString()??'')+'\n'+(s['mother_name']?.toString()??'')+' ('+(s['family_code']?.toString()??'')+')\n'+(en?'Monthly: ':'المبلغ الشهري: ')+(double.tryParse(s['monthly_amount']?.toString()??'0')??0).toStringAsFixed(2)+' · '+(s['start_date']?.toString()??''),isThreeLine:true,trailing:Chip(label:Text(status(s['status']?.toString()??'')))))),
 ])));}
}
class _Line extends StatelessWidget{const _Line({required this.label,required this.value});final String label,value;@override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.symmetric(vertical:3),child:Row(children:[Expanded(child:Text(label,style:const TextStyle(color:Color(0xFF64748B)))),const SizedBox(width:8),Flexible(child:Text(value,textAlign:TextAlign.end,style:const TextStyle(fontWeight:FontWeight.w700)))]));}
