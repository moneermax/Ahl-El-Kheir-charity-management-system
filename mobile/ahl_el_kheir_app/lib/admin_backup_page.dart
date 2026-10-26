import 'package:flutter/material.dart';

class AdminBackupPage extends StatefulWidget {
  const AdminBackupPage({
    super.key,
    required this.english,
    required this.loadBackups,
    required this.createBackup,
    required this.deleteBackup,
    required this.downloadBackup,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadBackups;
  final Future<Map<String, dynamic>> Function() createBackup;
  final Future<Map<String, dynamic>> Function(String filename) deleteBackup;
  final Future<bool> Function(String filename) downloadBackup;

  @override
  State<AdminBackupPage> createState() => _AdminBackupPageState();
}

class _AdminBackupPageState extends State<AdminBackupPage> {
  bool loading = true;
  bool busy = false;
  String? error;
  String databaseName = '';
  List<Map<String, dynamic>> backups = [];

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadBackups();
      if (!mounted) return;
      setState(() {
        databaseName = data['database_name']?.toString() ?? '';
        backups = data['backups'] is List
            ? (data['backups'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
            : [];
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> create() async {
    setState(() { busy = true; error = null; });
    try {
      final data = await widget.createBackup();
      final backup = data['backup'] is Map ? Map<String, dynamic>.from(data['backup'] as Map) : <String, dynamic>{};
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(t('تم إنشاء النسخة: ', 'Backup created: ') + (backup['filename']?.toString() ?? '')),
      ));
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> download(String filename) async {
    setState(() { busy = true; error = null; });
    try {
      final saved = await widget.downloadBackup(filename);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(saved
            ? t('تم حفظ ملف النسخة الاحتياطية.', 'Backup file saved.')
            : t('تم إلغاء الحفظ.', 'Save cancelled.')),
      ));
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> remove(String filename) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('حذف النسخة الاحتياطية؟', 'Delete backup?')),
        content: Text(filename),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(t('إلغاء', 'Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(t('حذف', 'Delete'))),
        ],
      ),
    );
    if (confirm != true) return;
    setState(() { busy = true; error = null; });
    try {
      await widget.deleteBackup(filename);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم حذف النسخة.', 'Backup deleted.'))));
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF6F4EE),
    appBar: AppBar(title: Text(t('النسخ الاحتياطي', 'Database backup')), actions: [
      IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: loading || busy ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
    ]),
    body: RefreshIndicator(
      onRefresh: refresh,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(t('نسخ قاعدة البيانات', 'Database backups'), style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(t('قاعدة البيانات: ', 'Database: ') + databaseName),
            const SizedBox(height: 6),
            Text(t('تُحفظ الملفات داخل مساحة تخزين محمية على الخادم. لا تشارك ملفات SQL لأنها قد تحتوي على بيانات حساسة.', 'Files are stored in a protected server directory. SQL backups may contain sensitive data; handle them securely.')),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: loading || busy ? null : create,
              icon: const Icon(Icons.backup_rounded),
              label: Text(t('إنشاء نسخة الآن', 'Create backup now')),
            ),
            if (loading) const Padding(padding: EdgeInsets.only(top: 12), child: LinearProgressIndicator()),
            if (busy) const Padding(padding: EdgeInsets.only(top: 8), child: LinearProgressIndicator()),
            if (error != null) ...[
              const SizedBox(height: 10),
              Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
              Align(alignment: AlignmentDirectional.centerStart, child: TextButton(onPressed: refresh, child: Text(t('إعادة المحاولة', 'Retry')))),
            ],
          ]))),
          const SizedBox(height: 12),
          Text(t('النسخ المتوفرة', 'Available backups') + ' (' + backups.length.toString() + ')',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          if (backups.isEmpty && !loading)
            Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(t('لا توجد نسخ احتياطية بعد.', 'No backups available yet.')))),
          for (final backup in backups) Card(
            child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              SelectableText(backup['filename']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
              const SizedBox(height: 4),
              Text((backup['size_kb']?.toString() ?? '0') + ' KB · ' + (backup['modified_at']?.toString() ?? ''),
                style: const TextStyle(color: Color(0xFF647067), fontSize: 11)),
              const SizedBox(height: 8),
              Wrap(spacing: 8, runSpacing: 8, children: [
                OutlinedButton.icon(
                  onPressed: busy ? null : () => download(backup['filename']?.toString() ?? ''),
                  icon: const Icon(Icons.download_rounded),
                  label: Text(t('تنزيل', 'Download')),
                ),
                OutlinedButton.icon(
                  onPressed: busy ? null : () => remove(backup['filename']?.toString() ?? ''),
                  icon: const Icon(Icons.delete_outline_rounded),
                  label: Text(t('حذف', 'Delete')),
                ),
              ]),
            ])),
          ),
        ],
      ),
    ),
  );
}
