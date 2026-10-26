import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'projects_page.dart';
import 'attendance_page.dart';
import 'employees_page.dart';
import 'employee_detail_page.dart';
import 'my_salary_statements_page.dart';
import 'leave_requests_page.dart';
import 'sponsors_page.dart';
import 'supervisors_page.dart';
import 'families_page.dart';
import 'admin_dashboard_page.dart';
import 'admin_finance_overview_page.dart';
import 'admin_accounts_page.dart';
import 'admin_disbursements_page.dart';
import 'role_dashboard_page.dart';
import 'sponsor_assignment_page.dart';
import 'family_detail_page.dart';
import 'fm_review_queue_page.dart';
import 'transactions_page.dart';
import 'sponsorships_page.dart';
import 'sponsor_requests_page.dart';
import 'nanny_group_verification_page.dart';
import 'nanny_pre_verification_page.dart';
import 'role_tools_section.dart';
import 'suspension_cases_page.dart';
import 'audit_log_page.dart';
import 'admin_users_page.dart';
import 'admin_departments_page.dart';
import 'admin_recovery_page.dart';
import 'admin_settings_page.dart';
import 'reports_page.dart';
import 'orphaned_families_report_page.dart';
import 'fina_collection_page.dart';
import 'fina_review_page.dart';

void main() => runApp(const AhlElKheirApp());
String? normalizeApiBaseUrl(String value) {
  final normalized = value.trim().replaceAll(RegExp(r'/+$'), '');
  final uri = Uri.tryParse(normalized);
  if (uri == null ||
      !['http', 'https'].contains(uri.scheme) ||
      uri.host.isEmpty ||
      uri.userInfo.isNotEmpty ||
      uri.hasQuery ||
      uri.hasFragment ||
      !uri.path.replaceAll(RegExp(r'/+$'), '').endsWith('/api/v1')) {
    return null;
  }
  return normalized;
}

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
  static const apiBaseUrlKey = 'mobile_api_base_url';
  static const baseUrl = String.fromEnvironment('API_BASE_URL',
      defaultValue: 'http://192.168.18.9:8081/AhlElKheir/api/v1');
  String apiBaseUrl = baseUrl;
  bool english = false, loading = true;
  Map<String, dynamic>? user;
  String? error;
  bool ageAlertsLoading = false;
  List<Map<String, dynamic>> ageAlertItems = [];

  Future<Map<String, dynamic>> call(String method, String path,
      {Map<String, dynamic>? body, String? token}) async {
    final headers = <String, String>{'Accept': 'application/json'};
    if (body != null) headers['Content-Type'] = 'application/json';
    if (token != null) headers['Authorization'] = 'Bearer $token';
    try {
      final uri = Uri.parse('${apiBaseUrl.replaceFirst(RegExp(r'/+$'), '')}/$path');
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
      final savedBaseUrl = await storage.read(key: apiBaseUrlKey);
      final normalizedBaseUrl = savedBaseUrl == null ? null : normalizeApiBaseUrl(savedBaseUrl);
      if (normalizedBaseUrl != null) apiBaseUrl = normalizedBaseUrl;
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
    if (user != null && ['admin', 'sudo', 'super_admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'nanny'].contains(user?['role_code']?.toString())) {
      await openAgeAlerts(onlyIfItems: true);
    }
  }

  Future<void> configureApiBaseUrl() async {
    final controller = TextEditingController(text: apiBaseUrl);
    final value = await showDialog<String>(
      context: navigatorKey.currentContext!,
      builder: (dialogContext) => AlertDialog(
        title: Text(english ? 'API server address' : 'عنوان خادم API'),
        content: SizedBox(
          width: 420,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                english
                    ? 'Enter the full API URL ending in /api/v1. For a phone, use your computer’s reachable LAN IP, not localhost.'
                    : 'أدخل رابط API كاملاً وينتهي بـ /api/v1. عند استخدام الهاتف، أدخل عنوان IP المحلي القابل للوصول من الهاتف، وليس localhost.',
                style: const TextStyle(height: 1.45),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: controller,
                autofocus: true,
                keyboardType: TextInputType.url,
                textDirection: TextDirection.ltr,
                decoration: const InputDecoration(
                  labelText: 'http://192.168.x.x:8081/AhlElKheir/api/v1',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(english ? 'Cancel' : 'إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, controller.text),
            child: Text(english ? 'Save server' : 'حفظ الخادم'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (value == null || !mounted) return;
    final normalized = normalizeApiBaseUrl(value);
    if (normalized == null) {
      ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
        SnackBar(
          content: Text(english
              ? 'Invalid API URL. Use http:// or https:// and end the path with /api/v1.'
              : 'رابط API غير صالح. استخدم http:// أو https:// وأنهِ المسار بـ /api/v1.'),
          duration: const Duration(seconds: 5),
        ),
      );
      return;
    }
    await storage.write(key: apiBaseUrlKey, value: normalized);
    await storage.delete(key: tokenKey);
    if (!mounted) return;
    setState(() {
      apiBaseUrl = normalized;
      user = null;
      error = null;
      loading = false;
    });
    ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
      SnackBar(content: Text(english ? 'API server saved. Sign in again.' : 'تم حفظ خادم API. سجّل الدخول مرة أخرى.')),
    );
  }

  Future<void> login(String username, String password) async {
    final data = await call('POST', 'auth/login.php',
        body: {'username': username.trim(), 'password': password});
    if (data['access_token'] is! String || data['user'] is! Map<String, dynamic>) {
      throw Exception('Invalid login response');
    }
    await storage.write(key: tokenKey, value: data['access_token'] as String);
    if (mounted) setState(() => user = data['user'] as Map<String, dynamic>);
    if (['admin', 'sudo', 'super_admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'nanny'].contains(data['user']['role_code']?.toString())) {
      await openAgeAlerts(onlyIfItems: true);
    }
  }

  Future<void> openNativeTool(String relativePath, {Map<String, String>? queryParameters}) async {
    // Native-only policy: never launch a website, browser, or external application.
    if (relativePath == 'modules/transactions/fina_payment_create.php') {
      openFinaCollectionPage();
      return;
    }
    if (relativePath == 'modules/accounting/fina_payment_review.php' ||
        relativePath == 'modules/accounting/fina_payment_history.php') {
      openFinaReviewPage();
      return;
    }
    if (relativePath == 'modules/families/suspensions.php') {
      openSuspensionCases();
      return;
    }
    if (relativePath == 'modules/logs/audit.php') {
      openAuditLogPage();
      return;
    }
    if (relativePath == 'modules/reports/index.php') {
      const reportOverviewRoles = [
        'admin', 'general_manager', 'vice_general_manager', 'supervisor',
        'accountant', 'accountant_staff', 'financial_manager', 'nanny',
        'hr_manager', 'hr_staff',
      ];
      if (!reportOverviewRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english
              ? 'General reports are not available for this role.'
              : 'التقارير العامة غير متاحة لهذا الدور.')),
        );
        return;
      }
      openReportsPage();
      return;
    }
    if (relativePath == 'modules/reports/orphaned_families.php') {
      openOrphanedFamiliesReport();
      return;
    }
    if (relativePath == 'modules/users/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => AdminUsersPage(
          english: english,
          loadPage: loadAdminUsers,
          loadOptions: loadAdminUserOptions,
          currentUserId: int.tryParse(user?['id']?.toString() ?? '') ?? 0,
          submitAction: submitAdminUserAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/departments/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => AdminDepartmentsPage(
          english: english,
          loadDepartments: loadAdminDepartments,
          submitAction: submitAdminDepartmentAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/users/recovery.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => AdminRecoveryPage(
          english: english,
          loadRequests: loadAdminRecovery,
          submitAction: submitAdminRecoveryAction,
        ),
      ));
      return;
    }

    if (relativePath == 'modules/hr/employees.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => EmployeesPage(
          english: english,
          loadPage: loadEmployees,
          openDetail: (employee) {
            final id = int.tryParse(employee['id']?.toString() ?? '');
            if (id == null || id < 1) return;
            navigatorKey.currentState?.push(MaterialPageRoute<void>(
              builder: (_) => EmployeeDetailPage(employeeId: id, english: english, loadDetail: loadEmployeeDetail),
            ));
          },
        ),
      ));
      return;
    }
    if (relativePath == 'modules/hr/attendance.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => AttendancePage(english: english, loadAttendance: loadAttendance),
      ));
      return;
    }
    if (relativePath == 'modules/hr/leaves.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => LeaveRequestsPage(
          english: english,
          roleCode: user?['role_code']?.toString() ?? '',
          loadLeaves: loadLeaves,
          submitAction: submitLeaveAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/projects/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => ProjectsPage(
          english: english,
          loadPage: loadProjects,
          loadDetail: loadProjectDetail,
          submitProgress: submitProjectProgressUpdate,
          createMilestone: createProjectMilestone,
          actOnMilestone: actOnProjectMilestone,
        ),
      ));
      return;
    }
    if ([
      'modules/sponsors/new_requests.php',
      'modules/sponsors/contacted_requests.php',
      'modules/sponsor_requests/index.php',
    ].contains(relativePath)) {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SponsorRequestsPage(
          english: english,
          loadRequests: loadSponsorRequests,
          submitAction: submitSponsorRequestAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/sponsors/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SponsorsPage(english: english, loadSponsors: loadSponsors, loadDetail: loadSponsorDetail),
      ));
      return;
    }
    if (relativePath == 'modules/families/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => FamiliesPage(
          english: english,
          loadFamilies: loadFamilies,
          loadDetail: loadFamilyDetail,
          openOrphanForm: (childId) => openNativeTool('modules/families/orphan_form.php', queryParameters: {'child': childId.toString()}),
        ),
      ));
      return;
    }
    if (relativePath == 'modules/sponsorships/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SponsorshipsPage(english: english, loadSponsorships: loadSponsorships, loadDetail: loadSponsorshipDetail),
      ));
      return;
    }
    if (relativePath == 'modules/transactions/index.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => TransactionsPage(english: english, loadTransactions: loadTransactions),
      ));
      return;
    }
    if (relativePath == 'modules/accounting/fm_review_queue.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => FmReviewQueuePage(english: english, loadQueue: loadFmReviewQueue, submitAction: submitFmReviewQueueAction),
      ));
      return;
    }
    if (relativePath == 'modules/hr/my_salary_statements.php') {
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => MySalaryStatementsPage(english: english, loadPage: loadMySalaryStatements),
      ));
      return;
    }

    final labels = english ? 'This feature is not available natively yet. No website or external page will be opened.' : 'هذه الميزة لم تُنفذ داخل التطبيق بعد. لن يتم فتح الموقع أو أي صفحة خارجية.';
    final messenger = navigatorKey.currentState?.context;
    if (messenger != null) {
      ScaffoldMessenger.of(messenger).showSnackBar(
        SnackBar(content: Text(labels), duration: const Duration(seconds: 4)),
      );
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

  Future<Map<String, dynamic>> loadSponsorAssignmentOptions() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'sponsors/assignment_options.php', token: token);
  }

  Future<Map<String, dynamic>> submitSponsorAssignment(int sponsorId, int supervisorId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'sponsors/assign.php', token: token, body: {
      'sponsor_id': sponsorId,
      'supervisor_id': supervisorId,
    });
  }

  void openSponsorAssignmentPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => SponsorAssignmentPage(
        english: english,
        loadOptions: loadSponsorAssignmentOptions,
        assignSponsor: submitSponsorAssignment,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminOverview() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'admin/overview.php', token: token);
  }

  Future<Map<String, dynamic>> loadAdminFinanceOverview() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'admin/finance_overview.php', token: token);
  }

  void openAdminFinanceOverview() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminFinanceOverviewPage(english: english, loadOverview: loadAdminFinanceOverview),
    ));
  }

  Future<Map<String, dynamic>> loadAdminDisbursements({
    required String month,
    required String status,
    required String query,
    required int page,
  }) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final parameters = <String, String>{
      'month': month,
      'status': status,
      'q': query,
      'page': page.toString(),
    };
    return call('GET', 'admin/disbursements.php?${Uri(queryParameters: parameters).query}', token: token);
  }

  Future<Map<String, dynamic>> loadAdminDisbursementDetail(int id, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'admin/disbursements.php?${Uri(queryParameters: {'id': id.toString(), 'items_page': page.toString()}).query}', token: token);
  }

  void openAdminDisbursements() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminDisbursementsPage(
        english: english,
        loadPage: loadAdminDisbursements,
        loadDetail: loadAdminDisbursementDetail,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadFinaSponsors(String query) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final encoded = Uri(queryParameters: {'q': query}).query;
    return call('GET', 'fina/sponsors.php?$encoded', token: token);
  }

  Future<Map<String, dynamic>> loadFinaReceipt(int collectionId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    try {
      final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') +
          '/fina/receipt.php?id=' + collectionId.toString());
      final response = await http.get(uri, headers: {
        'Accept': 'image/*,application/pdf',
        'Authorization': 'Bearer ' + token,
      }).timeout(const Duration(seconds: 20));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        String message = english ? 'Receipt could not be retrieved.' : 'تعذر استرجاع الإيصال.';
        try {
          final decoded = jsonDecode(utf8.decode(response.bodyBytes));
          if (decoded is Map && decoded['error'] is Map) {
            message = (decoded['error']['message'] ?? message).toString();
          }
        } catch (_) {}
        throw ApiFailure(message, statusCode: response.statusCode);
      }
      return {
        'bytes': response.bodyBytes,
        'mime': (response.headers['content-type'] ?? 'application/octet-stream').split(';').first,
      };
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw ApiFailure(english ? 'Could not reach the API server.' : 'تعذر الاتصال بخادم API.');
    }
  }

  Future<Map<String, dynamic>> loadFinaReview() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'fina/review.php', token: token);
  }

  Future<Map<String, dynamic>> submitFinaReviewAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'fina/review.php', token: token, body: payload);
  }

  void openFinaReviewPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => FinaReviewPage(
        english: english,
        loadReview: loadFinaReview,
        submitAction: submitFinaReviewAction,
      ),
    ));
  }

  Future<Map<String, dynamic>> submitFinaCollection(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }

    final receiptPath = payload['_receipt_path']?.toString();
    final fields = Map<String, dynamic>.from(payload)..remove('_receipt_path');
    if (receiptPath == null || receiptPath.isEmpty) {
      return call('POST', 'fina/collections.php', token: token, body: fields);
    }

    try {
      final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/fina/collections.php');
      final request = http.MultipartRequest('POST', uri)
        ..headers['Accept'] = 'application/json'
        ..headers['Authorization'] = 'Bearer ' + token;
      request.fields.addAll(fields.map((key, value) => MapEntry(key, value?.toString() ?? '')));
      request.files.add(await http.MultipartFile.fromPath('receipt_file', receiptPath));
      final streamed = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamed);
      final decoded = jsonDecode(utf8.decode(response.bodyBytes));
      if (decoded is! Map<String, dynamic>) throw const FormatException();
      if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
        final e = decoded['error'];
        throw ApiFailure(
          e is Map ? (e['message']?.toString() ?? 'Request failed') : 'Request failed',
          statusCode: response.statusCode,
        );
      }
      if (decoded['data'] is! Map<String, dynamic>) throw Exception('Invalid API response');
      return decoded['data'] as Map<String, dynamic>;
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw ApiFailure(english ? 'Could not reach the API server.' : 'تعذر الاتصال بخادم API.');
    }
  }

  void openFinaCollectionPage() {
    final role = user?['role_code']?.toString() ?? '';
    if (!['admin', 'sudo', 'super_admin', 'financial_manager', 'fm', 'finance'].contains(role)) {
      ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
        SnackBar(content: Text(english
            ? 'Fina collection registration is restricted to Finance Manager and Administrator.'
            : 'تسجيل تحصيلات فينا الخير متاح للمدير المالي والمسؤول فقط.')),
      );
      return;
    }
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => FinaCollectionPage(
        english: english,
        submitCollection: submitFinaCollection,
        loadSponsors: loadFinaSponsors,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminAccounts() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'admin/accounts.php', token: token);
  }

  Future<Map<String, dynamic>> submitAdminAccountAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'admin/accounts_action.php', token: token, body: payload);
  }

  void openAdminAccounts() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminAccountsPage(
        english: english,
        loadAccounts: loadAdminAccounts,
        submitAction: submitAdminAccountAction,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminDepartments() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/departments.php', token: token);
  }

  Future<Map<String, dynamic>> submitAdminDepartmentAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/departments_action.php', token: token, body: payload);
  }

  Future<Map<String, dynamic>> loadAdminRecovery() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/recovery.php', token: token);
  }

  Future<Map<String, dynamic>> submitAdminRecoveryAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/recovery_action.php', token: token, body: payload);
  }

  Future<Map<String, dynamic>> submitAdminUserAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/users_action.php', token: token, body: payload);
  }

  Future<Map<String, dynamic>> loadAdminUserOptions() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/users.php?options=1', token: token);
  }

  Future<Map<String, dynamic>> loadAdminUsers(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final encodedQuery = Uri.encodeQueryComponent(query);
    return call('GET', 'admin/users.php?q=$encodedQuery&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadAdminAuditLog(int page, String query) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final encodedQuery = Uri.encodeQueryComponent(query);
    return call('GET', 'audit/index.php?page=$page&q=$encodedQuery', token: token);
  }

  Future<Map<String, dynamic>> loadReportsOverview() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'reports/overview.php', token: token);
  }

  Future<Map<String, dynamic>> loadOrphanedFamiliesReport(String city, String status) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final encodedCity = Uri.encodeQueryComponent(city);
    final encodedStatus = Uri.encodeQueryComponent(status);
    return call('GET', 'reports/orphaned_families.php?city=$encodedCity&status=$encodedStatus', token: token);
  }

  void openReportsPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => ReportsPage(
        english: english,
        loadOverview: loadReportsOverview,
        openReport: (key) {
          if (key == 'orphaned') openOrphanedFamiliesReport();
        },
      ),
    ));
  }

  void openOrphanedFamiliesReport() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => OrphanedFamiliesReportPage(
        english: english,
        loadReport: loadOrphanedFamiliesReport,
        openFamily: (familyId) {
          navigatorKey.currentState?.push(MaterialPageRoute<void>(
            builder: (_) => FamilyDetailPage(
              familyId: familyId,
              english: english,
              loadDetail: loadFamilyDetail,
              openOrphanForm: (childId) async {
                ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
                  SnackBar(content: Text(english
                      ? 'Orphan form is not implemented natively yet.'
                      : 'نموذج اليتيم غير منفذ داخل التطبيق بعد.')),
                );
              },
            ),
          ));
        },
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminSettings() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/settings.php', token: token);
  }

  Future<Map<String, dynamic>> saveAdminSettings(Map<String, dynamic> values) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/settings.php', token: token, body: {'values': values});
  }

  void openAdminSettingsPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminSettingsPage(
        english: english,
        loadSettings: loadAdminSettings,
        saveSettings: saveAdminSettings,
      ),
    ));
  }

  void openAuditLogPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AuditLogPage(
        english: english,
        loadPage: loadAdminAuditLog,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadRoleDashboard() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'dashboard/overview.php', token: token);
  }

  void openAdminTool(String tool) {
    final navigator = navigatorKey.currentState;
    if (navigator == null) return;
    switch (tool) {
      case 'sponsor_assignment':
        openSponsorAssignmentPage();
        return;
      case 'sponsors':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorsPage(english: english, loadSponsors: loadSponsors, loadDetail: loadSponsorDetail)));
        return;
      case 'families':
        navigator.push(MaterialPageRoute<void>(builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openNativeTool('modules/families/orphan_form.php', queryParameters: {'child': childId.toString()}))));
        return;
      case 'sponsorships':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorshipsPage(english: english, loadSponsorships: loadSponsorships, loadDetail: loadSponsorshipDetail)));
        return;
      case 'transactions':
        navigator.push(MaterialPageRoute<void>(builder: (_) => TransactionsPage(english: english, loadTransactions: loadTransactions)));
        return;
      case 'employees':
        navigator.push(MaterialPageRoute<void>(builder: (_) => EmployeesPage(english: english, loadPage: loadEmployees, openDetail: (employee) {
          final id = int.tryParse(employee['id']?.toString() ?? '');
          if (id == null || id < 1) return;
          navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => EmployeeDetailPage(employeeId: id, english: english, loadDetail: loadEmployeeDetail)));
        })));
        return;
      case 'attendance':
        navigator.push(MaterialPageRoute<void>(builder: (_) => AttendancePage(english: english, loadAttendance: loadAttendance)));
        return;
      case 'supervisors':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SupervisorsPage(english: english, loadPage: loadSupervisors, canManage: true, submitStatusAction: submitSupervisorStatusAction)));
        return;
      case 'sponsor_requests':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorRequestsPage(english: english, loadRequests: loadSponsorRequests, submitAction: submitSponsorRequestAction)));
        return;
      case 'fm_review_queue':
        navigator.push(MaterialPageRoute<void>(builder: (_) => FmReviewQueuePage(english: english, loadQueue: loadFmReviewQueue, submitAction: submitFmReviewQueueAction)));
        return;
      case 'projects':
        navigator.push(MaterialPageRoute<void>(builder: (_) => ProjectsPage(english: english, loadPage: loadProjects, loadDetail: loadProjectDetail, submitProgress: submitProjectProgressUpdate, createMilestone: createProjectMilestone, actOnMilestone: actOnProjectMilestone)));
        return;
      case 'finance_dashboard':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english ? 'Financial overview is restricted to the administrator role.' : 'المؤشرات المالية متاحة لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminFinanceOverview();
        return;
      case 'accounts':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english ? 'Chart of accounts is restricted to the administrator role.' : 'دليل الحسابات متاح لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminAccounts();
        return;
      case 'disbursements':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english
                ? 'Monthly disbursements are restricted to the administrator role.'
                : 'التحويلات الشهرية متاحة لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminDisbursements();
        return;
      case 'fina_collection':
      case 'modules/transactions/fina_payment_create.php':
        openFinaCollectionPage();
        return;
      case 'modules/accounting/fina_payment_review.php':
      case 'modules/accounting/fina_payment_history.php':
        openFinaReviewPage();
        return;
      case 'users':
        openNativeTool('modules/users/index.php');
        return;
      case 'departments':
        openNativeTool('modules/departments/index.php');
        return;
      case 'settings':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english
                ? 'System settings are restricted to the administrator role.'
                : 'إعدادات النظام متاحة لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminSettingsPage();
        return;
      case 'recovery':
        openNativeTool('modules/users/recovery.php');
        return;
      case 'web_dashboard':
        openNativeTool('dashboard/admin_dashboard.php');
        return;
      case 'reports':
        openNativeTool('modules/reports/index.php');
        return;
      case 'backup':
        openNativeTool('modules/system/backup.php');
        return;
      case 'database':
        openNativeTool('modules/system/database.php');
        return;
      case 'audit':
        openNativeTool('modules/logs/audit.php');
        return;
      case 'suspensions':
        openNativeTool('modules/families/suspensions.php');
        return;
      default:
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(SnackBar(content: Text(english ? 'This tool is not mapped yet.' : 'لم يتم ربط هذه الأداة بعد.')));
    }
  }

  Future<Map<String, dynamic>> loadSuspensionCases() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'suspensions/index.php', token: token);
  }

  Future<Map<String, dynamic>> liftSuspensionCase(int caseId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'suspensions/lift.php', token: token, body: {'case_id': caseId});
  }

  void openSuspensionCases() {
    final role = user?['role_code']?.toString() ?? '';
    final canLift = ['admin', 'sudo', 'super_admin', 'vice_general_manager', 'vgm'].contains(role);
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => SuspensionCasesPage(
        english: english,
        canLift: canLift,
        loadCases: loadSuspensionCases,
        liftCase: liftSuspensionCase,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAgeAlerts() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'age_alerts/index.php', token: token);
  }

  Future<void> suspendAgeAlertChild(int childId) async {
    final dialogContext = navigatorKey.currentContext;
    if (dialogContext == null || childId < 1) return;
    final confirmed = await showDialog<bool>(
      context: dialogContext,
      builder: (context) => AlertDialog(
        title: Text(english ? 'Suspend sponsorship?' : 'هل تريد تعليق الكفالة؟'),
        content: Text(english
            ? 'This records a legal-age suspension, marks the child inactive, and pauses active linked sponsorships.'
            : 'سيتم تسجيل تعليق بسبب بلوغ السن القانوني، وإيقاف نشاط الطفل، وتعليق الكفالات النشطة المرتبطة به.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(english ? 'Cancel' : 'إلغاء'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFFB42318)),
            onPressed: () => Navigator.pop(context, true),
            child: Text(english ? 'Confirm suspension' : 'تأكيد التعليق'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final token = await storage.read(key: tokenKey);
      if (token == null || token.isEmpty) {
        throw const ApiFailure('Authentication is required.', statusCode: 401);
      }
      await call(
        'POST',
        'age_alerts/suspend_child.php',
        token: token,
        body: {'child_id': childId},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
        SnackBar(
          content: Text(english
              ? 'Suspension completed and active linked sponsorships paused.'
              : 'تم التعليق وإيقاف الكفالات النشطة المرتبطة.'),
        ),
      );
      try {
        final refreshed = await loadAgeAlerts();
        final raw = refreshed['items'];
        if (mounted) {
          setState(() {
            ageAlertItems = raw is List
                ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
                : <Map<String, dynamic>>[];
          });
        }
      } catch (_) {
        // The suspension has already succeeded; keep the success result authoritative.
      }
    } catch (e) {
      if (!mounted) return;
      final context = navigatorKey.currentContext;
      if (context != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<void> openAgeAlerts({bool onlyIfItems = false}) async {
    if (ageAlertsLoading) return;
    setState(() => ageAlertsLoading = true);
    try {
      final data = await loadAgeAlerts();
      final raw = data['items'];
      ageAlertItems = raw is List
          ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      if (onlyIfItems && ageAlertItems.isEmpty) return;
      final sheetContext = navigatorKey.currentContext;
      if (sheetContext == null) return;
      await showModalBottomSheet<void>(
        context: sheetContext,
        isScrollControlled: true,
        showDragHandle: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        builder: (sheetContext) => SafeArea(
          child: SizedBox(
            height: MediaQuery.of(sheetContext).size.height * 0.72,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                  child: Row(children: [
                    const CircleAvatar(
                      backgroundColor: Color(0xFFFFF0C2),
                      child: Icon(Icons.hourglass_bottom_rounded, color: Color(0xFF8A5A00)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(english ? 'Legal age alerts' : 'تنبيه بلوغ السن القانوني',
                          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF154734))),
                        Text(english ? '${ageAlertItems.length} children need review' : '${ageAlertItems.length} طفل يحتاج إلى المراجعة',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF65756A))),
                      ],
                    )),
                  ]),
                ),
                const Divider(height: 1),
                Expanded(child: ageAlertItems.isEmpty
                    ? Center(child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(english ? 'No children are within the alert window.' : 'لا يوجد أطفال ضمن فترة التنبيه الحالية.', textAlign: TextAlign.center),
                      ))
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: ageAlertItems.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (_, index) {
                          final item = ageAlertItems[index];
                          final days = int.tryParse(item['days_until_18']?.toString() ?? '') ?? 999;
                          final overdue = days <= 0;
                          final soon = days > 0 && days <= 7;
                          final accent = overdue ? const Color(0xFFB42318) : (soon ? const Color(0xFFB54708) : const Color(0xFF2E63A8));
                          return Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: accent.withValues(alpha: 0.055),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: accent.withValues(alpha: 0.20)),
                            ),
                            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              CircleAvatar(backgroundColor: accent.withValues(alpha: 0.12), child: Icon(Icons.person_outline_rounded, color: accent)),
                              const SizedBox(width: 12),
                              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(item['child_name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF154734))),
                                const SizedBox(height: 3),
                                Text('${item['mother_name'] ?? ''} • ${item['family_code'] ?? ''}', style: const TextStyle(fontSize: 12, color: Color(0xFF65756A))),
                                const SizedBox(height: 6),
                                Text('${english ? 'Birth date' : 'تاريخ الميلاد'}: ${item['birth_date'] ?? '—'}', style: const TextStyle(fontSize: 12)),
                                const SizedBox(height: 5),
                                Text(overdue
                                  ? (english ? 'Legal age reached' : 'بلغ السن القانوني')
                                  : (english ? '$days days remaining' : 'متبقي $days يوم'),
                                  style: TextStyle(color: accent, fontSize: 12, fontWeight: FontWeight.w800)),
                                const SizedBox(height: 8),
                                Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: TextButton.icon(
                                    onPressed: () {
                                      Navigator.pop(sheetContext);
                                      final familyId = int.tryParse(item['family_id']?.toString() ?? '') ?? 0;
                                      if (familyId < 1) return;
                                      navigatorKey.currentState?.push(MaterialPageRoute<void>(
                                        builder: (_) => FamilyDetailPage(
                                          familyId: familyId,
                                          english: english,
                                          loadDetail: loadFamilyDetail,
                                          openOrphanForm: (childId) => openNativeTool(
                                            'modules/families/orphan_form.php',
                                            queryParameters: {'child': childId.toString()},
                                          ),
                                        ),
                                      ));
                                    },
                                    icon: const Icon(Icons.open_in_new_rounded, size: 16),
                                    label: Text(english ? 'Open family details' : 'عرض تفاصيل الأسرة'),
                                  ),
                                ),
                                if (['admin', 'sudo', 'super_admin', 'vice_general_manager', 'vgm', 'nanny'].contains(user?['role_code']?.toString()))
                                  Align(
                                    alignment: AlignmentDirectional.centerStart,
                                    child: TextButton.icon(
                                      style: TextButton.styleFrom(foregroundColor: const Color(0xFFB42318)),
                                      onPressed: () {
                                        Navigator.pop(sheetContext);
                                        final childId = int.tryParse(item['child_id']?.toString() ?? '') ?? 0;
                                        suspendAgeAlertChild(childId);
                                      },
                                      icon: const Icon(Icons.pause_circle_outline_rounded, size: 16),
                                      label: Text(english ? 'Suspend sponsorship' : 'تعليق الكفالة'),
                                    ),
                                  ),
                              ])),
                            ]),
                          );
                        },
                      )),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  child: SizedBox(width: double.infinity, child: OutlinedButton(
                    onPressed: () => Navigator.pop(sheetContext),
                    child: Text(english ? 'Close' : 'إغلاق'),
                  )),
                ),
              ],
            ),
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      final context = navigatorKey.currentContext;
      if (context != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => ageAlertsLoading = false);
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

  Future<Map<String, dynamic>> submitSupervisorStatusAction(int supervisorId, String action) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'supervisors/action.php', token: token, body: {
      'supervisor_id': supervisorId,
      'action': action,
    });
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
        seedColor: const Color(0xFF1E6B4E),
        primary: const Color(0xFF15583F),
        secondary: const Color(0xFFC89B3C),
        surface: Colors.white,
        error: const Color(0xFFB42318),
      ),
      scaffoldBackgroundColor: const Color(0xFFF6F4EE),
      visualDensity: VisualDensity.standard,
      dividerTheme: const DividerThemeData(
        color: Color(0xFFE7E2D5),
        thickness: 1,
        space: 1,
      ),
      fontFamily: 'Cairo',
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: Color(0xFFE7E2D5)),
        ),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        minVerticalPadding: 14,
        horizontalTitleGap: 14,
        iconColor: const Color(0xFF1E6B4E),
        textColor: const Color(0xFF20352B),
        titleTextStyle: const TextStyle(
          color: Color(0xFF20352B),
          fontFamily: 'Cairo',
          fontSize: 14,
          fontWeight: FontWeight.w800,
        ),
        subtitleTextStyle: const TextStyle(
          color: Color(0xFF65756A),
          fontFamily: 'Cairo',
          fontSize: 12,
          height: 1.45,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: const Color(0xFF15583F),
          backgroundColor: const Color(0xFFE9F2EB),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          minimumSize: const Size(44, 44),
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: Color(0xFF20352B),
        elevation: 0,
        scrolledUnderElevation: 0,
        toolbarHeight: 56,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: const Color(0xFFFBFAF6),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD5D9CD), width: 1.2)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFB7C7B9), width: 1.5)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1E6B4E), width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: const Color(0xFF15583F), foregroundColor: Colors.white, minimumSize: const Size.fromHeight(52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))),
    navigatorKey: navigatorKey,
  home: Directionality(
      textDirection: english ? TextDirection.ltr : TextDirection.rtl,
      child: loading
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : user == null
          ? LoginPage(
              english: english,
              toggle: () => setState(() => english = !english),
              submit: login,
              apiBaseUrl: apiBaseUrl,
              onConfigureApi: configureApiBaseUrl,
              onForgotPassword: () => openNativeTool('modules/users/password_recovery_request.php'),
            )
          : Scaffold(
              appBar: PreferredSize(
                preferredSize: const Size.fromHeight(104),
                child: AppBar(
                  toolbarHeight: 104,
                  titleSpacing: 16,
                  title: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Row(
                        children: [
                          const OrganizationLogo(size: 38),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              english
                                  ? 'Ahl El Kheir Women’s Organization for Orphan Sponsorship'
                                  : 'منظمة أهل الخير النسوية لكفالة الأيتام',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, height: 1.25),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          if (['admin', 'sudo', 'super_admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'nanny'].contains(user?['role_code']?.toString()))
                            IconButton(
                              tooltip: english ? 'Legal age alerts' : 'تنبيه بلوغ السن القانوني',
                              onPressed: openAgeAlerts,
                              icon: const Icon(Icons.hourglass_bottom_rounded, color: Color(0xFFB54708)),
                            ),
                          IconButton(
                            tooltip: english ? 'Global search (WEB)' : 'البحث الشامل (ويب)',
                            onPressed: () => openNativeTool('modules/search/index.php'),
                            icon: const Icon(Icons.search_rounded),
                          ),
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
                                            backgroundColor: Color(0xFFE7F0E8),
                                            child: Icon(Icons.person_rounded, color: Color(0xFF154734)),
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
                                        ListTile(
                                          leading: const Icon(Icons.person_outline_rounded),
                                          title: Text(english ? 'My profile' : 'ملفي الشخصي'),
                                          subtitle: Text(english ? 'Native workflow pending' : 'لم تُنفذ داخل التطبيق بعد'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/users/profile.php');
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.settings_outlined),
                                          title: Text(english ? 'Account settings' : 'إعدادات الحساب'),
                                          subtitle: Text(english ? 'Native workflow pending' : 'لم تُنفذ داخل التطبيق بعد'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/users/settings.php');
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.request_quote_outlined),
                                          title: Text(english ? 'Salary advance request' : 'طلب سلفة راتب'),
                                          subtitle: Text(english ? 'Native workflow pending · employee profile required' : 'لم تُنفذ داخل التطبيق بعد · يتطلب ملف موظف'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/hr/salary_advance_request.php');
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.chat_bubble_outline_rounded),
                                          title: Text(english ? 'Internal messages' : 'الرسائل الداخلية'),
                                          subtitle: Text(english ? 'Native workflow pending' : 'لم تُنفذ داخل التطبيق بعد'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/messages/index.php');
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
                            tooltip: english ? 'Change language' : 'تغيير اللغة',
                            onPressed: () => setState(() => english = !english),
                            icon: const Icon(Icons.language_rounded),
                          ),
                          IconButton(
                            tooltip: english ? 'Sign out' : 'تسجيل الخروج',
                            onPressed: logout,
                            icon: const Icon(Icons.logout_rounded),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              body: LayoutBuilder(builder: (context, constraints) {
                final moduleCardWidth = constraints.maxWidth - 40;
                final roleCode = user?['role_code']?.toString() ?? '';
                if (['admin', 'sudo', 'super_admin'].contains(roleCode)) {
                  return AdminDashboardPage(
                    english: english,
                    fullName: user?['full_name']?.toString() ?? '',
                    roleCode: roleCode,
                    loadOverview: loadAdminOverview,
                    onOpenTool: openAdminTool,
                    onOpenAgeAlerts: () => openAgeAlerts(),
                  );
                }
                final roleTitle = switch (roleCode) {
                  'admin' || 'sudo' || 'super_admin' => english ? 'System control center' : 'مركز إدارة النظام',
                  'general_manager' || 'gm' => english ? 'Executive overview' : 'نظرة الإدارة التنفيذية',
                  'vice_general_manager' || 'vgm' => english ? 'Operations oversight' : 'متابعة العمليات',
                  'financial_manager' || 'fm' || 'finance' => english ? 'Finance workspace' : 'مساحة العمل المالية',
                  'accountant' || 'accountant_staff' => english ? 'Accounting workspace' : 'مساحة عمل المحاسبة',
                  'supervisor' => english ? 'Supervisor workspace' : 'مساحة عمل المشرف',
                  'nanny' => english ? 'Family care workspace' : 'مساحة عمل الحاضنة',
                  'hr_manager' || 'hr_staff' => english ? 'Human resources' : 'الموارد البشرية',
                  'projects_manager' || 'project_supervisor' => english ? 'Project workspace' : 'مساحة عمل المشروعات',
                  'administration' || 'staff' || 'social_media' => english ? 'Organization workspace' : 'مساحة عمل المنظمة',
                  _ => english ? 'Your workspace' : 'مساحة عملك',
                };
                final heroColors = switch (roleCode) {
                  'admin' || 'sudo' || 'super_admin' => const [Color(0xFF173D2F), Color(0xFFC89B3C)],
                  'general_manager' || 'gm' => const [Color(0xFF154734), Color(0xFF478364)],
                  'vice_general_manager' || 'vgm' => const [Color(0xFF15583F), Color(0xFFC89B3C)],
                  'financial_manager' || 'fm' || 'finance' || 'accountant' || 'accountant_staff' => const [Color(0xFF173D2F), Color(0xFF478364)],
                  'supervisor' || 'nanny' => const [Color(0xFF1E6B4E), Color(0xFF8FAE91)],
                  'hr_manager' || 'hr_staff' => const [Color(0xFF154734), Color(0xFFC89B3C)],
                  'projects_manager' || 'project_supervisor' => const [Color(0xFF1E6B4E), Color(0xFFB58A3A)],
                  _ => const [Color(0xFF173D2F), Color(0xFF15583F)],
                };
                return ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: AlignmentDirectional.topStart,
                        end: AlignmentDirectional.bottomEnd,
                        colors: heroColors,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF154734).withValues(alpha: 0.14),
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
                                roleTitle,
                                style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800, height: 1.25),
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
                  RoleDashboardPage(
                    key: ValueKey('role-dashboard-$roleCode'),
                    english: english,
                    roleCode: roleCode,
                    loadOverview: loadRoleDashboard,
                  ),
                  const SizedBox(height: 24),
                  _HomeSectionTitle(
                    title: english ? 'Work areas' : 'مساحات العمل',
                    subtitle: '',
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
                        builder: (_) => SupervisorsPage(english: english, loadPage: loadSupervisors, canManage: true, submitStatusAction: submitSupervisorStatusAction),
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
                  if (['general_manager', 'gm', 'vice_general_manager', 'vgm'].contains(roleCode))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(backgroundColor: Color(0xFFE8F2E9), child: Icon(Icons.assignment_ind_rounded, color: Color(0xFF1E6B4E))),
                      title: Text(english ? 'Sponsor assignment' : 'إسناد الكفلاء'),
                      subtitle: Text(english ? 'Assign unassigned sponsors to active supervisors' : 'إسناد الكفلاء غير المسندين إلى المشرفين النشطين'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: openSponsorAssignmentPage,
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
                        builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openNativeTool('modules/families/orphan_form.php', queryParameters: {'child': childId.toString()})),
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
                  const SizedBox(height: 22),
                  RoleToolsSection(
                    roleCode: roleCode,
                    english: english,
                    onOpen: (path) => openNativeTool(path),
                  ),
                  const SizedBox(height: 22),
                  _HomeSectionTitle(
                    title: english ? 'My employment' : 'خدماتي الوظيفية',
                    subtitle: english
                        ? 'Personal leave requests and finalized salary statements'
                        : 'طلبات الإجازة الشخصية وكشوف الرواتب المعتمدة',
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      SizedBox(
                        width: moduleCardWidth,
                        child: Card(
                          child: ListTile(
                            leading: const CircleAvatar(
                              backgroundColor: Color(0xFFE8F2E9),
                              child: Icon(Icons.event_available_rounded, color: Color(0xFF1E6B4E)),
                            ),
                            title: Text(english ? 'My leave requests' : 'طلبات إجازاتي'),
                            subtitle: Text(english
                                ? 'Submit and track your own leave requests; authorized HR and GM users also see their approval queue.'
                                : 'تقديم ومتابعة طلبات إجازتك؛ ويظهر طابور الاعتماد للمخولين من الموارد البشرية والمدير العام.'),
                            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                            onTap: () => navigatorKey.currentState?.push(
                              MaterialPageRoute<void>(
                                builder: (_) => LeaveRequestsPage(
                                  english: english,
                                  roleCode: roleCode,
                                  loadLeaves: loadLeaves,
                                  submitAction: submitLeaveAction,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                      SizedBox(
                        width: moduleCardWidth,
                        child: Card(
                          child: ListTile(
                            leading: const CircleAvatar(
                              backgroundColor: Color(0xFFE8F8F1),
                              child: Icon(Icons.receipt_long_rounded, color: Color(0xFF16845B)),
                            ),
                            title: Text(english ? 'My salary statements' : 'كشوف رواتبي'),
                            subtitle: Text(english
                                ? 'View only paid payrolls already posted to accounting.'
                                : 'عرض الرواتب المصروفة والمرحّلة محاسبياً فقط.'),
                            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                            onTap: () => navigatorKey.currentState?.push(
                              MaterialPageRoute<void>(
                                builder: (_) => MySalaryStatementsPage(
                                  english: english,
                                  loadPage: loadMySalaryStatements,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
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
  const _HomeSectionTitle({required this.title, this.subtitle = ''});

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
                    color: const Color(0xFF154734),
                    fontWeight: FontWeight.w800,
                  ),
            ),
            if (subtitle.isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: const Color(0xFF65756A),
                    ),
              ),
            ],
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
                                          : const Color(0xFF1E6B4E),
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
          border: Border.all(color: const Color(0xFFE7F0E8)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF154734).withValues(alpha: 0.08),
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
              color: Color(0xFF1E6B4E),
            ),
          ),
        ),
      );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.english, required this.toggle, required this.submit, required this.apiBaseUrl, required this.onConfigureApi, required this.onForgotPassword});
  final bool english;
  final VoidCallback toggle;
  final Future<void> Function(String, String) submit;
  final String apiBaseUrl;
  final Future<void> Function() onConfigureApi;
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
    const navy900 = Color(0xFF154734);
    const navy600 = Color(0xFF1E6B4E);
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
                    Wrap(
                      alignment: WrapAlignment.end,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        TextButton.icon(
                          onPressed: widget.onConfigureApi,
                          style: TextButton.styleFrom(
                            foregroundColor: Colors.white,
                            backgroundColor: Colors.white.withValues(alpha: 0.10),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(24),
                              side: BorderSide(color: Colors.white.withValues(alpha: 0.24)),
                            ),
                          ),
                          icon: const Icon(Icons.dns_outlined, size: 18),
                          label: Text(en ? 'API server' : 'الخادم'),
                        ),
                        TextButton.icon(
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
                      ],
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
                                en ? 'Ahl El Kheir Women’s Organization for Orphan Sponsorship' : 'منظمة أهل الخير النسوية لكفالة الأيتام',
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  color: navy900, fontWeight: FontWeight.w800, height: 1.45,
                                ),
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
                                style: const TextStyle(color: Color(0xFF65756A), fontSize: 12),
                              ),
                              const SizedBox(height: 7),
                              SelectableText(
                                widget.apiBaseUrl,
                                textDirection: TextDirection.ltr,
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Color(0xFF65756A), fontSize: 10),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
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
                colors: [Color(0xFF154734), Color(0xFF1E6B4E)],
              ),
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Icon(Icons.notifications_active_rounded, size: 36, color: Colors.white),
          ),
          const SizedBox(height: 18),
          Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: const Color(0xFF154734), fontWeight: FontWeight.w800,
          )),
          if (createdAt.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(createdAt, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF65756A))),
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
), '')}/fina/collections.php');
      final request = http.MultipartRequest('POST', uri)
        ..headers['Accept'] = 'application/json'
        ..headers['Authorization'] = 'Bearer $token';
      request.fields.addAll(fields.map((key, value) => MapEntry(key, value?.toString() ?? '')));
      request.files.add(await http.MultipartFile.fromPath('receipt_file', receiptPath));
      final streamed = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamed);
      final decoded = jsonDecode(utf8.decode(response.bodyBytes));
      if (decoded is! Map<String, dynamic>) throw const FormatException();
      if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
        final e = decoded['error'];
        throw ApiFailure(
          e is Map ? (e['message']?.toString() ?? 'Request failed') : 'Request failed',
          statusCode: response.statusCode,
        );
      }
      if (decoded['data'] is! Map<String, dynamic>) throw Exception('Invalid API response');
      return decoded['data'] as Map<String, dynamic>;
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw ApiFailure(english ? 'Could not reach the API server.' : 'تعذر الاتصال بخادم API.');
    }
  }

  void openFinaCollectionPage() {
    final role = user?['role_code']?.toString() ?? '';
    if (!['admin', 'sudo', 'super_admin', 'financial_manager', 'fm', 'finance'].contains(role)) {
      ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
        SnackBar(content: Text(english
            ? 'Fina collection registration is restricted to Finance Manager and Administrator.'
            : 'تسجيل تحصيلات فينا الخير متاح للمدير المالي والمسؤول فقط.')),
      );
      return;
    }
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => FinaCollectionPage(
        english: english,
        submitCollection: submitFinaCollection,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminAccounts() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'admin/accounts.php', token: token);
  }

  Future<Map<String, dynamic>> submitAdminAccountAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'admin/accounts_action.php', token: token, body: payload);
  }

  void openAdminAccounts() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminAccountsPage(
        english: english,
        loadAccounts: loadAdminAccounts,
        submitAction: submitAdminAccountAction,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminDepartments() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/departments.php', token: token);
  }

  Future<Map<String, dynamic>> submitAdminDepartmentAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/departments_action.php', token: token, body: payload);
  }

  Future<Map<String, dynamic>> loadAdminRecovery() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/recovery.php', token: token);
  }

  Future<Map<String, dynamic>> submitAdminRecoveryAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/recovery_action.php', token: token, body: payload);
  }

  Future<Map<String, dynamic>> submitAdminUserAction(Map<String, dynamic> payload) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/users_action.php', token: token, body: payload);
  }

  Future<Map<String, dynamic>> loadAdminUserOptions() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/users.php?options=1', token: token);
  }

  Future<Map<String, dynamic>> loadAdminUsers(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final encodedQuery = Uri.encodeQueryComponent(query);
    return call('GET', 'admin/users.php?q=$encodedQuery&page=$page', token: token);
  }

  Future<Map<String, dynamic>> loadAdminAuditLog(int page, String query) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final encodedQuery = Uri.encodeQueryComponent(query);
    return call('GET', 'audit/index.php?page=$page&q=$encodedQuery', token: token);
  }

  Future<Map<String, dynamic>> loadReportsOverview() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'reports/overview.php', token: token);
  }

  Future<Map<String, dynamic>> loadOrphanedFamiliesReport(String city, String status) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final encodedCity = Uri.encodeQueryComponent(city);
    final encodedStatus = Uri.encodeQueryComponent(status);
    return call('GET', 'reports/orphaned_families.php?city=$encodedCity&status=$encodedStatus', token: token);
  }

  void openReportsPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => ReportsPage(
        english: english,
        loadOverview: loadReportsOverview,
        openReport: (key) {
          if (key == 'orphaned') openOrphanedFamiliesReport();
        },
      ),
    ));
  }

  void openOrphanedFamiliesReport() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => OrphanedFamiliesReportPage(
        english: english,
        loadReport: loadOrphanedFamiliesReport,
        openFamily: (familyId) {
          navigatorKey.currentState?.push(MaterialPageRoute<void>(
            builder: (_) => FamilyDetailPage(
              familyId: familyId,
              english: english,
              loadDetail: loadFamilyDetail,
              openOrphanForm: (childId) async {
                ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
                  SnackBar(content: Text(english
                      ? 'Orphan form is not implemented natively yet.'
                      : 'نموذج اليتيم غير منفذ داخل التطبيق بعد.')),
                );
              },
            ),
          ));
        },
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAdminSettings() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'admin/settings.php', token: token);
  }

  Future<Map<String, dynamic>> saveAdminSettings(Map<String, dynamic> values) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'admin/settings.php', token: token, body: {'values': values});
  }

  void openAdminSettingsPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminSettingsPage(
        english: english,
        loadSettings: loadAdminSettings,
        saveSettings: saveAdminSettings,
      ),
    ));
  }

  void openAuditLogPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AuditLogPage(
        english: english,
        loadPage: loadAdminAuditLog,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadRoleDashboard() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'dashboard/overview.php', token: token);
  }

  void openAdminTool(String tool) {
    final navigator = navigatorKey.currentState;
    if (navigator == null) return;
    switch (tool) {
      case 'sponsor_assignment':
        openSponsorAssignmentPage();
        return;
      case 'sponsors':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorsPage(english: english, loadSponsors: loadSponsors, loadDetail: loadSponsorDetail)));
        return;
      case 'families':
        navigator.push(MaterialPageRoute<void>(builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openNativeTool('modules/families/orphan_form.php', queryParameters: {'child': childId.toString()}))));
        return;
      case 'sponsorships':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorshipsPage(english: english, loadSponsorships: loadSponsorships, loadDetail: loadSponsorshipDetail)));
        return;
      case 'transactions':
        navigator.push(MaterialPageRoute<void>(builder: (_) => TransactionsPage(english: english, loadTransactions: loadTransactions)));
        return;
      case 'employees':
        navigator.push(MaterialPageRoute<void>(builder: (_) => EmployeesPage(english: english, loadPage: loadEmployees, openDetail: (employee) {
          final id = int.tryParse(employee['id']?.toString() ?? '');
          if (id == null || id < 1) return;
          navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => EmployeeDetailPage(employeeId: id, english: english, loadDetail: loadEmployeeDetail)));
        })));
        return;
      case 'attendance':
        navigator.push(MaterialPageRoute<void>(builder: (_) => AttendancePage(english: english, loadAttendance: loadAttendance)));
        return;
      case 'supervisors':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SupervisorsPage(english: english, loadPage: loadSupervisors, canManage: true, submitStatusAction: submitSupervisorStatusAction)));
        return;
      case 'sponsor_requests':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorRequestsPage(english: english, loadRequests: loadSponsorRequests, submitAction: submitSponsorRequestAction)));
        return;
      case 'fm_review_queue':
        navigator.push(MaterialPageRoute<void>(builder: (_) => FmReviewQueuePage(english: english, loadQueue: loadFmReviewQueue, submitAction: submitFmReviewQueueAction)));
        return;
      case 'projects':
        navigator.push(MaterialPageRoute<void>(builder: (_) => ProjectsPage(english: english, loadPage: loadProjects, loadDetail: loadProjectDetail, submitProgress: submitProjectProgressUpdate, createMilestone: createProjectMilestone, actOnMilestone: actOnProjectMilestone)));
        return;
      case 'finance_dashboard':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english ? 'Financial overview is restricted to the administrator role.' : 'المؤشرات المالية متاحة لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminFinanceOverview();
        return;
      case 'accounts':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english ? 'Chart of accounts is restricted to the administrator role.' : 'دليل الحسابات متاح لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminAccounts();
        return;
      case 'disbursements':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english
                ? 'Monthly disbursements are restricted to the administrator role.'
                : 'التحويلات الشهرية متاحة لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminDisbursements();
        return;
      case 'fina_collection':
        openFinaCollectionPage();
        return;
      case 'users':
        openNativeTool('modules/users/index.php');
        return;
      case 'departments':
        openNativeTool('modules/departments/index.php');
        return;
      case 'settings':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english
                ? 'System settings are restricted to the administrator role.'
                : 'إعدادات النظام متاحة لدور المسؤول فقط.')),
          );
          return;
        }
        openAdminSettingsPage();
        return;
      case 'recovery':
        openNativeTool('modules/users/recovery.php');
        return;
      case 'web_dashboard':
        openNativeTool('dashboard/admin_dashboard.php');
        return;
      case 'reports':
        openNativeTool('modules/reports/index.php');
        return;
      case 'backup':
        openNativeTool('modules/system/backup.php');
        return;
      case 'database':
        openNativeTool('modules/system/database.php');
        return;
      case 'audit':
        openNativeTool('modules/logs/audit.php');
        return;
      case 'suspensions':
        openNativeTool('modules/families/suspensions.php');
        return;
      default:
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(SnackBar(content: Text(english ? 'This tool is not mapped yet.' : 'لم يتم ربط هذه الأداة بعد.')));
    }
  }

  Future<Map<String, dynamic>> loadSuspensionCases() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'suspensions/index.php', token: token);
  }

  Future<Map<String, dynamic>> liftSuspensionCase(int caseId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'suspensions/lift.php', token: token, body: {'case_id': caseId});
  }

  void openSuspensionCases() {
    final role = user?['role_code']?.toString() ?? '';
    final canLift = ['admin', 'sudo', 'super_admin', 'vice_general_manager', 'vgm'].contains(role);
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => SuspensionCasesPage(
        english: english,
        canLift: canLift,
        loadCases: loadSuspensionCases,
        liftCase: liftSuspensionCase,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadAgeAlerts() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('GET', 'age_alerts/index.php', token: token);
  }

  Future<void> suspendAgeAlertChild(int childId) async {
    final dialogContext = navigatorKey.currentContext;
    if (dialogContext == null || childId < 1) return;
    final confirmed = await showDialog<bool>(
      context: dialogContext,
      builder: (context) => AlertDialog(
        title: Text(english ? 'Suspend sponsorship?' : 'هل تريد تعليق الكفالة؟'),
        content: Text(english
            ? 'This records a legal-age suspension, marks the child inactive, and pauses active linked sponsorships.'
            : 'سيتم تسجيل تعليق بسبب بلوغ السن القانوني، وإيقاف نشاط الطفل، وتعليق الكفالات النشطة المرتبطة به.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(english ? 'Cancel' : 'إلغاء'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFFB42318)),
            onPressed: () => Navigator.pop(context, true),
            child: Text(english ? 'Confirm suspension' : 'تأكيد التعليق'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final token = await storage.read(key: tokenKey);
      if (token == null || token.isEmpty) {
        throw const ApiFailure('Authentication is required.', statusCode: 401);
      }
      await call(
        'POST',
        'age_alerts/suspend_child.php',
        token: token,
        body: {'child_id': childId},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
        SnackBar(
          content: Text(english
              ? 'Suspension completed and active linked sponsorships paused.'
              : 'تم التعليق وإيقاف الكفالات النشطة المرتبطة.'),
        ),
      );
      try {
        final refreshed = await loadAgeAlerts();
        final raw = refreshed['items'];
        if (mounted) {
          setState(() {
            ageAlertItems = raw is List
                ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
                : <Map<String, dynamic>>[];
          });
        }
      } catch (_) {
        // The suspension has already succeeded; keep the success result authoritative.
      }
    } catch (e) {
      if (!mounted) return;
      final context = navigatorKey.currentContext;
      if (context != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<void> openAgeAlerts({bool onlyIfItems = false}) async {
    if (ageAlertsLoading) return;
    setState(() => ageAlertsLoading = true);
    try {
      final data = await loadAgeAlerts();
      final raw = data['items'];
      ageAlertItems = raw is List
          ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      if (onlyIfItems && ageAlertItems.isEmpty) return;
      final sheetContext = navigatorKey.currentContext;
      if (sheetContext == null) return;
      await showModalBottomSheet<void>(
        context: sheetContext,
        isScrollControlled: true,
        showDragHandle: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        builder: (sheetContext) => SafeArea(
          child: SizedBox(
            height: MediaQuery.of(sheetContext).size.height * 0.72,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                  child: Row(children: [
                    const CircleAvatar(
                      backgroundColor: Color(0xFFFFF0C2),
                      child: Icon(Icons.hourglass_bottom_rounded, color: Color(0xFF8A5A00)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(english ? 'Legal age alerts' : 'تنبيه بلوغ السن القانوني',
                          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF154734))),
                        Text(english ? '${ageAlertItems.length} children need review' : '${ageAlertItems.length} طفل يحتاج إلى المراجعة',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF65756A))),
                      ],
                    )),
                  ]),
                ),
                const Divider(height: 1),
                Expanded(child: ageAlertItems.isEmpty
                    ? Center(child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(english ? 'No children are within the alert window.' : 'لا يوجد أطفال ضمن فترة التنبيه الحالية.', textAlign: TextAlign.center),
                      ))
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: ageAlertItems.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (_, index) {
                          final item = ageAlertItems[index];
                          final days = int.tryParse(item['days_until_18']?.toString() ?? '') ?? 999;
                          final overdue = days <= 0;
                          final soon = days > 0 && days <= 7;
                          final accent = overdue ? const Color(0xFFB42318) : (soon ? const Color(0xFFB54708) : const Color(0xFF2E63A8));
                          return Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: accent.withValues(alpha: 0.055),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: accent.withValues(alpha: 0.20)),
                            ),
                            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              CircleAvatar(backgroundColor: accent.withValues(alpha: 0.12), child: Icon(Icons.person_outline_rounded, color: accent)),
                              const SizedBox(width: 12),
                              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(item['child_name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF154734))),
                                const SizedBox(height: 3),
                                Text('${item['mother_name'] ?? ''} • ${item['family_code'] ?? ''}', style: const TextStyle(fontSize: 12, color: Color(0xFF65756A))),
                                const SizedBox(height: 6),
                                Text('${english ? 'Birth date' : 'تاريخ الميلاد'}: ${item['birth_date'] ?? '—'}', style: const TextStyle(fontSize: 12)),
                                const SizedBox(height: 5),
                                Text(overdue
                                  ? (english ? 'Legal age reached' : 'بلغ السن القانوني')
                                  : (english ? '$days days remaining' : 'متبقي $days يوم'),
                                  style: TextStyle(color: accent, fontSize: 12, fontWeight: FontWeight.w800)),
                                const SizedBox(height: 8),
                                Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: TextButton.icon(
                                    onPressed: () {
                                      Navigator.pop(sheetContext);
                                      final familyId = int.tryParse(item['family_id']?.toString() ?? '') ?? 0;
                                      if (familyId < 1) return;
                                      navigatorKey.currentState?.push(MaterialPageRoute<void>(
                                        builder: (_) => FamilyDetailPage(
                                          familyId: familyId,
                                          english: english,
                                          loadDetail: loadFamilyDetail,
                                          openOrphanForm: (childId) => openNativeTool(
                                            'modules/families/orphan_form.php',
                                            queryParameters: {'child': childId.toString()},
                                          ),
                                        ),
                                      ));
                                    },
                                    icon: const Icon(Icons.open_in_new_rounded, size: 16),
                                    label: Text(english ? 'Open family details' : 'عرض تفاصيل الأسرة'),
                                  ),
                                ),
                                if (['admin', 'sudo', 'super_admin', 'vice_general_manager', 'vgm', 'nanny'].contains(user?['role_code']?.toString()))
                                  Align(
                                    alignment: AlignmentDirectional.centerStart,
                                    child: TextButton.icon(
                                      style: TextButton.styleFrom(foregroundColor: const Color(0xFFB42318)),
                                      onPressed: () {
                                        Navigator.pop(sheetContext);
                                        final childId = int.tryParse(item['child_id']?.toString() ?? '') ?? 0;
                                        suspendAgeAlertChild(childId);
                                      },
                                      icon: const Icon(Icons.pause_circle_outline_rounded, size: 16),
                                      label: Text(english ? 'Suspend sponsorship' : 'تعليق الكفالة'),
                                    ),
                                  ),
                              ])),
                            ]),
                          );
                        },
                      )),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  child: SizedBox(width: double.infinity, child: OutlinedButton(
                    onPressed: () => Navigator.pop(sheetContext),
                    child: Text(english ? 'Close' : 'إغلاق'),
                  )),
                ),
              ],
            ),
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      final context = navigatorKey.currentContext;
      if (context != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => ageAlertsLoading = false);
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

  Future<Map<String, dynamic>> submitSupervisorStatusAction(int supervisorId, String action) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'supervisors/action.php', token: token, body: {
      'supervisor_id': supervisorId,
      'action': action,
    });
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
        seedColor: const Color(0xFF1E6B4E),
        primary: const Color(0xFF15583F),
        secondary: const Color(0xFFC89B3C),
        surface: Colors.white,
        error: const Color(0xFFB42318),
      ),
      scaffoldBackgroundColor: const Color(0xFFF6F4EE),
      visualDensity: VisualDensity.standard,
      dividerTheme: const DividerThemeData(
        color: Color(0xFFE7E2D5),
        thickness: 1,
        space: 1,
      ),
      fontFamily: 'Cairo',
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: Color(0xFFE7E2D5)),
        ),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        minVerticalPadding: 14,
        horizontalTitleGap: 14,
        iconColor: const Color(0xFF1E6B4E),
        textColor: const Color(0xFF20352B),
        titleTextStyle: const TextStyle(
          color: Color(0xFF20352B),
          fontFamily: 'Cairo',
          fontSize: 14,
          fontWeight: FontWeight.w800,
        ),
        subtitleTextStyle: const TextStyle(
          color: Color(0xFF65756A),
          fontFamily: 'Cairo',
          fontSize: 12,
          height: 1.45,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: const Color(0xFF15583F),
          backgroundColor: const Color(0xFFE9F2EB),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          minimumSize: const Size(44, 44),
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: Color(0xFF20352B),
        elevation: 0,
        scrolledUnderElevation: 0,
        toolbarHeight: 56,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: const Color(0xFFFBFAF6),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD5D9CD), width: 1.2)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFB7C7B9), width: 1.5)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1E6B4E), width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: const Color(0xFF15583F), foregroundColor: Colors.white, minimumSize: const Size.fromHeight(52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))),
    navigatorKey: navigatorKey,
  home: Directionality(
      textDirection: english ? TextDirection.ltr : TextDirection.rtl,
      child: loading
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : user == null
          ? LoginPage(
              english: english,
              toggle: () => setState(() => english = !english),
              submit: login,
              apiBaseUrl: apiBaseUrl,
              onConfigureApi: configureApiBaseUrl,
              onForgotPassword: () => openNativeTool('modules/users/password_recovery_request.php'),
            )
          : Scaffold(
              appBar: PreferredSize(
                preferredSize: const Size.fromHeight(104),
                child: AppBar(
                  toolbarHeight: 104,
                  titleSpacing: 16,
                  title: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Row(
                        children: [
                          const OrganizationLogo(size: 38),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              english
                                  ? 'Ahl El Kheir Women’s Organization for Orphan Sponsorship'
                                  : 'منظمة أهل الخير النسوية لكفالة الأيتام',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, height: 1.25),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          if (['admin', 'sudo', 'super_admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'nanny'].contains(user?['role_code']?.toString()))
                            IconButton(
                              tooltip: english ? 'Legal age alerts' : 'تنبيه بلوغ السن القانوني',
                              onPressed: openAgeAlerts,
                              icon: const Icon(Icons.hourglass_bottom_rounded, color: Color(0xFFB54708)),
                            ),
                          IconButton(
                            tooltip: english ? 'Global search (WEB)' : 'البحث الشامل (ويب)',
                            onPressed: () => openNativeTool('modules/search/index.php'),
                            icon: const Icon(Icons.search_rounded),
                          ),
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
                                            backgroundColor: Color(0xFFE7F0E8),
                                            child: Icon(Icons.person_rounded, color: Color(0xFF154734)),
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
                                        ListTile(
                                          leading: const Icon(Icons.person_outline_rounded),
                                          title: Text(english ? 'My profile' : 'ملفي الشخصي'),
                                          subtitle: Text(english ? 'Native workflow pending' : 'لم تُنفذ داخل التطبيق بعد'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/users/profile.php');
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.settings_outlined),
                                          title: Text(english ? 'Account settings' : 'إعدادات الحساب'),
                                          subtitle: Text(english ? 'Native workflow pending' : 'لم تُنفذ داخل التطبيق بعد'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/users/settings.php');
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.request_quote_outlined),
                                          title: Text(english ? 'Salary advance request' : 'طلب سلفة راتب'),
                                          subtitle: Text(english ? 'Native workflow pending · employee profile required' : 'لم تُنفذ داخل التطبيق بعد · يتطلب ملف موظف'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/hr/salary_advance_request.php');
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.chat_bubble_outline_rounded),
                                          title: Text(english ? 'Internal messages' : 'الرسائل الداخلية'),
                                          subtitle: Text(english ? 'Native workflow pending' : 'لم تُنفذ داخل التطبيق بعد'),
                                          trailing: const Icon(Icons.phone_android_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openNativeTool('modules/messages/index.php');
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
                            tooltip: english ? 'Change language' : 'تغيير اللغة',
                            onPressed: () => setState(() => english = !english),
                            icon: const Icon(Icons.language_rounded),
                          ),
                          IconButton(
                            tooltip: english ? 'Sign out' : 'تسجيل الخروج',
                            onPressed: logout,
                            icon: const Icon(Icons.logout_rounded),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              body: LayoutBuilder(builder: (context, constraints) {
                final moduleCardWidth = constraints.maxWidth - 40;
                final roleCode = user?['role_code']?.toString() ?? '';
                if (['admin', 'sudo', 'super_admin'].contains(roleCode)) {
                  return AdminDashboardPage(
                    english: english,
                    fullName: user?['full_name']?.toString() ?? '',
                    roleCode: roleCode,
                    loadOverview: loadAdminOverview,
                    onOpenTool: openAdminTool,
                    onOpenAgeAlerts: () => openAgeAlerts(),
                  );
                }
                final roleTitle = switch (roleCode) {
                  'admin' || 'sudo' || 'super_admin' => english ? 'System control center' : 'مركز إدارة النظام',
                  'general_manager' || 'gm' => english ? 'Executive overview' : 'نظرة الإدارة التنفيذية',
                  'vice_general_manager' || 'vgm' => english ? 'Operations oversight' : 'متابعة العمليات',
                  'financial_manager' || 'fm' || 'finance' => english ? 'Finance workspace' : 'مساحة العمل المالية',
                  'accountant' || 'accountant_staff' => english ? 'Accounting workspace' : 'مساحة عمل المحاسبة',
                  'supervisor' => english ? 'Supervisor workspace' : 'مساحة عمل المشرف',
                  'nanny' => english ? 'Family care workspace' : 'مساحة عمل الحاضنة',
                  'hr_manager' || 'hr_staff' => english ? 'Human resources' : 'الموارد البشرية',
                  'projects_manager' || 'project_supervisor' => english ? 'Project workspace' : 'مساحة عمل المشروعات',
                  'administration' || 'staff' || 'social_media' => english ? 'Organization workspace' : 'مساحة عمل المنظمة',
                  _ => english ? 'Your workspace' : 'مساحة عملك',
                };
                final heroColors = switch (roleCode) {
                  'admin' || 'sudo' || 'super_admin' => const [Color(0xFF173D2F), Color(0xFFC89B3C)],
                  'general_manager' || 'gm' => const [Color(0xFF154734), Color(0xFF478364)],
                  'vice_general_manager' || 'vgm' => const [Color(0xFF15583F), Color(0xFFC89B3C)],
                  'financial_manager' || 'fm' || 'finance' || 'accountant' || 'accountant_staff' => const [Color(0xFF173D2F), Color(0xFF478364)],
                  'supervisor' || 'nanny' => const [Color(0xFF1E6B4E), Color(0xFF8FAE91)],
                  'hr_manager' || 'hr_staff' => const [Color(0xFF154734), Color(0xFFC89B3C)],
                  'projects_manager' || 'project_supervisor' => const [Color(0xFF1E6B4E), Color(0xFFB58A3A)],
                  _ => const [Color(0xFF173D2F), Color(0xFF15583F)],
                };
                return ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: AlignmentDirectional.topStart,
                        end: AlignmentDirectional.bottomEnd,
                        colors: heroColors,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF154734).withValues(alpha: 0.14),
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
                                roleTitle,
                                style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800, height: 1.25),
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
                  RoleDashboardPage(
                    key: ValueKey('role-dashboard-$roleCode'),
                    english: english,
                    roleCode: roleCode,
                    loadOverview: loadRoleDashboard,
                  ),
                  const SizedBox(height: 24),
                  _HomeSectionTitle(
                    title: english ? 'Work areas' : 'مساحات العمل',
                    subtitle: '',
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
                        builder: (_) => SupervisorsPage(english: english, loadPage: loadSupervisors, canManage: true, submitStatusAction: submitSupervisorStatusAction),
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
                  if (['general_manager', 'gm', 'vice_general_manager', 'vgm'].contains(roleCode))
                    SizedBox(width: moduleCardWidth, child: Card(child: ListTile(
                      leading: const CircleAvatar(backgroundColor: Color(0xFFE8F2E9), child: Icon(Icons.assignment_ind_rounded, color: Color(0xFF1E6B4E))),
                      title: Text(english ? 'Sponsor assignment' : 'إسناد الكفلاء'),
                      subtitle: Text(english ? 'Assign unassigned sponsors to active supervisors' : 'إسناد الكفلاء غير المسندين إلى المشرفين النشطين'),
                      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                      onTap: openSponsorAssignmentPage,
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
                        builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openNativeTool('modules/families/orphan_form.php', queryParameters: {'child': childId.toString()})),
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
                  const SizedBox(height: 22),
                  RoleToolsSection(
                    roleCode: roleCode,
                    english: english,
                    onOpen: (path) => openNativeTool(path),
                  ),
                  const SizedBox(height: 22),
                  _HomeSectionTitle(
                    title: english ? 'My employment' : 'خدماتي الوظيفية',
                    subtitle: english
                        ? 'Personal leave requests and finalized salary statements'
                        : 'طلبات الإجازة الشخصية وكشوف الرواتب المعتمدة',
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      SizedBox(
                        width: moduleCardWidth,
                        child: Card(
                          child: ListTile(
                            leading: const CircleAvatar(
                              backgroundColor: Color(0xFFE8F2E9),
                              child: Icon(Icons.event_available_rounded, color: Color(0xFF1E6B4E)),
                            ),
                            title: Text(english ? 'My leave requests' : 'طلبات إجازاتي'),
                            subtitle: Text(english
                                ? 'Submit and track your own leave requests; authorized HR and GM users also see their approval queue.'
                                : 'تقديم ومتابعة طلبات إجازتك؛ ويظهر طابور الاعتماد للمخولين من الموارد البشرية والمدير العام.'),
                            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                            onTap: () => navigatorKey.currentState?.push(
                              MaterialPageRoute<void>(
                                builder: (_) => LeaveRequestsPage(
                                  english: english,
                                  roleCode: roleCode,
                                  loadLeaves: loadLeaves,
                                  submitAction: submitLeaveAction,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                      SizedBox(
                        width: moduleCardWidth,
                        child: Card(
                          child: ListTile(
                            leading: const CircleAvatar(
                              backgroundColor: Color(0xFFE8F8F1),
                              child: Icon(Icons.receipt_long_rounded, color: Color(0xFF16845B)),
                            ),
                            title: Text(english ? 'My salary statements' : 'كشوف رواتبي'),
                            subtitle: Text(english
                                ? 'View only paid payrolls already posted to accounting.'
                                : 'عرض الرواتب المصروفة والمرحّلة محاسبياً فقط.'),
                            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
                            onTap: () => navigatorKey.currentState?.push(
                              MaterialPageRoute<void>(
                                builder: (_) => MySalaryStatementsPage(
                                  english: english,
                                  loadPage: loadMySalaryStatements,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
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
  const _HomeSectionTitle({required this.title, this.subtitle = ''});

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
                    color: const Color(0xFF154734),
                    fontWeight: FontWeight.w800,
                  ),
            ),
            if (subtitle.isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: const Color(0xFF65756A),
                    ),
              ),
            ],
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
                                          : const Color(0xFF1E6B4E),
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
          border: Border.all(color: const Color(0xFFE7F0E8)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF154734).withValues(alpha: 0.08),
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
              color: Color(0xFF1E6B4E),
            ),
          ),
        ),
      );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.english, required this.toggle, required this.submit, required this.apiBaseUrl, required this.onConfigureApi, required this.onForgotPassword});
  final bool english;
  final VoidCallback toggle;
  final Future<void> Function(String, String) submit;
  final String apiBaseUrl;
  final Future<void> Function() onConfigureApi;
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
    const navy900 = Color(0xFF154734);
    const navy600 = Color(0xFF1E6B4E);
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
                    Wrap(
                      alignment: WrapAlignment.end,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        TextButton.icon(
                          onPressed: widget.onConfigureApi,
                          style: TextButton.styleFrom(
                            foregroundColor: Colors.white,
                            backgroundColor: Colors.white.withValues(alpha: 0.10),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(24),
                              side: BorderSide(color: Colors.white.withValues(alpha: 0.24)),
                            ),
                          ),
                          icon: const Icon(Icons.dns_outlined, size: 18),
                          label: Text(en ? 'API server' : 'الخادم'),
                        ),
                        TextButton.icon(
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
                      ],
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
                                en ? 'Ahl El Kheir Women’s Organization for Orphan Sponsorship' : 'منظمة أهل الخير النسوية لكفالة الأيتام',
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  color: navy900, fontWeight: FontWeight.w800, height: 1.45,
                                ),
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
                                style: const TextStyle(color: Color(0xFF65756A), fontSize: 12),
                              ),
                              const SizedBox(height: 7),
                              SelectableText(
                                widget.apiBaseUrl,
                                textDirection: TextDirection.ltr,
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Color(0xFF65756A), fontSize: 10),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
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
                colors: [Color(0xFF154734), Color(0xFF1E6B4E)],
              ),
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Icon(Icons.notifications_active_rounded, size: 36, color: Colors.white),
          ),
          const SizedBox(height: 18),
          Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: const Color(0xFF154734), fontWeight: FontWeight.w800,
          )),
          if (createdAt.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(createdAt, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF65756A))),
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
