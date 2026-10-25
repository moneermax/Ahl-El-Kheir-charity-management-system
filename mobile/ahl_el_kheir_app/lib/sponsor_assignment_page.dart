import 'package:flutter/material.dart';

class SponsorAssignmentPage extends StatefulWidget {
  const SponsorAssignmentPage({
    super.key,
    required this.english,
    required this.loadOptions,
    required this.assignSponsor,
  });

  final bool english;
  final Future<Map<String, dynamic>> Function() loadOptions;
  final Future<Map<String, dynamic>> Function(int sponsorId, int supervisorId) assignSponsor;

  @override
  State<SponsorAssignmentPage> createState() => _SponsorAssignmentPageState();
}

class _SponsorAssignmentPageState extends State<SponsorAssignmentPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> sponsors = [];
  List<Map<String, dynamic>> supervisors = [];
  final Map<int, int?> selected = {};
  int? savingSponsorId;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.loadOptions();
      final rawSponsors = data['unassigned_sponsors'];
      final rawSupervisors = data['active_supervisors'];
      if (!mounted) return;
      setState(() {
        sponsors = rawSponsors is List
            ? rawSponsors.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
            : <Map<String, dynamic>>[];
        supervisors = rawSupervisors is List
            ? rawSupervisors.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
            : <Map<String, dynamic>>[];
        selected.removeWhere((key, _) => !sponsors.any((s) => _id(s['id']) == key));
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  int _id(dynamic value) => int.tryParse(value?.toString() ?? '') ?? 0;

  Future<void> assign(Map<String, dynamic> sponsor) async {
    final sponsorId = _id(sponsor['id']);
    final supervisorId = selected[sponsorId];
    if (sponsorId < 1 || supervisorId == null || supervisorId < 1 || savingSponsorId != null) {
      return;
    }
    setState(() {
      savingSponsorId = sponsorId;
      error = null;
    });
    try {
      final result = await widget.assignSponsor(sponsorId, supervisorId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(widget.english
            ? 'Sponsor assigned successfully.'
            : 'تم إسناد الكفيل بنجاح.'),
      ));
      setState(() {
        sponsors.removeWhere((item) => _id(item['id']) == sponsorId);
        selected.remove(sponsorId);
      });
      if (result['sponsor_id'] == null) await load();
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => savingSponsorId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(
        title: Text(en ? 'Sponsor assignment' : 'إسناد الكفلاء'),
        actions: [
          IconButton(
            tooltip: en ? 'Refresh' : 'تحديث',
            onPressed: loading ? null : load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null && sponsors.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: load,
                          icon: const Icon(Icons.refresh_rounded),
                          label: Text(en ? 'Try again' : 'إعادة المحاولة'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    children: [
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF2FF),
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.assignment_ind_rounded, color: Color(0xFF1F4E8C), size: 28),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(en ? 'Unassigned sponsors' : 'الكفلاء غير المسندين',
                                    style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0A1F44))),
                                  const SizedBox(height: 3),
                                  Text(en
                                      ? '${sponsors.length} shown · up to 100 records'
                                      : '${sponsors.length} كفيل معروض · حتى 100 سجل',
                                    style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
                        ),
                      if (supervisors.isEmpty)
                        Padding(
                          padding: const EdgeInsets.all(18),
                          child: Text(en ? 'No active supervisors are available.' : 'لا يوجد مشرفون نشطون متاحون.',
                            textAlign: TextAlign.center),
                        ),
                      if (sponsors.isEmpty)
                        Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(en ? 'There are no unassigned sponsors.' : 'لا يوجد كفلاء غير مسندين.',
                            textAlign: TextAlign.center),
                        ),
                      for (final sponsor in sponsors)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Card(
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text(sponsor['full_name']?.toString() ?? '',
                                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0A1F44))),
                                  const SizedBox(height: 4),
                                  Text(
                                    '${sponsor['sponsor_code'] ?? ''} · ${(sponsor['phone']?.toString().isNotEmpty == true) ? sponsor['phone'] : (en ? 'No phone' : 'لا يوجد هاتف')}',
                                    style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                                  ),
                                  const SizedBox(height: 12),
                                  DropdownButtonFormField<int>(
                                    initialValue: selected[_id(sponsor['id'])],
                                    decoration: InputDecoration(
                                      labelText: en ? 'Assign to supervisor' : 'إسناد إلى المشرف',
                                      border: const OutlineInputBorder(),
                                    ),
                                    items: supervisors.map((supervisor) => DropdownMenuItem<int>(
                                      value: _id(supervisor['id']),
                                      child: Text(supervisor['full_name']?.toString() ?? ''),
                                    )).toList(),
                                    onChanged: savingSponsorId != null || supervisors.isEmpty
                                        ? null
                                        : (value) => setState(() => selected[_id(sponsor['id'])] = value),
                                  ),
                                  const SizedBox(height: 10),
                                  FilledButton.icon(
                                    onPressed: savingSponsorId == _id(sponsor['id']) || supervisors.isEmpty || selected[_id(sponsor['id'])] == null
                                        ? null
                                        : () => assign(sponsor),
                                    icon: savingSponsorId == _id(sponsor['id'])
                                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                        : const Icon(Icons.person_add_alt_1_rounded),
                                    label: Text(en ? 'Assign sponsor' : 'إسناد الكفيل'),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
