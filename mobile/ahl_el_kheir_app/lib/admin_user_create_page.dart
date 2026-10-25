import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class AdminUserCreatePage extends StatefulWidget {
  const AdminUserCreatePage({
    super.key,
    required this.english,
    required this.loadOptions,
    required this.submitAction,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadOptions;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> payload) submitAction;

  @override
  State<AdminUserCreatePage> createState() => _AdminUserCreatePageState();
}

class _AdminUserCreatePageState extends State<AdminUserCreatePage> {
  final fullName = TextEditingController();
  final username = TextEditingController();
  final email = TextEditingController();
  final phone = TextEditingController();
  final hireDate = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
  final salary = TextEditingController();
  bool loading = true;
  bool saving = false;
  String? error;
  String? roleCode;
  String? gender;
  int? departmentId;
  int? managerId;
  String? temporaryPassword;
  int? createdUserId;
  int? createdEmployeeId;
  List<Map<String, dynamic>> roles = [];
  List<Map<String, dynamic>> departments = [];
  List<Map<String, dynamic>> managers = [];

  bool get en => widget.english;

  @override
  void initState() {
    super.initState();
    loadOptions();
  }

  @override
  void dispose() {
    fullName.dispose();
    username.dispose();
    email.dispose();
    phone.dispose();
    hireDate.dispose();
    salary.dispose();
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

  Future<void> chooseHireDate() async {
    final initial = DateTime.tryParse(hireDate.text) ?? DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1980),
      lastDate: DateTime(2100),
    );
    if (selected != null) {
      hireDate.text = selected.toIso8601String().substring(0, 10);
    }
  }

  Future<void> create() async {
    final name = fullName.text.trim();
    final userName = username.text.trim();
    final salaryValue = double.tryParse(salary.text.trim());
    if (name.isEmpty) {
      setState(() => error = en ? 'Full name is required.' : 'الاسم الكامل مطلوب.');
      return;
    }
    if (!RegExp(r'^[A-Za-z0-9_.]{3,30}$').hasMatch(userName)) {
      setState(() => error = en ? 'Username must be 3–30 letters, numbers, underscores or dots.' : 'اسم المستخدم يجب أن يكون من 3 إلى 30 حرفاً أو رقماً أو نقطة أو شرطة سفلية.');
      return;
    }
    if (roleCode == null || roleCode!.isEmpty) {
      setState(() => error = en ? 'Choose a role.' : 'اختر الدور.');
      return;
    }
    if (DateTime.tryParse(hireDate.text) == null) {
      setState(() => error = en ? 'Choose a valid hire date.' : 'اختر تاريخ تعيين صالحاً.');
      return;
    }
    if (salaryValue == null || !salaryValue.isFinite || salaryValue <= 0) {
      setState(() => error = en ? 'Basic salary must be greater than zero.' : 'يجب أن يكون الراتب الأساسي أكبر من صفر.');
      return;
    }

    setState(() {
      saving = true;
      error = null;
    });
    try {
      final result = await widget.submitAction({
        'action': 'create',
        'full_name': name,
        'username': userName,
        'email': email.text.trim(),
        'phone': phone.text.trim(),
        'gender': gender,
        'role_code': roleCode,
        'department_id': departmentId,
        'manager_id': managerId,
        'hire_date': hireDate.text,
        'basic_salary': salaryValue,
      });
      if (!mounted) return;
      setState(() {
        temporaryPassword = result['temporary_password']?.toString();
        createdUserId = int.tryParse(result['user_id']?.toString() ?? '');
        createdEmployeeId = int.tryParse(result['employee_id']?.toString() ?? '');
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Create employee account' : 'إنشاء حساب موظف')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (temporaryPassword != null && temporaryPassword!.isNotEmpty) ...[
                  Card(
                    color: scheme.primaryContainer,
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(en ? 'Account created successfully' : 'تم إنشاء الحساب بنجاح',
                            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
                          const SizedBox(height: 6),
                          Text(en
                              ? 'User ID: $createdUserId · Employee ID: $createdEmployeeId'
                              : 'رقم المستخدم: $createdUserId · رقم الموظف: $createdEmployeeId'),
                          const SizedBox(height: 12),
                          Text(en ? 'Temporary password — shown once' : 'كلمة المرور المؤقتة — تُعرض مرة واحدة',
                            style: const TextStyle(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          SelectableText(temporaryPassword!, textDirection: TextDirection.ltr,
                            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: 1.5)),
                          Align(
                            alignment: AlignmentDirectional.centerEnd,
                            child: TextButton.icon(
                              onPressed: () async {
                                await Clipboard.setData(ClipboardData(text: temporaryPassword!));
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                                    content: Text(en ? 'Copied to clipboard.' : 'تم النسخ إلى الحافظة.'),
                                  ));
                                }
                              },
                              icon: const Icon(Icons.copy_rounded),
                              label: Text(en ? 'Copy password' : 'نسخ كلمة المرور'),
                            ),
                          ),
                          Text(en
                              ? 'The employee profile and initial salary history were created automatically. The user must change the temporary password at first sign-in.'
                              : 'تم إنشاء ملف الموظف وسجل الراتب الأولي تلقائياً. يجب تغيير كلمة المرور المؤقتة عند أول تسجيل دخول.'),
                          const SizedBox(height: 10),
                          FilledButton(
                            onPressed: () => Navigator.pop(context),
                            child: Text(en ? 'Back to user directory' : 'العودة إلى دليل المستخدمين'),
                          ),
                        ],
                      ),
                    ),
                  ),
                ] else ...[
                  Text(en
                      ? 'Creates the login account, linked employee profile and initial salary history using the existing HR provisioning workflow.'
                      : 'ينشئ حساب الدخول وملف الموظف المرتبط وسجل الراتب الأولي باستخدام مسار التوظيف الحالي.',
                    style: TextStyle(color: scheme.onSurfaceVariant, height: 1.45)),
                  const SizedBox(height: 14),
                  _field(fullName, en ? 'Full name *' : 'الاسم الكامل *'),
                  _field(username, en ? 'Username *' : 'اسم المستخدم *', direction: TextDirection.ltr),
                  _field(email, en ? 'Email' : 'البريد الإلكتروني', direction: TextDirection.ltr, keyboardType: TextInputType.emailAddress),
                  _field(phone, en ? 'Phone' : 'الهاتف', direction: TextDirection.ltr, keyboardType: TextInputType.phone),
                  DropdownButtonFormField<String>(
                    initialValue: gender,
                    decoration: InputDecoration(labelText: en ? 'Gender' : 'الجنس', border: const OutlineInputBorder()),
                    items: [
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
                  const SizedBox(height: 12),
                  TextField(
                    controller: hireDate,
                    readOnly: true,
                    onTap: chooseHireDate,
                    decoration: InputDecoration(
                      labelText: en ? 'Hire date *' : 'تاريخ التعيين *',
                      suffixIcon: const Icon(Icons.calendar_month_rounded),
                      border: const OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _field(salary, en ? 'Monthly basic salary (SDG) *' : 'الراتب الأساسي الشهري (SDG) *',
                    direction: TextDirection.ltr, keyboardType: const TextInputType.numberWithOptions(decimal: true)),
                  if (error != null) ...[
                    const SizedBox(height: 10),
                    Text(error!, style: TextStyle(color: scheme.error)),
                  ],
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: saving ? null : create,
                    icon: saving
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.person_add_alt_1_rounded),
                    label: Text(en ? 'Create account and employee' : 'إنشاء الحساب والموظف'),
                  ),
                ],
              ],
            ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String label, {
    TextDirection? direction,
    TextInputType? keyboardType,
  }) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextField(
          controller: controller,
          textDirection: direction,
          keyboardType: keyboardType,
          decoration: InputDecoration(labelText: label, border: const OutlineInputBorder()),
        ),
      );
}
