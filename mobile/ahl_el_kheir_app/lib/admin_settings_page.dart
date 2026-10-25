import 'package:flutter/material.dart';

class AdminSettingsPage extends StatefulWidget {
  const AdminSettingsPage({
    super.key,
    required this.english,
    required this.loadSettings,
    required this.saveSettings,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadSettings;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic>) saveSettings;

  @override
  State<AdminSettingsPage> createState() => _AdminSettingsPageState();
}

class _AdminSettingsPageState extends State<AdminSettingsPage> {
  bool loading = true;
  bool saving = false;
  String? error;
  Map<String, dynamic> groups = {};
  Map<String, dynamic> values = {};
  final Map<String, TextEditingController> controllers = {};
  String encryption = 'tls';
  bool smtpPasswordConfigured = false;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    for (final controller in controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  String t(String ar, String en) => widget.english ? en : ar;

  String groupLabel(String key) => ({
    'organization': t('بيانات المنظمة', 'Organization'),
    'financial': t('الإعدادات المالية', 'Financial settings'),
    'smtp': t('البريد الإلكتروني SMTP', 'SMTP email'),
    'system': t('إعدادات النظام', 'System'),
  })[key] ?? key;

  String fieldLabel(String key, Map<String, dynamic> def) {
    if (!widget.english) return def['label_ar']?.toString() ?? key;
    return ({
      'org_name_ar': 'Organization name (Arabic)',
      'org_name_en': 'Organization name (English)',
      'org_address': 'Address',
      'tax_id': 'Tax ID',
      'support_phone': 'Support phone',
      'support_email': 'Support email',
      'currency': 'Currency',
      'default_sponsorship_amount': 'Default sponsorship amount',
      'default_admin_fee_percent': 'Default administrative fee (%)',
      'financial_year_start': 'Financial year start',
      'smtp_host': 'SMTP host',
      'smtp_port': 'SMTP port',
      'smtp_username': 'SMTP username',
      'smtp_password': 'SMTP password',
      'smtp_encryption': 'SMTP encryption',
      'mail_from': 'Sender email',
      'supervisor_auto_assign': 'Automatic supervisor assignment',
      'system_version': 'System version',
    })[key] ?? key;
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadSettings();
      final rawGroups = data['groups'];
      final rawValues = data['values'];
      if (!mounted) return;
      final parsedValues = rawValues is Map ? Map<String, dynamic>.from(rawValues) : <String, dynamic>{};
      for (final controller in controllers.values) {
        controller.dispose();
      }
      controllers.clear();
      for (final entry in parsedValues.entries) {
        if (entry.key == 'smtp_password' || entry.key == 'smtp_password_configured') continue;
        controllers[entry.key] = TextEditingController(text: entry.value?.toString() ?? '');
      }
      setState(() {
        groups = rawGroups is Map ? Map<String, dynamic>.from(rawGroups) : {};
        values = parsedValues;
        encryption = parsedValues['smtp_encryption']?.toString().isNotEmpty == true
            ? parsedValues['smtp_encryption'].toString() : 'tls';
        smtpPasswordConfigured = parsedValues['smtp_password_configured'] == true;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> save() async {
    FocusScope.of(context).unfocus();
    final payload = <String, dynamic>{};
    for (final entry in controllers.entries) {
      payload[entry.key] = entry.value.text.trim();
    }
    payload['smtp_encryption'] = encryption;
    final password = values['smtp_password']?.toString() ?? '';
    if (password.isNotEmpty) payload['smtp_password'] = password;
    setState(() { saving = true; error = null; });
    try {
      await widget.saveSettings(payload);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(t('تم حفظ الإعدادات.', 'Settings saved.')),
      ));
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget buildField(String key, Map<String, dynamic> def) {
    final type = def['type']?.toString() ?? 'text';
    if (type == 'select') {
      final rawOptions = def['options'];
      final options = rawOptions is List ? rawOptions.map((v) => v.toString()).toList() : <String>['tls', 'ssl', 'none'];
      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: DropdownButtonFormField<String>(
          value: options.contains(encryption) ? encryption : options.first,
          decoration: InputDecoration(labelText: fieldLabel(key, def), border: const OutlineInputBorder()),
          items: options.map((option) => DropdownMenuItem(value: option, child: Text(option))).toList(),
          onChanged: saving ? null : (value) { if (value != null) setState(() => encryption = value); },
        ),
      );
    }
    if (key == 'smtp_password') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: TextFormField(
          key: const ValueKey('smtp-password'),
          obscureText: true,
          enabled: !saving,
          decoration: InputDecoration(
            labelText: fieldLabel(key, def),
            hintText: smtpPasswordConfigured
                ? t('مضبوطة مسبقاً — اتركها فارغة للإبقاء عليها', 'Configured — leave blank to keep current password')
                : t('لم يتم ضبط كلمة مرور', 'No password configured'),
            border: const OutlineInputBorder(),
          ),
          onChanged: (value) => values['smtp_password'] = value,
        ),
      );
    }
    final controller = controllers.putIfAbsent(key, () => TextEditingController(text: values[key]?.toString() ?? ''));
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: controller,
        enabled: !saving,
        keyboardType: type == 'number' ? const TextInputType.numberWithOptions(decimal: true) : type == 'email' ? TextInputType.emailAddress : TextInputType.text,
        decoration: InputDecoration(labelText: fieldLabel(key, def), border: const OutlineInputBorder()),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(t('إعدادات النظام', 'System settings')),
        actions: [
          IconButton(
            tooltip: t('تحديث', 'Refresh'),
            onPressed: loading || saving ? null : refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  t('تعديل الإعدادات المتاحة للمسؤول. لا يتم عرض كلمة مرور SMTP الحالية. رفع الشعار غير متاح في هذه النسخة من الشاشة.',
                      'Edit the settings available to the administrator. The current SMTP password is never returned. Logo upload is not available in this screen yet.'),
                  style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 14),
                if (error != null)
                  Card(
                    color: Theme.of(context).colorScheme.errorContainer,
                    child: Padding(padding: const EdgeInsets.all(12), child: Text(error!)),
                  ),
                if (groups.isEmpty && error == null)
                  Text(t('لا توجد إعدادات متاحة.', 'No settings are available.')),
                ...groups.entries.map((group) {
                  final rawFields = group.value;
                  if (rawFields is! Map) return const SizedBox.shrink();
                  return Card(
                    margin: const EdgeInsets.only(bottom: 14),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(groupLabel(group.key), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 14),
                          ...rawFields.entries.map((field) {
                            if (field.value is! Map) return const SizedBox.shrink();
                            return buildField(field.key.toString(), Map<String, dynamic>.from(field.value as Map));
                          }),
                        ],
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 4),
                FilledButton.icon(
                  onPressed: saving || groups.isEmpty ? null : save,
                  icon: saving
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.save_rounded),
                  label: Text(saving ? t('جارٍ الحفظ…', 'Saving…') : t('حفظ الإعدادات', 'Save settings')),
                ),
              ],
            ),
    );
  }
}
