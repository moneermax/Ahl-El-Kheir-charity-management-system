import 'package:flutter/material.dart';
import 'admin_user_create_page.dart';

class AdminUsersPage extends StatefulWidget {
  const AdminUsersPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.loadOptions,
    required this.currentUserId,
    required this.submitAction,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(String query, int page) loadPage;
  final Future<Map<String, dynamic>> Function() loadOptions;
  final int currentUserId;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> payload) submitAction;

  @override
  State<AdminUsersPage> createState() => _AdminUsersPageState();
}

class _AdminUsersPageState extends State<AdminUsersPage> {
  final searchController = TextEditingController();
  bool loading = true;
  bool saving = false;
  String? error;
  int page = 1;
  int pages = 1;
  int total = 0;
  List<Map<String, dynamic>> items = [];

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  Future<void> refresh({int? requestedPage}) async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.loadPage(
        searchController.text.trim(),
        requestedPage ?? page,
      );
      final raw = response['items'];
      if (!mounted) return;
      setState(() {
        items = raw is List
            ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
            : <Map<String, dynamic>>[];
        total = int.tryParse(response['total']?.toString() ?? '') ?? 0;
        page = int.tryParse(response['page']?.toString() ?? '') ?? 1;
        pages = int.tryParse(response['pages']?.toString() ?? '') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> act(Map<String, dynamic> item, String action) async {
    final en = widget.english;
    final id = int.tryParse(item['id']?.toString() ?? '');
    if (id == null || id < 1 || id == widget.currentUserId) return;

    Map<String, dynamic>? payload;
    if (action == 'toggle') {
      final active = item['is_active'] == true;
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(active ? (en ? 'Disable account?' : 'إيقاف الحساب؟') : (en ? 'Enable account?' : 'تفعيل الحساب؟')),
          content: Text((en ? 'Account: ' : 'الحساب: ') + (item['username']?.toString() ?? '—')),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(en ? 'Cancel' : 'إلغاء')),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(active ? (en ? 'Disable' : 'إيقاف') : (en ? 'Enable' : 'تفعيل'))),
          ],
        ),
      );
      if (confirmed != true) return;
      payload = {'action': 'toggle', 'user_id': id};
    } else if (action == 'reset_password') {
      final passwordController = TextEditingController();
      final password = await showDialog<String>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(en ? 'Reset password' : 'إعادة تعيين كلمة المرور'),
          content: TextField(
            controller: passwordController,
            obscureText: true,
            textDirection: TextDirection.ltr,
            decoration: InputDecoration(
              labelText: en ? 'New password (min 6 characters)' : 'كلمة المرور الجديدة (6 أحرف على الأقل)',
              border: const OutlineInputBorder(),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(en ? 'Cancel' : 'إلغاء')),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, passwordController.text), child: Text(en ? 'Reset' : 'إعادة التعيين')),
          ],
        ),
      );
      passwordController.dispose();
      if (password == null) return;
      if (password.length < 6) {
        setState(() => error = en ? 'The password must contain at least 6 characters.' : 'يجب ألا تقل كلمة المرور عن 6 أحرف.');
        return;
      }
      payload = {'action': 'reset_password', 'user_id': id, 'new_password': password};
    }
    if (payload == null) return;

    setState(() {
      saving = true;
      error = null;
    });
    try {
      final result = await widget.submitAction(payload);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(result['message']?.toString() ??
            (en ? 'User account updated.' : 'تم تحديث حساب المستخدم.')),
      ));
      await refresh(requestedPage: page);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(en ? 'User directory' : 'دليل المستخدمين'),
        actions: [
          IconButton(
            tooltip: en ? 'Create account' : 'إنشاء حساب',
            onPressed: saving ? null : () {
              Navigator.of(context).push(MaterialPageRoute<void>(
                builder: (_) => AdminUserCreatePage(
                  english: en,
                  loadOptions: widget.loadOptions,
                  submitAction: widget.submitAction,
                ),
              )).then((_) => refresh(requestedPage: page));
            },
            icon: const Icon(Icons.person_add_alt_1_rounded),
          ),
          IconButton(
            tooltip: en ? 'Refresh' : 'تحديث',
            onPressed: loading ? null : () => refresh(requestedPage: page),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            child: TextField(
              controller: searchController,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => refresh(requestedPage: 1),
              decoration: InputDecoration(
                hintText: en ? 'Search name, username, email or role' : 'ابحث بالاسم أو اسم المستخدم أو البريد أو الدور',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: IconButton(
                  tooltip: en ? 'Search' : 'بحث',
                  onPressed: loading ? null : () => refresh(requestedPage: 1),
                  icon: const Icon(Icons.arrow_forward_rounded),
                ),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 5),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    en ? '$total accounts · page $page of $pages' : '$total حساب · الصفحة $page من $pages',
                    style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12),
                  ),
                ),
                if (loading)
                  const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
              ],
            ),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: Material(
                color: scheme.errorContainer,
                borderRadius: BorderRadius.circular(12),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Expanded(child: Text(error!, style: TextStyle(color: scheme.onErrorContainer))),
                      IconButton(
                        onPressed: loading ? null : () => refresh(requestedPage: page),
                        icon: const Icon(Icons.refresh_rounded),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => refresh(requestedPage: page),
              child: items.isEmpty && !loading
                  ? ListView(
                      children: [
                        const SizedBox(height: 80),
                        Icon(Icons.group_off_rounded, size: 48, color: scheme.onSurfaceVariant),
                        const SizedBox(height: 12),
                        Center(child: Text(en ? 'No users found.' : 'لا يوجد مستخدمون مطابقون.')),
                      ],
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(12, 6, 12, 12),
                      itemCount: items.length,
                      itemBuilder: (context, index) {
                        final item = items[index];
                        final active = item['is_active'] == true;
                        final role = (en ? item['role_en'] : item['role_name'])?.toString() ?? item['role_code']?.toString() ?? '—';
                        final department = (en ? item['dept_en'] : item['dept_name'])?.toString() ?? '';
                        return Card(
                          margin: const EdgeInsets.only(bottom: 9),
                          child: Padding(
                            padding: const EdgeInsets.all(13),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    CircleAvatar(
                                      backgroundColor: active ? scheme.primaryContainer : scheme.errorContainer,
                                      child: Icon(Icons.person_rounded, color: active ? scheme.onPrimaryContainer : scheme.onErrorContainer),
                                    ),
                                    const SizedBox(width: 11),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(item['full_name']?.toString().isNotEmpty == true ? item['full_name'].toString() : '—',
                                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                                          const SizedBox(height: 3),
                                          Text('@${item['username'] ?? '—'}', style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                                        ],
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                                      decoration: BoxDecoration(
                                        color: active ? const Color(0xFFE3F7EC) : scheme.errorContainer,
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Text(
                                        active ? (en ? 'Active' : 'نشط') : (en ? 'Disabled' : 'موقوف'),
                                        style: TextStyle(
                                          color: active ? const Color(0xFF176B45) : scheme.onErrorContainer,
                                          fontSize: 10,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                _InfoLine(icon: Icons.badge_outlined, text: role),
                                if (department.isNotEmpty) _InfoLine(icon: Icons.apartment_rounded, text: department),
                                if ((item['email']?.toString() ?? '').isNotEmpty)
                                  _InfoLine(icon: Icons.email_outlined, text: item['email'].toString()),
                                if ((item['phone']?.toString() ?? '').isNotEmpty)
                                  _InfoLine(icon: Icons.phone_outlined, text: item['phone'].toString()),
                                if ((item['manager_name']?.toString() ?? '').isNotEmpty)
                                  _InfoLine(icon: Icons.supervisor_account_outlined, text: '${en ? 'Manager' : 'المسؤول'}: ${item['manager_name']}'),
                                if ((item['last_login_at']?.toString() ?? '').isNotEmpty)
                                  _InfoLine(icon: Icons.access_time_rounded, text: '${en ? 'Last login' : 'آخر دخول'}: ${item['last_login_at']}'),
                                if (int.tryParse(item['id']?.toString() ?? '') != widget.currentUserId) ...[
                                  const SizedBox(height: 8),
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 4,
                                    children: [
                                      OutlinedButton.icon(
                                        onPressed: saving ? null : () => act(item, 'toggle'),
                                        icon: Icon(active ? Icons.pause_circle_outline_rounded : Icons.play_circle_outline_rounded),
                                        label: Text(active ? (en ? 'Disable' : 'إيقاف') : (en ? 'Enable' : 'تفعيل')),
                                      ),
                                      OutlinedButton.icon(
                                        onPressed: saving ? null : () => act(item, 'reset_password'),
                                        icon: const Icon(Icons.password_rounded),
                                        label: Text(en ? 'Reset password' : 'إعادة تعيين كلمة المرور'),
                                      ),
                                    ],
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 10),
              child: Row(
                children: [
                  OutlinedButton.icon(
                    onPressed: loading || page <= 1 ? null : () => refresh(requestedPage: page - 1),
                    icon: const Icon(Icons.chevron_left_rounded),
                    label: Text(en ? 'Previous' : 'السابق'),
                  ),
                  const Spacer(),
                  FilledButton.icon(
                    onPressed: loading || page >= pages ? null : () => refresh(requestedPage: page + 1),
                    icon: const Icon(Icons.chevron_right_rounded),
                    label: Text(en ? 'Next' : 'التالي'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 5),
        child: Row(
          children: [
            Icon(icon, size: 15, color: Theme.of(context).colorScheme.onSurfaceVariant),
            const SizedBox(width: 8),
            Expanded(child: Text(text, style: const TextStyle(fontSize: 12))),
          ],
        ),
      );
}
