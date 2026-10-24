import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

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
      defaultValue: 'http://10.0.2.2:8081/AhlElKheir/api/v1');
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

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: english ? 'Ahl El Kheir' : 'أهل الخير',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF176B55))),
    home: Directionality(
      textDirection: english ? TextDirection.ltr : TextDirection.rtl,
      child: loading
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : user == null
          ? LoginPage(english: english,
              toggle: () => setState(() => english = !english), submit: login)
          : Scaffold(
              appBar: AppBar(title: Text(english ? 'Ahl El Kheir' : 'أهل الخير'),
                actions: [TextButton(onPressed: () => setState(() => english = !english),
                  child: Text(english ? 'العربية' : 'English'))]),
              body: Center(child: Padding(padding: const EdgeInsets.all(24),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.volunteer_activism, size: 64),
                  Text(english ? 'Signed in successfully' : 'تم تسجيل الدخول بنجاح',
                    style: Theme.of(context).textTheme.headlineSmall),
                  Text(user?['full_name']?.toString() ?? ''),
                  Text('${english ? 'Role' : 'الدور'}: ${user?['role_code'] ?? ''}'),
                  const SizedBox(height: 24),
                  const Text('Business modules require server-side authorization.'),
                  FilledButton(onPressed: logout,
                    child: Text(english ? 'Sign out' : 'تسجيل الخروج')),
                ]))))
    ));
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
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Ahl El Kheir' : 'أهل الخير'),
        actions: [TextButton(onPressed: widget.toggle,
          child: Text(en ? 'العربية' : 'English'))]),
      body: Center(child: SingleChildScrollView(padding: const EdgeInsets.all(24),
        child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 420),
          child: Form(key: form, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(Icons.volunteer_activism, size: 72),
              Text(en ? 'Sign in' : 'تسجيل الدخول', textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium),
              const SizedBox(height: 24),
              TextFormField(controller: username, autocorrect: false,
                decoration: InputDecoration(labelText: en ? 'Username' : 'اسم المستخدم'),
                validator: (v) => v == null || v.trim().isEmpty
                  ? (en ? 'Enter username' : 'أدخل اسم المستخدم') : null),
              const SizedBox(height: 16),
              TextFormField(controller: password, obscureText: true,
                onFieldSubmitted: (_) { if (!busy) signIn(); },
                decoration: InputDecoration(labelText: en ? 'Password' : 'كلمة المرور'),
                validator: (v) => v == null || v.isEmpty
                  ? (en ? 'Enter password' : 'أدخل كلمة المرور') : null),
              if (error != null) Padding(padding: const EdgeInsets.all(12),
                child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
              const SizedBox(height: 16),
              FilledButton(onPressed: busy ? null : signIn,
                child: busy ? const CircularProgressIndicator() : Text(en ? 'Sign in' : 'دخول')),
              const SizedBox(height: 12),
              Text(en ? 'Do not use real accounts until API runtime and security checks pass.'
                : 'لا تستخدم حسابات حقيقية قبل اجتياز اختبارات API والأمان.',
                textAlign: TextAlign.center),
            ]))))));
  }
}
