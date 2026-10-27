import 'package:flutter/material.dart';

class OrphanGroupsPage extends StatefulWidget {
  const OrphanGroupsPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.submitAction,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(int? groupId) loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;

  @override
  State<OrphanGroupsPage> createState() => _OrphanGroupsPageState();
}

class _OrphanGroupsPageState extends State<OrphanGroupsPage> {
  bool loading = true;
  bool saving = false;
  String? error;
  int? selectedGroupId;
  Map<String, dynamic> data = {};

  bool get en => widget.english;
  List<Map<String, dynamic>> get groups => _maps(data['groups']);
  List<Map<String, dynamic>> get members => _maps(data['members']);
  List<Map<String, dynamic>> get nannies => _maps(data['nannies']);
  List<Map<String, dynamic>> get eligible => _maps(data['eligible_orphans']);
  List<Map<String, dynamic>> get targets => _maps(data['target_groups']);
  Map<String, dynamic>? get group => data['selected_group'] is Map
      ? Map<String, dynamic>.from(data['selected_group'] as Map)
      : null;
  Map<String, dynamic> get usage => data['usage'] is Map
      ? Map<String, dynamic>.from(data['usage'] as Map)
      : <String, dynamic>{};
  bool get locked => data['has_current_disbursement'] == true ||
      data['has_current_disbursement']?.toString() == '1';
  bool get editableMembers => data['can_change_members'] == true ||
      data['can_change_members']?.toString() == '1';

  List<Map<String, dynamic>> _maps(dynamic raw) => raw is List
      ? raw.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
      : <Map<String, dynamic>>[];
  String _s(dynamic v) => v?.toString() ?? '';
  int _i(dynamic v) => int.tryParse(_s(v)) ?? 0;

  String _status(String value) {
    const ar = {'draft':'مسودة','active':'نشطة','empty':'فارغة','suspended':'موقوفة','closed':'مغلقة','cancelled':'ملغاة','archived':'مؤرشفة'};
    const enLabels = {'draft':'Draft','active':'Active','empty':'Empty','suspended':'Suspended','closed':'Closed','cancelled':'Cancelled','archived':'Archived'};
    return (en ? enLabels : ar)[value] ?? value;
  }

  @override
  void initState() {
    super.initState();
    _load(preserve: false);
  }

  Future<void> _load({int? groupId, bool preserve = true}) async {
    final id = groupId ?? (preserve ? selectedGroupId : null);
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadPage(id);
      if (!mounted) return;
      setState(() {
        data = result;
        selectedGroupId = id != null && result['selected_group'] is Map ? id : null;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _act(Map<String, dynamic> body, {bool created = false}) async {
    if (saving) return;
    setState(() => saving = true);
    try {
      final result = await widget.submitAction(body);
      if (!mounted) return;
      if (created && _i(result['group_id']) > 0) selectedGroupId = _i(result['group_id']);
      if (body['action'] == 'delete_group') selectedGroupId = null;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(_s(result['message']).isNotEmpty
            ? _s(result['message'])
            : (en ? 'Group updated.' : 'تم تحديث المجموعة.')),
      ));
      await _load(groupId: selectedGroupId, preserve: false);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _edit({Map<String, dynamic>? existing}) async {
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (ctx) => _OrphanGroupEditDialog(english: en, existing: existing),
    );
    if (result != null) {
      final body = <String, dynamic>{
        'action': existing == null ? 'create_group' : 'update_group',
        if (existing != null) 'group_id': _i(existing['id']),
        ...result,
      };
      await _act(body, created: existing == null);
    }
  }

  Future<void> _confirm(String action, {String reason = ''}) async {
    final current = group;
    if (current == null) return;
    final titles = {
      'activate_group': en ? 'Activate group?' : 'تفعيل المجموعة؟',
      'suspend_group': en ? 'Suspend group?' : 'إيقاف المجموعة؟',
      'close_group': en ? 'Close group?' : 'إغلاق المجموعة؟',
      'delete_group': en ? 'Delete unused group?' : 'حذف مجموعة غير مستخدمة؟',
    };
    final messages = {
      'activate_group': en ? 'Activate this group?' : 'هل تريد تفعيل هذه المجموعة؟',
      'suspend_group': en ? 'Temporarily suspend this group?' : 'هل تريد إيقاف المجموعة مؤقتاً؟',
      'close_group': en ? 'The group history will be preserved.' : 'سيتم الحفاظ على سجل المجموعة.',
      'delete_group': en ? 'This permanently deletes a group with no history.' : 'سيتم حذف مجموعة لا تملك أي سجل.',
    };
    String actionReason = reason;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(titles[action] ?? action),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(messages[action] ?? ''),
          if (action == 'suspend_group' || action == 'close_group')
            TextField(maxLength: 500, onChanged: (v) => actionReason = v,
              decoration: InputDecoration(labelText: en ? 'Reason (optional)' : 'السبب (اختياري)')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(en ? 'Confirm' : 'تأكيد')),
        ],
      ),
    );
    if (ok == true) await _act({'action': action, 'group_id': _i(current['id']), 'reason': actionReason});
  }

  Future<void> _assignNanny() async {
    final current = group;
    if (current == null || nannies.isEmpty) return;
    int? nannyId;
    final result = await showDialog<int>(
      context: context,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setDialogState) => AlertDialog(
        title: Text(en ? 'Assign nanny' : 'تعيين الحاضنة'),
        content: DropdownButtonFormField<int>(
          initialValue: nannyId,
          isExpanded: true,
          decoration: InputDecoration(labelText: en ? 'Active nanny' : 'الحاضنة النشطة'),
          items: nannies.map((n) => DropdownMenuItem<int>(value: _i(n['id']), child: Text(_s(n['full_name'])))).toList(),
          onChanged: (v) => setDialogState(() => nannyId = v),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () { if (nannyId != null) Navigator.pop(ctx, nannyId); }, child: Text(en ? 'Assign' : 'تعيين')),
        ],
      )),
    );
    if (result != null) await _act({'action': 'assign_nanny', 'group_id': _i(current['id']), 'nanny_id': result});
  }

  Future<void> _addMembers() async {
    final current = group;
    if (current == null || eligible.isEmpty) return;
    final chosen = <int>{};
    final result = await showDialog<List<int>>(
      context: context,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setDialogState) => AlertDialog(
        title: Text(en ? 'Add eligible children' : 'إضافة أيتام مؤهلين'),
        content: SizedBox(width: 520, height: 420, child: ListView.builder(
          itemCount: eligible.length,
          itemBuilder: (_, index) {
            final child = eligible[index];
            final id = _i(child['id']);
            final details = [_s(child['family_name']), _s(child['family_code']), _s(child['sponsor_name'])].where((s) => s.isNotEmpty).join(' • ');
            return CheckboxListTile(
              value: chosen.contains(id),
              onChanged: (v) => setDialogState(() { if (v == true) { chosen.add(id); } else { chosen.remove(id); } }),
              title: Text(_s(child['child_name'])),
              subtitle: Text(details),
              controlAffinity: ListTileControlAffinity.leading,
            );
          },
        )),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () { if (chosen.isNotEmpty) Navigator.pop(ctx, chosen.toList()); }, child: Text(en ? 'Add selected' : 'إضافة المحددين')),
        ],
      )),
    );
    if (result != null && result.isNotEmpty) {
      await _act({'action': 'add_orphan_bulk', 'group_id': _i(current['id']), 'child_ids': result});
    }
  }

  Future<void> _transfer(Map<String, dynamic> member) async {
    final current = group;
    if (current == null || targets.isEmpty) return;
    int? targetId;
    final result = await showDialog<int>(
      context: context,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setDialogState) => AlertDialog(
        title: Text(en ? 'Transfer child' : 'نقل اليتيم'),
        content: DropdownButtonFormField<int>(
          initialValue: targetId,
          isExpanded: true,
          decoration: InputDecoration(labelText: en ? 'Target group' : 'المجموعة الهدف'),
          items: targets.map((g) => DropdownMenuItem<int>(value: _i(g['id']), child: Text(_s(g['group_name']) + ' (' + _s(g['orphan_count']) + ')'))).toList(),
          onChanged: (v) => setDialogState(() => targetId = v),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () { if (targetId != null) Navigator.pop(ctx, targetId); }, child: Text(en ? 'Transfer' : 'نقل')),
        ],
      )),
    );
    if (result != null) {
      await _act({'action': 'transfer_orphan', 'group_id': _i(current['id']), 'child_id': _i(member['child_id']), 'target_group_id': result});
    }
  }

  Future<void> _removeMember(Map<String, dynamic> member) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(en ? 'Remove child from group?' : 'إزالة اليتيم من المجموعة؟'),
        content: Text(en ? 'Membership history will be retained.' : 'سيتم الاحتفاظ بسجل العضوية.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(en ? 'Remove' : 'إزالة')),
        ],
      ),
    );
    if (ok == true && selectedGroupId != null) {
      await _act({'action': 'remove_orphan', 'group_id': selectedGroupId, 'child_id': _i(member['child_id'])});
    }
  }

  Widget _chip(String status) => Chip(label: Text(_status(status)), visualDensity: VisualDensity.compact);
  Widget _metric(String title, int value) => Container(
    constraints: const BoxConstraints(minWidth: 105),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(border: Border.all(color: Theme.of(context).dividerColor), borderRadius: BorderRadius.circular(10)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: Theme.of(context).textTheme.bodySmall),
      const SizedBox(height: 4),
      Text(value.toString(), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
    ]),
  );

  Widget _groupList() => Card(child: Column(children: [
    ListTile(
      title: Text(en ? 'Orphan groups' : 'مجموعات الأيتام'),
      subtitle: Text((en ? 'Current disbursement cycle: ' : 'دورة الصرف الحالية: ') + _s(data['month'])),
      trailing: IconButton(onPressed: saving ? null : () => _edit(), icon: const Icon(Icons.add_circle_outline_rounded), tooltip: en ? 'Create group' : 'إنشاء مجموعة'),
    ),
    if (groups.isEmpty)
      Padding(padding: const EdgeInsets.all(24), child: Text(en ? 'No groups found.' : 'لا توجد مجموعات.'))
    else
      ...groups.map((g) => ListTile(
        selected: _i(g['id']) == selectedGroupId,
        leading: const Icon(Icons.groups_rounded),
        title: Text(_s(g['group_name'])),
        subtitle: Text(_s(g['orphan_count']) + (en ? ' members • ' : ' عضو • ') + (_s(g['nanny_name']).isEmpty ? (en ? 'No nanny' : 'بدون حاضنة') : _s(g['nanny_name']))),
        trailing: _chip(_s(g['status'])),
        onTap: saving ? null : () => _load(groupId: _i(g['id'])),
      )),
  ]));

  Widget _details() {
    final current = group;
    if (current == null) return Card(child: Padding(
      padding: const EdgeInsets.all(24),
      child: Text(en ? 'Select a group to manage its lifecycle, members and nanny.' : 'اختر مجموعة لإدارة دورة حياتها وأعضائها والحاضنة.'),
    ));
    final status = _s(current['status']);
    final terminal = ['closed', 'cancelled', 'archived'].contains(status);
    final memberHistory = _i(usage['member_history']);
    final nannyHistory = _i(usage['nanny_history']);
    final disbursementHistory = _i(usage['disbursement_history']);
    Map<String, dynamic>? currentNanny;
    for (final n in _maps(data['nanny_history'])) {
      if (_s(n['end_date']).isEmpty) { currentNanny = n; break; }
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Expanded(child: Text(_s(current['group_name']), style: Theme.of(context).textTheme.titleLarge)), _chip(status)]),
        if (_s(current['description']).isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_s(current['description']))),
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 8, children: [
          _metric(en ? 'Current members' : 'الأعضاء الحاليون', members.length),
          _metric(en ? 'Member history' : 'سجل العضويات', memberHistory),
          _metric(en ? 'Nanny history' : 'سجل الحاضنات', nannyHistory),
          _metric(en ? 'Disbursements' : 'سجل الصرف', disbursementHistory),
        ]),
        if (locked) Padding(padding: const EdgeInsets.only(top: 10), child: Text(
          en ? 'Member changes are locked for the current month because a disbursement exists.' : 'تعديل الأعضاء مقفل لوجود صرف للشهر الحالي.',
          style: TextStyle(color: Theme.of(context).colorScheme.error, fontWeight: FontWeight.w600))),
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 8, children: [
          OutlinedButton.icon(onPressed: saving || terminal ? null : () => _edit(existing: current), icon: const Icon(Icons.edit_outlined), label: Text(en ? 'Edit' : 'تعديل')),
          if (['draft','empty','suspended'].contains(status) && members.isNotEmpty)
            FilledButton.icon(onPressed: saving ? null : () => _confirm('activate_group'), icon: const Icon(Icons.play_arrow_rounded), label: Text(en ? 'Activate' : 'تفعيل')),
          if (['active','empty'].contains(status))
            OutlinedButton.icon(onPressed: saving ? null : () => _confirm('suspend_group'), icon: const Icon(Icons.pause_rounded), label: Text(en ? 'Suspend' : 'إيقاف مؤقت')),
          if (['active','empty','suspended'].contains(status))
            OutlinedButton.icon(onPressed: saving ? null : () => _confirm('close_group'), icon: const Icon(Icons.lock_outline_rounded), label: Text(en ? 'Close' : 'إغلاق')),
          if (['draft','empty'].contains(status) && memberHistory + nannyHistory + disbursementHistory == 0)
            OutlinedButton.icon(onPressed: saving ? null : () => _confirm('delete_group'), icon: const Icon(Icons.delete_outline_rounded), label: Text(en ? 'Delete unused group' : 'حذف مجموعة غير مستخدمة')),
        ]),
      ]))),
      if (editableMembers) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(en ? 'Nanny assignment' : 'تعيين الحاضنة', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 6),
        Text(currentNanny == null ? (en ? 'No current nanny assigned.' : 'لا توجد حاضنة معينة حالياً.') : (en ? 'Current: ' : 'الحالية: ') + _s(currentNanny['nanny_name']) + ' • ' + _s(currentNanny['start_date'])),
        Align(alignment: AlignmentDirectional.centerStart, child: OutlinedButton.icon(
          onPressed: saving || nannies.isEmpty ? null : _assignNanny,
          icon: const Icon(Icons.person_add_alt_1_rounded), label: Text(en ? 'Assign / replace nanny' : 'تعيين / تغيير الحاضنة'))),
        const Divider(),
        Row(children: [Expanded(child: Text(en ? 'Current members' : 'الأعضاء الحاليون', style: Theme.of(context).textTheme.titleMedium)), Text(members.length.toString())]),
        if (members.isEmpty) Padding(padding: const EdgeInsets.symmetric(vertical: 12), child: Text(en ? 'No current members.' : 'لا يوجد أعضاء حاليون.')),
        ...members.map((m) => ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const CircleAvatar(child: Icon(Icons.child_care_rounded)),
          title: Text(_s(m['child_name'])),
          subtitle: Text(_s(m['family_name']) + ' • ' + _s(m['joined_date']) + (_s(m['age']).isNotEmpty ? ' • ' + _s(m['age']) : '')),
          trailing: locked ? null : PopupMenuButton<String>(
            enabled: !saving,
            onSelected: (v) { if (v == 'remove') _removeMember(m); if (v == 'transfer') _transfer(m); },
            itemBuilder: (_) => [
              PopupMenuItem(value: 'transfer', enabled: targets.isNotEmpty, child: Text(en ? 'Transfer' : 'نقل')),
              PopupMenuItem(value: 'remove', child: Text(en ? 'Remove' : 'إزالة')),
            ],
          ),
        )),
        const Divider(),
        Align(alignment: AlignmentDirectional.centerStart, child: OutlinedButton.icon(
          onPressed: saving || locked || eligible.isEmpty ? null : _addMembers,
          icon: const Icon(Icons.group_add_rounded),
          label: Text((en ? 'Add eligible children (' : 'إضافة أيتام مؤهلين (') + eligible.length.toString() + ')'))),
      ]))),
      if (_maps(data['nanny_history']).isNotEmpty)
        Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(en ? 'Nanny assignment history' : 'سجل تعيين الحاضنات', style: Theme.of(context).textTheme.titleMedium),
          ..._maps(data['nanny_history']).map((n) => ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.history_rounded),
            title: Text(_s(n['nanny_name'])),
            subtitle: Text(_s(n['start_date']) + ' → ' + (_s(n['end_date']).isEmpty ? (en ? 'Current' : 'حالياً') : _s(n['end_date']))),
            trailing: _s(n['assigned_by_name']).isEmpty ? null : Text(_s(n['assigned_by_name']), style: Theme.of(context).textTheme.bodySmall),
          )),
        ]))),
    ]);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(en ? 'Orphan groups' : 'إدارة مجموعات الأيتام'), actions: [
      IconButton(onPressed: loading || saving ? null : _load, icon: const Icon(Icons.refresh_rounded), tooltip: en ? 'Refresh' : 'تحديث'),
    ]),
    body: loading ? const Center(child: CircularProgressIndicator())
        : error != null ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(error!, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: Text(en ? 'Try again' : 'إعادة المحاولة')),
        ])))
        : RefreshIndicator(onRefresh: _load, child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(12), children: [
          _groupList(),
          const SizedBox(height: 8),
          _details(),
          if (saving) const Padding(padding: EdgeInsets.all(12), child: LinearProgressIndicator()),
        ])),
  );
}


class _OrphanGroupEditDialog extends StatefulWidget {
  const _OrphanGroupEditDialog({required this.english, this.existing});

  final bool english;
  final Map<String, dynamic>? existing;

  @override
  State<_OrphanGroupEditDialog> createState() => _OrphanGroupEditDialogState();
}

class _OrphanGroupEditDialogState extends State<_OrphanGroupEditDialog> {
  late final TextEditingController _name;
  late final TextEditingController _description;

  bool get en => widget.english;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.existing?['group_name']?.toString() ?? '');
    _description = TextEditingController(text: widget.existing?['description']?.toString() ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(en
        ? (widget.existing == null ? 'Create group draft' : 'Edit group')
        : (widget.existing == null ? 'إنشاء مجموعة كمسودة' : 'تعديل المجموعة')),
    content: SizedBox(width: 440, child: Column(mainAxisSize: MainAxisSize.min, children: [
      TextField(controller: _name, maxLength: 150,
        decoration: InputDecoration(labelText: en ? 'Group name' : 'اسم المجموعة')),
      TextField(controller: _description, maxLength: 1000, minLines: 2, maxLines: 4,
        decoration: InputDecoration(labelText: en ? 'Description' : 'الوصف')),
    ])),
    actions: [
      TextButton(onPressed: () => Navigator.pop(context),
        child: Text(en ? 'Cancel' : 'إلغاء')),
      FilledButton(onPressed: () {
        if (_name.text.trim().isNotEmpty) {
          Navigator.pop(context, {
            'group_name': _name.text.trim(),
            'description': _description.text.trim(),
          });
        }
      }, child: Text(en ? 'Save' : 'حفظ')),
    ],
  );
}
