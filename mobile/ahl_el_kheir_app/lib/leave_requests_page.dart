import 'package:flutter/material.dart';
class LeaveRequestsPage extends StatefulWidget {
 const LeaveRequestsPage({super.key,required this.english,required this.roleCode,required this.loadLeaves,required this.submitAction});
 final bool english; final String roleCode;
 final Future<Map<String,dynamic>> Function(String view,String status,int page) loadLeaves;
 final Future<Map<String,dynamic>> Function(String action,Map<String,dynamic> body) submitAction;
 @override State<LeaveRequestsPage> createState()=>_LeaveRequestsPageState();
}
class _LeaveRequestsPageState extends State<LeaveRequestsPage>{
 bool loading=true,submitting=false; String? error; String view='mine',status='all'; int page=1,pages=1,total=0;
 List<Map<String,dynamic>> items=[];
 bool get canQueue=>['hr_manager','hr_staff','general_manager','gm'].contains(widget.roleCode);
 @override void initState(){super.initState();load();}
 Future<void> load() async {
  setState((){loading=true;error=null;});
  try{final d=await widget.loadLeaves(view,status,page);if(!mounted)return;setState((){
   final raw=d['items'];items=raw is List?raw.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList():<Map<String,dynamic>>[];
   total=int.tryParse(d['total']?.toString()??'')??0;page=int.tryParse(d['page']?.toString()??'1')??1;pages=int.tryParse(d['pages']?.toString()??'1')??1;
  });}catch(e){if(mounted)setState(()=>error=e.toString());}finally{if(mounted)setState(()=>loading=false);}
 }
 String leaveType(String s){final m=widget.english?const {'annual':'Annual','sick':'Sick','emergency':'Emergency','unpaid':'Unpaid','remote_work_request':'Remote work'}:const {'annual':'سنوية','sick':'مرضية','emergency':'طارئة','unpaid':'بدون أجر','remote_work_request':'طلب عمل عن بُعد'};return m[s]??s;}
 String statusLabel(String s){final m=widget.english?const {'pending':'Pending','hr_approved':'Approved','rejected':'Rejected'}:const {'pending':'قيد المراجعة','hr_approved':'معتمدة','rejected':'مرفوضة'};return m[s]??s;}
 Future<void> submit(String action,Map<String,dynamic> body) async {
  setState(()=>submitting=true);
  try{final d=await widget.submitAction(action,body);if(!mounted)return;
   ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(d['message']?.toString()??(widget.english?'Saved':'تم الحفظ'))));
   await load();
  }catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.toString())));}
  finally{if(mounted)setState(()=>submitting=false);}
 }
 Future<void> requestLeave() async {
  final reason=TextEditingController();String type='annual';DateTime start=DateTime.now(),end=DateTime.now();
  final result=await showDialog<Map<String,dynamic>>(context:context,builder:(ctx)=>StatefulBuilder(builder:(ctx,setLocal)=>AlertDialog(
   title:Text(widget.english?'New leave request':'طلب إجازة جديد'),
   content:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,children:[
    DropdownButtonFormField<String>(value:type,decoration:InputDecoration(labelText:widget.english?'Leave type':'نوع الإجازة'),items:[
     DropdownMenuItem(value:'annual',child:Text(widget.english?'Annual':'سنوية')),DropdownMenuItem(value:'sick',child:Text(widget.english?'Sick':'مرضية')),
     DropdownMenuItem(value:'emergency',child:Text(widget.english?'Emergency':'طارئة')),DropdownMenuItem(value:'unpaid',child:Text(widget.english?'Unpaid':'بدون أجر')),
     DropdownMenuItem(value:'remote_work_request',child:Text(widget.english?'Remote work':'طلب عمل عن بُعد')),
    ],onChanged:(v){if(v!=null)setLocal(()=>type=v);}),
    ListTile(contentPadding:EdgeInsets.zero,title:Text(widget.english?'Start date':'تاريخ البداية'),subtitle:Text(start.toIso8601String().substring(0,10)),trailing:const Icon(Icons.calendar_today),onTap:()async{final v=await showDatePicker(context:ctx,initialDate:start,firstDate:DateTime(2020),lastDate:DateTime(2100));if(v!=null)setLocal(()=>start=v);}),
    ListTile(contentPadding:EdgeInsets.zero,title:Text(widget.english?'End date':'تاريخ النهاية'),subtitle:Text(end.toIso8601String().substring(0,10)),trailing:const Icon(Icons.event),onTap:()async{final v=await showDatePicker(context:ctx,initialDate:end.isBefore(start)?start:end,firstDate:DateTime(2020),lastDate:DateTime(2100));if(v!=null)setLocal(()=>end=v);}),
    TextField(controller:reason,maxLines:3,decoration:InputDecoration(labelText:widget.english?'Reason (optional)':'السبب (اختياري)')),
   ])),
   actions:[TextButton(onPressed:()=>Navigator.pop(ctx),child:Text(widget.english?'Cancel':'إلغاء')),
    FilledButton(onPressed:()=>Navigator.pop(ctx,{'leave_type':type,'start_date':start.toIso8601String().substring(0,10),'end_date':end.toIso8601String().substring(0,10),'reason':reason.text.trim()}),child:Text(widget.english?'Submit':'إرسال'))],
  )));
  reason.dispose();if(result!=null)await submit('request',result);
 }
 Future<void> decide(Map<String,dynamic> item,String action) async {
  final yes=await showDialog<bool>(context:context,builder:(ctx)=>AlertDialog(
   title:Text(action=='approve'?(widget.english?'Approve request?':'اعتماد الطلب؟'):(widget.english?'Reject request?':'رفض الطلب؟')),
   content:Text((item['employee_name']?.toString()??'')+' · '+(item['start_date']?.toString()??'')+' → '+(item['end_date']?.toString()??'')),
   actions:[TextButton(onPressed:()=>Navigator.pop(ctx,false),child:Text(widget.english?'Cancel':'إلغاء')),FilledButton(onPressed:()=>Navigator.pop(ctx,true),child:Text(action=='approve'?(widget.english?'Approve':'اعتماد'):(widget.english?'Reject':'رفض')))],
  ));
  if(yes==true)await submit(action,{'leave_id':item['id']});
 }
 String _date(dynamic v)=>v?.toString()??'';
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(
 appBar:AppBar(title:Text(en?'Leave requests':'طلبات الإجازة')),
 floatingActionButton:view=='mine'?FloatingActionButton.extended(onPressed:submitting?null:requestLeave,icon:const Icon(Icons.add),label:Text(en?'Request leave':'طلب إجازة')):null,
 body:Column(children:[
 if(canQueue) Padding(padding:const EdgeInsets.fromLTRB(12,12,12,4),child:SegmentedButton<String>(
  segments:[ButtonSegment(value:'mine',label:Text(en?'My requests':'طلباتي')),ButtonSegment(value:'queue',label:Text(en?'Review queue':'طلبات للمراجعة'))],
  selected:{view},onSelectionChanged:(v){setState(() { view=v.first; page=1; });load();},
 )),
 Padding(padding:const EdgeInsets.symmetric(horizontal:12,vertical:8),child:Row(children:[
  Expanded(child:Text((en?'Records: ':'عدد الطلبات: ')+total.toString())),
  DropdownButton<String>(value:status,items:[
   DropdownMenuItem(value:'all',child:Text(en?'All':'الكل')),DropdownMenuItem(value:'pending',child:Text(en?'Pending':'قيد المراجعة')),
   DropdownMenuItem(value:'hr_approved',child:Text(en?'Approved':'معتمدة')),DropdownMenuItem(value:'rejected',child:Text(en?'Rejected':'مرفوضة')),
  ],onChanged:(v){if(v!=null){setState(() { status=v; page=1; });load();}}),
 ])),
 Expanded(child:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),const SizedBox(height:12),OutlinedButton.icon(onPressed:load,icon:const Icon(Icons.refresh),label:Text(en?'Try again':'إعادة المحاولة'))]))):
 RefreshIndicator(onRefresh:load,child:items.isEmpty?ListView(children:[Padding(padding:const EdgeInsets.all(24),child:Text(en?'No leave requests found.':'لا توجد طلبات إجازة.',textAlign:TextAlign.center))]):ListView.separated(
 physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),itemCount:items.length,separatorBuilder:(_,__)=>const SizedBox(height:4),
 itemBuilder:(context,i){final v=items[i];final pending=v['status']=='pending'&&view=='queue';return Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
 Row(children:[Expanded(child:Text(leaveType(v['leave_type']?.toString()??''),style:const TextStyle(fontWeight:FontWeight.w800))),Chip(label:Text(statusLabel(v['status']?.toString()??'')))]),
 if(view=='queue')Text(v['employee_name']?.toString()??'',style:const TextStyle(color:Color(0xFF64748B))),
 const SizedBox(height:8),Text(_date(v['start_date'])+' → '+_date(v['end_date'])),
 Text((en?'Days: ':'عدد الأيام: ')+(v['days_count']?.toString()??'0')),
 if((v['department_name']?.toString()??'').isNotEmpty&&view=='queue')Text(v['department_name'].toString()),
 if((v['reason']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:6),child:Text(v['reason'].toString())),
 if((v['created_at']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:6),child:Text(v['created_at'].toString(),style:const TextStyle(fontSize:12,color:Color(0xFF64748B)))),
 if(pending)Padding(padding:const EdgeInsets.only(top:10),child:Wrap(spacing:8,children:[
 OutlinedButton.icon(onPressed:submitting?null:()=>decide(v,'reject'),icon:const Icon(Icons.close),label:Text(en?'Reject':'رفض')),
 FilledButton.icon(onPressed:submitting?null:()=>decide(v,'approve'),icon:const Icon(Icons.check),label:Text(en?'Approve':'اعتماد')),
 ])),
 ])));},
 ))),
 if(!loading&&error==null&&pages>1)SafeArea(top:false,child:Padding(padding:const EdgeInsets.all(10),child:Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[
 OutlinedButton(onPressed:page>1?(){setState(()=>page--);load();}:null,child:Text(en?'Previous':'السابق')),
 Text(en?'Page $page of $pages':'صفحة $page من $pages'),
 OutlinedButton(onPressed:page<pages?(){setState(()=>page++);load();}:null,child:Text(en?'Next':'التالي')),
 ]))),
 ]));
 }
}
