import 'package:flutter/material.dart';
class AttendancePage extends StatefulWidget {
 const AttendancePage({super.key,required this.english,required this.loadAttendance});
 final bool english;
 final Future<Map<String,dynamic>> Function(String? date) loadAttendance;
 @override State<AttendancePage> createState()=>_AttendancePageState();
}
class _AttendancePageState extends State<AttendancePage>{
 bool loading=true; String? error,date; List<Map<String,dynamic>> items=[]; Map<String,dynamic> counts={}; int total=0;
 @override void initState(){super.initState();load();}
 Future<void> load() async {
  setState(()=>{loading=true,error=null});
  try { final d=await widget.loadAttendance(date); if(!mounted)return; setState((){
   date=d['date']?.toString()??date; final raw=d['items'];
   items=raw is List?raw.whereType<Map>().map((v)=>Map<String,dynamic>.from(v)).toList():<Map<String,dynamic>>[];
   counts=d['counts'] is Map?Map<String,dynamic>.from(d['counts'] as Map):{}; total=int.tryParse(d['total']?.toString()??'')??items.length;
  }); } catch(e){if(mounted)setState(()=>error=e.toString());} finally{if(mounted)setState(()=>loading=false);}
 }
 String status(String s){final m=widget.english?const {'present':'Present','absent':'Absent','on_leave':'On leave','missing':'No record','late':'Late'}:const {'present':'حاضر','absent':'غائب','on_leave':'في إجازة','missing':'لا يوجد سجل','late':'متأخر'};return m[s]??s;}
 String mode(String s){final m=widget.english?const {'remote':'Remote','onsite':'On-site','hybrid':'Hybrid'}:const {'remote':'عن بُعد','onsite':'بالمكتب','hybrid':'هجين'};return m[s]??s;}
 Future<void> chooseDate() async {final init=DateTime.tryParse(date??'')??DateTime.now();final v=await showDatePicker(context:context,initialDate:init,firstDate:DateTime(2020),lastDate:DateTime(2100));if(v==null||!mounted)return;setState(()=>date=v.toIso8601String().substring(0,10));await load();}
 @override Widget build(BuildContext context){final en=widget.english;return Scaffold(
 appBar:AppBar(title:Text(en?'Attendance':'الحضور والانصراف'),actions:[IconButton(onPressed:chooseDate,icon:const Icon(Icons.calendar_month_rounded))]),
 body:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),const SizedBox(height:12),OutlinedButton.icon(onPressed:load,icon:const Icon(Icons.refresh),label:Text(en?'Try again':'إعادة المحاولة'))]))):
 RefreshIndicator(onRefresh:load,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(12),children:[
 Card(child:ListTile(leading:const Icon(Icons.calendar_today_rounded),title:Text(en?'Selected date':'التاريخ المحدد'),subtitle:Text(date??''),trailing:IconButton(onPressed:chooseDate,icon:const Icon(Icons.edit_calendar_rounded)))),
 Wrap(spacing:8,runSpacing:8,children:[
 _Count(label:en?'Employees':'الموظفون',value:total.toString()),_Count(label:en?'Present':'حاضر',value:(counts['present']??0).toString()),
 _Count(label:en?'Absent':'غائب',value:(counts['absent']??0).toString()),_Count(label:en?'On leave':'إجازة',value:(counts['on_leave']??0).toString()),_Count(label:en?'No record':'بلا سجل',value:(counts['missing']??0).toString())]),
 const SizedBox(height:8),
 if(items.isEmpty) Padding(padding:const EdgeInsets.all(20),child:Text(en?'No working employees for this date.':'لا يوجد موظفون على رأس العمل في هذا التاريخ.'))
 else ...items.map((v)=>Card(child:ListTile(
 leading:CircleAvatar(child:Icon(v['status']=='present'?Icons.check_rounded:v['status']=='on_leave'?Icons.beach_access_rounded:v['status']=='missing'?Icons.help_outline_rounded:Icons.person_outline_rounded)),
 title:Text(v['full_name']?.toString()??''),subtitle:Text([
 if((v['department_name']?.toString()??'').isNotEmpty)v['department_name'].toString(),status(v['status']?.toString()??'missing'),
 if((v['check_in']?.toString()??'').isNotEmpty)(en?'In: ':'حضور: ')+v['check_in'].toString(),
 if((v['check_out']?.toString()??'').isNotEmpty)(en?'Out: ':'انصراف: ')+v['check_out'].toString(),
 if((v['work_mode']?.toString()??'').isNotEmpty)mode(v['work_mode'].toString()),
 ].join('\n')),isThreeLine:true))),
 ])));
 }
}
class _Count extends StatelessWidget{
 const _Count({required this.label,required this.value});final String label,value;
 @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.symmetric(horizontal:12,vertical:10),decoration:BoxDecoration(color:const Color(0xFFEAF0F7),borderRadius:BorderRadius.circular(10)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(label,style:const TextStyle(color:Color(0xFF64748B),fontSize:12)),const SizedBox(height:4),Text(value,style:const TextStyle(fontWeight:FontWeight.w800,fontSize:18))]));
}
