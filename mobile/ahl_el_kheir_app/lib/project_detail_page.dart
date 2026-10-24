import 'package:flutter/material.dart';

class ProjectDetailPage extends StatefulWidget {
  const ProjectDetailPage({super.key, required this.projectId, required this.english, required this.loadDetail});
  final int projectId;
  final bool english;
  final Future<Map<String, dynamic>> Function(int projectId) loadDetail;
  @override
  State<ProjectDetailPage> createState() => _ProjectDetailPageState();
}

class _ProjectDetailPageState extends State<ProjectDetailPage> {
  bool loading = true;
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
          _SectionTitle(en ? 'Project team' : 'فريق المشروع', count: team.length),
          if (team.isEmpty) _Empty(en ? 'No active team assignments.' : 'لا توجد تكليفات فريق نشطة.')
          else ...team.map((m) => Card(child: ListTile(
            leading: CircleAvatar(child: Icon(m['is_lead'] == true ? Icons.star_rounded : Icons.person_outline_rounded)),
            title: Text(m['full_name']?.toString() ?? ''), subtitle: Text(_section(m['section_code']?.toString() ?? '')),
            trailing: m['is_lead'] == true ? Text(en ? 'Lead' : 'قائد') : null,
          ))),
          _SectionTitle(en ? 'Milestones' : 'مراحل المشروع', count: milestones.length),
          if (milestones.isEmpty) _Empty(en ? 'No milestones recorded.' : 'لا توجد مراحل مسجلة.')
          else ...milestones.map((m) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(m['title']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800)),
            if ((m['description']?.toString() ?? '').isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text(m['description'].toString())),
            const SizedBox(height: 8), Text((en ? 'Planned date: ' : 'التاريخ المخطط: ') + _date(m['planned_date'])),
            const SizedBox(height: 6), LinearProgressIndicator(value: ((double.tryParse(m['completion_percent']?.toString() ?? '') ?? 0) / 100).clamp(0.0, 1.0)),
            const SizedBox(height: 4), Text(_amount(m['completion_percent']) + '%'),
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
