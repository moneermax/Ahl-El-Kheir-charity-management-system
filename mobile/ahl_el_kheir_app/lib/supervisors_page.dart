import 'package:flutter/material.dart';

class SupervisorsPage extends StatefulWidget {
  const SupervisorsPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.canManage,
    required this.submitStatusAction,
  });

  final bool english;
  final bool canManage;
  final Future<Map<String, dynamic>> Function(String query, bool showArchived, int page) loadPage;
  final Future<Map<String, dynamic>> Function(int supervisorId, String action) submitStatusAction;

  @override
  State<SupervisorsPage> createState() => _SupervisorsPageState();
}

class _SupervisorsPageState extends State<SupervisorsPage> {
  final search = TextEditingController();
  bool loading = true;
  bool showArchived = false;
  String? error;
  List<Map<String, dynamic>> items = [];
  int total = 0, page = 1, pages = 1;
  final Set<int> savingStatusIds = <int>{};

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> toggleSupervisorStatus(Map<String, dynamic> item) async {
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (!widget.canManage || id < 1 || savingStatusIds.contains(id) || (item['supervisor_status']?.toString() == 'archived')) return;
    final en = widget.english;
    final isActive = item['is_active'] == true || item['is_active']?.toString() == '1';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(en ? (isActive ? 'Suspend supervisor?' : 'Activate supervisor?') : (isActive ? 'هل تريد إيقاف المشرف؟' : 'هل تريد تفعيل المشرف؟')),
        content: Text(en
            ? (isActive ? 'The supervisor will no longer be able to sign in until reactivated.' : 'The supervisor account will be activated.')
            : (isActive ? 'لن يتمكن المشرف من تسجيل الدخول حتى إعادة تفعيله.' : 'سيتم تفعيل حساب المشرف.')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(en ? 'Cancel' : 'إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(en ? 'Confirm' : 'تأكيد')),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => savingStatusIds.add(id));
    try {
      await widget.submitStatusAction(id, 'toggle_status');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(en ? 'Supervisor status updated.' : 'تم تحديث حالة المشرف.'),
      ));
      await load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => savingStatusIds.remove(id));
    }
  }


  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadPage(search.text.trim(), showArchived, page);
      final raw = data['items'];
      final next = raw is List
          ? raw.whereType<Map>().map((value) => Map<String, dynamic>.from(value)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        items = next;
        total = int.tryParse(data['total']?.toString() ?? '') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String statusLabel(String value) {
    final labels = widget.english
        ? const {'active': 'Active', 'suspended': 'Suspended', 'on_leave': 'On leave', 'returning': 'Returning', 'departed': 'Departed', 'archived': 'Archived'}
        : const {'active': 'نشط', 'suspended': 'موقوف', 'on_leave': 'في إجازة', 'returning': 'قيد العودة', 'departed': 'غادر العمل', 'archived': 'مؤرشف'};
    return labels[value] ?? value;
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Supervisors' : 'المشرفون')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: TextField(
              controller: search,
              onSubmitted: (_) {
                setState(() => page = 1);
                load();
              },
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search_rounded),
                hintText: en ? 'Search name, username or phone' : 'بحث بالاسم أو اسم المستخدم أو الهاتف',
                suffixIcon: IconButton(
                  onPressed: () {
                    search.clear();
                    setState(() => page = 1);
                    load();
                  },
                  tooltip: en ? 'Clear search' : 'مسح البحث',
                  icon: const Icon(Icons.clear_rounded),
                ),
              ),
            ),
          ),
          SwitchListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 16),
            title: Text(en ? 'Include departed and archived' : 'إظهار من غادروا والمؤرشفين'),
            value: showArchived,
            onChanged: (value) {
              setState(() {
                showArchived = value;
                page = 1;
              });
              load();
            },
          ),
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(16, 0, 16, 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(en ? 'Supervisors: $total' : 'عدد المشرفين: $total'),
            ),
          ),
          Expanded(
            child: loading
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
                                label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: load,
                        child: items.isEmpty
                            ? ListView(
                                physics: const AlwaysScrollableScrollPhysics(),
                                children: [
                                  Padding(
                                    padding: const EdgeInsets.all(28),
                                    child: Text(en ? 'No supervisors found.' : 'لا يوجد مشرفون مطابقون.', textAlign: TextAlign.center),
                                  ),
                                ],
                              )
                            : ListView.separated(
                                physics: const AlwaysScrollableScrollPhysics(),
                                padding: const EdgeInsets.all(12),
                                itemCount: items.length + (pages > 1 ? 1 : 0),
                                separatorBuilder: (_, __) => const SizedBox(height: 6),
                                itemBuilder: (context, index) {
                                  if (index == items.length) {
                                    return Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        IconButton(onPressed: page > 1 ? () { setState(() => page--); load(); } : null, icon: const Icon(Icons.chevron_left_rounded)),
                                        Text(en ? 'Page $page of $pages' : 'صفحة $page من $pages'),
                                        IconButton(onPressed: page < pages ? () { setState(() => page++); load(); } : null, icon: const Icon(Icons.chevron_right_rounded)),
                                      ],
                                    );
                                  }
                                  final item = items[index];
                                  final letters = item['letters']?.toString() ?? '';
                                  final phone = item['phone']?.toString() ?? '';
                                  final email = item['email']?.toString() ?? '';
                                  final status = item['supervisor_status']?.toString() ?? '';
                                  final subtitle = [
                                    if (phone.isNotEmpty) phone,
                                    if (email.isNotEmpty) email,
                                    if (letters.isNotEmpty) '${en ? 'Letters' : 'الحروف'}: $letters',
                                  ].join(' • ');
                                  return Card(
                                    child: ListTile(
                                      leading: const CircleAvatar(child: Icon(Icons.supervisor_account_rounded)),
                                      title: Text(item['full_name']?.toString() ?? ''),
                                      subtitle: Text(subtitle.isEmpty ? '@${item['username'] ?? ''}' : '@${item['username'] ?? ''}\n$subtitle'),
                                      isThreeLine: subtitle.isNotEmpty,
                                      trailing: widget.canManage && status != 'archived'
                                          ? Row(
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Text(statusLabel(status), textAlign: TextAlign.end),
                                                const SizedBox(width: 4),
                                                IconButton(
                                                  tooltip: en ? (item['is_active'] == true ? 'Suspend supervisor' : 'Activate supervisor') : (item['is_active'] == true ? 'إيقاف المشرف' : 'تفعيل المشرف'),
                                                  onPressed: savingStatusIds.contains(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                                                      ? null
                                                      : () => toggleSupervisorStatus(item),
                                                  icon: savingStatusIds.contains(int.tryParse(item['id']?.toString() ?? '') ?? 0)
                                                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                                      : Icon(item['is_active'] == true ? Icons.pause_circle_outline_rounded : Icons.play_circle_outline_rounded),
                                                ),
                                              ],
                                            )
                                          : Text(statusLabel(status), textAlign: TextAlign.end),
                                    ),
                                  );
                                },
                              ),
                      ),
          ),
        ],
      ),
    );
  }
}
