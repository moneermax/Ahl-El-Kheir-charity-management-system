import 'package:flutter/material.dart';

class AttendancePolicyPage extends StatefulWidget {
  const AttendancePolicyPage({super.key, required this.english, required this.loadPage, required this.submitAction});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;
  @override
  State<AttendancePolicyPage> createState() => _AttendancePolicyPageState();
}

class _AttendancePolicyPageState extends State<AttendancePolicyPage> {
  bool loading = true, saving = false;
  String? error, notice;
  String today = '';
  Map<String, dynamic>? active;
  List<Map<String, dynamic>> policies = [];
  int? editingId;
  final name = TextEditingController(), effective = TextEditingController(), start = TextEditingController(text: '07:00'),
      end = TextEditingController(text: '16:00'), cutoff = TextEditingController(text: '16:00'),
      absenceTime = TextEditingController(text: '16:00'), notes = TextEditingController();
  final formKey = GlobalKey<FormState>();
  final Set<int> workingDays = {1, 2, 3, 4, 5};
  String workMode = 'remote';
  bool autoLogin = true, autoAbsence = true;
  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() { super.initState(); refresh(); }
  @override
  void dispose() { name.dispose(); effective.dispose(); start.dispose(); end.dispose(); cutoff.dispose(); absenceTime.dispose(); notes.dispose(); super.dispose(); }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadPage();
      if (!mounted) return;
      setState(() {
        today = data['today']?.toString() ?? '';
        active = data['active_policy'] is Map ? Map<String, dynamic>.from(data['active_policy'] as Map) : null;
        policies = data['policies'] is List ? (data['policies'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : [];
      });
      if (editingId != null && !policies.any((p) => p['id'].toString() == editingId.toString() && p['can_manage'] == true)) _resetForm();
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }

  void _resetForm() {
    setState(() {
      editingId = null; name.clear(); effective.clear(); start.text = '07:00'; end.text = '16:00';
      cutoff.text = '16:00'; absenceTime.text = '16:00'; notes.clear();
      workingDays..clear()..addAll([1,2,3,4,5]); workMode = 'remote'; autoLogin = true; autoAbsence = true;
    });
  }

  void _edit(Map<String, dynamic> p) {
    setState(() {
      editingId = int.tryParse(p['id']?.toString() ?? '');
      name.text = p['policy_name']?.toString() ?? ''; effective.text = p['effective_from']?.toString() ?? '';
      start.text = (p['working_start_time']?.toString() ?? '07:00').substring(0,5);
      end.text = (p['working_end_time']?.toString() ?? '16:00').substring(0,5);
      cutoff.text = (p['attendance_cutoff_time']?.toString() ?? '16:00').substring(0,5);
      absenceTime.text = (p['absence_finalization_time']?.toString() ?? '16:00').substring(0,5);
      notes.text = p['notes']?.toString() ?? '';
      workingDays..clear()..addAll((p['working_days']?.toString() ?? '1,2,3,4,5').split(',').map((s) => int.tryParse(s) ?? 0).where((d) => d >= 1 && d <= 7));
      workMode = p['default_work_mode']?.toString() ?? 'remote';
      autoLogin = p['auto_login_attendance'] == true; autoAbsence = p['auto_absence_enabled'] == true;
    });
  }

  Future<void> _chooseDate() async {
    final first = DateTime.tryParse(today) ?? DateTime.now();
    final parsed = DateTime.tryParse(effective.text) ?? first;
    final picked = await showDatePicker(context: context, initialDate: parsed.isBefore(first) ? first : parsed, firstDate: first, lastDate: DateTime(2100));
    if (picked != null) setState(() => effective.text = '${picked.year.toString().padLeft(4,'0')}-${picked.month.toString().padLeft(2,'0')}-${picked.day.toString().padLeft(2,'0')}');
  }

  Future<void> _chooseTime(TextEditingController controller) async {
    final parts = controller.text.split(':');
    final picked = await showTimePicker(context: context, initialTime: TimeOfDay(hour: int.tryParse(parts[0]) ?? 8, minute: int.tryParse(parts.length > 1 ? parts[1] : '0') ?? 0));
    if (picked != null) setState(() => controller.text = '${picked.hour.toString().padLeft(2,'0')}:${picked.minute.toString().padLeft(2,'0')}');
  }

  Future<void> _save() async {
    if (!formKey.currentState!.validate()) return;
    if (workingDays.isEmpty) { setState(() => error = t('اختر يوم عمل واحداً على الأقل.','Select at least one working day.')); return; }
    setState(() { saving = true; error = null; notice = null; });
    try {
      final result = await widget.submitAction({
        'action': editingId == null ? 'create' : 'update', if (editingId != null) 'policy_id': editingId,
        'policy_name': name.text.trim(), 'effective_from': effective.text,
        'working_start_time': start.text, 'working_end_time': end.text,
        'working_days': (workingDays.toList()..sort()).join(','),
        'attendance_cutoff_time': cutoff.text, 'absence_finalization_time': absenceTime.text,
        'auto_login_attendance': autoLogin, 'auto_absence_enabled': autoAbsence,
        'default_work_mode': workMode, 'notes': notes.text.trim(),
      });
      if (!mounted) return;
      _resetForm();
      setState(() => notice = result['message']?.toString() ?? t('تم حفظ السياسة.','Policy saved.'));
      await refresh();
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => saving = false); }
  }

  Future<void> _delete(Map<String, dynamic> p) async {
    final yes = await showDialog<bool>(context: context, builder: (context) => AlertDialog(
      title: Text(t('حذف إصدار السياسة؟','Delete policy version?')),
      content: Text(t('لا يمكن حذف سياسة منتهية أو سياسة اليوم بعد تسجيل حركة حضور.','An expired policy or today’s policy after attendance is locked cannot be deleted.')),
      actions: [TextButton(onPressed:()=>Navigator.pop(context,false),child:Text(t('إلغاء','Cancel'))),FilledButton(onPressed:()=>Navigator.pop(context,true),child:Text(t('حذف','Delete')))],
    )) ?? false;
    if (!yes) return;
    setState(() { saving = true; error = null; notice = null; });
    try {
      final result = await widget.submitAction({'action':'delete','policy_id':p['id']});
      if (!mounted) return;
      if (editingId?.toString() == p['id']?.toString()) _resetForm();
      setState(() => notice = result['message']?.toString() ?? t('تم حذف الإصدار.','Version deleted.'));
      await refresh();
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => saving = false); }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final dayNames = widget.english ? const ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] : const ['الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت','الأحد'];
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: Text(t('سياسة الحضور والانصراف','Attendance policy')),actions:[IconButton(tooltip:t('تحديث','Refresh'),onPressed:loading||saving?null:refresh,icon:const Icon(Icons.refresh_rounded))]),
      body: RefreshIndicator(onRefresh:refresh,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(14),children:[
        if(notice!=null) Card(color:scheme.primaryContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(notice!,style:TextStyle(color:scheme.onPrimaryContainer)))),
        if(error!=null) Card(color:scheme.errorContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(error!,style:TextStyle(color:scheme.onErrorContainer)))),
        Card(child:Padding(padding:const EdgeInsets.all(15),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          Text(t('السياسة السارية','Active policy'),style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800)),
          const SizedBox(height:8),
          if(active==null) Text(t('لا توجد سياسة سارية. لن يتم احتساب الحضور التلقائي حتى إصدار سياسة.','No active policy. Automatic attendance remains unavailable until a policy is issued.'))
          else ...[
            Text('V${active!['version_no']} · ${active!['policy_name']}',style:const TextStyle(fontWeight:FontWeight.w800,color:green)),
            Text('${active!['effective_from']} · ${active!['working_start_time'].toString().substring(0,5)}–${active!['working_end_time'].toString().substring(0,5)}'),
            Text(t('أيام العمل: ','Working days: ')+_daysLabel(active!['working_days'],dayNames)),
            Text(t('حد الحضور: ','Attendance cutoff: ')+active!['attendance_cutoff_time'].toString().substring(0,5)+' · '+t('تثبيت الغياب: ','Absence finalization: ')+active!['absence_finalization_time'].toString().substring(0,5)),
            Text(t('التسجيل التلقائي: ','Auto check-in: ')+(active!['auto_login_attendance']==true?t('مفعل','On'):t('معطل','Off'))+' · '+t('تثبيت الغياب: ','Auto absence: ')+(active!['auto_absence_enabled']==true?t('مفعل','On'):t('معطل','Off'))),
          ],
          if(loading) const Padding(padding:EdgeInsets.only(top:8),child:LinearProgressIndicator()),
        ]))),
        Card(child:Padding(padding:const EdgeInsets.all(15),child:Form(key:formKey,child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          Text(editingId==null?t('إصدار سياسة جديد','New policy version'):t('تعديل إصدار السياسة','Edit policy version'),style:const TextStyle(fontSize:16,fontWeight:FontWeight.w800)),
          const SizedBox(height:12),
          TextFormField(controller:name,decoration:InputDecoration(labelText:t('اسم السياسة','Policy name')),validator:(v)=>(v??'').trim().isEmpty?t('هذا الحقل مطلوب','Required'):null),
          const SizedBox(height:10),
          TextFormField(controller:effective,readOnly:true,onTap:_chooseDate,decoration:InputDecoration(labelText:t('تاريخ السريان','Effective from'),suffixIcon:const Icon(Icons.calendar_month_rounded)),validator:(v)=>(v??'').isEmpty?t('اختر تاريخ السريان','Choose an effective date'):null),
          const SizedBox(height:10),
          DropdownButtonFormField<String>(initialValue:workMode,decoration:InputDecoration(labelText:t('نمط العمل الافتراضي','Default work mode')),items:[
            DropdownMenuItem(value:'remote',child:Text(t('عن بُعد','Remote'))),DropdownMenuItem(value:'onsite',child:Text(t('من المكتب','On-site'))),DropdownMenuItem(value:'hybrid',child:Text(t('هجين','Hybrid'))),
          ],onChanged:saving?null:(v){if(v!=null)setState(()=>workMode=v);}),
          const SizedBox(height:10),
          for(final field in [(start,t('بداية العمل','Work starts')),(end,t('نهاية العمل','Work ends')),(cutoff,t('آخر وقت للحضور','Attendance cutoff')),(absenceTime,t('وقت تثبيت الغياب','Absence finalization'))]) ...[
            TextFormField(controller:field.$1,readOnly:true,onTap:()=>_chooseTime(field.$1),decoration:InputDecoration(labelText:field.$2,suffixIcon:const Icon(Icons.schedule_rounded)),validator:(v)=>RegExp(r'^([01]\d|2[0-3]):[0-5]\d$').hasMatch(v??'')?null:t('وقت غير صالح','Invalid time')),
            const SizedBox(height:8),
          ],
          Text(t('أيام العمل الأسبوعية','Weekly working days'),style:const TextStyle(fontWeight:FontWeight.w800)),
          const SizedBox(height:6),
          Wrap(spacing:6,runSpacing:6,children:[for(var i=1;i<=7;i++)FilterChip(label:Text(dayNames[i-1]),selected:workingDays.contains(i),onSelected:saving?null:(v){setState(()=>v?workingDays.add(i):workingDays.remove(i));})]),
          const SizedBox(height:10),
          SwitchListTile(contentPadding:EdgeInsets.zero,title:Text(t('تسجيل الحضور عند تسجيل الدخول','Auto check-in on login')),value:autoLogin,onChanged:saving?null:(v)=>setState(()=>autoLogin=v)),
          SwitchListTile(contentPadding:EdgeInsets.zero,title:Text(t('تثبيت الغياب تلقائياً','Automatic absence finalization')),value:autoAbsence,onChanged:saving?null:(v)=>setState(()=>autoAbsence=v)),
          TextFormField(controller:notes,maxLines:3,decoration:InputDecoration(labelText:t('ملاحظات','Notes'))),
          const SizedBox(height:12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton(
                onPressed: saving ? null : _save,
                child: saving
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(t('حفظ الإصدار', 'Save version')),
              ),
              if (editingId != null)
                OutlinedButton(
                  onPressed: saving ? null : _resetForm,
                  child: Text(t('إلغاء التعديل', 'Cancel edit')),
                ),
            ],
          ),
        ])))),
        const SizedBox(height:6),Text(t('سجل إصدارات السياسة','Policy version history'),style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800)),
        for(final p in policies)Card(child:Padding(padding:const EdgeInsets.all(12),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          Text('V${p['version_no']} · ${p['policy_name']}',style:const TextStyle(fontWeight:FontWeight.w800)),
          Text('${p['effective_from']} · ${p['working_start_time'].toString().substring(0,5)}–${p['working_end_time'].toString().substring(0,5)}'),
          Text(t('النمط: ','Mode: ')+t(p['default_work_mode']=='remote'?'عن بُعد':p['default_work_mode']=='onsite'?'من المكتب':'هجين',p['default_work_mode']=='remote'?'Remote':p['default_work_mode']=='onsite'?'On-site':'Hybrid')+' · '+t('أيام العمل: ','Working days: ')+_daysLabel(p['working_days'],dayNames)),
          if(p['locked_by_attendance']==true)Text(t('مقفلة بعد تسجيل حركة حضور اليوم','Locked after operational attendance today'),style:TextStyle(color:Theme.of(context).colorScheme.error,fontWeight:FontWeight.w700)),
          Wrap(spacing:8,children:[OutlinedButton(onPressed:saving||p['can_manage']!=true?null:()=>_edit(p),child:Text(t('تعديل','Edit')),),TextButton(onPressed:saving||p['can_manage']!=true?null:()=>_delete(p),child:Text(t('حذف','Delete')))]),
        ]))),
      ])),
    );
  }

  String _daysLabel(dynamic value, List<String> names) => (value?.toString() ?? '').split(',').map((s)=>int.tryParse(s)??0).where((d)=>d>=1&&d<=7).map((d)=>names[d-1]).join(', ');
}
