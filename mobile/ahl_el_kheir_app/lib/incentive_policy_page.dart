
import 'dart:convert';
import 'package:flutter/material.dart';

class IncentivePolicyPage extends StatefulWidget {
  const IncentivePolicyPage({super.key, required this.english, required this.roleCode, required this.loadPage, required this.submitAction});
  final bool english;
  final String roleCode;
  final Future<Map<String, dynamic>> Function() loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic>) submitAction;

  @override
  State<IncentivePolicyPage> createState() => _IncentivePolicyPageState();
}

class _IncentivePolicyPageState extends State<IncentivePolicyPage> {
  bool loading = true, busy = false;
  String? error;
  Map<String, dynamic> data = {};
  String t(String ar, String en) => widget.english ? en : ar;
  String s(dynamic v) => v == null || v.toString().isEmpty ? '—' : v.toString();
  int n(dynamic v) => v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;
  bool yes(dynamic v) => v == true || v == 1 || v?.toString() == '1';
  bool get canManage => data['can_manage'] == true;
  bool get canReview => data['can_review'] == true;
  bool get canActivate => data['can_activate'] == true;
  String get currency => s(data['currency_code']);

  List<Map<String, dynamic>> rows(String key) {
    final raw = data[key];
    if (raw is! List) return [];
    return raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }
  List<Map<String, dynamic>> byPolicy(String key, int id) => rows(key).where((e) => n(e['policy_version_id']) == id).toList();
  List<String> options(Map<String, dynamic> field) {
    final raw = field['options_json'];
    if (raw is List) return raw.map((e) => e.toString()).toList();
    if (raw is String && raw.isNotEmpty) { try { final v = jsonDecode(raw); if (v is List) return v.map((e) => e.toString()).toList(); } catch (_) {} }
    return [];
  }

  @override
  void initState() { super.initState(); refresh(); }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final value = await widget.loadPage();
      if (!mounted) return;
      setState(() { data = value; loading = false; });
    } catch (e) {
      if (!mounted) return;
      setState(() { error = e.toString(); loading = false; });
    }
  }

  Future<void> act(Map<String, dynamic> body) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await widget.submitAction(body);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم حفظ الإجراء.', 'Action saved.'))));
      await refresh();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: Theme.of(context).colorScheme.error));
    } finally { if (mounted) setState(() => busy = false); }
  }

  String statusText(String status) => ({
    'draft': t('مسودة', 'Draft'),
    'pending_gm': t('بانتظار اعتماد المدير العام', 'Pending GM review'),
    'approved': t('معتمدة بانتظار التفعيل', 'Approved, awaiting activation'),
    'active': t('سارية', 'Active'),
    'retired': t('منتهية', 'Retired'),
  })[status] ?? status;

  Color statusColor(String status) => ({
    'draft': Colors.grey,
    'pending_gm': Colors.amber.shade800,
    'approved': Colors.blue,
    'active': Colors.green,
    'retired': Colors.blueGrey,
  })[status] ?? Colors.grey;

  Widget heading(String title) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Align(alignment: AlignmentDirectional.centerStart, child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
  );

  Future<void> createCategory() async {
    final ar = TextEditingController(), en = TextEditingController();
    try {
      await showDialog<void>(context: context, builder: (dc) => AlertDialog(
        title: Text(t('إضافة فئة حوافز', 'Add incentive category')),
        content: SizedBox(width: 440, child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: ar, decoration: InputDecoration(labelText: t('الاسم بالعربية', 'Arabic name'))),
          TextField(controller: en, decoration: InputDecoration(labelText: t('الاسم بالإنجليزية', 'English name'))),
          const SizedBox(height: 8),
          Text(t('تبدأ الفئة بمراجعة يدوية ولا تُفعّل مكافأة تلقائياً.', 'New categories start in manual-review mode.'), style: Theme.of(context).textTheme.bodySmall),
        ])),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dc), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: busy ? null : () {
            final body = {'action': 'create_category', 'name_ar': ar.text.trim(), 'name_en': en.text.trim()};
            if ((body['name_ar'] as String).isEmpty || (body['name_en'] as String).isEmpty) return;
            Navigator.pop(dc); act(body);
          }, child: Text(t('إنشاء', 'Create'))),
        ],
      ));
    } finally { ar.dispose(); en.dispose(); }
  }

  Future<void> createPolicy() async {
    final types = rows('types').where((e) => yes(e['is_active'])).toList();
    if (types.isEmpty) return;
    final name = TextEditingController(), date = TextEditingController(text: s(data['today']));
    final amount = <String, TextEditingController>{};
    final enabled = <String, bool>{};
    for (final type in types) { final code = s(type['type_code']); amount[code] = TextEditingController(text: '0'); enabled[code] = false; }
    try {
      await showDialog<void>(context: context, builder: (dc) => StatefulBuilder(builder: (dc, setDialogState) => AlertDialog(
        title: Text(t('إنشاء مسودة سياسة', 'Create policy draft')),
        content: SizedBox(width: 620, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: name, decoration: InputDecoration(labelText: t('اسم السياسة', 'Policy name'))),
          TextField(controller: date, decoration: InputDecoration(labelText: t('تاريخ السريان YYYY-MM-DD', 'Effective date YYYY-MM-DD'))),
          heading(t('قواعد الفئات', 'Category rules')),
          for (final type in types) Row(children: [
            Expanded(child: Text(s(widget.english ? type['name_en'] : type['name_ar']))),
            Switch(value: enabled[s(type['type_code'])] ?? false, onChanged: (v) => setDialogState(() => enabled[s(type['type_code'])] = v)),
            SizedBox(width: 110, child: TextField(controller: amount[s(type['type_code'])], keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: currency))),
          ]),
        ]))),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dc), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: busy ? null : () {
            final rules = types.map((type) { final code = s(type['type_code']); return {'type_code': code, 'enabled': enabled[code] ?? false, 'amount': amount[code]?.text.trim() ?? '0'}; }).toList();
            if (name.text.trim().isEmpty || date.text.trim().isEmpty || !rules.any((r) => r['enabled'] == true && (double.tryParse(r['amount'].toString()) ?? 0) > 0)) return;
            final body = {'action': 'create_policy', 'policy_name': name.text.trim(), 'effective_from': date.text.trim(), 'rules': rules};
            Navigator.pop(dc); act(body);
          }, child: Text(t('إنشاء المسودة', 'Create draft'))),
        ],
      )));
    } finally { name.dispose(); date.dispose(); for (final c in amount.values) { c.dispose(); } }
  }

  Future<void> addField(int policyId) async {
    final ar = TextEditingController(), en = TextEditingController(), opts = TextEditingController(), initial = TextEditingController();
    String type = 'short_text'; bool required = false;
    try {
      await showDialog<void>(context: context, builder: (dc) => StatefulBuilder(builder: (dc, setDialogState) => AlertDialog(
        title: Text(t('إضافة حقل مخصص', 'Add custom field')),
        content: SizedBox(width: 500, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: ar, decoration: InputDecoration(labelText: t('الاسم بالعربية', 'Arabic label'))),
          TextField(controller: en, decoration: InputDecoration(labelText: t('الاسم بالإنجليزية', 'English label'))),
          DropdownButtonFormField<String>(
            initialValue: type,
            decoration: InputDecoration(labelText: t('نوع الحقل', 'Field type')),
            items: ['short_text','long_text','number','date','boolean','select'].map((v) => DropdownMenuItem(value: v, child: Text(v))).toList(),
            onChanged: (v) => setDialogState(() => type = v ?? 'short_text'),
          ),
          if (type == 'select') TextField(controller: opts, maxLines: 3, decoration: InputDecoration(labelText: t('الخيارات، خيار لكل سطر', 'Options, one per line'))),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(t('حقل إلزامي', 'Required')), value: required, onChanged: (v) => setDialogState(() => required = v)),
          TextField(controller: initial, decoration: InputDecoration(labelText: t('القيمة الأولية', 'Initial value'))),
        ]))),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dc), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: busy ? null : () {
            final body = {'action':'add_custom_field','policy_id':policyId,'label_ar':ar.text.trim(),'label_en':en.text.trim(),'field_type':type,'is_required':required,'options':opts.text.split(RegExp(r'\r?\n')).map((v)=>v.trim()).where((v)=>v.isNotEmpty).toList(),'field_value':initial.text};
            Navigator.pop(dc); act(body);
          }, child: Text(t('إضافة', 'Add'))),
        ],
      )));
    } finally { ar.dispose(); en.dispose(); opts.dispose(); initial.dispose(); }
  }

  Future<void> editDraft(Map<String, dynamic> policy) async {
    final id = n(policy['id']), name = TextEditingController(text: s(policy['policy_name'])), date = TextEditingController(text: s(policy['effective_from']));
    final rules = byPolicy('rules', id), fields = byPolicy('custom_fields', id);
    final amount = <String, TextEditingController>{}, enabled = <String, bool>{}, values = <String, String>{};
    for (final rule in rules) { final code=s(rule['incentive_type']); amount[code]=TextEditingController(text:s(rule['reward_amount'])); enabled[code]=yes(rule['is_enabled']); }
    for (final field in fields) { values[s(field['id'])]=field['field_value']?.toString() ?? ''; }
    try {
      await showDialog<void>(context: context, builder: (dc) => StatefulBuilder(builder: (dc, setDialogState) => AlertDialog(
        title: Text(t('تعديل المسودة', 'Edit draft')),
        content: SizedBox(width: 650, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: name, decoration: InputDecoration(labelText: t('اسم السياسة', 'Policy name'))),
          TextField(controller: date, decoration: InputDecoration(labelText: t('تاريخ السريان YYYY-MM-DD', 'Effective date YYYY-MM-DD'))),
          heading(t('قواعد الفئات', 'Category rules')),
          for (final rule in rules) Row(children: [
            Expanded(child: Text(s(widget.english ? rule['name_en'] : rule['name_ar']))),
            Switch(value: enabled[s(rule['incentive_type'])] ?? false, onChanged: yes(rule['type_is_active']) ? (v)=>setDialogState(()=>enabled[s(rule['incentive_type'])]=v) : null),
            SizedBox(width: 110, child: TextField(controller: amount[s(rule['incentive_type'])], keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: currency))),
          ]),
          if (fields.isNotEmpty) heading(t('الحقول المخصصة (أدخل قيمة صالحة حسب النوع)', 'Custom fields (enter a value valid for the field type)')),
          for (final field in fields) TextFormField(
            key: ValueKey('field-${field['id']}'),
            initialValue: values[s(field['id'])] ?? '',
            decoration: InputDecoration(labelText: '${s(widget.english && s(field['label_en']) != '—' ? field['label_en'] : field['label_ar'])}${yes(field['is_required']) ? ' *' : ''}'),
            maxLines: s(field['field_type']) == 'long_text' ? 3 : 1,
            onChanged: (v) => values[s(field['id'])] = v,
          ),
        ]))),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dc), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: busy ? null : () {
            final ruleInput = rules.map((r) { final code=s(r['incentive_type']); return {'type_code':code,'enabled':enabled[code]??false,'amount':amount[code]?.text.trim()??'0'}; }).toList();
            final body = {'action':'update_policy','policy_id':id,'policy_name':name.text.trim(),'effective_from':date.text.trim(),'rules':ruleInput,'custom_values':values};
            Navigator.pop(dc); act(body);
          }, child: Text(t('حفظ المسودة', 'Save draft'))),
        ],
      )));
    } finally { name.dispose(); date.dispose(); for (final c in amount.values) { c.dispose(); } }
  }

  Future<void> reasonAction(Map<String, dynamic> policy, String action) async {
    final reason = TextEditingController();
    try {
      await showDialog<void>(context: context, builder: (dc) => AlertDialog(
        title: Text(action == 'reject_policy' ? t('رفض السياسة', 'Reject policy') : t('سحب الاعتماد', 'Withdraw approval')),
        content: TextField(controller: reason, maxLines: 3, decoration: InputDecoration(labelText: t('السبب (إلزامي)', 'Reason (required)'))),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dc), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: busy ? null : () { if (reason.text.trim().isEmpty) return; final body={'action':action,'policy_id':n(policy['id']),'reason':reason.text.trim()}; Navigator.pop(dc); act(body); }, child: Text(t('تأكيد', 'Confirm'))),
        ],
      ));
    } finally { reason.dispose(); }
  }

  Future<void> confirm(Map<String, dynamic> policy, String action) async {
    final accepted = await showDialog<bool>(context: context, builder: (dc) => AlertDialog(
      title: Text(action == 'activate_policy' ? t('تفعيل السياسة', 'Activate policy') : t('اعتماد السياسة', 'Approve policy')),
      content: Text(action == 'activate_policy'
          ? t('قد تنتهي السياسة السارية السابقة وفق تاريخ السريان. هل تريد المتابعة؟', 'The current active policy may be retired according to the effective date. Continue?')
          : t('سيتم اعتماد المسودة للمدير المالي فقط؛ لن تُفعّل تلقائياً.', 'This approves the draft for FM activation; it will not activate automatically.')),
      actions: [
        TextButton(onPressed: ()=>Navigator.pop(dc,false), child: Text(t('إلغاء','Cancel'))),
        FilledButton(onPressed: ()=>Navigator.pop(dc,true), child: Text(t('تأكيد','Confirm'))),
      ],
    ));
    if (accepted == true) await act({'action':action,'policy_id':n(policy['id'])});
  }

  Widget policyCard(Map<String, dynamic> p) {
    final id=n(p['id']), status=s(p['status']), rules=byPolicy('rules', n(p['id'])), fields=byPolicy('custom_fields', n(p['id'])), history=byPolicy('approval_history', n(p['id']));
    return Card(child: ExpansionTile(
      title: Text('V${s(p['version_no'])} — ${s(p['policy_name'])}', style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle: Wrap(spacing:8, runSpacing:4, children: [
        Chip(label:Text(statusText(status)), labelStyle:TextStyle(color:statusColor(status)), backgroundColor:statusColor(status).withValues(alpha:.08)),
        Text('${t('السريان','Effective')}: ${s(p['effective_from'])}'),
        Text('${t('القواعد المفعلة','Enabled rules')}: ${s(p['enabled_rules'])}'),
      ]),
      childrenPadding: const EdgeInsets.fromLTRB(16,0,16,16),
      children: [
        Align(alignment:AlignmentDirectional.centerStart, child:Text('${t('أنشأها','Created by')}: ${s(p['created_by_name'])}')),
        for(final r in rules) ListTile(dense:true, leading:Icon(yes(r['is_enabled'])?Icons.check_circle_outline:Icons.radio_button_unchecked,color:yes(r['is_enabled'])?Colors.green:Colors.grey), title:Text(s(widget.english?r['name_en']:r['name_ar'])), subtitle:Text(s(r['incentive_type'])), trailing:Text('${s(r['reward_amount'])} ${s(r['currency_code'])}')),
        for(final f in fields) ListTile(dense:true,title:Text(s(widget.english && s(f['label_en'])!='—'?f['label_en']:f['label_ar'])),subtitle:Text('${s(f['field_type'])}${yes(f['is_required'])?t(' • إلزامي',' • required'):''}'),trailing:Text(s(f['field_value']))),
        if(history.isNotEmpty) heading(t('سجل الاعتماد','Approval history')),
        for(final h in history) ListTile(dense:true,leading:const Icon(Icons.history_rounded),title:Text(s(h['action'])),subtitle:Text('${s(h['actor_name'])} • ${s(h['created_at'])}${s(h['reason'])=='—'?'':'\n${s(h['reason'])}'}')),
        if(canManage && status=='draft') Wrap(spacing:8,runSpacing:8,children:[
          OutlinedButton.icon(onPressed:busy?null:()=>editDraft(p),icon:const Icon(Icons.edit_outlined),label:Text(t('تعديل المسودة','Edit draft'))),
          OutlinedButton.icon(onPressed:busy?null:()=>addField(id),icon:const Icon(Icons.add_box_outlined),label:Text(t('إضافة حقل','Add field'))),
          FilledButton.icon(onPressed:busy?null:()=>act({'action':'submit_policy','policy_id':id}),icon:const Icon(Icons.send_rounded),label:Text(t('إرسال للاعتماد','Submit for approval'))),
        ]),
        if(canReview && status=='pending_gm') Wrap(spacing:8,children:[
          FilledButton.icon(onPressed:busy?null:()=>confirm(p,'approve_policy'),icon:const Icon(Icons.verified_outlined),label:Text(t('اعتماد','Approve'))),
          OutlinedButton.icon(onPressed:busy?null:()=>reasonAction(p,'reject_policy'),icon:const Icon(Icons.close_rounded),label:Text(t('رفض','Reject'))),
        ]),
        if(canReview && status=='approved' && p['activated_at']==null && p['activated_by_user_id']==null)
          OutlinedButton.icon(onPressed:busy?null:()=>reasonAction(p,'withdraw_approval_to_draft'),icon:const Icon(Icons.undo_rounded),label:Text(t('سحب الاعتماد وإعادة المسودة','Withdraw approval to draft'))),
        if(canActivate && status=='approved')
          FilledButton.icon(onPressed:busy?null:()=>confirm(p,'activate_policy'),icon:const Icon(Icons.power_settings_new_rounded),label:Text(t('تفعيل السياسة','Activate policy'))),
        if(status=='active') ListTile(dense:true,leading:const Icon(Icons.lock_outline_rounded),title:Text(t('السياسة السارية للقراءة فقط','Active policy is read-only')),subtitle:Text('${s(p['activated_by_name'])} • ${s(p['activated_at'])}')),
      ],
    ));
  }

  @override
  Widget build(BuildContext context) {
    final policies=rows('policies');
    return Scaffold(
      appBar:AppBar(title:Text(t('سياسة الحوافز','Incentive policy')),actions:[IconButton(onPressed:loading?null:refresh,icon:const Icon(Icons.refresh_rounded))]),
      body:loading?const Center(child:CircularProgressIndicator()):error!=null
        ? Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),const SizedBox(height:12),FilledButton(onPressed:refresh,child:Text(t('إعادة المحاولة','Try again')))])))
        : RefreshIndicator(onRefresh:refresh,child:ListView(padding:const EdgeInsets.all(16),children:[
          Text(t('إدارة نسخ السياسة ومراحل اعتمادها','Manage policy versions and approval stages'),style:Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight:FontWeight.w800)),
          const SizedBox(height:6),
          Text(t('تظل السياسة السارية للقراءة فقط. لا تتغير إلا عند تفعيل نسخة معتمدة بتاريخ سريان صالح.','The active policy is read-only and changes only when an approved version is activated.')),
          if(canManage) ...[
            const SizedBox(height:12),
            Wrap(spacing:8,children:[
              FilledButton.icon(onPressed:busy?null:createPolicy,icon:const Icon(Icons.add_rounded),label:Text(t('إنشاء مسودة','Create draft'))),
              OutlinedButton.icon(onPressed:busy?null:createCategory,icon:const Icon(Icons.category_outlined),label:Text(t('إضافة فئة','Add category'))),
            ]),
            heading(t('فئات الحوافز','Incentive categories')),
            for(final type in rows('types')) ListTile(dense:true,leading:Icon(yes(type['is_active'])?Icons.category_outlined:Icons.pause_circle_outline),title:Text(s(widget.english?type['name_en']:type['name_ar'])),subtitle:Text('${s(type['type_code'])} • ${s(type['qualification_mode'])}'),trailing:Text(yes(type['is_active'])?t('نشطة','Active'):t('غير نشطة','Inactive'))),
          ],
          heading(t('نسخ السياسة','Policy versions')),
          if(policies.isEmpty) Padding(padding:const EdgeInsets.all(20),child:Text(t('لا توجد نسخ سياسة متاحة لهذا الدور.','No policy versions are available for this role.'))),
          for(final p in policies) policyCard(p),
        ])),
    );
  }
}
