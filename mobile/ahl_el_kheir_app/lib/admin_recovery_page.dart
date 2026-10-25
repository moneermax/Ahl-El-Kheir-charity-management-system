import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class AdminRecoveryPage extends StatefulWidget {
  const AdminRecoveryPage({
    super.key,
    required this.english,
    required this.loadRequests,
    required this.submitAction,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadRequests;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic> payload) submitAction;

  @override
  State<AdminRecoveryPage> createState() => _AdminRecoveryPageState();
}

class _AdminRecoveryPageState extends State<AdminRecoveryPage> {
  bool loading = true;
  bool saving = false;
  String? error;
  String? temporaryPassword;
  String? approvedFor;
  List<Map<String, dynamic>> items = [];

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadRequests();
      final raw = data['items'];
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

  String statusLabel(String status) {
    if (widget.english) {
      return const {
        'pending': 'Pending',
        'approved': 'Approved',
        'completed': 'Completed',
        'rejected': 'Rejected',
        'expired': 'Expired',
      }[status] ?? status;
    }
    return const {
      'pending': 'قيد المراجعة',
      'approved': 'موافق عليه',
      'completed': 'مكتمل',
      'rejected': 'مرفوض',
      'expired': 'منتهي',
    }[status] ?? status;
  }

  Future<void> decide(Map<String, dynamic> item, String action) async {
    final en = widget.english;
    final isApprove = action == 'approve';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(isApprove
            ? (en ? 'Approve recovery request?' : 'الموافقة على طلب الاستعادة؟')
            : (en ? 'Reject recovery request?' : 'رفض طلب الاستعادة؟')),
        content: Text(
          (item['full_name']?.toString() ?? '—') + ' (@' +
          (item['username']?.toString() ?? '—') + ')\n' +
          (isApprove
              ? (en ? 'A temporary password will be generated and must be shared securely.' : 'سيتم إنشاء كلمة مرور مؤقتة ويجب تسليمها بأمان.')
              : (en ? 'The request will be marked rejected.' : 'سيتم تسجيل الطلب كمرفوض.')),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(en ? 'Cancel' : 'إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(isApprove ? (en ? 'Approve' : 'موافقة') : (en ? 'Reject' : 'رفض')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() {
      saving = true;
      error = null;
      temporaryPassword = null;
      approvedFor = null;
    });
    try {
      final result = await widget.submitAction({
        'request_id': int.tryParse(item['id']?.toString() ?? '') ?? 0,
        'action': action,
      });
      if (!mounted) return;
      if (isApprove) {
        setState(() {
          temporaryPassword = result['temporary_password']?.toString();
          approvedFor = result['full_name']?.toString() ?? item['full_name']?.toString() ?? '';
        });
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(result['message']?.toString() ??
            (en ? 'Recovery request processed.' : 'تمت معالجة طلب الاستعادة.')),
      ));
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
        title: Text(en ? 'Password recovery' : 'استعادة كلمات المرور'),
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
            Text(
              en
                  ? 'Approve or reject pending recovery requests. Temporary passwords are shown only in the approval response; share them securely and require the user to change the password.'
                  : 'اعتمد أو ارفض طلبات الاستعادة المعلقة. تظهر كلمة المرور المؤقتة عند الموافقة فقط؛ سلّمها بأمان وسيُطلب من المستخدم تغييرها.',
              style: TextStyle(color: scheme.onSurfaceVariant, height: 1.45),
            ),
            if (temporaryPassword != null && temporaryPassword!.isNotEmpty) ...[
              const SizedBox(height: 14),
              Card(
                color: const Color(0xFFFFF4D6),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.key_rounded, color: Color(0xFF8A5A00)),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              en ? 'Temporary password for $approvedFor' : 'كلمة المرور المؤقتة لـ $approvedFor',
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                          IconButton(
                            tooltip: en ? 'Dismiss' : 'إخفاء',
                            onPressed: () => setState(() {
                              temporaryPassword = null;
                              approvedFor = null;
                            }),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      SelectableText(
                        temporaryPassword!,
                        textDirection: TextDirection.ltr,
                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: 2),
                      ),
                      const SizedBox(height: 6),
                      Text(en
                          ? 'Do not send this password in an unsecured channel.'
                          : 'لا ترسل كلمة المرور عبر قناة غير آمنة.'),
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
                    ],
                  ),
                ),
              ),
            ],
            if (error != null) ...[
              const SizedBox(height: 10),
              Material(
                color: scheme.errorContainer,
                borderRadius: BorderRadius.circular(12),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(error!, style: TextStyle(color: scheme.onErrorContainer)),
                ),
              ),
            ],
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Text(
                    en ? 'Requests (' + items.length.toString() + ')' : 'الطلبات (' + items.length.toString() + ')',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ),
                if (loading) const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
              ],
            ),
            const SizedBox(height: 8),
            if (items.isEmpty && !loading)
              Padding(
                padding: const EdgeInsets.all(24),
                child: Text(en ? 'No recovery requests.' : 'لا توجد طلبات استعادة.'),
              ),
            ...items.map((item) {
              final status = item['status']?.toString() ?? '';
              final pending = status == 'pending';
              final statusColor = status == 'pending'
                  ? const Color(0xFF9A6700)
                  : status == 'approved' || status == 'completed'
                      ? const Color(0xFF18794E)
                      : scheme.onSurfaceVariant;
              return Card(
                margin: const EdgeInsets.only(bottom: 9),
                child: Padding(
                  padding: const EdgeInsets.all(13),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CircleAvatar(child: Text(item['id']?.toString() ?? '—')),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(item['full_name']?.toString() ?? '—',
                                  style: const TextStyle(fontWeight: FontWeight.w800)),
                                Text('@' + (item['username']?.toString() ?? '—'),
                                  style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                            decoration: BoxDecoration(
                              color: statusColor.withValues(alpha: .12),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(statusLabel(status),
                              style: TextStyle(color: statusColor, fontWeight: FontWeight.w800, fontSize: 11)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      _RecoveryInfo(icon: Icons.schedule_rounded, text: (en ? 'Requested' : 'تاريخ الطلب') + ': ' + (item['requested_at']?.toString().isNotEmpty == true ? item['requested_at'].toString() : '—')),
                      if ((item['reviewer_name']?.toString() ?? '').isNotEmpty)
                        _RecoveryInfo(icon: Icons.verified_user_outlined, text: (en ? 'Reviewed by' : 'راجعه') + ': ' + item['reviewer_name'].toString()),
                      if ((item['expires_at']?.toString() ?? '').isNotEmpty)
                        _RecoveryInfo(icon: Icons.hourglass_bottom_rounded, text: (en ? 'Expires' : 'الصلاحية') + ': ' + item['expires_at'].toString()),
                      if (pending) ...[
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: FilledButton.icon(
                                onPressed: saving ? null : () => decide(item, 'approve'),
                                icon: const Icon(Icons.check_rounded),
                                label: Text(en ? 'Approve' : 'موافقة'),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: saving ? null : () => decide(item, 'reject'),
                                icon: const Icon(Icons.close_rounded),
                                label: Text(en ? 'Reject' : 'رفض'),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

class _RecoveryInfo extends StatelessWidget {
  const _RecoveryInfo({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Row(
          children: [
            Icon(icon, size: 15, color: Theme.of(context).colorScheme.onSurfaceVariant),
            const SizedBox(width: 7),
            Expanded(child: Text(text, style: const TextStyle(fontSize: 12))),
          ],
        ),
      );
}
