import 'package:flutter/material.dart';

class AdminFinanceOverviewPage extends StatefulWidget {
  const AdminFinanceOverviewPage({super.key, required this.english, required this.loadOverview});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadOverview;
  @override State<AdminFinanceOverviewPage> createState() => _AdminFinanceOverviewPageState();
}
class _AdminFinanceOverviewPageState extends State<AdminFinanceOverviewPage> {
  bool loading=true; String? error; Map<String,dynamic> data={};
  @override void initState(){super.initState();refresh();}
  String t(String ar,String en)=>widget.english?en:ar;
  Map<String,dynamic> section(String key){final v=data[key];return v is Map?Map<String,dynamic>.from(v):<String,dynamic>{};}
  num n(Map<String,dynamic> m,String key)=>num.tryParse(m[key]?.toString()??'')??0;
  String money(num v)=>v.toStringAsFixed(2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'),(m)=>',');
  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {final v=await widget.loadOverview();if(mounted)setState(()=>data=v);}
    catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>loading=false);}
  }
  @override Widget build(BuildContext context){
    final treasury=section('treasury'),flow=section('monthly_flow'),fees=section('admin_fees'),d=section('disbursements'),q=section('quick_stats'),p=section('period');
    Widget item(String ar,String en,String value)=>ListTile(dense:true,title:Text(t(ar,en)),trailing:Text(value,textAlign:TextAlign.end,style:const TextStyle(fontWeight:FontWeight.w800)));
    Widget group(String ar,String en,List<Widget> items)=>Padding(padding:const EdgeInsets.only(top:12),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Padding(padding:const EdgeInsets.only(bottom:6),child:Text(t(ar,en),style:const TextStyle(fontSize:16,fontWeight:FontWeight.w800))),Card(child:Column(children:items))]));
    return Scaffold(appBar:AppBar(title:Text(t('لوحة المالية','Finance overview')),actions:[IconButton(onPressed:loading?null:refresh,tooltip:t('تحديث','Refresh'),icon:const Icon(Icons.refresh_rounded))]),body:RefreshIndicator(onRefresh:refresh,child:ListView(padding:const EdgeInsets.all(16),children:[
      Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(t('ملخص مالي للقراءة فقط','Read-only financial summary'),style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800)),const SizedBox(height:6),Text('${p['start']??''} — ${p['end']??''}'),if(loading)const LinearProgressIndicator()]))),
      if(error!=null)Card(color:Theme.of(context).colorScheme.errorContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(error!))),
      group('الخزينة','Treasury',[item('النقد','Cash',money(n(treasury,'cash'))),item('البنك','Bank',money(n(treasury,'bank'))),item('المحفظة','Wallet',money(n(treasury,'wallet'))),item('إجمالي الخزينة','Treasury total',money(n(treasury,'total')))]),
      group('حركة الشهر','Monthly flow',[item('الإيرادات المرحلة','Posted income',money(n(flow,'income_total'))),item('المصروفات المرحلة','Posted expenses',money(n(flow,'outgoing_total'))),item('الرسوم الإدارية — الشهر','Admin fees — month',money(n(fees,'admin_fees_month'))),item('الرسوم الإدارية — الإجمالي','Admin fees — total',money(n(fees,'admin_fees_total')))]),
      group('التحويلات الشهرية','Monthly disbursements',[item('بانتظار الاعتماد','Pending approval','${n(d,'pending_approval').toInt()}'),item('المعتمد','Approved','${n(d,'approved').toInt()}'),item('المحوّل','Transferred','${n(d,'transferred').toInt()}'),item('المستلم','Received','${n(d,'received').toInt()}'),item('قيمة بانتظار الاعتماد','Pending amount',money(n(d,'pending_amount'))),item('قيمة المحوّل','Transferred amount',money(n(d,'transferred_amount'))),item('قيمة المرتجع','Returned amount',money(n(d,'returned_amount'))),item('المستلم هذا الشهر','Received this month',money(n(q,'received_this_month')))]),
      group('السجلات التشغيلية','Operational counts',[item('الكفالات النشطة','Active sponsorships','${n(q,'active_sponsorships').toInt()}'),item('الأسر النشطة','Active families','${n(q,'active_families').toInt()}'),item('الحاضنات النشطات','Active nannies','${n(q,'active_nannies').toInt()}'),item('إجمالي دفعات التحويل','Disbursement batches','${n(d,'total').toInt()}')]),
      const SizedBox(height:12),Text(t('تعرض الشاشة مؤشرات من لوحة المالية الحالية ولا تنفذ أي إجراء محاسبي.','These figures mirror the existing finance dashboard and do not perform accounting actions.'),style:Theme.of(context).textTheme.bodySmall)
    ])));
  }
}
