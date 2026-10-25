import 'package:flutter/material.dart';

class AdminDepartmentsPage extends StatefulWidget {
  const AdminDepartmentsPage({
    super.key,
    required this.english,
    required this.loadDepartments,
    required this.submitAction,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadDepartments;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> payload) submitAction;

  @override
  State<AdminDepartmentsPage> createState() => _AdminDepartmentsPageState();
}

class _AdminDepartmentsPageState extends State<AdminDepartmentsPage> {
  final nameAr = TextEditingController();
  final nameEn = TextEditingController();
  final description = TextEditingController();
  bool loading = true;
  bool saving = false;
  String? error;
  int? editingId;
  List<Map<String, dynamic>> items = [];

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void dispose() {
    nameAr.dispose();
    nameEn.dispose();
    description.dispose();
    super.dispose();
  }

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.loadDepartments();
      final raw = response['items'];
      if (!mounted) return;
      setState(() {
        items = raw is List
            ? raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
            : <Map<String, dynamic>>[];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void startEdit(Map<String, dynamic> item) {
    setState(() {
      editingId = int.tryParse(item['id']?.toString() ?? '');
      nameAr.text = item['name_ar']?.toString() ?? '';
      nameEn.text = item['name_en']?.toString() ?? '';
      description.text = item['description']?.toString() ?? '';
    });
  }

  void clearForm() {
    setState(() {
      editingId = null;
      nameAr.clear();
      nameEn.clear();
      description.clear();
      error = null;
    });
  }

  Future<void> save() async {
    if (nameAr.text.trim().isEmpty || nameEn.text.trim().isEmpty) {
      setState(() => error = widget.english
          ? 'Arabic and English department names are required.'
          : 'اسم القسم بالعربية والإنجليزية مطلوب.');
      return;
    }
    setState(() {
      saving = true;
      error = null;
    });
    try {
      await widget.submitAction({
        'action': editingId == null ? 'create' : 'update',
        if (editingId != null) 'id': editingId,
        'name_ar': nameAr.text.trim(),
        'name_en': nameEn.text.trim(),
        'description': description.text.trim(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(widget.english
            ? (editingId == null ? 'Department created.' : 'Department updated.')
            : (editingId == null ? 'تم إنشاء القسم.' : 'تم تحديث القسم.')),
      ));
      clearForm();
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> delete(Map<String, dynamic> item) async {
    final en = widget.english;
    final id = int.tryParse(item['id']?.toString() ?? '');
    if (id == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(en ? 'Delete department?' : 'حذف القسم؟'),
        content: Text(en
            ? 'A department can only be deleted when no users or employees are assigned to it.'
            : 'لا يمكن حذف القسم إلا إذا لم يكن مرتبطاً بأي مستخدم أو موظف.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(en ? 'Cancel' : 'إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(en ? 'Delete' : 'حذف'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() {
      saving = true;
      error = null;
    });
    try {
      await widget.submitAction({'action': 'delete', 'id': id});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(en ? 'Department deleted.' : 'تم حذف القسم.'),
      ));
      if (editingId == id) clearForm();
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(en ? 'Department management' : 'إدارة الأقسام'),
        actions: [
          IconButton(
            tooltip: en ? 'Refresh' : 'تحديث',
            onPressed: loading ? null : refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      en
                          ? (editingId == null ? 'Create department' : 'Edit department #${editingId}')
                          : (editingId == null ? 'إنشاء قسم' : 'تعديل القسم رقم ${editingId}'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: nameAr,
                      textDirection: TextDirection.rtl,
                      maxLength: 100,
                      decoration: InputDecoration(
                        labelText: en ? 'Arabic name *' : 'الاسم بالعربية *',
                        border: const OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: nameEn,
                      maxLength: 100,
                      decoration: InputDecoration(
                        labelText: en ? 'English name *' : 'الاسم بالإنجليزية *',
                        border: const OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: description,
                      minLines: 2,
                      maxLines: 4,
                      decoration: InputDecoration(
                        labelText: en ? 'Description' : 'الوصف',
                        border: const OutlineInputBorder(),
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 8),
                      Text(error!, style: TextStyle(color: scheme.error)),
                    ],
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: saving ? null : save,
                            icon: saving
                                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                : const Icon(Icons.save_rounded),
                            label: Text(en ? 'Save' : 'حفظ'),
                          ),
                        ),
                        if (editingId != null) ...[
                          const SizedBox(width: 8),
                          OutlinedButton(
                            onPressed: saving ? null : clearForm,
                            child: Text(en ? 'Cancel' : 'إلغاء'),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text(en ? 'Departments (${items.length})' : 'الأقسام (${items.length})',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                ),
                if (loading) const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
              ],
            ),
            const SizedBox(height: 8),
            if (items.isEmpty && !loading)
              Padding(
                padding: const EdgeInsets.all(24),
                child: Text(en ? 'No departments found.' : 'لا توجد أقسام.'),
              ),
            ...items.map((item) {
              final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
              final users = int.tryParse(item['users_count']?.toString() ?? '') ?? 0;
              final employees = int.tryParse(item['employees_count']?.toString() ?? '') ?? 0;
              final selected = editingId == id;
              return Card(
                margin: const EdgeInsets.only(bottom: 9),
                color: selected ? scheme.primaryContainer.withValues(alpha: .45) : null,
                child: Padding(
                  padding: const EdgeInsets.all(13),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          CircleAvatar(child: Text('$id')),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(item['name_ar']?.toString() ?? '—',
                                  style: const TextStyle(fontWeight: FontWeight.w800)),
                                Text(item['name_en']?.toString() ?? '—',
                                  style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                              ],
                            ),
                          ),
                        ],
                      ),
                      if ((item['description']?.toString() ?? '').isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(item['description'].toString()),
                      ],
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          Chip(avatar: const Icon(Icons.manage_accounts_outlined, size: 16),
                            label: Text(en ? '$users users' : '$users مستخدم')),
                          Chip(avatar: const Icon(Icons.badge_outlined, size: 16),
                            label: Text(en ? '$employees employees' : '$employees موظف')),
                        ],
                      ),
                      Row(
                        children: [
                          TextButton.icon(
                            onPressed: saving ? null : () => startEdit(item),
                            icon: const Icon(Icons.edit_outlined),
                            label: Text(en ? 'Edit' : 'تعديل'),
                          ),
                          const Spacer(),
                          IconButton(
                            tooltip: en ? 'Delete' : 'حذف',
                            onPressed: saving ? null : () => delete(item),
                            icon: const Icon(Icons.delete_outline_rounded),
                            color: scheme.error,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            }),
            if (error != null && items.isEmpty && !loading)
              TextButton.icon(
                onPressed: loading ? null : refresh,
                icon: const Icon(Icons.refresh_rounded),
                label: Text(en ? 'Retry' : 'إعادة المحاولة'),
              ),
          ],
        ),
      ),
    );
  }
}
