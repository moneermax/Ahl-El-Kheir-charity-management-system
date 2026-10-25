import 'package:flutter/material.dart';

class AdminUserEditPage extends StatefulWidget {
  const AdminUserEditPage({
    super.key,
    required this.english,
    required this.user,
    required this.loadOptions,
    required this.submitAction,
  });

  final bool english;
  final Map<String, dynamic> user;
  final Future<Map<String, dynamic>> Function() loadOptions;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> payload) submitAction;

  @override
  State<AdminUserEditPage> createState() => _AdminUserEditPageState();
}

class _AdminUserEditPageState extends State<AdminUserEditPage> {
  late final TextEditingController fullName;
  late final TextEditingController email;
  late final TextEditingController phone;
  bool loading = true;
  bool saving = false;
  bool active = true;
  String? error;
  String? roleCode;
  String? gender;
  int? departmentId;
  int? managerId;
  List<Map<String, dynamic>> roles = [];
  List<Map<String, dynamic>> departments = [];
  List<Map<String, dynamic>> managers = [];

  bool get en => widget.english;

  @override
  void initState() {
    super.initState();
    fullName = TextEditingController(text: widget.user['full_name']?.toString() ?? '');
    email = TextEditingController(text: widget.user['email']?.toString() ?? '');
    phone = TextEditingController(text: widget.user['phone']?.toString() ?? '');
    roleCode = widget.user['role_code']?.toString();
    gender = ['male', 'female'].contains(widget.user['gender']) ? widget.user['gender']?.toString() : null;
    departmentId = int.tryParse(widget.user['department_id']?.toString() ?? '');
    managerId = int.tryParse(widget.user['manager_id']?.toString() ?? '');
    active = widget.user['is_active'] == true;
    loadOptions();
  }

  @override
  void dispose() {
    fullName.dispose();
    email.dispose();
    phone.dispose();
    super.dispose();
  }

  Future<void> loadOptions() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadOptions();
      if (!mounted) return;
      setState(() {
        roles = _items(data['roles']);
        departments = _items(data['departments']);
        managers = _items(data['managers']);
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  List<Map<String, dynamic>> _items(dynamic raw) => raw is List
      ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
      : <Map<String, dynamic>>[];

  Future<void> save() async {
    final name = fullName.text.trim();
    if (name.isEmpty) {
      setState(() => error = en ? 'Full name is required.' : 'الاسم الكامل مطلوب.');
      return;
    }
    if (roleCode == null || !roles.any((item) => item['code'] == roleCode)) {
      setState(() => error = en ? 'Choose a valid role.' : 'اختر دوراً صالحاً.');
      return;
    }
    setState(() {
      saving = true;
      error = null;
    });
    try {
      await widget.submitAction({
        'action': 'update',
        'user_id': int.tryParse(widget.user['id']?.toString() ?? '') ?? 0,
        'full_name': name,
        'email': email.text.trim(),
        'phone': phone.text.trim(),
        'role_code': roleCode,
        'department_id': departmentId,
        'manager_id': managerId,
        'gender': gender,
        'is_active': active,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(en ? 'User account updated.' : 'تم تحديث حساب المستخدم.'),
      ));
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final id = widget.user['id']?.toString() ?? '—';
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Edit user #$id' : 'تعديل المستخدم رقم $id')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text('@' + (widget.user['username']?.toString() ?? '—'),
                  style: TextStyle(color: scheme.onSurfaceVariant, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                TextField(
                  controller: fullName,
                  decoration: InputDecoration(labelText: en ? 'Full name *' : 'الاسم الكامل *', border: const OutlineInputBorder()),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  textDirection: TextDirection.ltr,
                  decoration: InputDecoration(labelText: en ? 'Email' : 'البريد الإلكتروني', border: const OutlineInputBorder()),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  textDirection: TextDirection.ltr,
                  decoration: InputDecoration(labelText: en ? 'Phone' : 'الهاتف', border: const OutlineInputBorder()),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: gender,
                  decoration: InputDecoration(labelText: en ? 'Gender' : 'الجنس', border: const OutlineInputBorder()),
                  items: [
                    DropdownMenuItem<String>(value: null, child: Text(en ? 'Not specified' : 'غير محدد')),
                    DropdownMenuItem(value: 'male', child: Text(en ? 'Male' : 'ذكر')),
                    DropdownMenuItem(value: 'female', child: Text(en ? 'Female' : 'أنثى')),
                  ],
                  onChanged: (value) => setState(() => gender = value),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: roleCode,
                  decoration: InputDecoration(labelText: en ? 'Role *' : 'الدور *', border: const OutlineInputBorder()),
                  items: roles.map((item) => DropdownMenuItem<String>(
                    value: item['code']?.toString(),
                    child: Text((en ? item['name_en'] : item['name_ar'])?.toString() ?? item['code']?.toString() ?? '—'),
                  )).toList(),
                  onChanged: (value) => setState(() => roleCode = value),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: departmentId,
                  decoration: InputDecoration(labelText: en ? 'Department' : 'القسم', border: const OutlineInputBorder()),
                  items: [
                    DropdownMenuItem<int>(value: null, child: Text(en ? 'Unassigned' : 'غير معين')),
                    ...departments.map((item) => DropdownMenuItem<int>(
                      value: int.tryParse(item['id']?.toString() ?? ''),
                      child: Text((en ? item['name_en'] : item['name_ar'])?.toString() ?? '—'),
                    )),
                  ],
                  onChanged: (value) => setState(() => departmentId = value),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: managerId,
                  decoration: InputDecoration(labelText: en ? 'Direct manager' : 'المسؤول المباشر', border: const OutlineInputBorder()),
                  items: [
                    DropdownMenuItem<int>(value: null, child: Text(en ? 'Unassigned' : 'غير معين')),
                    ...managers.map((item) => DropdownMenuItem<int>(
                      value: int.tryParse(item['id']?.toString() ?? ''),
                      child: Text((item['full_name']?.toString() ?? '—') + ' (@' + (item['username']?.toString() ?? '—') + ')'),
                    )),
                  ],
                  onChanged: (value) => setState(() => managerId = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: active,
                  onChanged: (value) => setState(() => active = value),
                  title: Text(en ? 'Account is active' : 'الحساب نشط'),
                ),
                if (error != null) ...[
                  const SizedBox(height: 8),
                  Text(error!, style: TextStyle(color: scheme.error)),
                ],
                const SizedBox(height: 12),
                FilledButton.icon(
                  onPressed: saving ? null : save,
                  icon: saving
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.save_rounded),
                  label: Text(en ? 'Save changes' : 'حفظ التعديلات'),
                ),
              ],
            ),
    );
  }
}
