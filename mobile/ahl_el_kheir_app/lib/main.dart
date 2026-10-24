import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'projects_page.dart';
import 'attendance_page.dart';
import 'employees_page.dart';
import 'leave_requests_page.dart';
import 'sponsors_page.dart';
import 'families_page.dart';
import 'transactions_page.dart';
import 'sponsorships_page.dart';
import 'sponsor_requests_page.dart';

void main() => runApp(const AhlElKheirApp());

class ApiFailure implements Exception {
  const ApiFailure(this.message, {this.statusCode});
  final String message;
  final int? statusCode;
  @override
  String toString() => message;
}

class AhlElKheirApp extends StatefulWidget {
  const AhlElKheirApp({super.key});
  @override
  State<AhlElKheirApp> createState() => _AhlElKheirAppState();
}

class _AhlElKheirAppState extends State<AhlElKheirApp> {
  static const storage = FlutterSecureStorage();
  static const tokenKey = 'mobile_access_token';
  static const baseUrl = String.fromEnvironment('API_BASE_URL',
      defaultValue: 'http://192.168.18.9:8081/AhlElKheir/api/v1');
  bool english = false, loading = true;
  Map<String, dynamic>? user;
  String? error;

  Future<Map<String, dynamic>> call(String method, String path,
      {Map<String, dynamic>? body, String? token}) async {
    final headers = <String, String>{'Accept': 'application/json'};
    if (body != null) headers['Content-Type'] = 'application/json';
    if (token != null) headers['Authorization'] = 'Bearer $token';
    try {
      final uri = Uri.parse('${baseUrl.replaceFirst(RegExp(r'/+$'), '')}/$path');
      final response = method == 'GET'
          ? await http.get(uri, headers: headers).timeout(const Duration(seconds: 20))
          : await http.post(uri, headers: headers,
              body: body == null ? null : jsonEncode(body))
              .timeout(const Duration(seconds: 20));
      final json = jsonDecode(utf8.decode(response.bodyBytes));
      if (json is! Map<String, dynamic>) throw const FormatException();
      if (response.statusCode < 200 || response.statusCode >= 300 || json['ok'] != true) {
        final e = json['error'];
        throw ApiFailure(e is Map ? (e['message']?.toString() ?? 'Request failed') : 'Request failed', statusCode: response.statusCode);
      }
      if (json['data'] is! Map<String, dynamic>) throw Exception('Invalid API response');
      return json['data'] as Map<String, dynamic>;
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw ApiFailure(english ? 'Could not reach the API server.' : 'تعذر الاتصال بخادم API.');
    }
  }

  @override
  void initState() {
    super.initState();
    restore();
  }

  Future<void> restore() async {
    try {
      final token = await storage.read(key: tokenKey);
      if (token != null && token.isNotEmpty) {
        final data = await call('GET', 'auth/me.php', token: token);
        if (data['user'] is Map<String, dynamic>) user = data['user'] as Map<String, dynamic>;
      }
    } catch (e) {
      if (e is ApiFailure && (e.statusCode == 401 || e.statusCode == 403)) {
        await storage.delete(key: tokenKey);
      } else {
        error = e.toString();
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> login(String username, String password) async {
    final data = await call('POST', 'auth/login.php',
        body: {'username': username.trim(), 'password': password});
    if (data['access_token'] is! String || data['user'] is! Map<String, dynamic>) {
      throw Exception('Invalid login response');
    }
    await storage.write(key: tokenKey, value: data['access_token'] as String);
    if (mounted) setState(() => user = data['user'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    final token = await storage.read(key: tokenKey);
    try {
      if (token != null) await call('POST', 'auth/logout.php', token: token);
    } finally {
      await storage.delete(key: tokenKey);
      if (mounted) setState(() => user = null);
    }
  }

  Future<Map<String, dynamic>> loadNotifications(int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'notifications/index.php?page=$page', token: token);
  }

  Future<void> markNotificationRead(int notificationId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    await call(
      'POST',
      'notifications/mark_read.php',
      token: token,
      body: {'notification_id': notificationId},
    );
  }

  Future<Map<String, dynamic>> loadProjects(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'projects/index.php?q=$q&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadProjectDetail(int projectId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'projects/detail.php?id=$projectId', token: token);
  }

  Future<Map<String, dynamic>> loadEmployees(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'hr/employees.php?q=$q&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadAttendance(String? date) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final query = date == null || date.isEmpty ? '' : '?date=$date';
    return call('GET', 'hr/attendance.php$query', token: token);
  }

  Future<Map<String, dynamic>> loadLeaves(String view, String status, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'hr/leaves.php?view=$view&status=$status&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadSponsors(String query, String status, String linkStatus, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'sponsors/index.php?q=$q&status=$status&link_status=$linkStatus&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadSponsorRequests(String query, String status, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'sponsor_requests/index.php?q=$q&status=$status&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadSponsorDetail(int id) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'sponsors/detail.php?id=$id', token: token);
  }

  Future<Map<String, dynamic>> loadFamilies(String query, String status, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'families/index.php?q=$q&status=$status&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadTransactions(String query, String status, int page, String from, String to) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final params = <String, String>{'q': query, 'status': status, 'page': page.toString()};
    if (from.isNotEmpty) params['from'] = from;
    if (to.isNotEmpty) params['to'] = to;
    return call('GET', 'transactions/index.php?${Uri(queryParameters: params).query}', token: token);
  }

  Future<Map<String, dynamic>> loadSponsorships(String query, String type, String status, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'sponsorships/index.php?q=$q&stype=$type&status=$status&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadSponsorshipDetail(int id) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'sponsorships/detail.php?id=$id', token: token);
  }

  Future<Map<String, dynamic>> loadFamilyDetail(int id) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'families/detail.php?id=$id', token: token);
  }

  Future<Map<String, dynamic>> submitLeaveAction(String action, Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'hr/leaves/action.php', token: token, body: {'action': action, ...body});
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: english ? 'Ahl El Kheir' : 'أهل الخير',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1F4E8C), primary: const Color(0xFF0A1F44), secondary: const Color(0xFF2E63A8)),
      scaffoldBackgroundColor: const Color(0xFFF0F2F5),
      fontFamily: 'Cairo',
      appBarTheme: const AppBarTheme(backgroundColor: Colors.white, foregroundColor: Color(0xFF0A1F44), elevation: 0),
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: const Color(0xFFF9FAFC),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF8AA6C8), width: 1.5)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF8AA6C8), width: 1.5)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1F4E8C), width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0A1F44), foregroundColor: Colors.white, minimumSize: const Size.fromHeight(52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))),
    home: Directionality(
      textDirection: english ? TextDirection.ltr : TextDirection.rtl,
      child: loading
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : user == null
          ? LoginPage(english: english,
              toggle: () => setState(() => english = !english), submit: login)
          : Scaffold(
              appBar: AppBar(
                title: Text(english ? 'Ahl El Kheir' : 'أهل الخير'),
                actions: [
                  TextButton(
                    onPressed: () => setState(() => english = !english),
                    child: Text(english ? 'العربية' : 'English'),
                  ),
                ],
              ),
              body: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        children: [
                          const OrganizationLogo(size: 84),
                          const SizedBox(height: 16),
                          Text(
                            english ? 'Welcome' : 'مرحباً',
                            style: Theme.of(context).textTheme.headlineSmall,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            user?['full_name']?.toString() ?? '',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${english ? 'Role' : 'الدور'}: ${user?['role_code'] ?? ''}',
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  if ([
                    'admin',
                    'general_manager',
                    'vice_general_manager',
                    'projects_manager',
                    'project_supervisor',
                    'accountant',
                    'financial_manager',
                  ].contains(user?['role_code']?.toString()))
                    Card(
                      child: ListTile(
                        leading: const CircleAvatar(
                          child: Icon(Icons.folder_open_rounded),
                        ),
                        title: Text(english ? 'Projects' : 'المشروعات'),
                        subtitle: Text(
                          english
                              ? 'View the project portfolio available to your role'
                              : 'عرض المشروعات المتاحة وفق صلاحيات حسابك',
                        ),
                        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => ProjectsPage(
                                english: english,
                                loadPage: loadProjects,
                                loadDetail: loadProjectDetail,
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  if (['hr_manager', 'hr_staff', 'admin'].contains(user?['role_code']?.toString()))
                    Card(
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.badge_outlined)),
                        title: Text(english ? 'Employees' : 'الموظفون'),
                        subtitle: Text(english ? 'Browse the staff directory' : 'استعراض دليل الموظفين'),
                        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                        onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                          builder: (_) => EmployeesPage(english: english, loadPage: loadEmployees),
                        )),
                      ),
                    ),
                  if (['hr_manager', 'hr_staff', 'admin'].contains(user?['role_code']?.toString()))
                    Card(
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.fact_check_outlined)),
                        title: Text(english ? 'Attendance' : 'الحضور والانصراف'),
                        subtitle: Text(english ? 'Review attendance records by date' : 'مراجعة سجلات الحضور حسب التاريخ'),
                        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                        onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                          builder: (_) => AttendancePage(english: english, loadAttendance: loadAttendance),
                        )),
                      ),
                    ),
                  const SizedBox(height: 12),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor'].contains(user?['role_code']?.toString()))
                    Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.volunteer_activism_rounded)),
                      title: Text(english ? 'Sponsors' : 'الكفلاء'),
                      subtitle: Text(english ? 'Browse sponsors within your role scope' : 'استعراض الكفلاء وفق صلاحيات حسابك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => SponsorsPage(english: english, loadSponsors: loadSponsors, loadDetail: loadSponsorDetail),
                      )),
                    )),
                  if (['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media'].contains(user?['role_code']?.toString()))
                    Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.campaign_outlined)),
                      title: Text(english ? 'Sponsor requests' : 'طلبات الرعاية'),
                      subtitle: Text(english ? 'Review sponsor leads and their current status' : 'مراجعة طلبات الرعاية وحالاتها الحالية'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => SponsorRequestsPage(english: english, loadRequests: loadSponsorRequests),
                      )),
                    )),
                  if (['admin', 'vice_general_manager', 'supervisor', 'accountant'].contains(user?['role_code']?.toString()))
                    Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.volunteer_activism_rounded)),
                      title: Text(english ? 'Sponsorships' : 'الكفالات'),
                      subtitle: Text(english ? 'Browse sponsorship records within your role scope' : 'استعراض سجلات الكفالات وفق صلاحيات حسابك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => SponsorshipsPage(english: english, loadSponsorships: loadSponsorships, loadDetail: loadSponsorshipDetail),
                      )),
                    )),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant_staff', 'financial_manager'].contains(user?['role_code']?.toString()))
                    Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.receipt_long_rounded)),
                      title: Text(english ? 'Transactions' : 'المعاملات'),
                      subtitle: Text(english ? 'Review payment records within your role scope' : 'مراجعة سجلات المعاملات وفق صلاحياتك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => TransactionsPage(english: english, loadTransactions: loadTransactions),
                      )),
                    )),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'nanny'].contains(user?['role_code']?.toString()))
                    Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.family_restroom_rounded)),
                      title: Text(english ? 'Families' : 'الأسر'),
                      subtitle: Text(english ? 'Browse families within your role scope' : 'استعراض الأسر وفق صلاحيات حسابك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail),
                      )),
                    )),
                  const SizedBox(height: 12),
                  Card(
                    child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.event_note_rounded)),
                      title: Text(english ? 'Leave requests' : 'طلبات الإجازة'),
                      subtitle: Text(english ? 'View your requests and authorized review queue' : 'متابعة طلباتك والطلبات المصرح بمراجعتها'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => LeaveRequestsPage(
                          english: english,
                          roleCode: user?['role_code']?.toString() ?? '',
                          loadLeaves: loadLeaves,
                          submitAction: submitLeaveAction,
                        ),
                      )),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Card(
                    child: ListTile(
                      leading: const CircleAvatar(
                        child: Icon(Icons.notifications_none_rounded),
                      ),
                      title: Text(english ? 'Notifications' : 'الإشعارات'),
                      subtitle: Text(
                        english
                            ? 'View your notification history'
                            : 'عرض سجل الإشعارات الخاصة بك',
                      ),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => NotificationPage(
                              english: english,
                              loadPage: loadNotifications,
                              markRead: markNotificationRead,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: logout,
                    icon: const Icon(Icons.logout_rounded),
                    label: Text(english ? 'Sign out' : 'تسجيل الخروج'),
                  ),
                ],
              ),
            )
    ));
}


class NotificationPage extends StatefulWidget {
  const NotificationPage({
    super.key,
    required this.english,
    required this.loadPage,
    required this.markRead,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(int page) loadPage;
  final Future<void> Function(int notificationId) markRead;

  @override
  State<NotificationPage> createState() => _NotificationPageState();
}

class _NotificationPageState extends State<NotificationPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  int total = 0;
  int unread = 0;
  int page = 1;
  int pages = 1;

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
      final data = await widget.loadPage(page);
      final rawItems = data['items'];
      final nextItems = rawItems is List
          ? rawItems
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        items = nextItems;
        total = int.tryParse(data['total']?.toString() ?? '') ?? 0;
        unread = int.tryParse(data['unread']?.toString() ?? '') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? '') ?? 1;
        pages = int.tryParse(data['pages']?.toString() ?? '') ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> markNotification(Map<String, dynamic> item) async {
    final isRead = item['is_read'] == true || item['is_read'] == 1;
    if (isRead) return;
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (id < 1) return;
    try {
      await widget.markRead(id);
      if (!mounted) return;
      await load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(
        title: Text(en ? 'Notifications' : 'الإشعارات'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(16, 12, 16, 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                en
                    ? 'Total: $total  •  Unread: $unread'
                    : 'الإجمالي: $total  •  غير المقروء: $unread',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
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
                    : items.isEmpty
                        ? Center(
                            child: Text(
                              en ? 'No notifications yet.' : 'لا توجد إشعارات.',
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: load,
                            child: ListView.separated(
                              physics: const AlwaysScrollableScrollPhysics(),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 4,
                              ),
                              itemCount: items.length,
                              separatorBuilder: (_, __) =>
                                  const Divider(height: 1),
                              itemBuilder: (context, index) {
                                final item = items[index];
                                final isRead = item['is_read'] == true ||
                                    item['is_read'] == 1;
                                return Card(
                                  margin: const EdgeInsets.symmetric(vertical: 4),
                                  child: ListTile(
                                    leading: Icon(
                                      isRead
                                          ? Icons.notifications_none_rounded
                                          : Icons.notifications_active_rounded,
                                      color: isRead
                                          ? Colors.grey
                                          : const Color(0xFF1F4E8C),
                                    ),
                                    title: Text(
                                      item['title']?.toString() ?? '',
                                      style: TextStyle(
                                        fontWeight: isRead
                                            ? FontWeight.normal
                                            : FontWeight.w700,
                                      ),
                                    ),
                                    subtitle: Padding(
                                      padding: const EdgeInsets.only(top: 5),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(item['body']?.toString() ?? ''),
                                          if ((item['created_at']?.toString() ??
                                                  '')
                                              .isNotEmpty)
                                            Padding(
                                              padding:
                                                  const EdgeInsets.only(top: 6),
                                              child: Text(
                                                item['created_at'].toString(),
                                                style: Theme.of(context)
                                                    .textTheme
                                                    .bodySmall,
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                    onTap: () => markNotification(item),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
          if (!loading && error == null && pages > 1)
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    OutlinedButton(
                      onPressed: page > 1
                          ? () {
                              setState(() => page--);
                              load();
                            }
                          : null,
                      child: Text(en ? 'Previous' : 'السابق'),
                    ),
                    Text(en ? 'Page $page of $pages' : 'صفحة $page من $pages'),
                    OutlinedButton(
                      onPressed: page < pages
                          ? () {
                              setState(() => page++);
                              load();
                            }
                          : null,
                      child: Text(en ? 'Next' : 'التالي'),
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

class OrganizationLogo extends StatelessWidget {
  const OrganizationLogo({super.key, this.size = 84});
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        padding: const EdgeInsets.all(7),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFDCE7F5)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0A1F44).withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(13),
          child: Image.asset(
            'assets/organization_logo.jpg',
            fit: BoxFit.contain,
            errorBuilder: (_, __, ___) => const Icon(
              Icons.volunteer_activism_rounded,
              size: 46,
              color: Color(0xFF1F4E8C),
            ),
          ),
        ),
      );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.english, required this.toggle, required this.submit});
  final bool english;
  final VoidCallback toggle;
  final Future<void> Function(String, String) submit;
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final form = GlobalKey<FormState>();
  final username = TextEditingController(), password = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() { username.dispose(); password.dispose(); super.dispose(); }

  Future<void> signIn() async {
    if (!form.currentState!.validate()) return;
    setState(() { busy = true; error = null; });
    try { await widget.submit(username.text, password.text); }
    catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => busy = false); }
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    const navy950 = Color(0xFF081527);
    const navy900 = Color(0xFF0A1F44);
    const navy600 = Color(0xFF1F4E8C);
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topRight,
            end: Alignment.bottomLeft,
            colors: [navy950, navy900, navy600],
            stops: [0, 0.56, 1],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 430),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Align(
                      alignment: AlignmentDirectional.topEnd,
                      child: TextButton.icon(
                        onPressed: widget.toggle,
                        style: TextButton.styleFrom(
                          foregroundColor: Colors.white,
                          backgroundColor: Colors.white.withValues(alpha: 0.10),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(24),
                            side: BorderSide(color: Colors.white.withValues(alpha: 0.24)),
                          ),
                        ),
                        icon: const Icon(Icons.language_rounded, size: 18),
                        label: Text(en ? 'العربية' : 'English'),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Card(
                      color: Colors.white,
                      elevation: 18,
                      shadowColor: Colors.black.withValues(alpha: 0.22),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(26, 30, 26, 26),
                        child: Form(
                          key: form,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Center(child: OrganizationLogo(size: 106)),
                              const SizedBox(height: 20),
                              Text(
                                en ? 'Ahl El Kheir' : 'أهل الخير',
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                                  color: navy900, fontWeight: FontWeight.w800, height: 1.25,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(
                                en ? 'Charity Management System' : 'نظام إدارة الجمعية الخيرية',
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                              ),
                              const SizedBox(height: 26),
                              Text(
                                en ? 'Sign in' : 'تسجيل الدخول',
                                textAlign: TextAlign.start,
                                style: const TextStyle(color: navy900, fontSize: 17, fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 16),
                              TextFormField(
                                controller: username,
                                autocorrect: false,
                                textInputAction: TextInputAction.next,
                                decoration: InputDecoration(
                                  labelText: en ? 'Username' : 'اسم المستخدم',
                                  prefixIcon: const Icon(Icons.person_outline_rounded),
                                ),
                                validator: (v) => v == null || v.trim().isEmpty
                                  ? (en ? 'Enter username' : 'أدخل اسم المستخدم') : null,
                              ),
                              const SizedBox(height: 15),
                              TextFormField(
                                controller: password,
                                obscureText: true,
                                onFieldSubmitted: (_) { if (!busy) signIn(); },
                                decoration: InputDecoration(
                                  labelText: en ? 'Password' : 'كلمة المرور',
                                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                                ),
                                validator: (v) => v == null || v.isEmpty
                                  ? (en ? 'Enter password' : 'أدخل كلمة المرور') : null,
                              ),
                              if (error != null) ...[
                                const SizedBox(height: 14),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFF1F2),
                                    border: Border.all(color: const Color(0xFFF5C2C7)),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Text(error!, style: const TextStyle(color: Color(0xFF842026), fontSize: 13)),
                                ),
                              ],
                              const SizedBox(height: 20),
                              FilledButton(
                                onPressed: busy ? null : signIn,
                                child: busy
                                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                                  : Text(en ? 'Sign in' : 'دخول'),
                              ),
                              const SizedBox(height: 17),
                              Text(
                                en ? 'Secure access to Ahl El Kheir' : 'دخول آمن إلى نظام أهل الخير',
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text('AHL EL KHEIR', textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white70, fontSize: 11, letterSpacing: 2.4, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
