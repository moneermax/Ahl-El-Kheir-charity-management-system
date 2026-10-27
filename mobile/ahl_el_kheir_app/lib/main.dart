import 'dart:async';
import 'dart:convert';
import 'dart:io' show SocketException;
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'projects_page.dart';
import 'attendance_page.dart';
import 'attendance_policy_page.dart';
import 'employees_page.dart';
import 'employee_detail_page.dart';
import 'my_salary_statements_page.dart';
import 'leave_requests_page.dart';
import 'sponsors_page.dart';
import 'supervisors_page.dart';
import 'orphan_groups_page.dart';
import 'families_page.dart';
import 'admin_dashboard_page.dart';
import 'admin_finance_overview_page.dart';
import 'admin_accounts_page.dart';
import 'admin_disbursements_page.dart';
import 'role_dashboard_page.dart';
import 'sponsor_assignment_page.dart';
import 'family_detail_page.dart';
import 'sponsor_detail_page.dart';
import 'sponsorship_detail_page.dart';
import 'global_search_page.dart';
import 'orphan_form_page.dart';
import 'fm_review_queue_page.dart';
import 'ordinary_transaction_review_page.dart';
import 'transactions_page.dart';
import 'sponsorships_page.dart';
import 'sponsor_requests_page.dart';
import 'nanny_group_verification_page.dart';
import 'nanny_pre_verification_page.dart';
import 'role_tools_section.dart';
import 'salary_portal_page.dart';
import 'suspension_cases_page.dart';
import 'audit_log_page.dart';
import 'admin_users_page.dart';
import 'admin_departments_page.dart';
import 'admin_recovery_page.dart';
import 'admin_settings_page.dart';
import 'reports_page.dart';
import 'native_report_detail_page.dart';
import 'orphaned_families_report_page.dart';
import 'fina_collection_page.dart';
import 'fina_review_page.dart';
import 'fina_dashboard_page.dart';
import 'fina_report_page.dart';
import 'assigned_nannies_page.dart';
import 'available_families_page.dart';
import 'admin_database_page.dart';
import 'admin_backup_page.dart';
import 'payroll_integrity_page.dart';
import 'payroll_management_page.dart';
import 'account_settings_page.dart';
import 'profile_page.dart';
import 'password_recovery_page.dart';
import 'journal_page.dart';
import 'voucher_page.dart';
import 'payroll_policy_page.dart';
import 'incentive_verification_page.dart';
import 'gm_payroll_history_page.dart';
import 'gm_payroll_report_page.dart';
import 'gm_reconciliation_page.dart';
import 'sponsor_winback_page.dart';
import 'incentive_policy_page.dart';
import 'salary_advance_waiver_page.dart';
import 'salary_advance_dashboard_page.dart';
import 'employment_states_page.dart';
import 'orphan_forms_index_page.dart';
import 'package:file_picker/file_picker.dart';

const defaultMobileApiBaseUrl = 'http://192.168.18.9:8081/AhlElKheir/api/v1';
const mobileBuildRevision = String.fromEnvironment('MOBILE_BUILD_REVISION', defaultValue: 'development-source');

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

String migrateKnownLegacyApiBaseUrl(String value) {
  final uri = Uri.tryParse(value);
  if (uri != null
      && uri.scheme == 'http'
      && uri.host == '192.168.18.9'
      && uri.port == 881
      && uri.path == '/AhlElKheir/api/v1'
      && !uri.hasQuery
      && !uri.hasFragment) {
    return uri.replace(port: 8081).toString();
  }
  return value;
}

class ApiProbeResult {
  const ApiProbeResult(this.success, this.message);
  final bool success;
  final String message;
}

Future<ApiProbeResult> probeApiEndpoint(String baseUrl, {required bool english}) async {
  final normalized = normalizeApiBaseUrl(baseUrl);
  if (normalized == null) {
    return ApiProbeResult(false, english ? 'Invalid API base URL. It must end in /api/v1.' : 'رابط API غير صالح. يجب أن ينتهي بـ /api/v1.');
  }
  Uri? uri;
  try {
    uri = Uri.parse('$normalized/auth/me.php');
    final response = await http.get(uri, headers: const {'Accept': 'application/json'}).timeout(const Duration(seconds: 8));
    dynamic payload;
    try {
      payload = jsonDecode(utf8.decode(response.bodyBytes));
    } on FormatException {
      return ApiProbeResult(false, english
          ? 'The server responded, but not with API JSON (HTTP ' + response.statusCode.toString() + '). Check the /api/v1 path and Apache/PHP errors.'
          : 'الخادم ردّ، لكن الاستجابة ليست JSON خاصاً بالـ API (HTTP ' + response.statusCode.toString() + '). تحقق من المسار /api/v1 وأخطاء Apache/PHP.');
    }
    if (payload is Map && payload['error'] is Map && payload['error']['code']?.toString() == 'unauthenticated' && response.statusCode == 401) {
      return ApiProbeResult(true, english
          ? 'Connection successful: the API answered correctly. Sign in normally.'
          : 'الاتصال ناجح: استجاب API بصورة صحيحة. يمكنك تسجيل الدخول.');
    }
    if (payload is Map && payload['ok'] == true) {
      return ApiProbeResult(true, english ? 'The API endpoint is reachable.' : 'عنوان API قابل للوصول.');
    }
    if (response.statusCode == 404) {
      return ApiProbeResult(false, english
          ? 'Apache was reached, but /auth/me.php returned 404. Check the base path and API deployment.'
          : 'تم الوصول إلى Apache، لكن /auth/me.php أعاد 404. تحقق من مسار API ووجود ملفاته.');
    }
    if (response.statusCode >= 500) {
      return ApiProbeResult(false, english
          ? 'The API server was reached but returned HTTP ' + response.statusCode.toString() + '. Check PHP and database errors.'
          : 'تم الوصول إلى خادم API لكنه أعاد HTTP ' + response.statusCode.toString() + '. تحقق من أخطاء PHP وقاعدة البيانات.');
    }
    return ApiProbeResult(false, english
        ? 'The server answered HTTP ' + response.statusCode.toString() + ', but this was not the expected unauthenticated API response.'
        : 'استجاب الخادم بالرمز HTTP ' + response.statusCode.toString() + '، لكن الاستجابة ليست رد API المتوقع قبل تسجيل الدخول.');
  } on SocketException {
    final host = uri?.host ?? 'server';
    final port = uri?.port ?? 80;
    return ApiProbeResult(false, english
        ? 'No TCP connection to ' + host + ':' + port.toString() + '. Check the PC LAN IP, Apache port, same Wi-Fi, and Windows Firewall.'
        : 'تعذر الاتصال بالشبكة إلى ' + host + ':' + port.toString() + '. تحقق من IP الكمبيوتر ومنفذ Apache واتصال الهاتف بنفس Wi-Fi وجدار حماية Windows.');
  } on TimeoutException {
    return ApiProbeResult(false, english
        ? 'The API did not answer within 8 seconds. Check Apache, the PC address, port, and firewall.'
        : 'لم يستجب API خلال 8 ثوانٍ. تحقق من Apache وعنوان الكمبيوتر والمنفذ وجدار الحماية.');
  } catch (e) {
    return ApiProbeResult(false, english
        ? 'Connection check failed (' + e.runtimeType.toString() + '). Check the API URL and server logs.'
        : 'فشل فحص الاتصال (' + e.runtimeType.toString() + '). تحقق من رابط API وسجلات الخادم.');
  }
}

class ApiFailure implements Exception {
  const ApiFailure(this.message, {this.statusCode, this.code});
  final String message;
  final int? statusCode;
  final String? code;
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
      defaultValue: defaultMobileApiBaseUrl);
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
    Uri? requestUri;
    try {
      requestUri = Uri.parse('${apiBaseUrl.replaceFirst(RegExp(r'/+$'), '')}/$path');
      final response = method == 'GET'
          ? await http.get(requestUri, headers: headers).timeout(const Duration(seconds: 20))
          : await http.post(requestUri, headers: headers,
              body: body == null ? null : jsonEncode(body))
              .timeout(const Duration(seconds: 20));
      final json = jsonDecode(utf8.decode(response.bodyBytes));
      if (json is! Map<String, dynamic>) throw const FormatException();
      if (response.statusCode < 200 || response.statusCode >= 300 || json['ok'] != true) {
        final e = json['error'];
        throw ApiFailure(
          e is Map ? (e['message']?.toString() ?? 'Request failed') : 'Request failed',
          statusCode: response.statusCode,
          code: e is Map ? e['code']?.toString() : null,
        );
      }
      if (json['data'] is! Map<String, dynamic>) throw const FormatException('Invalid API data response');
      return json['data'] as Map<String, dynamic>;
    } on ApiFailure {
      rethrow;
    } on SocketException {
      final host = requestUri?.host ?? 'server';
      final port = requestUri?.port ?? 80;
      throw ApiFailure(english
          ? 'Cannot connect to ' + host + ':' + port.toString() + '. Check the PC LAN IP, Apache port, same Wi-Fi, and Windows Firewall.'
          : 'تعذر الاتصال بـ ' + host + ':' + port.toString() + '. تحقق من IP الكمبيوتر ومنفذ Apache واتصال الهاتف بنفس Wi-Fi وجدار حماية Windows.');
    } on TimeoutException {
      throw ApiFailure(english
          ? 'The API request timed out. Check that Apache is running and the phone can reach the PC.'
          : 'انتهت مهلة طلب API. تحقق من تشغيل Apache وإمكانية وصول الهاتف إلى الكمبيوتر.');
    } on FormatException {
      throw ApiFailure(english
          ? 'The server responded, but its response was not valid API JSON. Check the /api/v1 URL and PHP/Apache errors.'
          : 'استجاب الخادم، لكن الرد ليس JSON صالحاً من API. تحقق من رابط /api/v1 وأخطاء PHP/Apache.');
    } on http.ClientException {
      throw ApiFailure(english
          ? 'The HTTP request failed before a valid API response was received. Check network access and server logs.'
          : 'فشل طلب HTTP قبل استلام رد API صالح. تحقق من الشبكة وسجلات الخادم.');
    } catch (e) {
      throw ApiFailure(english
          ? 'Unexpected API response error (' + e.runtimeType.toString() + '). Check the API URL and server logs.'
          : 'خطأ غير متوقع في استجابة API (' + e.runtimeType.toString() + '). تحقق من رابط API وسجلات الخادم.');
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
      if (normalizedBaseUrl != null) {
        apiBaseUrl = migrateKnownLegacyApiBaseUrl(normalizedBaseUrl);
        if (apiBaseUrl != normalizedBaseUrl) {
          // A previous mobile build saved the stale local port 881.
          // Migrate only that exact known endpoint to the verified phone-facing port 8081;
          // preserve other hosts and custom API URLs.
          await storage.write(key: apiBaseUrlKey, value: apiBaseUrl);
        }
      }
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

Future<void> completeForcedPasswordChange(String username, String currentPassword, String newPassword, String confirmPassword) async {
    final uri = Uri.parse('${apiBaseUrl.replaceFirst(RegExp(r'/+$'), '')}/auth/complete_forced_password_change.php');
    final response = await http.post(
      uri,
      headers: const {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({
        'username': username.trim(),
        'current_password': currentPassword,
        'new_password': newPassword,
        'confirm_password': confirmPassword,
      }),
    ).timeout(const Duration(seconds: 20));
    dynamic decoded;
    try {
      decoded = jsonDecode(utf8.decode(response.bodyBytes));
    } on FormatException {
      throw ApiFailure(english
          ? 'The server responded, but its password-change response was not valid API JSON.'
          : 'استجاب الخادم، لكن رد تغيير كلمة المرور ليس JSON صالحاً من API.', statusCode: response.statusCode);
    }
    if (decoded is! Map<String, dynamic>) {
      throw ApiFailure(english ? 'Invalid password-change response.' : 'استجابة تغيير كلمة المرور غير صالحة.', statusCode: response.statusCode);
    }
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
      final e = decoded['error'];
      throw ApiFailure(
        e is Map ? (e['message']?.toString() ?? 'Password change failed.') : 'Password change failed.',
        statusCode: response.statusCode,
        code: e is Map ? e['code']?.toString() : null,
      );
    }
  }

  Future<void> login(String username, String password) async {
    Map<String, dynamic> data;
    try {
      data = await call('POST', 'auth/login.php',
          body: {'username': username.trim(), 'password': password});
    } on ApiFailure catch (e) {
      if (e.code != 'password_change_required') rethrow;
      final newPassword = await showDialog<String>(
        context: navigatorKey.currentContext!,
        barrierDismissible: false,
        builder: (_) => _RequiredPasswordChangeDialog(
          english: english,
          onSubmit: (nextPassword, confirmation) async {
            await completeForcedPasswordChange(username, password, nextPassword, confirmation);
            return nextPassword;
          },
        ),
      );
      if (newPassword == null) {
        throw ApiFailure(
          english ? 'Password change was cancelled. Sign in again to continue.' : 'تم إلغاء تغيير كلمة المرور. سجّل الدخول مجدداً للمتابعة.',
          statusCode: 403,
          code: 'password_change_required',
        );
      }
      data = await call('POST', 'auth/login.php',
          body: {'username': username.trim(), 'password': newPassword});
    }
    if (data['access_token'] is! String || data['user'] is! Map<String, dynamic>) {
      throw Exception('Invalid login response');
    }
    await storage.write(key: tokenKey, value: data['access_token'] as String);
    if (mounted) setState(() => user = data['user'] as Map<String, dynamic>);
    if (['admin', 'sudo', 'super_admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'nanny'].contains(data['user']['role_code']?.toString())) {
      await openAgeAlerts(onlyIfItems: true);
    }
  }

  void openGlobalSearch() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => GlobalSearchPage(
        roleCode: user?['role_code']?.toString() ?? '',
        english: english,
        loadSearch: loadGlobalSearch,
        loadDocument: loadArchivedDocument,
        onOpenResult: (type, item) {
          final id = int.tryParse(item['id']?.toString() ?? '');
          if (id == null || id < 1) return;
          if (type == 'families') {
            navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => FamilyDetailPage(
              familyId: id, english: english, loadDetail: loadFamilyDetail, openOrphanForm: openOrphanFormPage,
            )));
          } else if (type == 'sponsors') {
            navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => SponsorDetailPage(
              sponsorId: id, english: english, loadDetail: loadSponsorDetail,
            )));
          } else if (type == 'sponsorships') {
            navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => SponsorshipDetailPage(
              sponsorshipId: id, english: english, loadDetail: loadSponsorshipDetail,
            )));
          } else if (type == 'payments') {
            openAdminDisbursements(initialQuery: id.toString());
          }
        },
      ),
    ));
  }

  Future<Map<String, dynamic>> loadGlobalSearch(String type, String query, int page) {
    switch (type) {
      case 'families': return loadFamilies(query, '', page);
      case 'sponsors':
        final rawRole = user?['role_code']?.toString() ?? '';
        if (['financial_manager', 'fm', 'finance', 'accountant'].contains(rawRole)) {
          return loadGlobalSponsorSearch(query, page);
        }
        return loadSponsors(query, '', '', page);
      case 'sponsorships': return loadSponsorships(query, 'all', '', page);
      case 'documents': return loadArchivedDocuments(query, page);
      case 'payments':
        return loadAdminDisbursements(month: '', status: 'all', query: query, page: page).then((response) {
          final pagination = response['pagination'];
          return <String, dynamic>{'ok': true, 'data': {
            'items': response['items'] is List ? response['items'] : <dynamic>[],
            'total': pagination is Map ? pagination['total'] ?? 0 : 0,
            'page': pagination is Map ? pagination['page'] ?? page : page,
            'pages': pagination is Map ? pagination['pages'] ?? 1 : 1,
          }};
        });
      default: throw const ApiFailure('This search category is not available natively yet.');
    }
  }

  Future<Map<String, dynamic>> loadGlobalSponsorSearch(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final encodedQuery = Uri.encodeQueryComponent(query);
    return call(
      'GET',
      'search/sponsors.php?q=' + encodedQuery + '&page=' + page.toString(),
      token: token,
    );
  }

  Future<Map<String, dynamic>> loadArchivedDocuments(String query, int page) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final encodedQuery = Uri.encodeQueryComponent(query);
    return call('GET', 'search/documents.php?q=' + encodedQuery + '&page=' + page.toString(), token: token);
  }

  Future<Map<String, dynamic>> loadArchivedDocument(int versionId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/search/document_file.php?version_id=' + versionId.toString());
    final response = await http.get(uri, headers: {'Accept': 'application/pdf,image/*,application/octet-stream', 'Authorization': 'Bearer ' + token}).timeout(const Duration(seconds: 30));
    if (response.statusCode < 200 || response.statusCode >= 300) {
      Map<String, dynamic> decoded = {};
      try {
        final value = jsonDecode(utf8.decode(response.bodyBytes));
        if (value is Map) decoded = Map<String, dynamic>.from(value);
      } catch (_) {}
      final failure = decoded['error'];
      final message = failure is Map ? (failure['message'] ?? (english ? 'Archived document could not be opened.' : 'تعذر فتح المستند المؤرشف.')).toString() : (english ? 'Archived document could not be opened.' : 'تعذر فتح المستند المؤرشف.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final mime = (response.headers['content-type'] ?? 'application/octet-stream').split(';').first.toLowerCase();
    final disposition = response.headers['content-disposition'] ?? '';
    final match = RegExp(r'filename="([^"]+)"').firstMatch(disposition);
    final filename = match?.group(1) ?? 'archived-document-' + versionId.toString();
    return {'bytes': response.bodyBytes, 'mime': mime, 'filename': filename};
  }

  Future<void> openNativeTool(String relativePath, {Map<String, String>? queryParameters}) async {
    // Native-only policy: never launch a website, browser, or external application.
    if (relativePath == 'modules/deputy_gm/groups.php') {
      if (!['vice_general_manager', 'vgm'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Orphan-group management is restricted to the Vice General Manager.' : 'إدارة مجموعات الأيتام متاحة لنائب المدير العام فقط.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => OrphanGroupsPage(
          english: english,
          loadPage: loadOrphanGroups,
          submitAction: submitOrphanGroupAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/accounting/vouchers.php') {
      const viewRoles = ['admin', 'financial_manager', 'accountant_staff', 'accountant', 'general_manager', 'vice_general_manager'];
      if (!viewRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Voucher access is not available for this role.' : 'عرض السندات غير متاح لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => VoucherPage(english: english, loadVouchers: loadVouchers, issueVoucher: issueVoucher, voidVoucher: voidVoucher),
      ));
      return;
    }
    if (relativePath == 'modules/accounting/journal.php') {
      openJournalPage();
      return;
    }
    if (relativePath == 'modules/accounting/disbursements.php') {
      const allowedRoles = [
        'admin', 'financial_manager', 'general_manager', 'accountant_staff', 'nanny',
      ];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english
              ? 'Monthly disbursements are not available for this role.'
              : 'التحويلات الشهرية غير متاحة لهذا الدور.')),
        );
        return;
      }
      openAdminDisbursements();
      return;
    }
    if (relativePath == 'modules/accounting/fina_dashboard.php') {
      openFinaDashboard();
      return;
    }
    if (relativePath == 'modules/accounting/fina_payment_report.php') {
      openFinaReportPage();
      return;
    }
    if (relativePath == 'modules/transactions/fina_payment_create.php') {
      openFinaCollectionPage();
      return;
    }
    if (relativePath == 'modules/accounting/fina_payment_review.php' ||
        relativePath == 'modules/accounting/fina_payment_history.php') {
      openFinaReviewPage();
      return;
    }
    if (relativePath == 'modules/accounting/my_nannies.php') {
      const allowedRoles = ['admin', 'accountant', 'accountant_staff', 'financial_manager'];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Assigned-nanny overview is not available for this role.' : 'عرض الحاضنات المعيّنات غير متاح لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => AssignedNanniesPage(english: english, loadPage: loadAssignedNannies),
      ));
      return;
    }
    if (relativePath == 'modules/administration/available_families.php') {
      const allowedRoles = ['admin', 'general_manager', 'vice_general_manager', 'administration'];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Available families are not available for this role.' : 'الأسر المتاحة للكفالة غير متاحة لهذا الدور.')),
        );
        return;
      }
      openAvailableFamiliesPage();
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
    if (relativePath == 'modules/families/orphan_forms_index.php') {
      const viewRoles = ['admin','vice_general_manager','general_manager','supervisor','nanny','accountant','accountant_staff','administration','staff','social_media'];
      if (!viewRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Orphan forms are not available for this role.' : 'استمارات الأيتام غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => OrphanFormsIndexPage(
        english: english, loadForms: loadOrphanForms, openForm: openOrphanFormPage,
      )));
      return;
    }
    if (relativePath == 'modules/hr/employment_states.php') {
      if (!['hr_manager', 'admin'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Employment states are restricted to HR Manager and administrator roles.' : 'الحالة الوظيفية متاحة لمدير الموارد البشرية والمسؤول فقط.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => EmploymentStatesPage(english: english, loadPage: loadEmploymentStates),
      ));
      return;
    }
    if (relativePath == 'modules/hr/salary_advance_dashboard.php') {
      const allowedRoles = ['financial_manager', 'admin', 'fm'];
      final currentRole = user?['role_code']?.toString() ?? '';
      if (!allowedRoles.contains(currentRole)) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Salary-advance dashboard is not available for this role.' : 'لوحة السلف غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SalaryAdvanceDashboardPage(
          english: english,
          loadDashboard: loadSalaryAdvanceDashboard,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/hr/salary_portal.php') {
      const allowedRoles = ['financial_manager', 'admin', 'fm', 'hr_manager', 'hr_staff'];
      final currentRole = user?['role_code']?.toString() ?? '';
      if (!allowedRoles.contains(currentRole)) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Payroll portal is not available for this role.' : 'بوابة الرواتب غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SalaryPortalPage(
          english: english,
          roleCode: currentRole,
          onOpen: (path) => openNativeTool(path),
        ),
      ));
      return;
    }
    if (relativePath == 'modules/hr/incentive_policy.php') {
      const allowedRoles = ['hr_manager', 'general_manager', 'gm', 'financial_manager', 'fm', 'finance'];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Incentive policy is not available for this role.' : 'سياسة الحوافز غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => IncentivePolicyPage(
          english: english,
          roleCode: user?['role_code']?.toString() ?? '',
          loadPage: loadIncentivePolicy,
          submitAction: submitIncentivePolicyAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/hr/salary_advance_waiver_fm.php' ||
        relativePath == 'modules/hr/salary_advance_waiver_gm.php') {
      const fmRoles = ['admin', 'financial_manager', 'fm', 'finance'];
      const gmRoles = ['admin', 'general_manager', 'gm'];
      final currentRole = user?['role_code']?.toString() ?? '';
      final isFmPath = relativePath == 'modules/hr/salary_advance_waiver_fm.php';
      if (isFmPath ? !fmRoles.contains(currentRole) : !gmRoles.contains(currentRole)) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Salary-advance waiver access is not available for this role.' : 'إعفاء السلف غير متاح لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SalaryAdvanceWaiverPage(
          english: english,
          roleCode: currentRole,
          loadPage: loadSalaryAdvanceWaiver,
          submitAction: submitSalaryAdvanceWaiverAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/administration/winback.php') {
      const allowedRoles = ['admin', 'sudo', 'super_admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'administration'];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Sponsor win-back is not available for this role.' : 'متابعات استرجاع الكفلاء غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => SponsorWinbackPage(
          english: english,
          loadPage: loadSponsorWinback,
          submitAction: submitSponsorWinbackAction,
          openFamily: (familyId) {
            navigatorKey.currentState?.push(MaterialPageRoute<void>(
              builder: (_) => FamilyDetailPage(
                familyId: familyId,
                english: english,
                loadDetail: loadFamilyDetail,
                openOrphanForm: openOrphanFormPage,
              ),
            ));
          },
        ),
      ));
      return;
    }
    if (relativePath == 'modules/accounting/gm_reconciliation.php') {
      const allowedRoles = ['admin', 'general_manager', 'gm', 'vice_general_manager', 'vgm', 'financial_manager', 'fm', 'finance'];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Reconciliation access is not available for this role.' : 'عرض المصالحة غير متاح لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => GmReconciliationPage(english: english, loadPage: loadGmReconciliation),
      ));
      return;
    }
    if (relativePath == 'modules/hr/gm_payroll_period_history.php') {
      if (!['general_manager', 'gm'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Payroll period history is restricted to the General Manager role.' : 'سجل فترات الرواتب متاح للمدير العام فقط.')),
        );
        return;
      }
      openGmPayrollHistory();
      return;
    }
    if (relativePath == 'modules/hr/attendance_policy.php') {
      if (!['hr_manager', 'admin'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english
              ? 'Attendance policy is restricted to HR Manager and administrator roles.'
              : 'سياسة الحضور متاحة لمدير الموارد البشرية والمسؤول فقط.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => AttendancePolicyPage(
          english: english,
          loadPage: loadAttendancePolicy,
          submitAction: submitAttendancePolicyAction,
        ),
      ));
      return;
    }
    if (relativePath == 'modules/hr/incentive_verification.php') {
      if (user?['role_code']?.toString() != 'hr_manager') {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Incentive verification is restricted to HR Manager.' : 'التحقق من الحوافز متاح لمدير الموارد البشرية فقط.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => IncentiveVerificationPage(english: english, loadEvents: loadIncentiveVerification, submitAction: submitIncentiveVerificationAction),
      ));
      return;
    }
    if (relativePath == 'modules/hr/payroll_policy.php') {
      if (!['hr_manager', 'hr_staff', 'admin'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Payroll policy is not available for this role.' : 'سياسة الرواتب غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => PayrollPolicyPage(english: english, loadPage: loadPayrollPolicy, submitAction: submitPayrollPolicyAction),
      ));
      return;
    }
    if (relativePath == 'modules/hr/payroll.php') {
      if (!['hr_manager', 'hr_staff', 'financial_manager', 'admin'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Payroll management is not available for this role.' : 'إدارة الرواتب غير متاحة لهذا الدور.')),
        );
        return;
      }
      openPayrollManagement();
      return;
    }
    if (relativePath == 'modules/hr/payroll_integrity.php') {
      if (!['hr_manager', 'admin'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english
              ? 'Payroll integrity is restricted to HR Manager and administrator roles.'
              : 'فحص سلامة الرواتب متاح لمدير الموارد البشرية والمسؤول فقط.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => PayrollIntegrityPage(english: english, loadChecks: loadPayrollIntegrity),
      ));
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
    const nativeReportPaths = <String, String>{
      'modules/reports/financial.php': 'financial',
      'modules/reports/sponsorship.php': 'sponsorship',
      'modules/reports/operational.php': 'operational',
      'modules/reports/hr.php': 'hr',
    };
    final nativeReportKey = nativeReportPaths[relativePath];
    if (nativeReportKey != null) {
      openNativeReportDetail(nativeReportKey);
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
    if (relativePath == 'modules/users/password_recovery_request.php') {
      openPasswordRecoveryPage();
      return;
    }
    if (relativePath == 'modules/users/profile.php') {
      openMyProfilePage();
      return;
    }
    if (relativePath == 'modules/users/settings.php') {
      openAccountSettingsPage();
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
    if (relativePath == 'modules/sponsors/assign.php') {
      if (!['admin', 'vice_general_manager', 'general_manager'].contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english
              ? 'Sponsor assignment is restricted to administrator and management roles.'
              : 'إسناد الكفلاء متاح للمسؤول والإدارة العليا فقط.')),
        );
        return;
      }
      openSponsorAssignmentPage();
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
          openOrphanForm: (childId) => openOrphanFormPage(childId),
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
    if (relativePath == 'modules/accounting/fm_transaction_review.php') {
      const allowedRoles = ['admin', 'financial_manager', 'fm', 'finance'];
      if (!allowedRoles.contains(user?['role_code']?.toString())) {
        ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
          SnackBar(content: Text(english ? 'Financial transaction review is not available for this role.' : 'مراجعة الدفعات المالية غير متاحة لهذا الدور.')),
        );
        return;
      }
      navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => OrdinaryTransactionReviewPage(
          english: english,
          loadQueue: loadOrdinaryTransactionReview,
          submitAction: submitOrdinaryTransactionReviewAction,
          loadReceipt: loadOrdinaryTransactionReceipt,
        ),
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

  Future<Map<String, dynamic>> submitPasswordChange(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    return call('POST', 'auth/change_password.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> issueVoucher(Map<String, dynamic> input) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'accounting/vouchers.php', token: token, body: input);
  }

  Future<Map<String, dynamic>> voidVoucher(int id, String reason) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'accounting/vouchers.php', token: token, body: {'action':'void_voucher','voucher_id':id,'reason':reason});
  }

  Future<Map<String, dynamic>> loadVouchers(String type) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final suffix = type.isEmpty ? '' : '?vtype=${Uri.encodeQueryComponent(type)}';
    return call('GET', 'accounting/vouchers.php$suffix', token: token);
  }

  Future<Map<String, dynamic>> loadJournalEntries({required String from, required String to}) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final query = <String, String>{
      if (from.isNotEmpty) 'from': from,
      if (to.isNotEmpty) 'to': to,
    };
    final suffix = query.isEmpty ? '' : '?${Uri(queryParameters: query).query}';
    return call('GET', 'accounting/journal.php$suffix', token: token);
  }

  Future<Map<String, dynamic>> loadJournalEntry(int id) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'accounting/journal.php?view=$id', token: token);
  }

  Future<Map<String, dynamic>> loadJournalAccounts() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'accounting/journal.php', token: token, body: {'action': 'list_accounts'});
  }

  Future<Map<String, dynamic>> submitManualJournalEntry(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'accounting/journal.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> submitJournalVoid(int id, String reason) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'accounting/journal.php', token: token, body: {'action': 'void_manual_entry', 'entry_id': id, 'reason': reason});
  }

  void openJournalPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => JournalPage(
        english: english,
        roleCode: user?['role_code']?.toString() ?? '',
        currentUserId: int.tryParse(user?['id']?.toString() ?? '') ?? 0,
        loadEntries: loadJournalEntries,
        loadEntry: loadJournalEntry,
        loadAccounts: loadJournalAccounts,
        submitManualEntry: submitManualJournalEntry,
        submitVoid: submitJournalVoid,
      ),
    ));
  }

  Future<Map<String, dynamic>> submitPasswordRecoveryRequest(String identity) async {
    return call('POST', 'auth/password_recovery_request.php', body: {'identity': identity});
  }

  void openPasswordRecoveryPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => PasswordRecoveryPage(
        english: english,
        submitRequest: submitPasswordRecoveryRequest,
      ),
    ));
  }

  void openAccountSettingsPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AccountSettingsPage(
        english: english,
        submitPasswordChange: submitPasswordChange,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadMyProfile() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'account/profile.php', token: token);
  }

  Future<Map<String, dynamic>> saveMyProfile(Map<String, dynamic> values) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'account/profile.php', token: token, body: values);
  }

  Future<Map<String, dynamic>> loadMyAvatar() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/account/avatar.php');
    final response = await http.get(uri, headers: {'Accept': 'image/*, application/json', 'Authorization': 'Bearer ' + token}).timeout(const Duration(seconds: 20));
    if (response.statusCode < 200 || response.statusCode >= 300) {
      Map<String, dynamic> decoded = {};
      try {
        final value = jsonDecode(utf8.decode(response.bodyBytes));
        if (value is Map) decoded = Map<String, dynamic>.from(value);
      } catch (_) {}
      final failure = decoded['error'];
      final message = failure is Map ? (failure['message'] ?? 'Profile photo could not be loaded.').toString() : 'Profile photo could not be loaded.';
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    return {'bytes': response.bodyBytes, 'mime': (response.headers['content-type'] ?? '').split(';').first.toLowerCase()};
  }

  Future<PlatformFile?> pickMyAvatar() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'], withData: true);
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.single;
    if (file.bytes == null || file.bytes!.isEmpty) throw const ApiFailure('The selected profile photo could not be read.', statusCode: 422);
    if (file.size > 10 * 1024 * 1024) throw const ApiFailure('Profile photos must not exceed 10 MB.', statusCode: 413);
    return file;
  }

  Future<Map<String, dynamic>> uploadMyAvatar(PlatformFile file) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/account/avatar.php');
    final request = http.MultipartRequest('POST', uri);
    request.headers['Accept'] = 'application/json';
    request.headers['Authorization'] = 'Bearer ' + token;
    request.files.add(http.MultipartFile.fromBytes('avatar', file.bytes!, filename: file.name));
    final streamed = await request.send().timeout(const Duration(seconds: 30));
    final response = await http.Response.fromStream(streamed);
    dynamic decoded;
    try { decoded = jsonDecode(utf8.decode(response.bodyBytes)); } catch (_) { decoded = null; }
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded is! Map || decoded['ok'] != true) {
      final failure = decoded is Map ? decoded['error'] : null;
      final message = failure is Map ? (failure['message'] ?? 'Profile photo upload failed.').toString() : 'Profile photo upload failed.';
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final data = decoded['data'];
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }

  Future<Map<String, dynamic>> removeMyAvatar() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'account/avatar.php', token: token, body: {'action': 'remove_avatar'});
  }

  void openMyProfilePage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => ProfilePage(
        english: english,
        loadProfile: loadMyProfile,
        saveProfile: saveMyProfile,
        pickAvatar: pickMyAvatar,
        uploadAvatar: uploadMyAvatar,
        loadAvatar: loadMyAvatar,
        removeAvatar: removeMyAvatar,
      ),
    ));
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

  Future<Map<String, dynamic>> loadOrphanForms(String query) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final suffix = query.trim().isEmpty ? '' : '?q=' + Uri.encodeQueryComponent(query.trim());
    return call('GET', 'families/orphan_forms.php' + suffix, token: token);
  }

  Future<Map<String, dynamic>> loadEmploymentStates() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/employment_states.php', token: token);
  }

  Future<Map<String, dynamic>> loadIncentivePolicy() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/incentive_policy.php', token: token);
  }

  Future<Map<String, dynamic>> submitIncentivePolicyAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'hr/incentive_policy.php', body: body, token: token);
  }

  Future<Map<String, dynamic>> loadSalaryAdvanceDashboard() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/salary_advance_dashboard.php', token: token);
  }

  Future<Map<String, dynamic>> loadSalaryAdvanceWaiver(String effectiveMonth) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/salary_advance_waiver.php?effective_month=' + Uri.encodeQueryComponent(effectiveMonth), token: token);
  }

  Future<Map<String, dynamic>> submitSalaryAdvanceWaiverAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'hr/salary_advance_waiver.php', body: body, token: token);
  }

  Future<Map<String, dynamic>> loadSponsorWinback(int? viewId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final suffix = viewId == null ? '' : '?view_id=$viewId';
    return call('GET', 'administration/winback.php' + suffix, token: token);
  }

  Future<Map<String, dynamic>> submitSponsorWinbackAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'administration/winback.php', body: body, token: token);
  }

  Future<Map<String, dynamic>> loadGmReconciliation() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'accounting/gm_reconciliation.php', token: token);
  }

  Future<Map<String, dynamic>> loadGmPayrollHistory() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/gm_payroll_period_history.php', token: token);
  }

  Future<Map<String, dynamic>> loadGmPayrollReport(int month, int year) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/gm_payroll_period_report.php?month=$month&year=$year', token: token);
  }

  void openGmPayrollHistory() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => GmPayrollHistoryPage(
      english: english, loadHistory: loadGmPayrollHistory,
      openReport: (month, year) => navigatorKey.currentState?.push(MaterialPageRoute<void>(
        builder: (_) => GmPayrollReportPage(english: english, month: month, year: year, loadReport: loadGmPayrollReport),
      )),
    )));
  }

  Future<Map<String, dynamic>> loadIncentiveVerification() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/incentive_verification.php', token: token);
  }

  Future<Map<String, dynamic>> submitIncentiveVerificationAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'hr/incentive_verification.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> loadPayrollPolicy() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/payroll_policy.php', token: token);
  }

  Future<Map<String, dynamic>> submitPayrollPolicyAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'hr/payroll_policy.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> loadAttendancePolicy() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/attendance_policy.php', token: token);
  }

  Future<Map<String, dynamic>> submitAttendancePolicyAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'hr/attendance_policy.php', token: token, body: body);
  }

  Future<Map<String, dynamic>> loadPayrollProof(int payrollId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/hr/payroll_proof.php?payroll_id=' + payrollId.toString());
    final response = await http.get(uri, headers: {'Accept': 'application/pdf,image/*', 'Authorization': 'Bearer ' + token}).timeout(const Duration(seconds: 30));
    if (response.statusCode < 200 || response.statusCode >= 300) {
      Map<String, dynamic> decoded = {};
      try {
        final value = jsonDecode(utf8.decode(response.bodyBytes));
        if (value is Map) decoded = Map<String, dynamic>.from(value);
      } catch (_) {}
      final failure = decoded['error'];
      final message = failure is Map ? (failure['message'] ?? (english ? 'Payroll proof could not be opened.' : 'تعذر فتح إثبات الراتب.')).toString() : (english ? 'Payroll proof could not be opened.' : 'تعذر فتح إثبات الراتب.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final mime = (response.headers['content-type'] ?? '').split(';').first.toLowerCase();
    final extension = mime == 'application/pdf' ? 'pdf' : (mime == 'image/jpeg' ? 'jpg' : 'png');
    return {'bytes': response.bodyBytes, 'mime': mime, 'filename': 'payroll-proof-' + payrollId.toString() + '.' + extension};
  }

  Future<Map<String, dynamic>> loadPayrollManagement(int month, int year) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/payroll_management.php?month=' + month.toString() + '&year=' + year.toString(), token: token);
  }

  Future<Map<String, dynamic>> submitPayrollManagementAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'hr/payroll_management.php', token: token, body: body);
  }

  Future<PlatformFile?> pickPayrollProof() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'], withData: true);
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.single;
    if (file.bytes == null || file.size <= 0 || file.size > 10 * 1024 * 1024) {
      throw ApiFailure(english ? 'Choose a PDF, JPG or PNG proof no larger than 10 MB.' : 'اختر إثبات PDF أو JPG أو PNG لا يتجاوز 10 ميجابايت.');
    }
    return file;
  }

  Future<Map<String, dynamic>> uploadPayrollProof(int payrollId, int month, int year, PlatformFile proof, bool legacyEvidence) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    if (proof.bytes == null) throw const ApiFailure('Proof bytes are unavailable.');
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/hr/payroll_management.php');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Authorization'] = 'Bearer ' + token
      ..fields['action'] = legacyEvidence ? 'upload_legacy_payment_evidence' : 'upload_payment_proof'
      ..fields['payroll_id'] = payrollId.toString()
      ..fields['month'] = month.toString()
      ..fields['year'] = year.toString()
      ..files.add(http.MultipartFile.fromBytes('proof_file', proof.bytes!, filename: proof.name));
    final streamed = await request.send().timeout(const Duration(seconds: 45));
    final response = await http.Response.fromStream(streamed);
    Map<String, dynamic> decoded = {};
    try {
      final value = jsonDecode(utf8.decode(response.bodyBytes));
      if (value is Map) decoded = Map<String, dynamic>.from(value);
    } catch (_) {}
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
      final failure = decoded['error'];
      final message = failure is Map ? (failure['message'] ?? (english ? 'Payroll proof upload failed.' : 'تعذر رفع إثبات الراتب.')).toString() : (english ? 'Payroll proof upload failed.' : 'تعذر رفع إثبات الراتب.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final data = decoded['data'];
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }

  void openPayrollManagement() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => PayrollManagementPage(
      english: english,
      loadPage: loadPayrollManagement,
      submitAction: submitPayrollManagementAction,
      pickProof: pickPayrollProof,
      uploadProof: uploadPayrollProof,
      loadProof: loadPayrollProof,
    )));
  }

  Future<Map<String, dynamic>> loadPayrollIntegrity() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'hr/payroll_integrity.php', token: token);
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

  Future<Map<String, dynamic>> loadAdminDatabase(String? table) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final query = table == null || table.isEmpty ? '' : '?table=' + Uri.encodeQueryComponent(table);
    return call('GET', 'admin/database.php' + query, token: token);
  }

  void openAdminDatabase() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminDatabasePage(english: english, loadPage: loadAdminDatabase),
    ));
  }

  Future<Map<String, dynamic>> loadAdminBackups() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'admin/backups.php', token: token);
  }

  Future<Map<String, dynamic>> createAdminBackup() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'admin/backups.php', token: token, body: {'action': 'create'});
  }

  Future<Map<String, dynamic>> deleteAdminBackup(String filename) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'admin/backups.php', token: token, body: {'action': 'delete', 'filename': filename});
  }

  Future<bool> downloadAdminBackup(String filename) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    try {
      final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/admin/backups.php?download=' + Uri.encodeQueryComponent(filename));
      final response = await http.get(uri, headers: {'Accept': 'application/sql', 'Authorization': 'Bearer ' + token}).timeout(const Duration(seconds: 60));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        String message = english ? 'Backup download failed.' : 'تعذر تنزيل النسخة الاحتياطية.';
        try {
          final decoded = jsonDecode(utf8.decode(response.bodyBytes));
          if (decoded is Map && decoded['error'] is Map) message = (decoded['error']['message'] ?? message).toString();
        } catch (_) {}
        throw ApiFailure(message, statusCode: response.statusCode);
      }
      final savedPath = await FilePicker.platform.saveFile(
        dialogTitle: english ? 'Save database backup' : 'حفظ النسخة الاحتياطية',
        fileName: filename,
        bytes: response.bodyBytes,
      );
      return savedPath != null && savedPath.isNotEmpty;
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw ApiFailure(english ? 'Could not save the backup file.' : 'تعذر حفظ ملف النسخة الاحتياطية.');
    }
  }

  void openAdminBackupPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => AdminBackupPage(
      english: english,
      loadBackups: loadAdminBackups,
      createBackup: createAdminBackup,
      deleteBackup: deleteAdminBackup,
      downloadBackup: downloadAdminBackup,
    )));
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

  Future<Map<String, dynamic>> loadEligibleDisbursementGroups(String month) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final query = Uri(queryParameters: {'month': month, 'status': 'all', 'page': '1'}).query;
    return call('GET', 'admin/disbursements.php?$query', token: token);
  }

  Future<Map<String, dynamic>> createDisbursementBatches(String month, List<int> groupIds) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'admin/disbursements_action.php', token: token, body: {
      'action': 'create_batch',
      'month': month,
      'group_ids': groupIds,
    });
  }

  Future<PlatformFile?> pickNannyDisbursementReceipt() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'gif'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.single;
    if (file.bytes == null || file.size <= 0 || file.size > 10 * 1024 * 1024) {
      throw ApiFailure(english
          ? 'Choose a JPG, PNG or GIF receipt no larger than 10 MB.'
          : 'اختر إيصال JPG أو PNG أو GIF لا يتجاوز 10 ميجابايت.');
    }
    return file;
  }

  Future<Map<String, dynamic>> submitNannyDisbursementConfirmation(int itemId, PlatformFile receipt) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    if (receipt.bytes == null) throw const ApiFailure('Receipt bytes are unavailable.');
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/admin/disbursements_action.php');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Authorization'] = 'Bearer ' + token
      ..fields['action'] = 'confirm_item'
      ..fields['item_id'] = itemId.toString()
      ..files.add(http.MultipartFile.fromBytes('receipt_file', receipt.bytes!, filename: receipt.name));
    final streamed = await request.send().timeout(const Duration(seconds: 45));
    final response = await http.Response.fromStream(streamed);
    Map<String, dynamic> decoded = {};
    try {
      final value = jsonDecode(utf8.decode(response.bodyBytes));
      if (value is Map) decoded = Map<String, dynamic>.from(value);
    } catch (_) {}
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
      final error = decoded['error'];
      final message = error is Map
          ? (error['message'] ?? (english ? 'Receipt confirmation failed.' : 'تعذر تأكيد الإيصال.')).toString()
          : (english ? 'Receipt confirmation failed.' : 'تعذر تأكيد الإيصال.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final data = decoded['data'];
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }

  Future<PlatformFile?> pickDisbursementReturnReceipt() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'gif'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.single;
    if (file.bytes == null || file.size <= 0 || file.size > 10 * 1024 * 1024) {
      throw ApiFailure(english
          ? 'Choose a PDF, JPG, PNG or GIF return receipt no larger than 10 MB.'
          : 'اختر إيصال إرجاع PDF أو JPG أو PNG أو GIF لا يتجاوز 10 ميجابايت.');
    }
    return file;
  }

  Future<Map<String, dynamic>> submitNannyDisbursementReturn(
    int batchId,
    String reasonType,
    String reasonDetails,
    PlatformFile receipt,
  ) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    if (receipt.bytes == null) throw const ApiFailure('Receipt bytes are unavailable.');
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/admin/disbursements_action.php');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Authorization'] = 'Bearer ' + token
      ..fields['action'] = 'close_with_return'
      ..fields['batch_id'] = batchId.toString()
      ..fields['return_reason_type'] = reasonType
      ..fields['return_reason_details'] = reasonDetails
      ..files.add(http.MultipartFile.fromBytes('return_receipt', receipt.bytes!, filename: receipt.name));
    final streamed = await request.send().timeout(const Duration(seconds: 45));
    final response = await http.Response.fromStream(streamed);
    Map<String, dynamic> decoded = {};
    try {
      final value = jsonDecode(utf8.decode(response.bodyBytes));
      if (value is Map) decoded = Map<String, dynamic>.from(value);
    } catch (_) {}
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
      final error = decoded['error'];
      final message = error is Map
          ? (error['message'] ?? (english ? 'Return closure failed.' : 'تعذر إقفال الدفعة وإرجاع المبلغ.')).toString()
          : (english ? 'Return closure failed.' : 'تعذر إقفال الدفعة وإرجاع المبلغ.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final data = decoded['data'];
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }

  Future<PlatformFile?> pickDisbursementTransferReceipt() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.single;
    if (file.bytes == null || file.size <= 0 || file.size > 10 * 1024 * 1024) {
      throw ApiFailure(english
          ? 'Choose a PDF, JPG or PNG transfer receipt no larger than 10 MB.'
          : 'اختر إيصال تحويل PDF أو JPG أو PNG لا يتجاوز 10 ميجابايت.');
    }
    return file;
  }

  Future<Map<String, dynamic>> submitDisbursementTransfer(int batchId, PlatformFile receipt) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    if (receipt.bytes == null) throw const ApiFailure('Receipt bytes are unavailable.');
    final uri = Uri.parse(apiBaseUrl.replaceFirst(RegExp(r'/+$'), '') + '/admin/disbursements_action.php');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Authorization'] = 'Bearer ' + token
      ..fields['action'] = 'transfer_with_receipt'
      ..fields['batch_id'] = batchId.toString()
      ..files.add(http.MultipartFile.fromBytes('transfer_receipt', receipt.bytes!, filename: receipt.name));
    final streamed = await request.send().timeout(const Duration(seconds: 45));
    final response = await http.Response.fromStream(streamed);
    Map<String, dynamic> decoded = {};
    try {
      final value = jsonDecode(utf8.decode(response.bodyBytes));
      if (value is Map) decoded = Map<String, dynamic>.from(value);
    } catch (_) {}
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['ok'] != true) {
      final error = decoded['error'];
      final message = error is Map
          ? (error['message'] ?? (english ? 'Transfer failed.' : 'تعذر تنفيذ التحويل.')).toString()
          : (english ? 'Transfer failed.' : 'تعذر تنفيذ التحويل.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final data = decoded['data'];
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }

  void openAdminDisbursements({String initialQuery = ''}) {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AdminDisbursementsPage(
        english: english,
        initialQuery: initialQuery,
        roleCode: user?['role_code']?.toString() ?? '',
        loadPage: loadAdminDisbursements,
        loadDetail: loadAdminDisbursementDetail,
        pickReceipt: pickNannyDisbursementReceipt,
        submitItemConfirmation: submitNannyDisbursementConfirmation,
        loadEligibleGroups: loadEligibleDisbursementGroups,
        createBatches: createDisbursementBatches,
        pickTransferReceipt: pickDisbursementTransferReceipt,
        submitTransfer: submitDisbursementTransfer,
        pickReturnReceipt: pickDisbursementReturnReceipt,
        submitReturn: submitNannyDisbursementReturn,
      ),
    ));
  }

  Future<Map<String, dynamic>> loadFinaDashboard() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'fina/dashboard.php', token: token);
  }

  void openFinaDashboard() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(builder: (_) => FinaDashboardPage(
      english: english,
      loadDashboard: loadFinaDashboard,
      onOpenReview: () => openNativeTool('modules/accounting/fina_payment_review.php'),
    )));
  }

  Future<Map<String, dynamic>> loadAssignedNannies(String month, {int? nannyId}) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final parameters = <String, String>{'vmonth': month};
    if (nannyId != null) parameters['nanny'] = nannyId.toString();
    return call('GET', 'accounting/assigned_nannies.php?${Uri(queryParameters: parameters).query}', token: token);
  }

  Future<Map<String, dynamic>> loadFinaReport(String from, String to) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final query = Uri(queryParameters: {'from': from, 'to': to}).query;
    return call('GET', 'fina/report.php?$query', token: token);
  }

  void openFinaReportPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => FinaReportPage(
        english: english,
        loadReport: loadFinaReport,
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
        loadReceipt: loadFinaReceipt,
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
    if (!['admin', 'financial_manager', 'fm'].contains(role)) {
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

  Future<Map<String, dynamic>> loadNativeReportDetail(String report, String type, String from, String to) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final query = Uri(queryParameters: {'report': report, 'type': type, 'from': from, 'to': to}).query;
    return call('GET', 'reports/detail.php?' + query, token: token);
  }

  void openNativeReportDetail(String report) {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => NativeReportDetailPage(reportKey: report, english: english, loadReport: loadNativeReportDetail),
    ));
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
          if (key == 'orphaned') {
            openOrphanedFamiliesReport();
          } else if (['financial', 'sponsorship', 'operational', 'hr'].contains(key)) {
            openNativeReportDetail(key);
          } else {
            ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
              SnackBar(content: Text(english ? 'This report is not available natively yet.' : 'هذا التقرير غير متاح داخل التطبيق حتى الآن.')),
            );
          }
        },
      ),
    ));
  }

  void openAvailableFamiliesPage() {
    navigatorKey.currentState?.push(MaterialPageRoute<void>(
      builder: (_) => AvailableFamiliesPage(
        english: english,
        loadFamilies: loadAvailableFamilies,
        openFamily: (familyId) {
          navigatorKey.currentState?.push(MaterialPageRoute<void>(
            builder: (_) => FamilyDetailPage(
              familyId: familyId,
              english: english,
              loadDetail: loadFamilyDetail,
              openOrphanForm: (childId) async { await openOrphanFormPage(childId); },
            ),
          ));
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
                openOrphanFormPage(childId);
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

  Future<PlatformFile?> pickAdminLogo() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.single;
    if (file.bytes == null || file.bytes!.isEmpty) {
      throw const ApiFailure('The selected logo image could not be read.', statusCode: 422);
    }
    if (file.size > 2 * 1024 * 1024) {
      throw const ApiFailure('Logo images must not exceed 2 MB.', statusCode: 422);
    }
    return file;
  }

  Future<Map<String, dynamic>> uploadAdminLogo(PlatformFile file) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final request = http.MultipartRequest('POST', Uri.parse(baseUrl + '/admin/settings.php'));
    request.headers['Accept'] = 'application/json';
    request.headers['Authorization'] = 'Bearer ' + token;
    request.files.add(http.MultipartFile.fromBytes('site_logo', file.bytes!, filename: file.name));
    final streamed = await request.send().timeout(const Duration(seconds: 30));
    final response = await http.Response.fromStream(streamed);
    dynamic decoded;
    try { decoded = jsonDecode(response.body); } catch (_) { decoded = null; }
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded is! Map || decoded['ok'] != true) {
      final error = decoded is Map ? decoded['error'] : null;
      final message = error is Map ? (error['message'] ?? 'Logo upload failed.').toString() : 'Logo upload failed.';
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final data = decoded['data'];
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
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
        pickLogo: pickAdminLogo,
        uploadLogo: uploadAdminLogo,
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
        navigator.push(MaterialPageRoute<void>(builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openOrphanFormPage(childId))));
        return;
      case 'sponsorships':
        navigator.push(MaterialPageRoute<void>(builder: (_) => SponsorshipsPage(english: english, loadSponsorships: loadSponsorships, loadDetail: loadSponsorshipDetail)));
        return;
      case 'transactions':
        navigator.push(MaterialPageRoute<void>(builder: (_) => TransactionsPage(english: english, loadTransactions: loadTransactions)));
        return;
      case 'payroll':
        if (!['admin', 'hr_manager', 'hr_staff', 'financial_manager'].contains(user?['role_code']?.toString())) {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english ? 'Payroll management is not available for this role.' : 'إدارة الرواتب غير متاحة لهذا الدور.')),
          );
          return;
        }
        openPayrollManagement();
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
        if (!['admin', 'financial_manager', 'general_manager', 'accountant_staff', 'nanny']
            .contains(user?['role_code']?.toString())) {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(
            SnackBar(content: Text(english
                ? 'Monthly disbursements are not available for this role.'
                : 'التحويلات الشهرية غير متاحة لهذا الدور.')),
          );
          return;
        }
        openAdminDisbursements();
        return;
      case 'fina_dashboard':
      case 'modules/accounting/fina_dashboard.php':
        openFinaDashboard();
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
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(SnackBar(content: Text(english ? 'Database backup is restricted to the administrator role.' : 'النسخ الاحتياطي متاح لدور المسؤول فقط.')));
          return;
        }
        openAdminBackupPage();
        return;
      case 'database':
        if (user?['role_code']?.toString() != 'admin') {
          ScaffoldMessenger.of(navigatorKey.currentContext!).showSnackBar(SnackBar(content: Text(english ? 'Database browser is restricted to the administrator role.' : 'متصفح قاعدة البيانات متاح لدور المسؤول فقط.')));
          return;
        }
        openAdminDatabase();
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

  Future<Map<String, dynamic>> loadSuspensionOptions(String query) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final encoded = Uri.encodeQueryComponent(query);
    return call('GET', 'suspensions/index.php?options=1&query=$encoded', token: token);
  }

  Future<Map<String, dynamic>> createSuspensionCase(Map<String, dynamic> values) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'suspensions/index.php', token: token, body: values);
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
        loadOptions: loadSuspensionOptions,
        createCase: createSuspensionCase,
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
                          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF5B21B6))),
                        Text(english ? '${ageAlertItems.length} children need review' : '${ageAlertItems.length} طفل يحتاج إلى المراجعة',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF756B84))),
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
                                Text(item['child_name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF5B21B6))),
                                const SizedBox(height: 3),
                                Text('${item['mother_name'] ?? ''} • ${item['family_code'] ?? ''}', style: const TextStyle(fontSize: 12, color: Color(0xFF756B84))),
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
                                          openOrphanForm: (childId) => openOrphanFormPage(childId),
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

  Future<Map<String, dynamic>> loadOrphanGroups(int? groupId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    final suffix = groupId == null ? '' : '?group_id=' + groupId.toString();
    return call('GET', 'deputy_gm/groups.php' + suffix, token: token);
  }

  Future<Map<String, dynamic>> submitOrphanGroupAction(Map<String, dynamic> body) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'deputy_gm/groups.php', body: body, token: token);
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

  Future<Map<String, dynamic>> loadOrdinaryTransactionReceipt(int transactionId, String kind) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) {
      throw const ApiFailure('Authentication is required.', statusCode: 401);
    }
    final base = apiBaseUrl.replaceFirst(RegExp(r'/+$'), '');
    final uri = Uri.parse('$base/accounting/fm_transaction_receipt.php').replace(queryParameters: {
      'id': transactionId.toString(),
      'kind': kind,
    });
    final response = await http.get(uri, headers: {
      'Accept': 'application/pdf,image/*,application/octet-stream',
      'Authorization': 'Bearer $token',
    }).timeout(const Duration(seconds: 30));
    if (response.statusCode < 200 || response.statusCode >= 300) {
      Map<String, dynamic> decoded = {};
      try {
        final value = jsonDecode(utf8.decode(response.bodyBytes));
        if (value is Map) decoded = Map<String, dynamic>.from(value);
      } catch (_) {}
      final failure = decoded['error'];
      final message = failure is Map
          ? (failure['message'] ?? (english ? 'The receipt could not be opened.' : 'تعذر فتح الإيصال.')).toString()
          : (english ? 'The receipt could not be opened.' : 'تعذر فتح الإيصال.');
      throw ApiFailure(message, statusCode: response.statusCode);
    }
    final mime = (response.headers['content-type'] ?? 'application/octet-stream').split(';').first.toLowerCase();
    final disposition = response.headers['content-disposition'] ?? '';
    final match = RegExp(r'filename="([^"]+)"').firstMatch(disposition);
    return {
      'bytes': response.bodyBytes,
      'mime': mime,
      'filename': match?.group(1) ?? 'transaction-receipt-$transactionId',
    };
  }


  Future<Map<String, dynamic>> loadOrdinaryTransactionReview() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'accounting/fm_transaction_review.php', token: token);
  }

  Future<void> submitOrdinaryTransactionReviewAction(int transactionId, String action, String returnReason) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    await call('POST', 'accounting/fm_transaction_review.php', token: token, body: {
      'transaction_id': transactionId,
      'action': action,
      'return_reason': returnReason,
    });
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

  Future<Map<String, dynamic>> loadOrphanForm(int childId) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'families/orphan_form.php?child_id=' + childId.toString(), token: token);
  }

  Future<Map<String, dynamic>> saveOrphanForm(int childId, Map<String, dynamic> values) async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('POST', 'families/orphan_form.php?child_id=' + childId.toString(), token: token, body: {'values': values});
  }

  Future<void> openOrphanFormPage(int childId) async {
    final navigator = navigatorKey.currentState;
    if (navigator == null) return;
    await navigator.push<void>(MaterialPageRoute<void>(
      builder: (_) => OrphanFormPage(childId: childId, english: english, loadForm: loadOrphanForm, saveForm: saveOrphanForm),
    ));
  }

  Future<Map<String, dynamic>> loadAvailableFamilies() async {
    final token = await storage.read(key: tokenKey);
    if (token == null || token.isEmpty) throw const ApiFailure('Authentication is required.', statusCode: 401);
    return call('GET', 'administration/available_families.php', token: token);
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
        seedColor: const Color(0xFF7C3AED),
        primary: const Color(0xFF5B21B6),
        secondary: const Color(0xFFF59E0B),
        tertiary: const Color(0xFFDB2777),
        surface: Colors.white,
        error: const Color(0xFFB42318),
      ),
      scaffoldBackgroundColor: const Color(0xFFF8F5FF),
      visualDensity: VisualDensity.standard,
      dividerTheme: const DividerThemeData(
        color: Color(0xFFE8E0F4),
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
          side: const BorderSide(color: Color(0xFFE8E0F4)),
        ),
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        minVerticalPadding: 14,
        horizontalTitleGap: 14,
        iconColor: const Color(0xFF7C3AED),
        textColor: const Color(0xFF251B35),
        titleTextStyle: const TextStyle(
          color: Color(0xFF251B35),
          fontFamily: 'Cairo',
          fontSize: 14,
          fontWeight: FontWeight.w800,
        ),
        subtitleTextStyle: const TextStyle(
          color: Color(0xFF756B84),
          fontFamily: 'Cairo',
          fontSize: 12,
          height: 1.45,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: const Color(0xFF5B21B6),
          backgroundColor: const Color(0xFFEDE9FE),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          minimumSize: const Size(44, 44),
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: Color(0xFF251B35),
        elevation: 0,
        scrolledUnderElevation: 0,
        toolbarHeight: 56,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: const Color(0xFFF8FAFC),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFCBD5E1), width: 1.2)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFC9B9E8), width: 1.5)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF7C3AED), width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: const Color(0xFF5B21B6), foregroundColor: Colors.white, minimumSize: const Size.fromHeight(52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))),
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
              buildRevision: mobileBuildRevision,
              onForgotPassword: () async => openPasswordRecoveryPage(),
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
                            tooltip: english ? 'Global search' : 'البحث الشامل',
                            onPressed: openGlobalSearch,
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
                                            backgroundColor: Color(0xFFE8E0F4),
                                            child: Icon(Icons.person_rounded, color: Color(0xFF5B21B6)),
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
                                          subtitle: Text(english ? 'Edit personal details and profile photo' : 'تعديل البيانات الشخصية والصورة'),
                                          trailing: const Icon(Icons.chevron_right_rounded),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openMyProfilePage();
                                          },
                                        ),
                                        ListTile(
                                          leading: const Icon(Icons.settings_outlined),
                                          title: Text(english ? 'Account settings' : 'إعدادات الحساب'),
                                          subtitle: Text(english ? 'Change your password securely' : 'تغيير كلمة المرور بأمان'),
                                          trailing: const Icon(Icons.chevron_right_rounded, size: 18),
                                          onTap: () {
                                            Navigator.pop(sheetContext);
                                            openAccountSettingsPage();
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
                  'admin' || 'sudo' || 'super_admin' => const [Color(0xFF25103F), Color(0xFFF59E0B)],
                  'general_manager' || 'gm' => const [Color(0xFF5B21B6), Color(0xFFDB2777)],
                  'vice_general_manager' || 'vgm' => const [Color(0xFF5B21B6), Color(0xFFF59E0B)],
                  'financial_manager' || 'fm' || 'finance' || 'accountant' || 'accountant_staff' => const [Color(0xFF25103F), Color(0xFFDB2777)],
                  'supervisor' || 'nanny' => const [Color(0xFF7C3AED), Color(0xFFF59E0B)],
                  'hr_manager' || 'hr_staff' => const [Color(0xFF5B21B6), Color(0xFFF59E0B)],
                  'projects_manager' || 'project_supervisor' => const [Color(0xFF7C3AED), Color(0xFFF59E0B)],
                  _ => const [Color(0xFF25103F), Color(0xFF5B21B6)],
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
                          color: const Color(0xFF5B21B6).withValues(alpha: 0.14),
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
                    onOpenTool: (path) => openNativeTool(path),
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
                      leading: const CircleAvatar(backgroundColor: Color(0xFFEDE9FE), child: Icon(Icons.assignment_ind_rounded, color: Color(0xFF7C3AED))),
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
                        builder: (_) => FamiliesPage(english: english, loadFamilies: loadFamilies, loadDetail: loadFamilyDetail, openOrphanForm: (childId) => openOrphanFormPage(childId)),
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
                              backgroundColor: Color(0xFFEDE9FE),
                              child: Icon(Icons.event_available_rounded, color: Color(0xFF7C3AED)),
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
                              backgroundColor: Color(0xFFEDE9FE),
                              child: Icon(Icons.receipt_long_rounded, color: Color(0xFF5B21B6)),
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
                    color: const Color(0xFF5B21B6),
                    fontWeight: FontWeight.w800,
                  ),
            ),
            if (subtitle.isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: const Color(0xFF756B84),
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
    final isRead = item['is_read'] == true || item['is_read'] == 1;
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    final Future<String?>? markReadResult =
        !isRead && id > 0 ? _tryMarkNotificationRead(id) : null;

    await Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => NotificationDetailPage(
        english: widget.english,
        item: item,
      ),
    ));

    if (!mounted || markReadResult == null) return;
    final markReadError = await markReadResult;
    await load();
    if (markReadError != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(markReadError)),
      );
    }
  }

  Future<String?> _tryMarkNotificationRead(int notificationId) async {
    try {
      await widget.markRead(notificationId);
      return null;
    } catch (e) {
      return e.toString();
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
                                          : const Color(0xFF7C3AED),
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
          border: Border.all(color: const Color(0xFFE8E0F4)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF5B21B6).withValues(alpha: 0.08),
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
              color: Color(0xFF7C3AED),
            ),
          ),
        ),
      );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.english, required this.toggle, required this.submit, required this.buildRevision, required this.onForgotPassword});
  final bool english;
  final VoidCallback toggle;
  final Future<void> Function(String, String) submit;
  final String buildRevision;
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
    const navy950 = Color(0xFF25103F);
    const navy900 = Color(0xFF5B21B6);
    const navy600 = Color(0xFF7C3AED);
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
                                'Build: ${widget.buildRevision.length > 12 ? widget.buildRevision.substring(0, 12) : widget.buildRevision}',
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Color(0xFF756B84), fontSize: 10, letterSpacing: 0.2),
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
                colors: [Color(0xFF5B21B6), Color(0xFF7C3AED)],
              ),
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Icon(Icons.notifications_active_rounded, size: 36, color: Colors.white),
          ),
          const SizedBox(height: 18),
          Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: const Color(0xFF5B21B6), fontWeight: FontWeight.w800,
          )),
          if (createdAt.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(createdAt, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF756B84))),
          ],
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: SelectableText(body, style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.6)),
            ),
          ),
        ],
      ),
    );
  }
}


class _RequiredPasswordChangeDialog extends StatefulWidget {
  const _RequiredPasswordChangeDialog({required this.english, required this.onSubmit});
  final bool english;
  final Future<String> Function(String newPassword, String confirmation) onSubmit;

  @override
  State<_RequiredPasswordChangeDialog> createState() => _RequiredPasswordChangeDialogState();
}

class _RequiredPasswordChangeDialogState extends State<_RequiredPasswordChangeDialog> {
  final formKey = GlobalKey<FormState>();
  final newPassword = TextEditingController();
  final confirmation = TextEditingController();
  bool busy = false, showNew = false, showConfirmation = false;
  String? error;
  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void dispose() {
    newPassword.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;
    setState(() { busy = true; error = null; });
    try {
      final value = await widget.onSubmit(newPassword.text, confirmation.text);
      if (mounted) Navigator.of(context).pop(value);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget field(TextEditingController controller, String label, bool visible, VoidCallback toggle, String? Function(String?) validator) {
    return TextFormField(
      controller: controller,
      obscureText: !visible,
      autocorrect: false,
      enableSuggestions: false,
      validator: validator,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.lock_outline_rounded),
        suffixIcon: IconButton(
          onPressed: toggle,
          icon: Icon(visible ? Icons.visibility_off_rounded : Icons.visibility_rounded),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(t('يجب تغيير كلمة المرور', 'Password change required')),
    content: SizedBox(
      width: 420,
      child: Form(
        key: formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(t(
              'يتطلب هذا الحساب تغيير كلمة المرور قبل المتابعة. يمكنك إتمام ذلك داخل التطبيق.',
              'This account must change its password before continuing. You can complete the change here in the app.',
            )),
            const SizedBox(height: 16),
            field(newPassword, t('كلمة المرور الجديدة', 'New password'), showNew,
              () => setState(() => showNew = !showNew), (v) {
                if ((v ?? '').length < 6) return t('استخدم 6 أحرف على الأقل.', 'Use at least 6 characters.');
                return null;
              }),
            const SizedBox(height: 12),
            field(confirmation, t('تأكيد كلمة المرور الجديدة', 'Confirm new password'), showConfirmation,
              () => setState(() => showConfirmation = !showConfirmation), (v) {
                if (v != newPassword.text) return t('كلمتا المرور غير متطابقتين.', 'The passwords do not match.');
                return null;
              }),
            if (error != null) ...[
              const SizedBox(height: 12),
              Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: busy ? null : () => Navigator.of(context).pop(),
        child: Text(t('إلغاء', 'Cancel')),
      ),
      FilledButton(
        onPressed: busy ? null : submit,
        child: busy
            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
            : Text(t('تغيير والمتابعة', 'Change and continue')),
      ),
    ],
  );
}
