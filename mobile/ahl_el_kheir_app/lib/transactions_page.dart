import 'package:flutter/material.dart';
class TransactionsPage extends StatefulWidget{
 const TransactionsPage({super.key,required this.english,required this.loadTransactions});
 final bool english;final Future<Map<String,dynamic>> Function(String query,String status,int page,String from,String to) loadTransactions;
 @override State<TransactionsPage> createState()=>_TransactionsPageState();
}
class _TransactionsPageState extends State<TransactionsPage>{
 final search=TextEditingController();bool loading=true;String? error;String status='';DateTime? fromDate,toDate;int page=1,pages=1,total=0;double postedTotal=0;List<Map<String,dynamic>> items=[];
 @override void initState(){super.initState();load();}
 @override void dispose(){search.dispose();super.dispose();}
 Future<void> load()async{setState((){loading=true;error=null;});try{final d=await widget.loadTransactions(search.text.trim(),status,page,isoDate(fromDate),isoDate(toDate));if(!mounted)return;final raw=d['items'];setState((){items=raw is List?raw.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList():<Map<String,dynamic>>[];total=int.tryParse(d['total']?.toString()??'0')??0;page=int.tryParse(d['page']?.toString()??'1')??1;pages=int.tryParse(d['pages']?.toString()??'1')??1;postedTotal=double.tryParse(d['posted_total']?.toString()??'0')??0;});}catch(e){if(mounted)setState(()=>error=e.toString());}finally{if(mounted)setState(()=>loading=false);}}
 String isoDate(DateTime? d)=>d==null?'':d.year.toString().padLeft(4,'0')+'-'+d.month.toString().padLeft(2,'0')+'-'+d.day.toString().padLeft(2,'0');
 Future<void> chooseDate(bool start) async {
  final current=start?fromDate:toDate;
  final picked=await showDatePicker(context:context,initialDate:current??(start?toDate:fromDate)??DateTime.now(),firstDate:DateTime(2000),lastDate:DateTime(2100),helpText:widget.english?(start?'Start date':'End date'):(start?'تاريخ البداية':'تاريخ النهاية'));
  if(picked==null||!mounted)return;
  final nextFrom=start?picked:fromDate;
  final nextTo=start?toDate:picked;
  if(nextFrom!=null&&nextTo!=null&&nextFrom.isAfter(nextTo)){ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(widget.english?'Start date must not be after end date.':'يجب ألا يسبق تاريخ البداية تاريخ النهاية.')));return;}
  setState(() { if(start){fromDate=picked;}else{toDate=picked;} page=1; });
  await load();
 }
 String statusLabel(String s){final m=widget.english?const {'pending_fm_review':'Awaiting review','returned':'Returned','cancelled':'Cancelled','posted':'Posted','voided':'Voided'}:const {'pending_fm_review':'بانتظار المراجعة','returned':'مُعادة للتعديل','cancelled':'ملغاة','posted':'مرحّلة','voided':'مبطلة'};return m[s]??s;}
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(appBar:AppBar(title:Text(en?'Transactions':'المعاملات')),body:Column(children:[
 Padding(padding:const EdgeInsets.fromLTRB(12,12,12,4),child:Row(children:[Expanded(child:TextField(controller:search,onSubmitted:(_){setState(()=>page=1);load();},decoration:InputDecoration(hintText:en?'Search code, sponsor, family or receipt':'بحث بالكود أو الكفيل أو الأسرة أو الإيصال',prefixIcon:const Icon(Icons.search)))),IconButton(onPressed:(){setState(()=>page=1);load();},icon:const Icon(Icons.search))])),
 Padding(padding:const EdgeInsets.symmetric(horizontal:12,vertical:6),child:Wrap(spacing:8,runSpacing:8,children:[
 OutlinedButton.icon(onPressed:()=>chooseDate(true),icon:const Icon(Icons.calendar_today,size:16),label:Text((en?'From: ':'من: ')+(fromDate==null?(en?'Any date':'أي تاريخ'):isoDate(fromDate)))),
 OutlinedButton.icon(onPressed:()=>chooseDate(false),icon:const Icon(Icons.event,size:16),label:Text((en?'To: ':'إلى: ')+(toDate==null?(en?'Any date':'أي تاريخ'):isoDate(toDate)))),
 if(fromDate!=null||toDate!=null)TextButton.icon(onPressed:(){setState(() {fromDate=null;toDate=null;page=1;});load();},icon:const Icon(Icons.clear,size:16),label:Text(en?'Clear dates':'مسح التاريخ')),
 ])),
 Padding(padding:const EdgeInsets.symmetric(horizontal:12),child:DropdownButtonFormField<String>(initialValue:status,decoration:InputDecoration(labelText:en?'Status':'الحالة'),items:[
 DropdownMenuItem(value:'',child:Text(en?'All':'الكل')),DropdownMenuItem(value:'pending_fm_review',child:Text(en?'Awaiting review':'بانتظار المراجعة')),DropdownMenuItem(value:'returned',child:Text(en?'Returned':'مُعادة للتعديل')),DropdownMenuItem(value:'cancelled',child:Text(en?'Cancelled':'ملغاة')),DropdownMenuItem(value:'posted',child:Text(en?'Posted':'مرحّلة')),DropdownMenuItem(value:'voided',child:Text(en?'Voided':'مبطلة')),
 ],onChanged:(v){if(v!=null){setState(() { status = v; page = 1; });load();}})),
 Padding(padding:const EdgeInsets.all(12),child:Wrap(spacing:12,runSpacing:6,children:[Text((en?'Records: ':'عدد المعاملات: ')+total.toString()),Text((en?'Posted total: ':'إجمالي المرحّل: ')+postedTotal.toStringAsFixed(2)+' SDG')])),
 Expanded(child:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),OutlinedButton(onPressed:load,child:Text(en?'Try again':'إعادة المحاولة'))]))):RefreshIndicator(onRefresh:load,child:items.isEmpty?ListView(children:[Padding(padding:const EdgeInsets.all(24),child:Text(en?'No transactions found.':'لا توجد معاملات',textAlign:TextAlign.center))]):ListView.separated(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),itemCount:items.length,separatorBuilder:(_,__)=>const SizedBox(height:4),itemBuilder:(ctx,i){final t=items[i];return Card(child:ListTile(
 leading:CircleAvatar(child:Icon(t['status']=='posted'?Icons.check_circle_outline_rounded:Icons.receipt_long_rounded)),
 title:Text(t['transaction_code']?.toString()??'',style:const TextStyle(fontWeight:FontWeight.w800)),
 subtitle:Text((t['transaction_date']?.toString()??'')+'\n'+(t['sponsor_name']?.toString().isNotEmpty==true?t['sponsor_name'].toString():(en?'No sponsor':'بدون كفيل'))+' · '+(t['family_name']?.toString()??'')+'\n'+(t['transaction_type']?.toString()??'')+' · '+(t['payment_method']?.toString()??'')+'\n'+(en?'Amount: ':'المبلغ: ')+(double.tryParse(t['amount']?.toString()??'0')??0).toStringAsFixed(2)+' SDG · '+(en?'Receipt: ':'الإيصال: ')+(t['receipt_number']?.toString().isNotEmpty==true?t['receipt_number'].toString():'—')),
 isThreeLine:true,trailing:Chip(label:Text(statusLabel(t['status']?.toString()??''))),
 ));}))),
 if(!loading&&error==null&&pages>1)SafeArea(top:false,child:Padding(padding:const EdgeInsets.all(10),child:Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[OutlinedButton(onPressed:page>1?(){setState(()=>page--);load();}:null,child:Text(en?'Previous':'السابق')),Text(en?'Page '+page.toString()+' of '+pages.toString():'صفحة '+page.toString()+' من '+pages.toString()),OutlinedButton(onPressed:page<pages?(){setState(()=>page++);load();}:null,child:Text(en?'Next':'التالي'))]))),
 ]));}
}
