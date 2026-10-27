import 'package:flutter/material.dart';

class AssignedNanniesPage extends StatefulWidget {
  const AssignedNanniesPage({super.key, required this.english, required this.loadPage});
  final bool english;
  final Future<Map<String, dynamic>> Function(String month, {int? nannyId}) loadPage;
  @override
  State<AssignedNanniesPage> createState() => _AssignedNanniesPageState();
}
class _AssignedNanniesPageState extends State<AssignedNanniesPage> {
  late String month;
  bool loading = true;
  String? error;
  int? selected;
  Map<String,dynamic> data = {};
  String t(String ar,String en) => widget.english ? en : ar;
  List<Map<String,dynamic>> rows(String key) {
    final v=data[key];
    return v is List ? v.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList() : <Map<String,dynamic>>[];
  }
  Map<String,dynamic>? obj(String key) => data[key] is Map ? Map<String,dynamic>.from(data[key] as Map) : null;
  @override void initState() {
    super.initState();
    final now=DateTime.now();
    month='${now.year.toString().padLeft(4,'0')}-${now.month.toString().padLeft(2,'0')}';
    _load();
  }
  Future<void> _load({int? nannyId,bool clear=false}) async {
    final id=clear ? null : (nannyId ?? selected);
    setState(()=>{loading=true,error=null,selected=id});
    try {
      final result=await widget.loadPage(month,nannyId:id);
      if(mounted) setState(()=>{data=result,loading=false});
    } catch(e) { if(mounted) setState(()=>{error=e.toString(),loading=false}); }
  }
  Future<void> _chooseMonth() async {
    final p=month.split('-');
    final picked=await showDatePicker(context:context,initialDate:DateTime(int.parse(p[0]),int.parse(p[1]),1),
      firstDate:DateTime(2000),lastDate:DateTime(2100),helpText:t('اختر شهر التوثيق','Choose verification month'));
    if(picked==null || !mounted) return;
    setState(()=>{month='${picked.year.toString().padLeft(4,'0')}-${picked.month.toString().padLeft(2,'0')}',selected=null});
    await _load(clear:true);
  }
  bool _flag(Map<String,dynamic> f,String k)=>(int.tryParse('${f[k]??0}')??0)==1;
  bool _verified(Map<String,dynamic> f)=>_flag(f,'is_orphan_verified')&&_flag(f,'is_mother_contact_verified')&&_flag(f,'is_bank_verified');
  bool _partial(Map<String,dynamic> f)=>f['is_orphan_verified']!=null||f['is_mother_contact_verified']!=null||f['is_bank_verified']!=null;
  String _batch(Object? s) {
    if(s==null || s.toString().isEmpty) return t('لا توجد دفعة','No batch');
    const en={'draft':'Draft','pending_approval':'Awaiting approval','approved':'Approved','transferred':'Transferred','received':'Received','returned':'Returned','cancelled':'Cancelled','voided':'Voided'};
    const ar={'draft':'مسودة','pending_approval':'بانتظار الاعتماد','approved':'معتمدة','transferred':'تم التحويل','received':'تم الاستلام','returned':'مرتجعة','cancelled':'ملغاة','voided':'ملغاة محاسبياً'};
    return (widget.english?en:ar)[s.toString()]??s.toString();
  }
  @override Widget build(BuildContext context) {
    final nannies=rows('nannies'); final detail=obj('detail'); final families=rows('families');
    return Scaffold(appBar:AppBar(title:Text(t('الحاضنات المعيّنات','Assigned nannies'))),
      body:RefreshIndicator(onRefresh:()=>_load(),child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(16),children:[
        Card(child:ListTile(leading:const Icon(Icons.calendar_month),title:Text(t('شهر التوثيق','Verification month')),subtitle:Text(month),trailing:const Icon(Icons.edit_calendar),onTap:_chooseMonth)),
        if(loading) const Padding(padding:EdgeInsets.all(24),child:Center(child:CircularProgressIndicator())),
        if(error!=null) Card(child:ListTile(title:Text(t('تعذر تحميل البيانات','Could not load data')),subtitle:Text(error!),trailing:IconButton(icon:const Icon(Icons.refresh),onPressed:_load))),
        if(!loading&&error==null) Padding(padding:const EdgeInsets.symmetric(vertical:8),child:Text(t('الأخصائيات (${nannies.length})','Nannies (${nannies.length})'),style:Theme.of(context).textTheme.titleMedium)),
        if(!loading&&error==null&&nannies.isEmpty) Card(child:Padding(padding:const EdgeInsets.all(16),child:Text(t('لا توجد أخصائيات ضمن نطاقك.','No nannies are assigned within your scope.')))),
        for(final n in nannies) Card(child:ListTile(
          leading:const CircleAvatar(child:Icon(Icons.support_agent)),title:Text('${n['full_name']??''}'),
          subtitle:Text('${t('الهاتف','Phone')}: ${n['phone']??'—'}\n${t('الأسر النشطة','Active families')}: ${n['active_families']??0} • ${t('موثقة','Verified')}: ${n['verified_now']??0} • ${_batch(n['batch_status'])}'),
          isThreeLine:true,trailing:Icon(selected==int.tryParse('${n['id']}')?Icons.expand_less:Icons.chevron_right),
          onTap:(){final id=int.tryParse('${n['id']}');if(id!=null)_load(nannyId:selected==id?null:id);},
        )),
        if(!loading&&detail!=null) Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
          Text(t('الأسر النشطة — ${detail['full_name']??''}','Active families — ${detail['full_name']??''}'),style:Theme.of(context).textTheme.titleMedium),
          if(families.isEmpty) Text(t('لا توجد أسر نشطة.','No active families.')),
          for(final f in families) Card(child:Padding(padding:const EdgeInsets.all(10),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
            Text('${f['family_code']??''} — ${f['mother_name']??''}',style:const TextStyle(fontWeight:FontWeight.bold)),
            Text('${t('الهاتف','Phone')}: ${f['mother_phone']??'—'}'),
            Text('${t('البنك','Bank')}: ${f['bank_name']??'—'} • ${f['bank_account_number']??'—'}'),
            Text(_verified(f)?t('التوثيق مكتمل ✓','Verification complete ✓'):_partial(f)?t('التوثيق جزئي','Partially verified'):t('لم تُوثّق','Not verified')),
            if((f['verification_notes']??'').toString().trim().isNotEmpty) Text('${t('ملاحظات','Notes')}: ${f['verification_notes']}'),
          ]))),
        ]))),
      ])));
  }
}
