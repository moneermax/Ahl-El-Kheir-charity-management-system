import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({
    super.key, required this.english, required this.loadProfile, required this.saveProfile,
    required this.pickAvatar, required this.uploadAvatar, required this.loadAvatar, required this.removeAvatar,
  });
  final bool english;
  final Future<Map<String, dynamic>> Function() loadProfile;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> values) saveProfile;
  final Future<PlatformFile?> Function() pickAvatar;
  final Future<Map<String, dynamic>> Function(PlatformFile file) uploadAvatar;
  final Future<Map<String, dynamic>> Function() loadAvatar;
  final Future<Map<String, dynamic>> Function() removeAvatar;
  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final formKey = GlobalKey<FormState>();
  final fullName = TextEditingController(), email = TextEditingController(), phone = TextEditingController();
  final address = TextEditingController(), birthDate = TextEditingController();
  bool loading = true, saving = false, photoBusy = false;
  String gender = '';
  Map<String, dynamic> profile = {};
  Uint8List? avatarBytes;
  String? error, notice;
  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() { super.initState(); load(); }
  @override
  void dispose() {
    fullName.dispose(); email.dispose(); phone.dispose(); address.dispose(); birthDate.dispose(); super.dispose();
  }

  Future<void> load({bool showLoading = true}) async {
    if (mounted) setState(() { if (showLoading) loading = true; error = null; });
    try {
      final result = await widget.loadProfile();
      final raw = result['profile'];
      if (raw is! Map) throw StateError(t('تعذر قراءة بيانات الملف الشخصي.', 'The profile response was invalid.'));
      final next = Map<String, dynamic>.from(raw);
      Uint8List? imageBytes;
      if (next['has_avatar'] == true) {
        try {
          final image = await widget.loadAvatar();
          if (image['bytes'] is Uint8List) imageBytes = image['bytes'] as Uint8List;
        } catch (_) { /* Keep editing available if an old photo cannot be streamed. */ }
      }
      if (!mounted) return;
      setState(() {
        profile = next;
        fullName.text = next['full_name']?.toString() ?? '';
        email.text = next['email']?.toString() ?? '';
        phone.text = next['phone']?.toString() ?? '';
        address.text = next['address']?.toString() ?? '';
        birthDate.text = next['birth_date']?.toString() ?? '';
        gender = next['gender']?.toString() ?? '';
        avatarBytes = imageBytes;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted && showLoading) setState(() => loading = false);
    }
  }

  Future<void> chooseBirthDate() async {
    final now = DateTime.now();
    final existing = DateTime.tryParse(birthDate.text);
    final picked = await showDatePicker(
      context: context, initialDate: existing ?? DateTime(now.year - 25, now.month, now.day),
      firstDate: DateTime(1900), lastDate: now, helpText: t('اختر تاريخ الميلاد', 'Choose birth date'),
    );
    if (picked == null || !mounted) return;
    birthDate.text = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    setState(() {});
  }

  Future<void> save() async {
    if (!formKey.currentState!.validate()) return;
    setState(() { saving = true; error = null; notice = null; });
    try {
      final result = await widget.saveProfile({
        'action': 'update_profile', 'full_name': fullName.text.trim(), 'email': email.text.trim(),
        'phone': phone.text.trim(), 'address': address.text.trim(), 'birth_date': birthDate.text.trim(), 'gender': gender,
      });
      if (!mounted) return;
      final next = result['profile'];
      setState(() {
        if (next is Map) profile = Map<String, dynamic>.from(next);
        notice = result['message']?.toString() ?? t('تم حفظ الملف الشخصي.', 'Profile saved.');
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> uploadPhoto() async {
    setState(() { photoBusy = true; error = null; notice = null; });
    try {
      final file = await widget.pickAvatar();
      if (file == null) return;
      if (file.bytes == null || file.bytes!.isEmpty) throw StateError(t('تعذر قراءة الصورة المختارة.', 'The selected image could not be read.'));
      if (file.size > 10 * 1024 * 1024) throw StateError(t('يجب ألا يتجاوز حجم الصورة 10 ميجابايت.', 'The photo must not exceed 10 MB.'));
      final result = await widget.uploadAvatar(file);
      await load(showLoading: false);
      if (mounted) setState(() => notice = result['message']?.toString() ?? t('تم تحديث الصورة الشخصية.', 'Profile photo updated.'));
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => photoBusy = false);
    }
  }

  Future<void> removePhoto() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('إزالة الصورة الشخصية؟', 'Remove profile photo?')),
        content: Text(t('سيتم حذف الصورة الحالية من ملفك الشخصي.', 'Your current profile photo will be removed.')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(t('إزالة', 'Remove'))),
        ],
      ),
    );
    if (confirm != true || !mounted) return;
    setState(() { photoBusy = true; error = null; notice = null; });
    try {
      final result = await widget.removeAvatar();
      if (!mounted) return;
      setState(() {
        avatarBytes = null; profile['has_avatar'] = false;
        notice = result['message']?.toString() ?? t('تمت إزالة الصورة.', 'Profile photo removed.');
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => photoBusy = false);
    }
  }

  Widget field(TextEditingController controller, String ar, String en, {
    TextInputType? keyboardType, String? Function(String?)? validator, int maxLines = 1,
    bool readOnly = false, VoidCallback? onTap,
  }) => TextFormField(
    controller: controller, keyboardType: keyboardType, maxLines: maxLines, readOnly: readOnly, onTap: onTap,
    validator: validator, decoration: InputDecoration(labelText: t(ar, en)),
  );

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: Text(t('ملفي الشخصي', 'My profile')), actions: [
        IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading || saving || photoBusy ? null : () => load(), icon: const Icon(Icons.refresh_rounded)),
      ]),
      body: loading ? const Center(child: CircularProgressIndicator()) : ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(children: [
              CircleAvatar(
                radius: 48, backgroundColor: scheme.primaryContainer,
                backgroundImage: avatarBytes == null ? null : MemoryImage(avatarBytes!),
                child: avatarBytes == null ? Icon(Icons.person_rounded, size: 48, color: scheme.primary) : null,
              ),
              const SizedBox(height: 12),
              Text(profile['full_name']?.toString() ?? '', textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text('${profile['username'] ?? ''} · ${profile['role_name'] ?? profile['role_code'] ?? ''}', textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
              const SizedBox(height: 16),
              Wrap(alignment: WrapAlignment.center, spacing: 10, runSpacing: 8, children: [
                OutlinedButton.icon(
                  onPressed: photoBusy ? null : uploadPhoto,
                  icon: photoBusy ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.add_a_photo_outlined),
                  label: Text(t('تغيير الصورة', 'Change photo')),
                ),
                if (profile['has_avatar'] == true)
                  TextButton.icon(onPressed: photoBusy ? null : removePhoto, icon: const Icon(Icons.delete_outline_rounded), label: Text(t('إزالة الصورة', 'Remove photo'))),
              ]),
            ]),
          )),
          const SizedBox(height: 14),
          Card(child: Padding(
            padding: const EdgeInsets.all(18),
            child: Form(key: formKey, child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(t('البيانات الشخصية', 'Personal information'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 16),
                field(fullName, 'الاسم الكامل', 'Full name', validator: (v) => (v ?? '').trim().isEmpty ? t('الاسم الكامل مطلوب.', 'Full name is required.') : null),
                const SizedBox(height: 12),
                field(email, 'البريد الإلكتروني', 'Email', keyboardType: TextInputType.emailAddress, validator: (v) {
                  final value = (v ?? '').trim();
                  if (value.isNotEmpty && (!value.contains('@') || !value.contains('.'))) return t('البريد الإلكتروني غير صالح.', 'Enter a valid email address.');
                  return null;
                }),
                const SizedBox(height: 12),
                field(phone, 'رقم الهاتف', 'Phone', keyboardType: TextInputType.phone),
                const SizedBox(height: 12),
                field(address, 'العنوان', 'Address', maxLines: 2),
                const SizedBox(height: 12),
                field(birthDate, 'تاريخ الميلاد', 'Birth date', readOnly: true, onTap: chooseBirthDate),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  key: ValueKey<String>(gender), initialValue: gender.isEmpty ? null : gender, isExpanded: true,
                  decoration: InputDecoration(labelText: t('النوع', 'Gender')),
                  items: [
                    DropdownMenuItem(value: 'ذكر', child: Text(t('ذكر', 'Male'))),
                    DropdownMenuItem(value: 'أنثى', child: Text(t('أنثى', 'Female'))),
                  ],
                  onChanged: saving ? null : (value) => setState(() => gender = value ?? ''),
                ),
                if (error != null) ...[const SizedBox(height: 14), _ProfileMessage(text: error!, isError: true)],
                if (notice != null) ...[const SizedBox(height: 14), _ProfileMessage(text: notice!, isError: false)],
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: saving ? null : save,
                  icon: saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.save_rounded),
                  label: Text(t('حفظ البيانات', 'Save profile')),
                ),
              ],
            )),
          )),
        ],
      ),
    );
  }
}

class _ProfileMessage extends StatelessWidget {
  const _ProfileMessage({required this.text, required this.isError});
  final String text;
  final bool isError;
  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final color = isError ? scheme.error : scheme.primary;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: color.withValues(alpha: .08), borderRadius: BorderRadius.circular(12), border: Border.all(color: color.withValues(alpha: .22))),
      child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
    );
  }
}
