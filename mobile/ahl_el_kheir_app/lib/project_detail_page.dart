import 'package:flutter/material.dart';

class ProjectDetailPage extends StatefulWidget {
  const ProjectDetailPage({super.key, required this.projectId, required this.english, required this.loadDetail, required this.submitProgress, required this.createMilestone, this.actOnMilestone});
  final int projectId;
  final bool english;
  final Future<Map<String, dynamic>> Function(int projectId) loadDetail;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitProgress;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) createMilestone;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body)? actOnMilestone;
  @override
  State<ProjectDetailPage> createState() => _ProjectDetailPageState();
}

class _ProjectDetailPageState extends State<ProjectDetailPage> {
  bool loading = true, savingProgress = false, savingMilestone = false, savingMilestoneAction = false;
  String? error;
  Map<String, dynamic> project = {};
  List<Map<String, dynamic>> team = [], milestones = [], progressUpdates = [], history = [];
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final d = await widget.loadDetail(widget.projectId);
      if (!mounted) return;
      setState(() {
        project = d['project'] is Map ? Map<String, dynamic>.from(d['project'] as Map) : {};
        team = _maps(d['team']); milestones = _maps(d['milestones']); progressUpdates = _maps(d['progress_updates']); history = _maps(d['history']);
      });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  List<Map<String, dynamic>> _maps(dynamic v) => v is List ? v.whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList() : <Map<String, dynamic>>[];
  String _status(String s) {
    final m = widget.english ? const {'planned':'Planned','active':'Active','completed':'Completed','under_review':'Under review','closed':'Closed','reopened':'Reopened','cancelled':'Cancelled'} : const {'planned':'مخطط','active':'نشط','completed':'مكتمل','under_review':'قيد المراجعة','closed':'مغلق','reopened':'أعيد فتحه','cancelled':'ملغي'};
    return m[s] ?? s;
  }
  String _approval(String s) {
    final m = widget.english ? const {'draft':'Draft','submitted':'Awaiting approval','fm_approved':'Financially approved','approved':'Approved','rejected':'Rejected'} : const {'draft':'مسودة','submitted':'بانتظار الاعتماد','fm_approved':'معتمد مالياً','approved':'معتمد','rejected':'مرفوض'};
    return m[s] ?? s;
  }
  String _section(String s) {
    final m = widget.english ? const {'general':'General','finance':'Finance','operations':'Operations','documents':'Documents','closure':'Closure'} : const {'general':'عام','finance':'مالي','operations':'تشغيلي','documents':'وثائق','closure':'إغلاق'};
    return m[s] ?? s;
  }
  Future<void> _addProgressUpdate() async {
    final body = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _ProjectProgressDialog(projectId: widget.projectId, english: widget.english),
    );
    if (body == null || !mounted) return;
    setState(() => savingProgress = true);
    try {
      await widget.submitProgress(body);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.english ? 'Progress update saved.' : 'تم حفظ تحديث التقدم.')),
      );
      await load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    } finally {
      if (mounted) setState(() => savingProgress = false);
    }
  }
  Future<void> _addMilestone() async {
    final body = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _ProjectMilestoneDialog(projectId: widget.projectId, english: widget.english),
    );
    if (body == null || !mounted) return;
    setState(() => savingMilestone = true);
    try {
      await widget.createMilestone(body);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.english ? 'Milestone saved.' : 'تم حفظ المرحلة.')),
      );
      await load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => savingMilestone = false);
    }
  }
  Future<void> _editMilestone(Map<String, dynamic> milestone) async {
    final body = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _ProjectMilestoneDialog(projectId: widget.projectId, english: widget.english, existing: milestone),
    );
    if (body == null || !mounted || widget.actOnMilestone == null) return;
    setState(() => savingMilestoneAction = true);
    try {
      await widget.actOnMilestone!({...body, 'action': 'edit', 'milestone_id': int.tryParse(milestone['id']?.toString() ?? '')});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(widget.english ? 'Milestone updated.' : 'تم تعديل المرحلة.')));
      await load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => savingMilestoneAction = false);
    }
  }

  Future<void> _deleteMilestone(Map<String, dynamic> milestone) async {
    final en = widget.english;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(en ? 'Delete milestone?' : 'حذف المرحلة؟'),
        content: Text(en ? 'This will permanently delete “${milestone['title'] ?? ''}”.' : 'سيتم حذف المرحلة «${milestone['title'] ?? ''}» نهائياً.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(en ? 'Delete' : 'حذف')),
        ],
      ),
    );
    if (confirmed != true || !mounted || widget.actOnMilestone == null) return;
    final id = int.tryParse(milestone['id']?.toString() ?? '');
    if (id == null || id < 1) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(en ? 'Invalid milestone ID.' : 'معرّف المرحلة غير صالح.')));
      return;
    }
    setState(() => savingMilestoneAction = true);
    try {
      await widget.actOnMilestone!({'action': 'delete', 'project_id': widget.projectId, 'milestone_id': id});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(en ? 'Milestone deleted.' : 'تم حذف المرحلة.')));
      await load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => savingMilestoneAction = false);
    }
  }
  String _amount(dynamic v) => (double.tryParse(v?.toString() ?? '') ?? 0).toStringAsFixed(2);
  String _date(dynamic v) => (v?.toString().isNotEmpty ?? false) ? v.toString() : (widget.english ? 'Not set' : 'غير محدد');
  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Project details' : 'تفاصيل المشروع')),
      body: loading ? const Center(child: CircularProgressIndicator()) :
        error != null ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(error!, textAlign: TextAlign.center), const SizedBox(height: 12),
          OutlinedButton.icon(onPressed: load, icon: const Icon(Icons.refresh), label: Text(en ? 'Try again' : 'إعادة المحاولة'))
        ]))) :
        RefreshIndicator(onRefresh: load, child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(14), children: [
          Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(project['name']?.toString() ?? '', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: const Color(0xFF0A1F44))),
            const SizedBox(height: 5), Text(project['project_code']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF64748B))),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [Chip(label: Text(_status(project['current_status']?.toString() ?? ''))), Chip(label: Text(_approval(project['approval_status']?.toString() ?? '')))]),
            if ((project['description']?.toString() ?? '').trim().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(project['description'].toString())),
            const SizedBox(height: 12),
            _Info(label: en ? 'Budget' : 'الميزانية', value: _amount(project['budget']) + ' ' + (project['currency_code']?.toString() ?? 'SDG')),
            _Info(label: en ? 'Funded' : 'الممول', value: _amount(project['funded']) + ' ' + (project['currency_code']?.toString() ?? 'SDG')),
            _Info(label: en ? 'Expenses' : 'المصروفات', value: _amount(project['expenses']) + ' ' + (project['currency_code']?.toString() ?? 'SDG')),
            _Info(label: en ? 'Supervisor' : 'المشرف', value: project['supervisor_name']?.toString() ?? '—'),
            if ((project['closed_at']?.toString() ?? '').isNotEmpty) _Info(label: en ? 'Closed at' : 'تاريخ الإغلاق', value: project['closed_at'].toString()),
            if ((project['closure_summary']?.toString() ?? '').isNotEmpty) _Info(label: en ? 'Closure summary' : 'ملخص الإغلاق', value: project['closure_summary'].toString()),
          ]))),
          if (project['can_add_progress'] == true)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: FilledButton.icon(
                onPressed: savingProgress ? null : _addProgressUpdate,
                icon: savingProgress
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.add_task_rounded),
                label: Text(en ? 'Add progress update' : 'إضافة تحديث تقدم'),
              ),
            ),
          _SectionTitle(en ? 'Project team' : 'فريق المشروع', count: team.length),
          if (team.isEmpty) _Empty(en ? 'No active team assignments.' : 'لا توجد تكليفات فريق نشطة.')
          else ...team.map((m) => Card(child: ListTile(
            leading: CircleAvatar(child: Icon(m['is_lead'] == true ? Icons.star_rounded : Icons.person_outline_rounded)),
            title: Text(m['full_name']?.toString() ?? ''), subtitle: Text(_section(m['section_code']?.toString() ?? '')),
            trailing: m['is_lead'] == true ? Text(en ? 'Lead' : 'قائد') : null,
          ))),
          if (project['can_add_milestone'] == true)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: FilledButton.icon(
                onPressed: savingMilestone ? null : _addMilestone,
                icon: savingMilestone
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.flag_rounded),
                label: Text(en ? 'Add milestone' : 'إضافة مرحلة'),
              ),
            ),
          _SectionTitle(en ? 'Milestones' : 'مراحل المشروع', count: milestones.length),
          if (milestones.isEmpty) _Empty(en ? 'No milestones recorded.' : 'لا توجد مراحل مسجلة.')
          else ...milestones.map((m) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(m['title']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800)),
            if ((m['description']?.toString() ?? '').isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text(m['description'].toString())),
            const SizedBox(height: 8), Text((en ? 'Planned date: ' : 'التاريخ المخطط: ') + _date(m['planned_date'])),
            const SizedBox(height: 6), LinearProgressIndicator(value: ((double.tryParse(m['completion_percent']?.toString() ?? '') ?? 0) / 100).clamp(0.0, 1.0)),
            const SizedBox(height: 4), Text(_amount(m['completion_percent']) + '%'),
            if (project['can_manage_milestones'] == true && widget.actOnMilestone != null)
              Align(alignment: AlignmentDirectional.centerEnd, child: Wrap(spacing: 8, children: [
                TextButton.icon(
                  key: ValueKey('edit-project-milestone-${m['id']}'),
                  onPressed: savingMilestoneAction ? null : () => _editMilestone(m),
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(en ? 'Edit' : 'تعديل'),
                ),
                TextButton.icon(
                  key: ValueKey('delete-project-milestone-${m['id']}'),
                  onPressed: savingMilestoneAction ? null : () => _deleteMilestone(m),
                  icon: const Icon(Icons.delete_outline_rounded),
                  label: Text(en ? 'Delete' : 'حذف'),
                ),
              ])),
          ])))),
          _SectionTitle(en ? 'Progress updates' : 'تحديثات التقدم', count: progressUpdates.length),
          if (progressUpdates.isEmpty) _Empty(en ? 'No progress updates recorded.' : 'لا توجد تحديثات تقدم مسجلة.')
          else ...progressUpdates.map((u) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(u['summary']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text((en ? 'Date: ' : 'التاريخ: ') + _date(u['update_date'])),
            Text((en ? 'Completion: ' : 'نسبة الإنجاز: ') + _amount(u['completion_percent']) + '%'),
            if ((u['achievements']?.toString() ?? '').trim().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text((en ? 'Achievements: ' : 'الإنجازات: ') + u['achievements'].toString())),
            if ((u['issues']?.toString() ?? '').trim().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text((en ? 'Issues: ' : 'المعوقات: ') + u['issues'].toString())),
            if ((u['next_steps']?.toString() ?? '').trim().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text((en ? 'Next steps: ' : 'الخطوات القادمة: ') + u['next_steps'].toString())),
            if ((u['submitter_name']?.toString() ?? '').trim().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text((en ? 'Submitted by: ' : 'بواسطة: ') + u['submitter_name'].toString(), style: const TextStyle(color: Color(0xFF64748B)))),
          ])))),
          _SectionTitle(en ? 'Recent history' : 'آخر التغييرات', count: history.length),
          if (history.isEmpty) _Empty(en ? 'No history recorded.' : 'لا يوجد سجل تغييرات.')
          else ...history.map((h) => Card(child: ListTile(
            leading: const Icon(Icons.history_rounded), title: Text((h['old_status'] ?? '—').toString() + ' → ' + (h['new_status'] ?? '—').toString()),
            subtitle: Text([if ((h['reason']?.toString() ?? '').isNotEmpty) h['reason'].toString(), if ((h['actor_name']?.toString() ?? '').isNotEmpty) h['actor_name'].toString(), if ((h['created_at']?.toString() ?? '').isNotEmpty) h['created_at'].toString()].join('\n')),
            isThreeLine: true,
          ))),
        ])),
    );
  }
}
class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.label, {required this.count});
  final String label; final int count;
  @override Widget build(BuildContext context) => Padding(padding: const EdgeInsets.fromLTRB(4, 12, 4, 6), child: Row(children: [
    Expanded(child: Text(label, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))), Text(count.toString())
  ]));
}
class _Info extends StatelessWidget {
  const _Info({required this.label, required this.value});
  final String label, value;
  @override Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Row(children: [
    Expanded(child: Text(label, style: const TextStyle(color: Color(0xFF64748B)))), const SizedBox(width: 8),
    Flexible(child: Text(value, textAlign: TextAlign.end, style: const TextStyle(fontWeight: FontWeight.w800)))
  ]));
}
class _Empty extends StatelessWidget {
  const _Empty(this.label); final String label;
  @override Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8), child: Text(label, style: const TextStyle(color: Color(0xFF64748B))));
}

class _ProjectProgressDialog extends StatefulWidget {
  const _ProjectProgressDialog({required this.projectId, required this.english});
  final int projectId;
  final bool english;
  @override
  State<_ProjectProgressDialog> createState() => _ProjectProgressDialogState();
}

class _ProjectProgressDialogState extends State<_ProjectProgressDialog> {
  final summary = TextEditingController();
  final percent = TextEditingController(text: '0');
  final achievements = TextEditingController();
  final issues = TextEditingController();
  final nextSteps = TextEditingController();
  late final TextEditingController date;
  String? validationError;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    date = TextEditingController(text: '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}');
  }

  @override
  void dispose() {
    summary.dispose(); percent.dispose(); achievements.dispose(); issues.dispose(); nextSteps.dispose(); date.dispose();
    super.dispose();
  }

  Widget _field(TextEditingController controller, String label, {int lines = 1, TextInputType? keyboardType}) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextField(
      controller: controller,
      maxLines: lines,
      keyboardType: keyboardType,
      decoration: InputDecoration(labelText: label, border: const OutlineInputBorder(), isDense: true),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return AlertDialog(
      title: Text(en ? 'New progress update' : 'تحديث تقدم جديد'),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        if (validationError != null)
          Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(validationError!, style: const TextStyle(color: Colors.red))),
        _field(summary, en ? 'Summary *' : 'ملخص التقدم *', lines: 2),
        _field(date, en ? 'Date (YYYY-MM-DD) *' : 'التاريخ (YYYY-MM-DD) *'),
        _field(percent, en ? 'Completion %' : 'نسبة الإنجاز %', keyboardType: const TextInputType.numberWithOptions(decimal: true)),
        _field(achievements, en ? 'Achievements' : 'الإنجازات', lines: 2),
        _field(issues, en ? 'Issues' : 'المعوقات', lines: 2),
        _field(nextSteps, en ? 'Next steps' : 'الخطوات القادمة', lines: 2),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(en ? 'Cancel' : 'إلغاء')),
        FilledButton(
          onPressed: () {
            final summaryValue = summary.text.trim();
            final dateValue = date.text.trim();
            final percentValue = double.tryParse(percent.text.trim().isEmpty ? '0' : percent.text.trim());
            if (summaryValue.isEmpty) {
              setState(() => validationError = en ? 'A summary is required.' : 'ملخص التقدم مطلوب.');
              return;
            }
            if (dateValue.isEmpty || !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(dateValue)) {
              setState(() => validationError = en ? 'Enter the date as YYYY-MM-DD.' : 'أدخل التاريخ بصيغة YYYY-MM-DD.');
              return;
            }
            if (percentValue == null || !percentValue.isFinite || percentValue < 0 || percentValue > 100) {
              setState(() => validationError = en ? 'Completion must be between 0 and 100.' : 'نسبة الإنجاز يجب أن تكون بين 0 و100.');
              return;
            }
            Navigator.of(context).pop({
              'project_id': widget.projectId,
              'summary': summaryValue,
              'update_date': dateValue,
              'completion_percent': percentValue,
              'achievements': achievements.text.trim(),
              'issues': issues.text.trim(),
              'next_steps': nextSteps.text.trim(),
            });
          },
          child: Text(en ? 'Save' : 'حفظ'),
        ),
      ],
    );
  }
}

class _ProjectMilestoneDialog extends StatefulWidget {
  const _ProjectMilestoneDialog({required this.projectId, required this.english, this.existing});
  final int projectId;
  final bool english;
  final Map<String, dynamic>? existing;
  @override
  State<_ProjectMilestoneDialog> createState() => _ProjectMilestoneDialogState();
}

class _ProjectMilestoneDialogState extends State<_ProjectMilestoneDialog> {
  final title = TextEditingController();
  final description = TextEditingController();
  final date = TextEditingController();
  final percent = TextEditingController(text: '0');
  final notes = TextEditingController();
  String? validationError;

  @override
  void initState() {
    super.initState();
    final m = widget.existing;
    if (m != null) {
      title.text = m['title']?.toString() ?? '';
      description.text = m['description']?.toString() ?? '';
      date.text = m['planned_date']?.toString() ?? '';
      percent.text = m['completion_percent']?.toString() ?? '0';
    }
  }

  @override
  void dispose() {
    title.dispose(); description.dispose(); date.dispose(); percent.dispose(); notes.dispose();
    super.dispose();
  }

  Widget _field(TextEditingController controller, String label, {int lines = 1, TextInputType? keyboardType}) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextField(controller: controller, maxLines: lines, keyboardType: keyboardType,
      decoration: InputDecoration(labelText: label, border: const OutlineInputBorder(), isDense: true)),
  );

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return AlertDialog(
      title: Text(widget.existing == null ? (en ? 'New project milestone' : 'مرحلة مشروع جديدة') : (en ? 'Edit project milestone' : 'تعديل مرحلة المشروع')),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        if (validationError != null) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(validationError!, style: const TextStyle(color: Colors.red))),
        _field(title, en ? 'Title *' : 'عنوان المرحلة *'),
        _field(description, en ? 'Description' : 'الوصف', lines: 2),
        _field(date, en ? 'Planned date (YYYY-MM-DD)' : 'التاريخ المخطط (YYYY-MM-DD)'),
        _field(percent, en ? 'Completion %' : 'نسبة الإنجاز %', keyboardType: const TextInputType.numberWithOptions(decimal: true)),
        if (widget.existing == null) _field(notes, en ? 'Notes' : 'ملاحظات', lines: 2),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(en ? 'Cancel' : 'إلغاء')),
        FilledButton(onPressed: () {
          final titleValue = title.text.trim();
          final dateValue = date.text.trim();
          final percentValue = double.tryParse(percent.text.trim().isEmpty ? '0' : percent.text.trim());
          if (titleValue.isEmpty) {
            setState(() => validationError = en ? 'A title is required.' : 'عنوان المرحلة مطلوب.');
            return;
          }
          if (dateValue.isNotEmpty && !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(dateValue)) {
            setState(() => validationError = en ? 'Enter the planned date as YYYY-MM-DD.' : 'أدخل التاريخ المخطط بصيغة YYYY-MM-DD.');
            return;
          }
          if (percentValue == null || !percentValue.isFinite || percentValue < 0 || percentValue > 100) {
            setState(() => validationError = en ? 'Completion must be between 0 and 100.' : 'نسبة الإنجاز يجب أن تكون بين 0 و100.');
            return;
          }
          Navigator.of(context).pop({
            'project_id': widget.projectId,
            'title': titleValue,
            'description': description.text.trim(),
            'planned_date': dateValue,
            'completion_percent': percentValue,
            if (widget.existing == null) 'notes': notes.text.trim(),
          });
        }, child: Text(en ? 'Save' : 'حفظ')),
      ],
    );
  }
}
