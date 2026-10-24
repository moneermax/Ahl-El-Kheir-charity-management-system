import 'dart:async';
import 'package:flutter/material.dart';
import 'project_detail_page.dart';

class ProjectsPage extends StatefulWidget {
  const ProjectsPage({super.key, required this.english, required this.loadPage, required this.loadDetail, required this.submitProgress, required this.createMilestone, this.actOnMilestone});
  final bool english;
  final Future<Map<String, dynamic>> Function(String query, int page) loadPage;
  final Future<Map<String, dynamic>> Function(int projectId) loadDetail;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitProgress;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) createMilestone;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body)? actOnMilestone;

  @override
  State<ProjectsPage> createState() => _ProjectsPageState();
}

class _ProjectsPageState extends State<ProjectsPage> {
  final search = TextEditingController();
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  int total = 0, page = 1, pages = 1;
  Timer? searchDebounce;
  int loadGeneration = 0;

  @override
  void initState() { super.initState(); load(); }

  @override
  void dispose() { searchDebounce?.cancel(); search.dispose(); super.dispose(); }

  Future<void> load() async {
    final generation = ++loadGeneration;
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadPage(search.text.trim(), page);
      final raw = data['items'];
      final next = raw is List
          ? raw.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted || generation != loadGeneration) return;
      setState(() {
        items = next;
        total = int.tryParse(data['total']?.toString() ?? '') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '') ?? 1;
      });
    } catch (e) {
      if (mounted && generation == loadGeneration) setState(() => error = e.toString());
    } finally {
      if (mounted && generation == loadGeneration) setState(() => loading = false);
    }
  }

  void searchChanged(String value) {
    setState(() {});
    searchDebounce?.cancel();
    page = 1;
    searchDebounce = Timer(const Duration(milliseconds: 350), load);
  }

  String statusLabel(String status) {
    final labels = widget.english
        ? const {'planned':'Planned','active':'Active','completed':'Completed','under_review':'Under review','closed':'Closed','reopened':'Reopened','cancelled':'Cancelled'}
        : const {'planned':'مخطط','active':'نشط','completed':'مكتمل','under_review':'قيد المراجعة','closed':'مغلق','reopened':'أعيد فتحه','cancelled':'ملغي'};
    return labels[status] ?? status;
  }

  String approvalLabel(String status) {
    final labels = widget.english
        ? const {'draft':'Draft','submitted':'Awaiting approval','fm_approved':'Financially approved','approved':'Approved','rejected':'Rejected'}
        : const {'draft':'مسودة','submitted':'بانتظار الاعتماد','fm_approved':'معتمد مالياً','approved':'معتمد','rejected':'مرفوض'};
    return labels[status] ?? status;
  }

  String amount(dynamic value) => (double.tryParse(value?.toString() ?? '') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    final query = search.text.trim();
    final visible = items;

    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Projects' : 'المشروعات')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: search,
            onChanged: searchChanged,
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.search_rounded),
              hintText: en ? 'Search projects' : 'بحث عن مشروع',
              suffixIcon: query.isEmpty ? null : IconButton(
                onPressed: () { search.clear(); searchChanged(''); },
                icon: const Icon(Icons.clear_rounded),
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(16, 0, 16, 8),
          child: Align(
            alignment: AlignmentDirectional.centerStart,
            child: Text(en ? 'Projects available: $total' : 'عدد المشروعات المتاحة: $total'),
          ),
        ),
        Expanded(
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : error != null
                  ? Center(child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: load,
                          icon: const Icon(Icons.refresh_rounded),
                          label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                        ),
                      ]),
                    ))
                  : visible.isEmpty
                      ? Center(child: Text(en ? 'No projects found.' : 'لا توجد مشروعات مطابقة.'))
                      : RefreshIndicator(
                          onRefresh: load,
                          child: ListView.separated(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                            itemCount: visible.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 4),
                            itemBuilder: (context, index) {
                              final project = visible[index];
                              final approval = project['approval_status']?.toString() ?? '';
                              return Card(child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProjectDetailPage(projectId: int.tryParse(project['id']?.toString() ?? '') ?? 0, english: en, loadDetail: widget.loadDetail, submitProgress: widget.submitProgress, createMilestone: widget.createMilestone, actOnMilestone: widget.actOnMilestone))),
                                child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Expanded(child: Text(
                                      project['name']?.toString() ?? '',
                                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: const Color(0xFF0A1F44),
                                      ),
                                    )),
                                    const SizedBox(width: 8),
                                    Chip(label: Text(statusLabel(project['current_status']?.toString() ?? '')),
                                      visualDensity: VisualDensity.compact),
                                  ]),
                                  const SizedBox(height: 4),
                                  Text(project['project_code']?.toString() ?? '',
                                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: const Color(0xFF64748B), fontWeight: FontWeight.w700)),
                                  const SizedBox(height: 10),
                                  Wrap(spacing: 8, runSpacing: 8, children: [
                                    _ProjectMetric(label: en ? 'Budget' : 'الميزانية', value: amount(project['budget'])),
                                    _ProjectMetric(label: en ? 'Funded' : 'الممول', value: amount(project['funded'])),
                                    _ProjectMetric(label: en ? 'Expenses' : 'المصروفات', value: amount(project['expenses'])),
                                  ]),
                                  const SizedBox(height: 8),
                                  Text((en ? 'Approval: ' : 'حالة الاعتماد: ') + approvalLabel(approval),
                                    style: Theme.of(context).textTheme.bodySmall),
                                  const SizedBox(height: 4),
                                  Text((en ? 'Beneficiaries: ' : 'المستفيدون: ') +
                                      (project['beneficiary_count']?.toString() ?? '0'),
                                    style: Theme.of(context).textTheme.bodySmall),
                                  if ((project['supervisor_name']?.toString() ?? '').isNotEmpty)
                                    Text((en ? 'Supervisor: ' : 'المشرف: ') +
                                        (project['supervisor_name']?.toString() ?? ''),
                                      style: Theme.of(context).textTheme.bodySmall),
                                ]),
                              )));
                            },
                          ),
                        ),
        ),
        if (!loading && error == null && pages > 1)
          SafeArea(top: false, child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              OutlinedButton(
                onPressed: page > 1 ? () { setState(() => page--); load(); } : null,
                child: Text(en ? 'Previous' : 'السابق'),
              ),
              Text(en ? 'Page $page of $pages' : 'صفحة $page من $pages'),
              OutlinedButton(
                onPressed: page < pages ? () { setState(() => page++); load(); } : null,
                child: Text(en ? 'Next' : 'التالي'),
              ),
            ]),
          )),
      ]),
    );
  }
}

class _ProjectMetric extends StatelessWidget {
  const _ProjectMetric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(10)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: Theme.of(context).textTheme.bodySmall),
      const SizedBox(height: 3),
      Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
    ]),
  );
}
