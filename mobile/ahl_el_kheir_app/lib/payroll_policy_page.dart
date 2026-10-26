import 'package:flutter/material.dart';

class PayrollPolicyPage extends StatefulWidget {
  const PayrollPolicyPage({super.key, required this.english, required this.loadPage, required this.submitAction});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;
  @override State<PayrollPolicyPage> createState() => _PayrollPolicyPageState();
}

class _PayrollPolicyPageState extends State<PayrollPolicyPage> {
  final formKey = GlobalKey<FormState>();
  final effective = TextEditingController();
  final absencePercent = TextEditingController(text: '100');
  final unpaidPercent = TextEditingController(text: '100');
  final paidPercent = TextEditingController(text: '0');
  final overtimeMultiplier = TextEditingController(text: '1');
  final rounding = TextEditingController(text: '2');
  final notes = TextEditingController(text: 'الإعدادات الافتراضية — يجب اعتمادها من إدارة الجمعية');
  bool loading = true, saving = false;
  bool absenceEnabled = true, unpaidLeaveEnabled = true, lateEnabled = false, earlyDepartureEnabled = false, overtimeEnabled = false;
  String today = '';
  String? error, notice;
  Map<String,dynamic>? activePolicy, nextPolicy;
  List<Map<String,dynamic>> policies = [];
  String t(String ar,String en)=>widget.english?en:ar;

  @override void initState(){super.initState();refresh();}
  @override void dispose(){effective.dispose();absencePercent.dispose();unpaidPercent.dispose();paidPercent.dispose();overtimeMultiplier.dispose();rounding.dispose();notes.dispose();super.dispose();}

  Future<void> refresh() async {
    setState(()=>loading=true);
    try {
      final data=await widget.loadPage();
      if(!mounted)return;
      setState((){
        today=data['today']?.toString()??'';
        activePolicy=data['active_policy'] is Map?Map<String,dynamic>.from(data['active_policy'] as Map):null;
        nextPolicy=data['next_policy'] is Map?Map<String,dynamic>.from(data['next_policy'] as Map):null;
        policies=data['policies'] is List?(data['policies'] as List).whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList():[];
        error=null;
      });
    } catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>loading=false);}
  }

  Future<void> chooseEffectiveDate() async {
    final d=DateTime.tryParse(today)??DateTime.now();
    final first=DateTime(d.year,d.month,d.day).add(const Duration(days:1));
    final existing=DateTime.tryParse(effective.text);
    final picked=await showDatePicker(context:context,initialDate:existing!=null&&!existing.isBefore(first)?existing:first,firstDate:first,lastDate:DateTime(2100,12,31));
    if(picked!=null)setState(()=>effective.text='${picked.year.toString().padLeft(4,'0')}-${picked.month.toString().padLeft(2,'0')}-${picked.day.toString().padLeft(2,'0')}');
  }

  String? numberValidator(String? value,{required double min,required double max,bool integer=false}){
    final n=double.tryParse((value??'').trim());
    if(n==null||!n.isFinite)return t('أدخل رقماً صالحاً.','Enter a valid number.');
    if(n<min||n>max)return t('القيمة خارج النطاق المسموح.','Value is outside the allowed range.');
    if(integer&&n!=n.roundToDouble())return t('أدخل عدداً صحيحاً.','Enter a whole number.');
    return null;
  }

  Future<void> save() async {
    if(!formKey.currentState!.validate())return;
    final p=DateTime.tryParse(effective.text), d=DateTime.tryParse(today);
    if(p==null||d==null||!p.isAfter(DateTime(d.year,d.month,d.day))){setState(()=>error=t('يجب أن يكون تاريخ السريان في المستقبل.','The effective date must be in the future.'));return;}
    if((double.tryParse(paidPercent.text)??-1)!=0){setState(()=>error=t('خصم الإجازة المدفوعة يجب أن يساوي صفراً.','Paid-leave deduction must be zero.'));return;}
    setState(() { saving=true; error=null; notice=null; });
    try{
      final result=await widget.submitAction({
        'action':'create','effective_from':effective.text,
        'absence_enabled':absenceEnabled,'absence_deduction_percent':double.parse(absencePercent.text),
        'unpaid_leave_enabled':unpaidLeaveEnabled,'unpaid_leave_deduction_percent':double.parse(unpaidPercent.text),
        'paid_leave_deduction_percent':double.parse(paidPercent.text),'late_enabled':lateEnabled,
        'early_departure_enabled':earlyDepartureEnabled,'overtime_enabled':overtimeEnabled,
        'overtime_multiplier':double.parse(overtimeMultiplier.text),'rounding_decimals':int.parse(rounding.text),'notes':notes.text.trim(),
      });
      if(!mounted)return;
      setState(()=>notice=result['message']?.toString()??t('تم إنشاء إصدار السياسة.','Policy version created.'));
      effective.clear();
      await refresh();
    }catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>saving=false);}
  }

  Widget metric(String label,String value){
    final s=Theme.of(context).colorScheme;
    return Container(constraints:const BoxConstraints(minWidth:130),padding:const EdgeInsets.all(11),decoration:BoxDecoration(color:s.surfaceContainerHighest.withValues(alpha:.55),borderRadius:BorderRadius.circular(13)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
      Text(label,style:Theme.of(context).textTheme.bodySmall),const SizedBox(height:4),Text(value,style:Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight:FontWeight.w800)),
    ]));
  }

  Widget policyCard(Map<String,dynamic> p){
    final s=Theme.of(context).colorScheme;final active=p['is_active']==true;final future=p['is_future']==true;
    final color=active?s.primary:future?s.tertiary:s.outline;
    final status=active?t('سارية حالياً','Active now'):future?t('مجدولة','Scheduled'):t('سجل تاريخي','Historical');
    return Card(child:Padding(padding:const EdgeInsets.all(13),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
      Row(children:[Expanded(child:Text('V${p['version_no']} · ${p['effective_from']}',style:Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight:FontWeight.w800))),Container(padding:const EdgeInsets.symmetric(horizontal:9,vertical:5),decoration:BoxDecoration(color:color.withValues(alpha:.12),borderRadius:BorderRadius.circular(20)),child:Text(status,style:TextStyle(color:color,fontSize:11,fontWeight:FontWeight.w800)))]),
      const SizedBox(height:8),
      Wrap(spacing:8,runSpacing:8,children:[
        metric(t('خصم الغياب','Absence deduction'),'${p['absence_enabled']==true?t('مفعل','On'):t('معطل','Off')} · ${p['absence_deduction_percent']}%'),
        metric(t('الإجازة غير المدفوعة','Unpaid leave'),'${p['unpaid_leave_enabled']==true?t('مفعل','On'):t('معطل','Off')} · ${p['unpaid_leave_deduction_percent']}%'),
        metric(t('الإجازة المدفوعة','Paid leave'),'${p['paid_leave_deduction_percent']}%'),
        metric(t('العمل الإضافي','Overtime'),'${p['overtime_enabled']==true?t('مفعل','On'):t('معطل','Off')} · ×${p['overtime_multiplier']}'),
      ]),
      if((p['notes']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:7),child:Text(p['notes'].toString(),style:Theme.of(context).textTheme.bodySmall)),
      if((p['created_by_name']?.toString()??'').isNotEmpty)Padding(padding:const EdgeInsets.only(top:5),child:Text(t('أنشأه: ','Created by: ')+p['created_by_name'].toString(),style:Theme.of(context).textTheme.bodySmall)),
    ])));
  }

  Widget percentField(String label,TextEditingController controller)=>TextFormField(controller:controller,keyboardType:const TextInputType.numberWithOptions(decimal:true),decoration:InputDecoration(labelText:label,suffixText:'%'),validator:(v)=>numberValidator(v,min:0,max:100));

  @override Widget build(BuildContext context){
    final s=Theme.of(context).colorScheme;
    return Scaffold(appBar:AppBar(title:Text(t('سياسة الرواتب','Payroll policy')),actions:[IconButton(tooltip:t('تحديث','Refresh'),onPressed:loading||saving?null:refresh,icon:const Icon(Icons.refresh_rounded))]),
      body:RefreshIndicator(onRefresh:refresh,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(16),children:[
        if(notice!=null)Card(color:s.primaryContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(notice!))),
        if(error!=null)Card(color:s.errorContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(error!,style:TextStyle(color:s.onErrorContainer)))),
        if(loading)const LinearProgressIndicator(),
        Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          Text(t('السياسة السارية حالياً','Current active policy'),style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800)),
          const SizedBox(height:8),
          if(activePolicy==null)Text(t('لا توجد سياسة سارية حتى تاريخ اليوم.','No policy is active as of today.')) else policyCard(activePolicy!),
          if(nextPolicy!=null)Padding(padding:const EdgeInsets.only(top:8),child:Text(t('الإصدار القادم: ','Next version: ')+'V${nextPolicy!['version_no']} · ${nextPolicy!['effective_from']}')),
        ]))),
        Card(child:Padding(padding:const EdgeInsets.all(14),child:Form(key:formKey,child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          Text(t('إنشاء إصدار مستقبلي','Create a future version'),style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800)),
          const SizedBox(height:5),
          Text(t('الإصدارات السابقة لا تتغير، ولا يعاد احتساب مسيرات الرواتب المعتمدة.','Previous versions remain unchanged; approved payrolls are not recalculated.'),style:Theme.of(context).textTheme.bodySmall),
          const SizedBox(height:12),
          TextFormField(key:const Key('payroll-policy-effective-date'),controller:effective,readOnly:true,onTap:saving?null:chooseEffectiveDate,decoration:InputDecoration(labelText:t('تاريخ السريان *','Effective date *'),hintText:'YYYY-MM-DD',suffixIcon:IconButton(onPressed:saving?null:chooseEffectiveDate,icon:const Icon(Icons.calendar_month_rounded))),validator:(v)=>(v==null||DateTime.tryParse(v)==null)?t('اختر تاريخ السريان.','Choose an effective date.'):null),
          const SizedBox(height:10),percentField(t('خصم الغياب','Absence deduction'),absencePercent),
          const SizedBox(height:10),percentField(t('خصم الإجازة غير المدفوعة','Unpaid-leave deduction'),unpaidPercent),
          const SizedBox(height:10),percentField(t('خصم الإجازة المدفوعة (يجب أن يكون 0)','Paid-leave deduction (must be 0)'),paidPercent),
          const SizedBox(height:10),TextFormField(controller:overtimeMultiplier,keyboardType:const TextInputType.numberWithOptions(decimal:true),decoration:InputDecoration(labelText:t('معامل العمل الإضافي','Overtime multiplier')),validator:(v)=>numberValidator(v,min:.0001,max:10000)),
          const SizedBox(height:10),TextFormField(controller:rounding,keyboardType:TextInputType.number,decoration:InputDecoration(labelText:t('منازل التقريب (0–4)','Rounding decimals (0–4)')),validator:(v)=>numberValidator(v,min:0,max:4,integer:true)),
          const SizedBox(height:8),
          SwitchListTile(contentPadding:EdgeInsets.zero,value:absenceEnabled,onChanged:saving?null:(v)=>setState(()=>absenceEnabled=v),title:Text(t('تفعيل خصم الغياب','Enable absence deduction'))),
          SwitchListTile(contentPadding:EdgeInsets.zero,value:unpaidLeaveEnabled,onChanged:saving?null:(v)=>setState(()=>unpaidLeaveEnabled=v),title:Text(t('تفعيل خصم الإجازة غير المدفوعة','Enable unpaid-leave deduction'))),
          SwitchListTile(contentPadding:EdgeInsets.zero,value:lateEnabled,onChanged:saving?null:(v)=>setState(()=>lateEnabled=v),title:Text(t('تفعيل معالجة التأخير','Enable lateness treatment'))),
          SwitchListTile(contentPadding:EdgeInsets.zero,value:earlyDepartureEnabled,onChanged:saving?null:(v)=>setState(()=>earlyDepartureEnabled=v),title:Text(t('تفعيل معالجة الانصراف المبكر','Enable early-departure treatment'))),
          SwitchListTile(contentPadding:EdgeInsets.zero,value:overtimeEnabled,onChanged:saving?null:(v)=>setState(()=>overtimeEnabled=v),title:Text(t('تفعيل العمل الإضافي','Enable overtime treatment'))),
          const SizedBox(height:8),TextFormField(controller:notes,maxLength:1000,maxLines:3,decoration:InputDecoration(labelText:t('ملاحظات','Notes'))),
          const SizedBox(height:12),FilledButton.icon(onPressed:saving?null:save,icon:saving?const SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2)):const Icon(Icons.add_rounded),label:Text(t('إنشاء إصدار السياسة','Create policy version'))),
        ])))),
        const SizedBox(height:4),Text(t('سجل إصدارات السياسة','Policy version history'),style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800)),
        if(!loading&&policies.isEmpty)Card(child:Padding(padding:const EdgeInsets.all(14),child:Text(t('لا توجد إصدارات سياسة مسجلة.','No policy versions recorded.')))),
        for(final p in policies)policyCard(p),
      ])));
  }
}
