import 'package:flutter/material.dart';

class IncentiveVerificationPage extends StatefulWidget {
  const IncentiveVerificationPage({super.key,required this.english,required this.loadEvents,required this.submitAction});
  final bool english;
  final Future<Map<String,dynamic>> Function() loadEvents;
  final Future<Map<String,dynamic>> Function(Map<String,dynamic> body) submitAction;
  @override State<IncentiveVerificationPage> createState()=>_IncentiveVerificationPageState();
}

class _IncentiveVerificationPageState extends State<IncentiveVerificationPage>{
  bool loading=true,saving=false;
  String? error,notice;
  String filter='pending';
  List<Map<String,dynamic>> events=[];
  String t(String ar,String en)=>widget.english?en:ar;
  @override void initState(){super.initState();refresh();}
  Future<void> refresh() async{
    setState(()=>loading=true);
    try{
      final data=await widget.loadEvents();
      if(!mounted)return;
      setState(() { events=data['events'] is List?(data['events'] as List).whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList():<Map<String,dynamic>>[]; error=null; });
    }catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>loading=false);}
  }
  List<Map<String,dynamic>> get visibleEvents=>events.where((e){
    final status=e['status']?.toString()??'';
    if(filter=='pending')return status=='pending_verification';
    if(filter=='held')return status=='held'||e['award_status']=='held_currency';
    if(filter=='verified')return status=='verified';
    return true;
  }).toList();

  Future<void> verify(Map<String,dynamic> event) async{
    final note=TextEditingController();
    final yes=await showDialog<bool>(context:context,builder:(context)=>AlertDialog(
      title:Text(t('تأكيد إنجاز وإصدار حافز','Verify achievement and issue award')),
      content:Column(mainAxisSize:MainAxisSize.min,crossAxisAlignment:CrossAxisAlignment.stretch,children:[
        Text(t('سيُسجل الاستحقاق ويُثبت محاسبياً إذا اجتازت قواعد العمل والعملة. هذا لا يعني أن الحافز صُرف.','This records the earned award and posts its recognition journal if eligibility and currency rules pass. It does not mean the award was paid.')),
        const SizedBox(height:12),
        TextField(controller:note,maxLength:2000,maxLines:3,decoration:InputDecoration(labelText:t('ملاحظة التحقق (اختيارية)','Verification note (optional)'))),
      ]),
      actions:[TextButton(onPressed:()=>Navigator.pop(context,false),child:Text(t('إلغاء','Cancel'))),FilledButton(onPressed:()=>Navigator.pop(context,true),child:Text(t('تأكيد التحقق','Confirm verification'))],
    ))??false;
    if(!yes){note.dispose();return;}
    setState(() { saving=true; error=null; notice=null; });
    try{
      final result=await widget.submitAction({'action':'verify_event','event_id':event['id'],'verification_note':note.text.trim()});
      if(!mounted)return;
      setState(()=>notice=result['message']?.toString()??t('تمت معالجة الحدث.','Event processed.'));
      await refresh();
    }catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{note.dispose();if(mounted)setState(()=>saving=false);}
  }

  Widget eventCard(Map<String,dynamic> e){
    final s=Theme.of(context).colorScheme;
    final status=e['status']?.toString()??'';
    final held=status=='held'||e['award_status']=='held_currency';
    final color=held?s.error:status=='verified'?s.primary:s.tertiary;
    final title=widget.english?(e['incentive_name_en']??e['incentive_type']).toString():(e['incentive_name_ar']??e['incentive_type']).toString();
    final recipient=(e['recipient_name']?.toString().isNotEmpty==true)?e['recipient_name'].toString():t('مستفيد غير محدد','Recipient unavailable');
    final canVerify=e['can_verify']==true;
    return Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
      Row(crossAxisAlignment:CrossAxisAlignment.start,children:[
        Container(width:42,height:42,decoration:BoxDecoration(color:color.withValues(alpha:.12),borderRadius:BorderRadius.circular(13)),child:Icon(held?Icons.pause_circle_outline_rounded:status=='verified'?Icons.verified_rounded:Icons.fact_check_outlined,color:color)),
        const SizedBox(width:10),
        Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
          Text(title,style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800)),
          Text(' #${e['id']} · ${e['incentive_type']}',style:Theme.of(context).textTheme.bodySmall),
          const SizedBox(height:4),
          Text(recipient,style:Theme.of(context).textTheme.titleSmall),
          Text(t('الموظف #','Employee #')+'${e['recipient_employee_id']}',style:Theme.of(context).textTheme.bodySmall),
        ])),
        const SizedBox(width:8),
        Container(padding:const EdgeInsets.symmetric(horizontal:9,vertical:5),decoration:BoxDecoration(color:color.withValues(alpha:.12),borderRadius:BorderRadius.circular(20)),child:Text(status=='pending_verification'?t('بانتظار التحقق','Pending'):held?t('موقوف','Held'):t('تم التحقق','Verified'),style:TextStyle(color:color,fontSize:11,fontWeight:FontWeight.w800))),
      ]),
      const SizedBox(height:12),
      Wrap(spacing:8,runSpacing:8,children:[
        _Info(label:t('المصدر','Source'),value:'${e['source_table']} #${e['source_record_id']}'),
        _Info(label:t('تاريخ الإنجاز','Occurred'),value:(e['occurred_at']??'—').toString()),
        _Info(label:t('قيمة الحافز','Reward'),value:'${(e['reward_amount']??0).toString()} ${e['reward_currency_code']??''}'),
      ]),
      if((e['hold_reason']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:8),child:Text(e['hold_reason'].toString(),style:TextStyle(color:s.error,fontWeight:FontWeight.w600))),
      if((e['action_message']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:8),child:Text(e['action_message'].toString(),style:Theme.of(context).textTheme.bodySmall)),
      if(e['incentive_type']=='project_completion'&&status=='verified')Padding(padding:const EdgeInsets.only(top:8),child:Text(t('التوزيع الآلي: ','Automatic allocations: ')+ '${e['project_award_count']} · ${e['project_award_total']} ${e['reward_currency_code']}',style:Theme.of(context).textTheme.bodySmall)),
      if(canVerify)Padding(padding:const EdgeInsets.only(top:12),child:FilledButton.icon(onPressed:saving?null:()=>verify(e),icon:const Icon(Icons.verified_rounded),label:Text(t('تحقق وأصدر الحافز','Verify and issue award')))),
      if((e['award_id']??0)!=0)Padding(padding:const EdgeInsets.only(top:6),child:Text(t('سجل الحافز #','Award record #')+'${e['award_id']} · ${e['award_status']}',style:Theme.of(context).textTheme.bodySmall)),
    ])));
  }

  @override Widget build(BuildContext context){
    final s=Theme.of(context).colorScheme;
    return Scaffold(appBar:AppBar(title:Text(t('التحقق من الحوافز','Incentive verification')),actions:[IconButton(tooltip:t('تحديث','Refresh'),onPressed:loading||saving?null:refresh,icon:const Icon(Icons.refresh_rounded))]),
      body:RefreshIndicator(onRefresh:refresh,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(16),children:[
        Text(t('تُصدر الحوافز بعد التحقق من المستفيد. حوافز إكمال المشروع والتحصيل الروتيني لا تُصدر يدوياً.','Awards are issued only after recipient verification. Project-completion and routine-collection incentives are not manually issued.'),style:Theme.of(context).textTheme.bodySmall),
        const SizedBox(height:12),
        if(notice!=null)Card(color:s.primaryContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(notice!))),
        if(error!=null)Card(color:s.errorContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(error!,style:TextStyle(color:s.onErrorContainer)))),
        if(loading)const LinearProgressIndicator(),
        SegmentedButton<String>(segments:[
          ButtonSegment(value:'pending',label:Text(t('بانتظار التحقق','Pending'))),
          ButtonSegment(value:'held',label:Text(t('موقوف','Held'))),
          ButtonSegment(value:'verified',label:Text(t('تم التحقق','Verified'))),
          ButtonSegment(value:'all',label:Text(t('الكل','All'))),
        ],selected:{filter},onSelectionChanged:saving?null:(v)=>setState(()=>filter=v.first)),
        const SizedBox(height:12),
        if(!loading&&visibleEvents.isEmpty)Card(child:Padding(padding:const EdgeInsets.all(18),child:Text(t('لا توجد أحداث في هذا التصنيف.','No events in this filter.')))),
        for(final e in visibleEvents)Padding(padding:const EdgeInsets.only(bottom:10),child:eventCard(e)),
      ])));
  }
}

class _Info extends StatelessWidget{
  const _Info({required this.label,required this.value});
  final String label,value;
  @override Widget build(BuildContext context)=>Container(constraints:const BoxConstraints(minWidth:130),padding:const EdgeInsets.all(10),decoration:BoxDecoration(color:Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha:.5),borderRadius:BorderRadius.circular(12)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(label,style:Theme.of(context).textTheme.bodySmall),const SizedBox(height:3),Text(value,style:Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight:FontWeight.w800))]));
}
