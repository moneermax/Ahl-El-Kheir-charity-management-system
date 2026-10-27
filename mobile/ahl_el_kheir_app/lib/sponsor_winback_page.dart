
import 'package:flutter/material.dart';

class SponsorWinbackPage extends StatefulWidget {
  const SponsorWinbackPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.submitAction,
    required this.openFamily,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(int? viewId) loadPage;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitAction;
  final void Function(int familyId) openFamily;

  @override
  State<SponsorWinbackPage> createState() => _SponsorWinbackPageState();
}

class _SponsorWinbackPageState extends State<SponsorWinbackPage> {
  bool loading = true;
  bool submitting = false;
  String? error;
  Map<String, dynamic> data = {};
  int? selectedCaseId;
  int? selectedReactivatorId;
  String contactMethod = 'phone';
  String contactOutcome = 'no_answer';
  final notesController = TextEditingController();

  String t(String ar, String en) => widget.english ? en : ar;
  String str(dynamic v) => v == null || v.toString().isEmpty ? '—' : v.toString();
  int number(dynamic v) => v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;
  String amount(dynamic v) => (v is num ? v : num.tryParse(v?.toString() ?? '') ?? 0).toStringAsFixed(0);

  Map<String, dynamic>? get detail {
    final value = data['detail'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  List<Map<String, dynamic>> rows(String key) {
    final value = data[key];
    if (value is! List) return [];
    return value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList();
  }

  String statusLabel(String status) => ({
    'open': t('مفتوحة', 'Open'),
    'contacted': t('تم التواصل', 'Contacted'),
    'persuaded': t('عاد للكفالة', 'Returned'),
    'declined': t('اعتذر عن العودة', 'Declined'),
  })[status] ?? status;

  Color statusColor(String status) => ({
    'open': Colors.amber.shade800,
    'contacted': Colors.blue.shade700,
    'persuaded': Colors.green.shade700,
    'declined': Colors.red.shade700,
  })[status] ?? Colors.grey;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    notesController.dispose();
    super.dispose();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final result = await widget.loadPage(selectedCaseId);
      if (!mounted) return;
      setState(() {
        data = result;
        loading = false;
        final employees = rows('reactivators');
        if (employees.isEmpty) {
          selectedReactivatorId = null;
        } else if (selectedReactivatorId == null ||
            !employees.any((e) => number(e['user_id']) == selectedReactivatorId)) {
          selectedReactivatorId = number(employees.first['user_id']);
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { error = e.toString(); loading = false; });
    }
  }

  Future<void> runAction(Map<String, dynamic> body, {bool openCreatedCase = false}) async {
    if (submitting) return;
    setState(() => submitting = true);
    try {
      final result = await widget.submitAction(body);
      final newId = number(result['campaign_id']);
      if (openCreatedCase && newId > 0) selectedCaseId = newId;
      if (body['action'] == 'add_contact') notesController.clear();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('تم حفظ الإجراء بنجاح.', 'Action saved successfully.'))),
      );
      await refresh();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Theme.of(context).colorScheme.error),
      );
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  Widget heading(String title, String subtitle) => Padding(
    padding: const EdgeInsets.fromLTRB(2, 20, 2, 8),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
      const SizedBox(height: 3),
      Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
    ]),
  );

  Widget empty(String message) => Padding(
    padding: const EdgeInsets.all(18),
    child: Center(child: Text(message, textAlign: TextAlign.center)),
  );

  Widget metric(String label, String value, IconData icon, Color color) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(children: [
        CircleAvatar(backgroundColor: color.withValues(alpha: 0.12), child: Icon(icon, color: color)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 4),
          Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
        ])),
      ]),
    ),
  );

  Widget familyCard(Map<String, dynamic> item, {bool past = false}) {
    final familyId = number(item['id']);
    final activeNow = number(item['active_now']) > 0;
    final subtitle = past
        ? '${str(item['family_code'])}\n${t('الحالة', 'Status')}: ${activeNow ? t('مكفولة حالياً', 'Currently sponsored') : t('متاحة حالياً', 'Currently available')}'
        : '${str(item['family_code'])} • ${str(item['city'])}\n${t('الأطفال', 'Children')}: ${number(item['children_count'])} • ${t('الاحتياج الشهري', 'Monthly need')}: ${amount(item['monthly_need_amount'])}';
    return Card(child: ListTile(
      title: Text(str(item['mother_name']), style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle: Text(subtitle),
      isThreeLine: true,
      trailing: IconButton(
        tooltip: t('عرض الأسرة', 'Open family'),
        onPressed: familyId > 0 ? () => widget.openFamily(familyId) : null,
        icon: const Icon(Icons.open_in_new_rounded),
      ),
    ));
  }

  Widget sponsorQueueCard(Map<String, dynamic> item) {
    final sponsorId = number(item['id']);
    return Card(child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(str(item['full_name']), style: const TextStyle(fontWeight: FontWeight.w800)),
        Text(str(item['sponsor_code']), style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 8),
        Wrap(spacing: 14, runSpacing: 6, children: [
          Text('${t('الهاتف', 'Phone')}: ${str(item['phone'])}'),
          Text('${t('الكفالات السابقة', 'Past sponsorships')}: ${number(item['total_ships'])}'),
          Text('${t('آخر توقف', 'Last stopped')}: ${str(item['last_stop'])}'),
        ]),
        const SizedBox(height: 10),
        Align(alignment: AlignmentDirectional.centerEnd, child: FilledButton.icon(
          onPressed: submitting ? null : () => runAction(
            {'action': 'open_case', 'sponsor_id': sponsorId},
            openCreatedCase: true,
          ),
          icon: const Icon(Icons.folder_open_rounded),
          label: Text(t('بدء المتابعة', 'Start follow-up')),
        )),
      ]),
    ));
  }

  Widget caseCard(Map<String, dynamic> item) {
    final caseId = number(item['id']);
    final status = str(item['status']);
    return Card(child: ListTile(
      title: Text(str(item['sponsor_name']), style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Wrap(spacing: 10, runSpacing: 6, children: [
          Text('${t('المسؤول', 'Handler')}: ${str(item['handler_name'])}'),
          Text('${t('آخر تواصل', 'Last contact')}: ${str(item['last_contact_at'])}'),
          Chip(
            visualDensity: VisualDensity.compact,
            label: Text(statusLabel(status)),
            labelStyle: TextStyle(color: statusColor(status), fontWeight: FontWeight.w700),
            backgroundColor: statusColor(status).withValues(alpha: 0.08),
            side: BorderSide(color: statusColor(status).withValues(alpha: 0.35)),
          ),
        ]),
      ),
      trailing: IconButton(
        tooltip: t('عرض المتابعة', 'Open case'),
        onPressed: () async { setState(() => selectedCaseId = caseId); await refresh(); },
        icon: const Icon(Icons.chevron_right_rounded),
      ),
    ));
  }

  Widget contactForm(Map<String, dynamic> item) {
    final campaignId = number(item['id']);
    final employees = rows('reactivators');
    return Card(child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(t('تسجيل محاولة تواصل', 'Record a contact attempt'), style: const TextStyle(fontWeight: FontWeight.w800)),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: contactMethod,
          decoration: InputDecoration(labelText: t('طريقة التواصل', 'Contact method')),
          items: [
            DropdownMenuItem(value: 'phone', child: Text(t('هاتفياً', 'Phone'))),
            DropdownMenuItem(value: 'whatsapp', child: Text(t('واتساب', 'WhatsApp'))),
            DropdownMenuItem(value: 'visit', child: Text(t('زيارة', 'Visit'))),
          ],
          onChanged: submitting ? null : (v) => setState(() => contactMethod = v ?? 'phone'),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          initialValue: contactOutcome,
          decoration: InputDecoration(labelText: t('النتيجة', 'Outcome')),
          items: [
            DropdownMenuItem(value: 'no_answer', child: Text(t('لا رد', 'No answer'))),
            DropdownMenuItem(value: 'reached', child: Text(t('تم الوصول', 'Reached'))),
            DropdownMenuItem(value: 'promised', child: Text(t('وعد بالعودة', 'Promised to return'))),
            DropdownMenuItem(value: 'refused', child: Text(t('اعتذر عن العودة', 'Declined return'))),
          ],
          onChanged: submitting ? null : (v) => setState(() => contactOutcome = v ?? 'no_answer'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: notesController,
          maxLines: 2,
          decoration: InputDecoration(labelText: t('ملاحظات', 'Notes'), border: const OutlineInputBorder()),
        ),
        Align(alignment: AlignmentDirectional.centerEnd, child: FilledButton.icon(
          onPressed: submitting ? null : () => runAction({
            'action': 'add_contact',
            'campaign_id': campaignId,
            'method': contactMethod,
            'outcome': contactOutcome,
            'notes': notesController.text.trim(),
          }),
          icon: const Icon(Icons.add_call),
          label: Text(t('تسجيل التواصل', 'Save contact')),
        )),
        const Divider(height: 28),
        Text(t('تأكيد العودة الفعلية', 'Confirm actual reactivation'), style: const TextStyle(fontWeight: FontWeight.w800)),
        const SizedBox(height: 8),
        if (employees.isEmpty)
          Text(t('لا يوجد موظف نشط مؤهل مرتبط بحساب مستخدم.', 'No eligible active employee is linked to a user account.'))
        else
          DropdownButtonFormField<int>(
            initialValue: selectedReactivatorId,
            decoration: InputDecoration(labelText: t('الموظف الذي أعاد الكفيل', 'Employee who reactivated the sponsor')),
            items: employees.map((employee) => DropdownMenuItem<int>(
              value: number(employee['user_id']),
              child: Text('${str(employee['full_name'])} (${str(employee['employee_code'])})'),
            )).toList(),
            onChanged: submitting ? null : (v) => setState(() => selectedReactivatorId = v),
          ),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [
          FilledButton.icon(
            onPressed: submitting || employees.isEmpty || selectedReactivatorId == null ? null : () => runAction({
              'action': 'mark_returned',
              'campaign_id': campaignId,
              'reactivated_by_user_id': selectedReactivatorId,
            }),
            icon: const Icon(Icons.verified_user_outlined),
            label: Text(t('تأكيد عودة الكفيل', 'Confirm sponsor returned')),
          ),
          OutlinedButton.icon(
            onPressed: submitting ? null : () => runAction({'action': 'mark_declined', 'campaign_id': campaignId}),
            icon: const Icon(Icons.person_off_outlined),
            label: Text(t('اعتذر عن العودة', 'Declined')),
          ),
        ]),
      ]),
    ));
  }

  Widget detailBody(Map<String, dynamic> item) {
    final campaignId = number(item['id']);
    final status = str(item['status']);
    final contacts = rows('contacts');
    final pastFamilies = rows('past_families');
    return ListView(padding: const EdgeInsets.all(16), children: [
      Row(children: [
        IconButton(
          tooltip: t('رجوع للقائمة', 'Back to list'),
          onPressed: () async { setState(() => selectedCaseId = null); await refresh(); },
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        Expanded(child: Text(str(item['sponsor_name']), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
        Chip(
          label: Text(statusLabel(status)),
          labelStyle: TextStyle(color: statusColor(status), fontWeight: FontWeight.w700),
          backgroundColor: statusColor(status).withValues(alpha: 0.08),
          side: BorderSide(color: statusColor(status).withValues(alpha: 0.35)),
        ),
      ]),
      Card(child: Padding(
        padding: const EdgeInsets.all(14),
        child: Wrap(spacing: 18, runSpacing: 10, children: [
          Text('${t('رمز الكفيل', 'Sponsor code')}: ${str(item['sponsor_code'])}'),
          Text('${t('الهاتف', 'Phone')}: ${str(item['sponsor_phone'])}'),
          Text('${t('الحالة الحالية', 'Sponsor status')}: ${str(item['sponsor_status'])}'),
          Text('${t('المسؤول', 'Handler')}: ${str(item['handler_name'])}'),
          Text('${t('بدأت في', 'Opened')}: ${str(item['opened_at'])}'),
          Text('${t('آخر تواصل', 'Last contact')}: ${str(item['last_contact_at'])}'),
        ]),
      )),
      heading(t('الأسر السابقة لهذا الكفيل', 'This sponsor’s previous families'), t('عرض حالة التغطية الحالية دون منع إعادة التكليف.', 'Shows current coverage without blocking future assignment.')),
      if (pastFamilies.isEmpty) empty(t('لا توجد كفالات سابقة مسجلة.', 'No previous sponsorships were found.')),
      for (final family in pastFamilies) familyCard(family, past: true),
      heading(t('سجل التواصل', 'Contact history'), t('جميع محاولات التواصل المسجلة لهذه المتابعة.', 'All contact attempts recorded for this case.')),
      if (contacts.isEmpty) empty(t('لا توجد محاولات تواصل مسجلة.', 'No contact attempts recorded.')),
      for (final contact in contacts)
        Card(child: ListTile(
          leading: const Icon(Icons.call_outlined),
          title: Text('${str(contact['method'])} • ${str(contact['outcome'])}'),
          subtitle: Text('${str(contact['contact_date'])}\n${t('بواسطة', 'By')}: ${str(contact['by_name'])}\n${str(contact['notes'])}'),
          isThreeLine: true,
        )),
      if (status == 'open' || status == 'contacted') contactForm(item),
      if (status == 'declined')
        Card(child: Padding(
          padding: const EdgeInsets.all(14),
          child: FilledButton.icon(
            onPressed: submitting ? null : () => runAction({'action': 'reopen_case', 'campaign_id': campaignId}),
            icon: const Icon(Icons.folder_open_rounded),
            label: Text(t('إعادة فتح المتابعة', 'Reopen case')),
          ),
        )),
      if (status == 'persuaded')
        Card(child: ListTile(
          leading: const Icon(Icons.check_circle_outline, color: Colors.green),
          title: Text(t('تمت إعادة تفعيل الكفيل', 'Sponsor reactivation confirmed')),
          subtitle: Text('${t('الموظف المنسوب إليه النشاط', 'Credited employee')}: ${str(item['reactivated_by_name'] ?? item['reactivated_by_user_id'])}\n${t('وقت التفعيل', 'Reactivated at')}: ${str(item['reactivated_at'])}'),
          isThreeLine: true,
        )),
    ]);
  }

  Widget listBody() {
    final queue = rows('queue');
    final cases = rows('cases');
    final families = rows('uncovered_families');
    final openCount = cases.where((item) => ['open', 'contacted'].contains(item['status'])).length;
    return ListView(padding: const EdgeInsets.all(16), children: [
      Text(t('استرجاع الكفلاء المتوقفين', 'Lapsed sponsor recovery'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
      const SizedBox(height: 4),
      Text(t('متابعة الكفلاء المتوقفين لأكثر من 90 يوماً مع حفظ سجل التواصل وإسناد إعادة التفعيل.', 'Follow up sponsors inactive for more than 90 days, preserving contact history and reactivation attribution.')),
      const SizedBox(height: 12),
      metric(t('كفلاء مؤهلون', 'Eligible sponsors'), queue.length.toString(), Icons.person_search_outlined, Colors.amber.shade800),
      metric(t('متابعات مفتوحة', 'Open follow-ups'), openCount.toString(), Icons.pending_actions_rounded, Colors.indigo),
      metric(t('أسر متاحة للتكليف', 'Available families'), families.length.toString(), Icons.home_work_outlined, Colors.teal),
      heading(t('الكفلاء المتوقفون المؤهلون', 'Eligible lapsed sponsors'), t('بدء متابعة جديدة متاح فقط عند تحقق شروط الأهلية.', 'A new case can be opened only when eligibility rules are met.')),
      if (queue.isEmpty) empty(t('لا يوجد كفلاء مؤهلون حالياً.', 'No eligible sponsors right now.')),
      for (final sponsor in queue) sponsorQueueCard(sponsor),
      heading(t('سجل المتابعات', 'Follow-up register'), t('آخر 200 متابعة، مع تقديم المفتوحة أولاً.', 'Latest 200 cases, with open cases first.')),
      if (cases.isEmpty) empty(t('لا توجد متابعات بعد.', 'No win-back cases yet.')),
      for (final item in cases) caseCard(item),
      heading(t('أسر متاحة للتكليف حالياً', 'Families currently available for assignment'), t('مرجع سريع للأسر النشطة أو المعلقة غير المكفولة.', 'Quick reference for active or pending families without active sponsorship.')),
      if (families.isEmpty) empty(t('لا توجد أسر متاحة حالياً.', 'No available families right now.')),
      for (final family in families) familyCard(family),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(t('متابعات استرجاع الكفلاء', 'Sponsor win-back')),
        actions: [
          IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Center(child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(Icons.cloud_off_rounded, size: 42),
                    const SizedBox(height: 12),
                    Text(error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton.icon(onPressed: refresh, icon: const Icon(Icons.refresh_rounded), label: Text(t('إعادة المحاولة', 'Try again'))),
                  ]),
                ))
              : detail != null
                  ? detailBody(detail!)
                  : RefreshIndicator(onRefresh: refresh, child: listBody()),
    );
  }
}
