import 'package:flutter/material.dart';

class EmployeeDetailPage extends StatefulWidget {
  const EmployeeDetailPage({
    super.key,
    required this.employeeId,
    required this.english,
    required this.loadDetail,
  });

  final int employeeId;
  final bool english;
  final Future<Map<String, dynamic>> Function(int id) loadDetail;

  @override
  State<EmployeeDetailPage> createState() => _EmployeeDetailPageState();
}

class _EmployeeDetailPageState extends State<EmployeeDetailPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> employee = {};

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadDetail(widget.employeeId);
      final raw = data['employee'];
      if (raw is! Map) throw Exception('Invalid employee response');
      if (!mounted) return;
      setState(() => employee = Map<String, dynamic>.from(raw));
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String label(String en, String ar) => widget.english ? en : ar;

  String value(String key) {
    final raw = employee[key]?.toString().trim() ?? '';
    return raw.isEmpty ? '—' : raw;
  }

  String employmentState() {
    if (employee['employment_state_category'] == 'working') {
      return label('Working', 'على رأس العمل');
    }
    final raw = widget.english
        ? employee['employment_state_name_en']
        : employee['employment_state_name'];
    final text = raw?.toString().trim() ?? '';
    return text.isEmpty ? '—' : text;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(label('Employee details', 'بيانات الموظف'))),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: load,
                          icon: const Icon(Icons.refresh_rounded),
                          label: Text(label('Try again', 'إعادة المحاولة')),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                value('full_name'),
                                style: Theme.of(context).textTheme.titleLarge
                                    ?.copyWith(fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                value('employee_code'),
                                style: const TextStyle(color: Color(0xFF64748B)),
                              ),
                              const SizedBox(height: 10),
                              Chip(label: Text(employmentState())),
                            ],
                          ),
                        ),
                      ),
                      Card(
                        child: Column(
                          children: [
                            _EmployeeDetailRow(label: label('Position', 'المسمى الوظيفي'), value: value('position')),
                            _EmployeeDetailRow(label: label('Department', 'القسم'), value: value(en ? 'department_name_en' : 'department_name_ar')),
                            _EmployeeDetailRow(label: label('Hire date', 'تاريخ التعيين'), value: value('hire_date')),
                            _EmployeeDetailRow(label: label('Employment type', 'نوع التوظيف'), value: value('employment_type')),
                            _EmployeeDetailRow(label: label('Work mode', 'نظام العمل'), value: value('work_mode')),
                            _EmployeeDetailRow(label: label('Phone', 'الهاتف'), value: value('phone')),
                            _EmployeeDetailRow(label: label('Email', 'البريد الإلكتروني'), value: value('email')),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}

class _EmployeeDetailRow extends StatelessWidget {
  const _EmployeeDetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            Expanded(
              child: Text(label, style: const TextStyle(color: Color(0xFF64748B))),
            ),
            const SizedBox(width: 12),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      );
}
