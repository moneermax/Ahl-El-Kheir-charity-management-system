import 'package:flutter/material.dart';

class AccountSettingsPage extends StatefulWidget {
  const AccountSettingsPage({
    super.key,
    required this.english,
    required this.submitPasswordChange,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> body) submitPasswordChange;

  @override
  State<AccountSettingsPage> createState() => _AccountSettingsPageState();
}

class _AccountSettingsPageState extends State<AccountSettingsPage> {
  final formKey = GlobalKey<FormState>();
  final currentPassword = TextEditingController();
  final newPassword = TextEditingController();
  final confirmPassword = TextEditingController();
  bool saving = false;
  bool showCurrent = false, showNew = false, showConfirm = false;
  String? error, notice;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void dispose() {
    currentPassword.dispose();
    newPassword.dispose();
    confirmPassword.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    setState(() { saving = true; error = null; notice = null; });
    try {
      final result = await widget.submitPasswordChange({
        'current_password': currentPassword.text,
        'new_password': newPassword.text,
        'confirm_password': confirmPassword.text,
      });
      if (!mounted) return;
      setState(() {
        notice = result['message']?.toString() ?? t('تم تغيير كلمة المرور بنجاح.', 'Password changed successfully.');
        currentPassword.clear();
        newPassword.clear();
        confirmPassword.clear();
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget passwordField({
    required TextEditingController controller,
    required String label,
    required bool visible,
    required VoidCallback toggle,
    String? Function(String?)? validator,
  }) => TextFormField(
    controller: controller,
    obscureText: !visible,
    autocorrect: false,
    enableSuggestions: false,
    textInputAction: TextInputAction.next,
    validator: validator,
    decoration: InputDecoration(
      labelText: label,
      prefixIcon: const Icon(Icons.lock_outline_rounded),
      suffixIcon: IconButton(
        tooltip: visible ? t('إخفاء كلمة المرور', 'Hide password') : t('إظهار كلمة المرور', 'Show password'),
        onPressed: toggle,
        icon: Icon(visible ? Icons.visibility_off_rounded : Icons.visibility_rounded),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: Text(t('إعدادات الحساب', 'Account settings'))),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    width: 54,
                    height: 54,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: s.primaryContainer,
                      borderRadius: BorderRadius.circular(17),
                    ),
                    child: Icon(Icons.password_rounded, color: s.primary, size: 28),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    t('تغيير كلمة المرور', 'Change password'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    t(
                      'أدخل كلمة المرور الحالية ثم كلمة المرور الجديدة. يجب ألا تقل الجديدة عن 6 أحرف.',
                      'Enter your current password and a new password. The new password must be at least 6 characters.',
                    ),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 18),
                  Form(
                    key: formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        passwordField(
                          controller: currentPassword,
                          label: t('كلمة المرور الحالية', 'Current password'),
                          visible: showCurrent,
                          toggle: () => setState(() => showCurrent = !showCurrent),
                          validator: (v) => (v ?? '').isEmpty ? t('أدخل كلمة المرور الحالية.', 'Enter your current password.') : null,
                        ),
                        const SizedBox(height: 14),
                        passwordField(
                          controller: newPassword,
                          label: t('كلمة المرور الجديدة', 'New password'),
                          visible: showNew,
                          toggle: () => setState(() => showNew = !showNew),
                          validator: (v) {
                            if ((v ?? '').isEmpty) return t('أدخل كلمة المرور الجديدة.', 'Enter a new password.');
                            if (v!.length < 6) return t('يجب ألا تقل كلمة المرور عن 6 أحرف.', 'Use at least 6 characters.');
                            return null;
                          },
                        ),
                        const SizedBox(height: 14),
                        passwordField(
                          controller: confirmPassword,
                          label: t('تأكيد كلمة المرور الجديدة', 'Confirm new password'),
                          visible: showConfirm,
                          toggle: () => setState(() => showConfirm = !showConfirm),
                          validator: (v) => v != newPassword.text ? t('كلمتا المرور غير متطابقتين.', 'The passwords do not match.') : null,
                        ),
                      ],
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 14),
                    _Message(text: error!, isError: true),
                  ],
                  if (notice != null) ...[
                    const SizedBox(height: 14),
                    _Message(text: notice!, isError: false),
                  ],
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: saving ? null : save,
                    icon: saving
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.save_rounded),
                    label: Text(t('حفظ كلمة المرور', 'Save password')),
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

class _Message extends StatelessWidget {
  const _Message({required this.text, required this.isError});
  final String text;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final s = Theme.of(context).colorScheme;
    final color = isError ? s.error : s.primary;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: .25)),
      ),
      child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
    );
  }
}
