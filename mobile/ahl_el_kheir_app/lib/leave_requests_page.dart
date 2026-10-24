import 'package:flutter/material.dart';
class LeaveRequestsPage extends StatefulWidget {
 const LeaveRequestsPage({super.key,required this.english,required this.roleCode,required this.loadLeaves});
 final bool english; final String roleCode;
 final Future<Map<String,dynamic>> Function(String view,String status,int page) loadLeaves;
 @override State<LeaveRequestsPage> createState()=>_LeaveRequestsPageState();
}
class _LeaveRequestsPageState extends State<LeaveRequestsPage>{
 bool loading=true; String? error; String view='mine',status='all'; int page=1,pages=1,total=0;
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
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(
 appBar:AppBar(title:Text(en?'Leave requests':'طلبات الإجازة')),
 body:Column(children:[
 if(canQueue) Padding(padding:const EdgeInsets.fromLTRB(12,12,12,4),child:SegmentedButton<String>(
  segments:[ButtonSegment(value:'mine',label:Text(en?'My requests':'طلباتي')),ButtonSegment(value:'queue',label:Text(en?'Review queue':'طلبات للمراجعة'))],
  selected:{view},onSelectionChanged:(v){setState(()=>{view=v.first,page=1});load();},
 )),
 Padding(padding:const EdgeInsets.symmetric(horizontal:12,vertical:8),child:Row(children:[
  Expanded(child:Text(en?'Records: ':'عدد الطلبات: '+total.toString())),
  DropdownButton<String>(value:status,items:[
   DropdownMenuItem(value:'all',child:Text(en?'All':'الكل')),DropdownMenuItem(value:'pending',child:Text(en?'Pending':'قيد المراجعة')),
   DropdownMenuItem(value:'hr_approved',child:Text(en?'Approved':'معتمدة')),DropdownMenuItem(value:'rejected',child:Text(en?'Rejected':'مرفوضة')),
  ],onChanged:(v){if(v!=null){setState(()=>{status=v,page=1});load();}}),
 ])),
 Expanded(child:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),const SizedBox(height:12),OutlinedButton.icon(onPressed:load,icon:const Icon(Icons.refresh),label:Text(en?'Try again':'إعادة المحاولة'))]))):
 RefreshIndicator(onRefresh:load,child:items.isEmpty?ListView(children:[Padding(padding:const EdgeInsets.all(24),child:Text(en?'No leave requests found.':'لا توجد طلبات إجازة.',textAlign:TextAlign.center))]):ListView.separated(
 physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),itemCount:items.length,separatorBuilder:(_,__)=>const SizedBox(height:4),
 itemBuilder:(context,i){final v=items[i];return Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
 Row(children:[Expanded(child:Text(leaveType(v['leave_type']?.toString()??''),style:const TextStyle(fontWeight:FontWeight.w800))),Chip(label:Text(statusLabel(v['status']?.toString()??'')))]),
 if(view=='queue')Text(v['employee_name']?.toString()??'',style:const TextStyle(color:Color(0xFF64748B))),
 const SizedBox(height:8),Text((v['start_date']?.toString()??'')+' → '+(v['end_date']?.toString()??'')),
 Text((en?'Days: ':'عدد الأيام: ')+(v['days_count']?.toString()??'0')),
 if((v['department_name']?.toString()??'').isNotEmpty&&view=='queue')Text(v['department_name'].toString()),
 if((v['reason']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:6),child:Text(v['reason'].toString())),
 if((v['created_at']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:6),child:Text(v['created_at'].toString(),style:const TextStyle(fontSize:12,color:Color(0xFF64748B)))),
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
