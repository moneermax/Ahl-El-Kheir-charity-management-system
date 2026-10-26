import 'package:flutter/material.dart';

class OrphanFormPage extends StatefulWidget {
  const OrphanFormPage({super.key,required this.childId,required this.english,required this.loadForm,required this.saveForm});
  final int childId;
  final bool english;
  final Future<Map<String,dynamic>> Function(int childId) loadForm;
  final Future<Map<String,dynamic>> Function(int childId,Map<String,dynamic> values) saveForm;
  @override
  State<OrphanFormPage> createState()=>_OrphanFormPageState();
}
class _OrphanFormPageState extends State<OrphanFormPage>{
  bool loading=true,saving=false,canEdit=false;
  String? error;
  Map<String,dynamic> child={},family={},options={};
  List<Map<String,dynamic>> sponsorships=[];
  final Map<String,TextEditingController> controllers={};
  String health='سليم',psych='سليم',marital='أرملة';
  String t(String ar,String en)=>widget.english?en:ar;
  @override void initState(){super.initState();refresh();}
  @override void dispose(){for(final c in controllers.values){c.dispose();}super.dispose();}
  Future<void> refresh()async{
    setState(() { loading = true; error = null; });
    try{
      final data=await widget.loadForm(widget.childId);
      if(!mounted)return;
      final nextChild=data['child'] is Map?Map<String,dynamic>.from(data['child'] as Map):<String,dynamic>{};
      final nextFamily=data['family'] is Map?Map<String,dynamic>.from(data['family'] as Map):<String,dynamic>{};
      for(final c in controllers.values){c.dispose();}controllers.clear();
      for(final key in ['nationality','health_status_other','psychological_state_other','guardian_name','guardian_relationship','father_death_cause']){
        final value=key=='father_death_cause'?nextFamily[key]:nextChild[key];
        controllers[key]=TextEditingController(text:value?.toString()??'');
      }
      setState((){
        child=nextChild;family=nextFamily;canEdit=data['can_edit']==true;
        options=data['options'] is Map?Map<String,dynamic>.from(data['options'] as Map):{};
        sponsorships=data['sponsorships'] is List?(data['sponsorships'] as List).whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList():[];
        health=child['health_status']?.toString().isNotEmpty==true?child['health_status'].toString():'سليم';
        psych=child['psychological_state']?.toString().isNotEmpty==true?child['psychological_state'].toString():'سليم';
        marital=family['mother_marital_status']?.toString().isNotEmpty==true?family['mother_marital_status'].toString():'أرملة';
      });
    }catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>loading=false);}
  }
  List<String> choices(String key,List<String> fallback){
    final raw=options[key];return raw is List?raw.map((e)=>e.toString()).toList():fallback;
  }
  Widget textField(String key,String label,{int maxLength=150}){
    return Padding(padding:const EdgeInsets.only(bottom:12),child:TextFormField(
      controller:controllers[key],enabled:canEdit&&!saving,maxLength:maxLength,
      decoration:InputDecoration(labelText:label,border:const OutlineInputBorder()),
    ));
  }
  Widget dropdown(String current,String label,List<String> items,ValueChanged<String> changed){
    final values=items.contains(current)?items:(items.isEmpty?<String>[]:[items.first]);
    return Padding(padding:const EdgeInsets.only(bottom:12),child:DropdownButtonFormField<String>(
      initialValue:values.isEmpty?null:values.firstWhere((v)=>v==current,orElse:()=>values.first),
      isExpanded:true,decoration:InputDecoration(labelText:label,border:const OutlineInputBorder()),
      items:values.map((v)=>DropdownMenuItem<String>(value:v,child:Text(v))).toList(),
      onChanged:canEdit&&!saving?(v){if(v!=null)changed(v);}:null,
    ));
  }
  Future<void> save()async{
    FocusScope.of(context).unfocus();
    final values=<String,dynamic>{
      'nationality':controllers['nationality']!.text.trim(),'health_status':health,
      'health_status_other':controllers['health_status_other']!.text.trim(),
      'psychological_state':psych,'psychological_state_other':controllers['psychological_state_other']!.text.trim(),
      'guardian_name':controllers['guardian_name']!.text.trim(),'guardian_relationship':controllers['guardian_relationship']!.text.trim(),
      'father_death_cause':controllers['father_death_cause']!.text.trim(),'mother_marital_status':marital,
    };
    setState(() { saving = true; error = null; });
    try{
      await widget.saveForm(widget.childId,values);
      if(!mounted)return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(t('تم حفظ الاستمارة.','Orphan form saved.'))));
      await refresh();
    }catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.toString())));}
    finally{if(mounted)setState(()=>saving=false);}
  }
  @override Widget build(BuildContext context){
    return Scaffold(appBar:AppBar(title:Text(t('استمارة اليتيم','Orphan form')),actions:[IconButton(onPressed:loading?null:refresh,icon:const Icon(Icons.refresh_rounded))]),
      body:loading?const Center(child:CircularProgressIndicator()):error!=null?Center(child:Text(error!,textAlign:TextAlign.center)):ListView(
        padding:const EdgeInsets.all(16),children:[
          Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
            Text(child['child_name']?.toString()??'',style:Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight:FontWeight.w800)),
            Text(t('رقم اليتيم: ','Child ID: ')+widget.childId.toString()),
            Text(t('رقم الاستمارة: ','Form serial: ')+(child['form_serial']?.toString().isNotEmpty==true?child['form_serial'].toString():'AK-_____')),
            Text(t('الأسرة: ','Family: ')+(family['family_code']?.toString()??'')),
            Text(t('الأم: ','Mother: ')+(family['mother_name']?.toString()??'')),
            Text(t('الهاتف: ','Phone: ')+(family['mother_phone']?.toString()??'')),
            Text(t('المدينة: ','City: ')+(family['city']?.toString()??'')),
            Text(t('العنوان: ','Address: ')+(family['address']?.toString()??'')),
            Text(t('الميلاد: ','Birth date: ')+(child['birth_date']?.toString()??'')),
            Text(t('الجنس: ','Gender: ')+(child['gender']?.toString()??'')),
            Text(t('الصورة: ','Photo: ')+(child['photo_path']?.toString().isNotEmpty==true?child['photo_path'].toString():t('غير مرفوعة','Not uploaded'))),
          ]))),
          Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
            Text(t('البيانات الصحية والاجتماعية','Health and social details'),style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800)),
            const SizedBox(height:12),
            textField('nationality',t('الجنسية','Nationality'),maxLength:50),
            dropdown(health,t('الحالة الصحية','Health status'),choices('health',['سليم']), (v)=>setState(()=>health=v)),
            if(health=='أخرى')textField('health_status_other',t('تفاصيل الحالة الصحية','Health details')),
            dropdown(psych,t('الحالة النفسية','Psychological status'),choices('psych',['سليم']),(v)=>setState(()=>psych=v)),
            if(psych=='أخرى')textField('psychological_state_other',t('تفاصيل الحالة النفسية','Psychological details')),
            textField('guardian_name',t('اسم الوصي','Guardian name')),
            textField('guardian_relationship',t('صلة الوصي','Guardian relationship'),maxLength:60),
            textField('father_death_cause',t('سبب وفاة الأب','Father death cause')),
            dropdown(marital,t('الحالة الاجتماعية للأم','Mother marital status'),choices('marital',['أرملة']),(v)=>setState(()=>marital=v)),
          ]))),
          Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
            Text(t('الكفالات المرتبطة','Linked sponsorships'),style:Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight:FontWeight.w800)),
            if(sponsorships.isEmpty)Text(t('لا توجد كفالات مرتبطة.','No linked sponsorships.')),
            for(final s in sponsorships)ListTile(contentPadding:EdgeInsets.zero,title:Text((s['sponsor_name']?.toString()??'')+' ('+(s['sponsor_code']?.toString()??'')+')'),
              subtitle:Text((s['status']?.toString()??'')+' · '+(s['start_date']?.toString()??'')),trailing:s.containsKey('monthly_amount')?Text((s['monthly_amount']?.toString()??'0')+' SDG'):null),
          ]))),
          if(error!=null)Text(error!,style:const TextStyle(color:Colors.red)),
          if(canEdit)FilledButton.icon(onPressed:saving?null:save,icon:saving?const SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2)):const Icon(Icons.save_rounded),
            label:Text(saving?t('جارٍ الحفظ…','Saving…'):t('حفظ الاستمارة','Save form'))),
        ]));
  }
}
