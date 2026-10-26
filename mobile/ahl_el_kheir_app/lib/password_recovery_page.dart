import 'package:flutter/material.dart';

class PasswordRecoveryPage extends StatefulWidget {
  const PasswordRecoveryPage({
    super.key,
    required this.english,
    required this.submitRequest,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(String identity) submitRequest;

  @override
  State<PasswordRecoveryPage> createState() => _PasswordRecoveryPageState();
}

class _PasswordRecoveryPageState extends State<PasswordRecoveryPage> {
  final formKey = GlobalKey<FormState>();
  final identity = TextEditingController();
  bool sending = false;
  String? error;
  bool received = false;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void dispose() {
    identity.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (!formKey.currentState!.validate()) return;
    setState(() { sending = true; error = null; });
    try {
      await widget.submitRequest(identity.text.trim());
      if (mounted) setState(() => received = true);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: Text(t('استعادة كلمة المرور', 'Password recovery'))),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: s.primaryContainer,
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Icon(Icons.key_rounded, color: s.primary, size: 30),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    t('طلب استعادة كلمة المرور', 'Request password recovery'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Text(t(
                    'أدخل اسم المستخدم أو البريد الإلكتروني المسجل. سيصل الطلب إلى الموارد البشرية ومسؤول النظام للمراجعة.',
                    'Enter the username or registered email address. The request will be sent to HR and system administrators for review.',
                  )),
                  if (received) ...[
                    const SizedBox(height: 18),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: s.primary.withValues(alpha: .08),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: s.primary.withValues(alpha: .22)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.check_circle_outline_rounded, color: s.primary),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(t(
                              'تم استلام الطلب. إذا كان الحساب موجوداً ونشطاً، فستراجعه الموارد البشرية أو مسؤول النظام ويتواصلون معك.',
                              'The request was received. If the account exists and is active, HR or a system administrator will review it and contact you.',
                            )),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    OutlinedButton.icon(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.arrow_back_rounded),
                      label: Text(t('العودة لتسجيل الدخول', 'Back to sign in')),
                    ),
                  ] else ...[
                    const SizedBox(height: 20),
                    Form(
                      key: formKey,
                      child: TextFormField(
                        controller: identity,
                        autocorrect: false,
                        textInputAction: TextInputAction.done,
                        onFieldSubmitted: (_) { if (!sending) send(); },
                        maxLength: 190,
                        decoration: InputDecoration(
                          labelText: t('اسم المستخدم أو البريد الإلكتروني', 'Username or registered email'),
                          prefixIcon: const Icon(Icons.person_search_outlined),
                        ),
                        validator: (value) {
                          final v = (value ?? '').trim();
                          if (v.isEmpty) return t('أدخل اسم المستخدم أو البريد الإلكتروني.', 'Enter a username or email address.');
                          if (v.length > 190) return t('القيمة طويلة جداً.', 'The value is too long.');
                          return null;
                        },
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: s.error.withValues(alpha: .08),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: s.error.withValues(alpha: .22)),
                        ),
                        child: Text(error!, style: TextStyle(color: s.error)),
                      ),
                    ],
                    const SizedBox(height: 8),
                    FilledButton.icon(
                      onPressed: sending ? null : send,
                      icon: sending
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.send_rounded),
                      label: Text(t('إرسال طلب الاستعادة', 'Send recovery request')),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      t('لأمان الحساب، تظهر الرسالة نفسها سواء كان الحساب موجوداً أم لا.', 'For account security, the confirmation is the same whether or not the account exists.'),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
