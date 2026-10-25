import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'projects_page.dart';
import 'attendance_page.dart';
import 'employees_page.dart';
import 'employee_detail_page.dart';
import 'my_salary_statements_page.dart';
import 'leave_requests_page.dart';
import 'sponsors_page.dart';
import 'supervisors_page.dart';
import 'families_page.dart';
import 'fm_review_queue_page.dart';
import 'transactions_page.dart';
import 'sponsorships_page.dart';
import 'sponsor_requests_page.dart';
import 'nanny_group_verification_page.dart';
import 'nanny_pre_verification_page.dart';

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
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
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

  Uri webPageUri(String relativePath, {Map<String, String>? queryParameters}) {
    final apiUri = Uri.parse(baseUrl);
    final segments = apiUri.pathSegments.where((part) => part.isNotEmpty).toList();
    if (segments.length >= 2) segments.removeRange(segments.length - 2, segments.length);
    final pageSegments = relativePath.split('/').where((part) => part.isNotEmpty);
    return apiUri.replace(
      pathSegments: [...segments, ...pageSegments],
      queryParameters: queryParameters,
    );
  }

  Future<void> openWebPage(String relativePath, {Map<String, String>? queryParameters}) async {
    final uri = webPageUri(relativePath, queryParameters: queryParameters);
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened) {
      final context = navigatorKey.currentContext;
      if (context != null && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(english ? 'Could not open the page.' : 'تعذر فتح الصفحة.')),
        );
      }
    }
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

  Future<Map<String, dynamic>> loadNannyPreVerification(String month) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'nanny/pre_verification.php?month=${Uri.encodeQueryComponent(month)}', token: token);
  }

  Future<Map<String, dynamic>> submitNannyPreVerificationAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'nanny/pre_verification_action.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> loadNannyGroupVerification(String month, int? groupId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final params = <String, String>{'month': month};
    if (groupId != null) params['group_id'] = groupId.toString();
    return call('GET', 'nanny/group_verification.php?${Uri(queryParameters: params).query}', token: token);
  }

  Future<Map<String, dynamic>> submitNannyGroupVerificationAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'nanny/group_verification_action.php', token: token, body: body);
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

  Future<Map<String, dynamic>> createProjectMilestone(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'projects/milestone_create.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> actOnProjectMilestone(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'projects/milestone_action.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> submitProjectProgressUpdate(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'projects/progress_update.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> loadSupervisors(String query, bool showArchived, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final params = <String, String>{
      'q': query,
      'show_archived': showArchived ? '1' : '0',
      'page': page.toString(),
    };
    return call('GET', 'supervisors/index.php?${Uri(queryParameters: params).query}', token: token);
  }

  Future<Map<String, dynamic>> loadEmployees(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final q = Uri.encodeQueryComponent(query);
    return call('GET', 'hr/employees.php?q=$q&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadEmployeeDetail(int id) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'hr/employee_detail.php?id=$id', token: token);
  }

  Future<Map<String, dynamic>> loadMySalaryStatements(int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'hr/my_salary_statements.php?page=$page', token: token);
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

  Future<Map<String, dynamic>> submitSponsorRequestAction(String action, int requestId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'sponsor_requests/action.php', token: token, body: {'action': action, 'request_id': requestId});
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

  Future<Map<String, dynamic>> loadFmReviewQueue() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'accounting/fm_review_queue.php', token: token);
  }
 
  Future<void> submitFmReviewQueueAction(int paymentId, String action, String returnNote) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    await call('POST', 'accounting/fm_review_queue_action.php', token: token, body: {
      'payment_id': paymentId,
      'action': action,
      'return_note': returnNote,
    });
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
      colorScheme: ColorScheme.fromSeed(
        seedColor: const Color(0xFF1F4E8C),
        primary: const Color(0xFF0A1F44),
        secondary: const Color(0xFF2E63A8),
        surface: Colors.white,
      ),
      scaffoldBackgroundColor: const Color(0xFFF0F2F5),
      visualDensity: VisualDensity.standard,
      dividerTheme: const DividerThemeData(
        color: Color(0xFFE3E8EF),
        thickness: 1,
        space: 1,
      ),
      fontFamily: 'Cairo',
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: Color(0xFFE3E8EF)),
        ),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        minVerticalPadding: 14,
        horizontalTitleGap: 14,
        iconColor: const Color(0xFF1F4E8C),
        textColor: const Color(0xFF0A1F44),
        titleTextStyle: const TextStyle(
          color: Color(0xFF0A1F44),
          fontFamily: 'Cairo',
          fontSize: 14,
          fontWeight: FontWeight.w800,
        ),
        subtitleTextStyle: const TextStyle(
          color: Color(0xFF64748B),
          fontFamily: 'Cairo',
          fontSize: 12,
          height: 1.45,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: const Color(0xFF0A1F44),
          backgroundColor: const Color(0xFFF0F4FA),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          minimumSize: const Size(44, 44),
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: Color(0xFF0A1F44),
        elevation: 0,
        scrolledUnderElevation: 0,
        toolbarHeight: 56,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: const Color(0xFFF9FAFC),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF8AA6C8), width: 1.5)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF8AA6C8), width: 1.5)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1F4E8C), width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0A1F44), foregroundColor: Colors.white, minimumSize: const Size.fromHeight(52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))),
    navigatorKey: navigatorKey,
  home: Directionality(
      textDirection: english ? TextDirection.ltr : TextDirection.rtl,
      child: loading
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : user == null
          ? LoginPage(english: english,
              toggle: () => setState(() => english = !english), submit: login,
              onForgotPassword: () => openWebPage('modules/users/password_recovery_request.php'))
          : Scaffold(
              appBar: AppBar(
                title: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const OrganizationLogo(size: 38),
                    const SizedBox(width: 10),
                    Flexible(
                      child: Text(
                        english ? 'Ahl El Kheir' : 'أهل الخير',
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                actions: [
                  IconButton(
                    tooltip: english ? 'Notifications' : 'الإشعارات',
                    onPressed: () => navigatorKey.currentState?.push(
                      MaterialPageRoute<void>(
                        builder: (_) => NotificationPage(
                          english: english,
                          loadPage: loadNotifications,
                          markRead: markNotificationRead,
                        ),
                      ),
                    ),
                    icon: const Icon(Icons.notifications_none_rounded),
                  ),
                  IconButton(
                    tooltip: english ? 'My account' : 'حسابي',
                    onPressed: () {
                    final overlayContext = navigatorKey.currentState?.overlay?.context;
                    if (overlayContext == null) return;
                    showModalBottomSheet<void>(
                      context: overlayContext,
                      showDragHandle: true,
                      isScrollControlled: true,
                      backgroundColor: Colors.white,
                      shape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                      ),
                      builder: (sheetContext) => SafeArea(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              ListTile(
                                leading: const CircleAvatar(
                                  backgroundColor: Color(0xFFDCE7F5),
                                  child: Icon(Icons.person_rounded, color: Color(0xFF0A1F44)),
                                ),
                                title: Text(user?['full_name']?.toString() ?? '',
                                  style: const TextStyle(fontWeight: FontWeight.w800)),
                                subtitle: Text(user?['role_code']?.toString() ?? ''),
                              ),
                              const Divider(height: 20),
                              ListTile(
                                leading: const Icon(Icons.payments_outlined),
                                title: Text(english ? 'My salary statements' : 'كشوف رواتبي'),
                                trailing: const Icon(Icons.chevron_right_rounded),
                                onTap: () {
                                  Navigator.pop(sheetContext);
                                  navigatorKey.currentState?.push(MaterialPageRoute<void>(
                                    builder: (_) => MySalaryStatementsPage(
                                      english: english,
                                      loadPage: loadMySalaryStatements,
                                    ),
                                  ));
                                },
                              ),
                              ListTile(
                                leading: const Icon(Icons.event_note_rounded),
                                title: Text(english ? 'Leave requests' : 'طلبات الإجازة'),
                                trailing: const Icon(Icons.chevron_right_rounded),
                                onTap: () {
                                  Navigator.pop(sheetContext);
                                  navigatorKey.currentState?.push(MaterialPageRoute<void>(
                                    builder: (_) => LeaveRequestsPage(
                                      english: english,
                                      roleCode: user?['role_code']?.toString() ?? '',
                                      loadLeaves: loadLeaves,
                                      submitAction: submitLeaveAction,
                                    ),
                                  ));
                                },
                              ),

                            ],
                          ),
                        ),
                      ),
                    );
                  },
                    icon: const Icon(Icons.account_circle_outlined),
                  ),
                  IconButton(
                    tooltip: english ? 'Sign out' : 'تسجيل الخروج',
                    onPressed: logout,
                    icon: const Icon(Icons.logout_rounded),
                  ),
                  IconButton(
                    tooltip: english ? 'Change language' : 'تغيير اللغة',
                    onPressed: () => setState(() => english = !english),
                    icon: const Icon(Icons.language_rounded),
                  ),
                  const SizedBox(width: 6),
                ],
              ),
              body: LayoutBuilder(builder: (context, constraints) {
                final moduleCardWidth = constraints.maxWidth - 40;
                return ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        begin: AlignmentDirectional.topStart,
                        end: AlignmentDirectional.bottomEnd,
                        colors: [Color(0xFF081527), Color(0xFF1F4E8C)],
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0A1F44).withValues(alpha: 0.14),
                          blurRadius: 20,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        const OrganizationLogo(size: 68),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                english ? 'Welcome back' : 'أهلاً بعودتك',
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(
                                user?['full_name']?.toString() ?? '',
                                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  height: 1.25,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  _HomeSectionTitle(
                    title: english ? 'Work areas' : 'مساحات العمل',
                    subtitle: english
                        ? 'Tools available to your account'
                        : 'الأدوات المتاحة لحسابك',
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                  if ([
                    'admin',
                    'general_manager',
                    'vice_general_manager',
                    'projects_manager',
                    'project_supervisor',
                    'accountant',
                    'financial_manager',
                  ].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(
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
                          navigatorKey.currentState?.push(
                            MaterialPageRoute<void>(
                              builder: (_) => ProjectsPage(
                                english: english,
                                loadPage: loadProjects,
                                loadDetail: loadProjectDetail,
                                submitProgress: submitProjectProgressUpdate,
                                createMilestone: createProjectMilestone,
                                actOnMilestone: actOnProjectMilestone,
                              ),
                            ),
                          );
                        },
                      ),
                    )),
                  if (['hr_manager', 'hr_staff', 'admin'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.badge_outlined)),
                        title: Text(english ? 'Employees' : 'الموظفون'),
                        subtitle: Text(english ? 'Browse the staff directory' : 'استعراض دليل الموظفين'),
                        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                        onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                          builder: (_) => EmployeesPage(english: english, loadPage: loadEmployees, openDetail: (employee) { final id = int.tryParse(employee['id']?.toString() ?? ''); if (id == null || id < 1) return; navigatorKey.currentState?.push( MaterialPageRoute<void>(builder: (_) => EmployeeDetailPage(employeeId: id, english: english, loadDetail: loadEmployeeDetail))); }),
                        )),
                      ),
                    )),
                  if (['hr_manager', 'hr_staff', 'admin'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.fact_check_outlined)),
                        title: Text(english ? 'Attendance' : 'الحضور والانصراف'),
                        subtitle: Text(english ? 'Review attendance records by date' : 'مراجعة سجلات الحضور حسب التاريخ'),
                        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                        onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                          builder: (_) => AttendancePage(english: english, loadAttendance: loadAttendance),
                        )),
                      ),
                    )),
                  if (['admin', 'vice_general_manager'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.supervisor_account_rounded)),
                      title: Text(english ? 'Supervisors' : 'المشرفون'),
                      subtitle: Text(english ? 'View supervisor assignments and status' : 'عرض المشرفين والحروف المسندة والحالة'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => SupervisorsPage(english: english, loadPage: loadSupervisors),
                      )),
                    ))),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.volunteer_activism_rounded)),
                      title: Text(english ? 'Sponsors' : 'الكفلاء'),
                      subtitle: Text(english ? 'Browse sponsors within your role scope' : 'استعراض الكفلاء وفق صلاحيات حسابك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => SponsorsPage(english: english, loadSponsors: loadSponsors, loadDetail: loadSponsorDetail),
                      )),
                    ))),
                  if (['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.campaign_outlined)),
                      title: Text(english ? 'Sponsor requests' : 'طلبات الرعاية'),
                      subtitle: Text(english ? 'Review sponsor leads and their current status' : 'مراجعة طلبات الرعاية وحالاتها الحالية'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => SponsorRequestsPage(english: english, loadRequests: loadSponsorRequests, submitAction: submitSponsorRequestAction),
                      )),
                    ))),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.volunteer_activism_rounded)),
                      title: Text(english ? 'Sponsorships' : 'الكفالات'),
                      subtitle: Text(english ? 'Browse sponsorship records within your role scope' : 'استعراض سجلات الكفالات وفق صلاحيات حسابك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => SponsorshipsPage(english: english, loadSponsorships: loadSponsorships, loadDetail: loadSponsorshipDetail),
                      )),
                    ))),
                  if (['financial_manager', 'fm', 'finance', 'admin', 'sudo', 'general_manager'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.fact_check_outlined)),
                      title: Text(english ? 'Financial review queue' : 'طابور المراجعة المالية'),
                      subtitle: Text(english ? 'Review pending supervisor collections and recent decisions' : 'عرض تحصيلات المشرفين المعلقة وسجل المراجعات'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => FmReviewQueuePage(english: english, loadQueue: loadFmReviewQueue, submitAction: submitFmReviewQueueAction),
                      )),
                    ))),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant_staff', 'financial_manager'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.receipt_long_rounded)),
                      title: Text(english ? 'Transactions' : 'المعاملات'),
                      subtitle: Text(english ? 'Review payment records within your role scope' : 'مراجعة سجلات المعاملات وفق صلاحياتك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => TransactionsPage(english: english, loadTransactions: loadTransactions),
                      )),
                    ))),
                  if (['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'nanny'].contains(user?['role_code']?.toString()))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.family_restroom_rounded)),
                      title: Text(english ? 'Families' : 'الأسر'),
                      subtitle: Text(english ? 'Browse families within your role scope' : 'استعراض الأسر وفق صلاحيات حسابك'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openWebPage('modules/families/orphan_form.php', queryParameters: {'child': childId.toString()})),
                      )),
                    ))),
                  if (user?['role_code']?.toString() == 'nanny')
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.playlist_add_check_rounded)),
                      title: Text(english ? 'Preliminary verification' : 'التحقق المسبق للأسر'),
                      subtitle: Text(english ? 'Verify families across your active groups before group submission' : 'توثيق الأسر ضمن مجموعاتك النشطة قبل إرسال المجموعة'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => NannyPreVerificationPage(
                          english: english,
                          loadData: loadNannyPreVerification,
                          submitAction: submitNannyPreVerificationAction,
                        ),
                      )),
                    ))),
                  if (user?['role_code']?.toString() == 'nanny')
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.fact_check_rounded)),
                      title: Text(english ? 'Group verification' : 'توثيق المجموعات'),
                      subtitle: Text(english ? 'Verify family eligibility, contact and bank details' : 'التحقق من حالة الأيتام والتواصل والحسابات البنكية'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: () => navigatorKey.currentState?.push(MaterialPageRoute<void>(
                        builder: (_) => NannyGroupVerificationPage(
                          english: english,
                          loadData: loadNannyGroupVerification,
                          submitAction: submitNannyGroupVerificationAction,
                        ),
                      )),
                    ))),
                    ],
                  ),
                  const SizedBox(height: 20),
                ],
              );
              }),
            )
    ));
}


class _HomeSectionTitle extends StatelessWidget {
  const _HomeSectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsetsDirectional.only(start: 2, end: 2, bottom: 2),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: const Color(0xFF0A1F44),
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 3),
            Text(
              subtitle,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: const Color(0xFF64748B),
                  ),
            ),
          ],
        ),
      );
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

  Future<void> openNotification(Map<String, dynamic> item) async {
    final marked = await markNotification(item);
    if (!marked || !mounted) return;
    await Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => NotificationDetailPage(
        english: widget.english,
        item: item,
      ),
    ));
  }

  Future<bool> markNotification(Map<String, dynamic> item) async {
    final isRead = item['is_read'] == true || item['is_read'] == 1;
    if (isRead) return true;
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (id < 1) return true;
    try {
      await widget.markRead(id);
      if (!mounted) return false;
      await load();
      return true;
    } catch (e) {
      if (!mounted) return false;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
      return false;
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
                                    onTap: () => openNotification(item),
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
  const LoginPage({super.key, required this.english, required this.toggle, required this.submit, required this.onForgotPassword});
  final bool english;
  final VoidCallback toggle;
  final Future<void> Function(String, String) submit;
  final Future<void> Function() onForgotPassword;
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final form = GlobalKey<FormState>();
  final username = TextEditingController(), password = TextEditingController();
  bool busy = false;
  bool showPassword = false;
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
                                obscureText: !showPassword,
                                onFieldSubmitted: (_) { if (!busy) signIn(); },
                                decoration: InputDecoration(
                                  labelText: en ? 'Password' : 'كلمة المرور',
                                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                                  suffixIcon: IconButton(
                                    tooltip: showPassword ? (en ? 'Hide password' : 'إخفاء كلمة المرور') : (en ? 'Show password' : 'إظهار كلمة المرور'),
                                    onPressed: () => setState(() => showPassword = !showPassword),
                                    icon: Icon(showPassword ? Icons.visibility_off_rounded : Icons.visibility_rounded),
                                  ),
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
                              Align(
                                alignment: AlignmentDirectional.centerEnd,
                                child: TextButton.icon(
                                  onPressed: busy ? null : widget.onForgotPassword,
                                  icon: const Icon(Icons.key_rounded, size: 17),
                                  label: Text(en ? 'Forgot password?' : 'نسيت كلمة المرور؟'),
                                ),
                              ),
                              const SizedBox(height: 8),
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


class NotificationDetailPage extends StatelessWidget {
  const NotificationDetailPage({
    super.key,
    required this.english,
    required this.item,
  });

  final bool english;
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final title = item['title']?.toString() ?? '';
    final body = item['body']?.toString() ?? '';
    final createdAt = item['created_at']?.toString() ?? '';
    final link = item['link']?.toString() ?? '';
    return Scaffold(
      appBar: AppBar(title: Text(english ? 'Notification details' : 'تفاصيل الإشعار')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: AlignmentDirectional.topStart,
                end: AlignmentDirectional.bottomEnd,
                colors: [Color(0xFF0A1F44), Color(0xFF1F4E8C)],
              ),
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Icon(Icons.notifications_active_rounded, size: 36, color: Colors.white),
          ),
          const SizedBox(height: 18),
          Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: const Color(0xFF0A1F44), fontWeight: FontWeight.w800,
          )),
          if (createdAt.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(createdAt, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B))),
          ],
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: SelectableText(body, style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.6)),
            ),
          ),
          if (link.isNotEmpty) ...[
            const SizedBox(height: 18),
            Card(
              child: ListTile(
                leading: const Icon(Icons.info_outline_rounded),
                title: Text(english ? 'Related page' : 'الصفحة المرتبطة'),
                subtitle: Text(
                  english
                      ? 'The notification details stay inside this app. Opening the linked website page is not available in-app yet.'
                      : 'تفاصيل الإشعار معروضة داخل التطبيق. فتح صفحة الموقع المرتبطة غير متاح داخل التطبيق حالياً.',
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
