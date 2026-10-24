import 'package:flutter/material.dart';
class SponsorshipDetailPage extends StatefulWidget {
 const SponsorshipDetailPage({super.key,required this.sponsorshipId,required this.english,required this.loadDetail});
 final int sponsorshipId; final bool english; final Future<Map<String,dynamic>> Function(int id) loadDetail;
 @override State<SponsorshipDetailPage> createState()=>_SponsorshipDetailPageState();
}
class _SponsorshipDetailPageState extends State<SponsorshipDetailPage>{
 bool loading=true; String? error; Map<String,dynamic> sponsorship={}; List<Map<String,dynamic>> payments=[];
 @override void initState(){super.initState();load();}
 Future<void> load()async{
  setState((){loading=true;error=null;});
  try{final d=await widget.loadDetail(widget.sponsorshipId);if(!mounted)return;final raw=d['payments'];setState((){
   sponsorship=Map<String,dynamic>.from(d['sponsorship'] as Map);
   payments=raw is List?raw.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList():<Map<String,dynamic>>[];
  });}catch(e){if(mounted)setState(()=>error=e.toString());}finally{if(mounted)setState(()=>loading=false);}
 }
 String statusLabel(String s){final m=widget.english?const {'active':'Active','paused':'Paused','completed':'Completed','cancelled':'Cancelled','posted':'Posted','pending_fm_review':'Awaiting review','returned':'Returned','voided':'Voided'}:const {'active':'نشطة','paused':'موقوفة','completed':'مكتملة','cancelled':'ملغاة','posted':'مرحّلة','pending_fm_review':'بانتظار المراجعة','returned':'مُعادة للتعديل','voided':'مبطلة'};return m[s]??s;}
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(appBar:AppBar(title:Text(en?'Sponsorship details':'تفاصيل الكفالة')),body:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Text(error!,textAlign:TextAlign.center)):RefreshIndicator(onRefresh:load,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),children:[
  Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
   Text(sponsorship['sponsorship_code']?.toString()??'',style:Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight:FontWeight.w800)),
   Chip(label:Text(statusLabel(sponsorship['status']?.toString()??''))),
   _InfoLine(label:en?'Sponsor':'الكفيل',value:(sponsorship['sponsor_name']?.toString()??'')+' ('+(sponsorship['sponsor_code']?.toString()??'')+')'),
   _InfoLine(label:en?'Child':'الطفل',value:sponsorship['child_name']?.toString()??''),
   _InfoLine(label:en?'Family':'الأسرة',value:(sponsorship['mother_name']?.toString()??'')+' ('+(sponsorship['family_code']?.toString()??'')+')'),
   _InfoLine(label:en?'Monthly amount':'المبلغ الشهري',value:(double.tryParse(sponsorship['monthly_amount']?.toString()??'0')??0).toStringAsFixed(2)+' SDG'),
   _InfoLine(label:en?'Start date':'تاريخ البداية',value:sponsorship['start_date']?.toString()??''),
   _InfoLine(label:en?'End date':'تاريخ النهاية',value:(sponsorship['end_date']?.toString().isNotEmpty==true)?sponsorship['end_date'].toString():'—'),
   if((sponsorship['pause_reason']?.toString()??'').isNotEmpty)_InfoLine(label:en?'Pause reason':'سبب الإيقاف',value:sponsorship['pause_reason'].toString()),
   _InfoLine(label:en?'Payment count':'عدد الدفعات',value:sponsorship['payment_count']?.toString()??'0'),
   _InfoLine(label:en?'Posted total':'إجمالي المرحّل',value:(double.tryParse(sponsorship['total_paid']?.toString()??'0')??0).toStringAsFixed(2)+' SDG'),
  ]))),
  Padding(padding:const EdgeInsets.fromLTRB(4,14,4,6),child:Text(en?'Payment history':'سجل الدفعات',style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800))),
  if(payments.isEmpty)Padding(padding:const EdgeInsets.all(16),child:Text(en?'No payments found.':'لا توجد دفعات مسجلة.'))
  else ...payments.map((p)=>Card(child:ListTile(title:Text(p['transaction_code']?.toString()??''),subtitle:Text((p['transaction_date']?.toString()??'')+'\n'+(p['payment_method']?.toString()??'')+' · '+(en?'Receipt: ':'الإيصال: ')+((p['receipt_number']?.toString().isNotEmpty==true)?p['receipt_number'].toString():'—')+'\n'+statusLabel(p['status']?.toString()??'')),isThreeLine:true,trailing:Text((double.tryParse(p['amount']?.toString()??'0')??0).toStringAsFixed(2)+' SDG')))),
 ])));}
}
class _InfoLine extends StatelessWidget{
 const _InfoLine({required this.label,required this.value});final String label,value;
 @override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.symmetric(vertical:4),child:Row(children:[Expanded(child:Text(label,style:const TextStyle(color:Color(0xFF64748B)))),const SizedBox(width:8),Flexible(child:Text(value,textAlign:TextAlign.end,style:const TextStyle(fontWeight:FontWeight.w700)))]));
}