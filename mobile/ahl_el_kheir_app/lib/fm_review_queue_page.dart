import 'package:flutter/material.dart';

class FmReviewQueuePage extends StatefulWidget {
  const FmReviewQueuePage({super.key, required this.english, required this.loadQueue});
  final bool english;
  final Future<Map<String, dynamic>> Function() loadQueue;
  @override
  State<FmReviewQueuePage> createState() => _FmReviewQueuePageState();
}

class _FmReviewQueuePageState extends State<FmReviewQueuePage> with SingleTickerProviderStateMixin {
  late final TabController tabs;
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> pending = [];
  List<Map<String, dynamic>> history = [];

  @override
  void initState() { super.initState(); tabs = TabController(length: 2, vsync: this); load(); }
  @override
  void dispose() { tabs.dispose(); super.dispose(); }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final data = await widget.loadQueue();
      List<Map<String, dynamic>> parse(dynamic value) => value is List
          ? value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() { pending = parse(data['pending']); history = parse(data['history']); });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }

  String purpose(Map<String, dynamic> item) {
    final label = item['purpose_label']?.toString() ?? '';
    if (!widget.english) return label;
    const labels = {'كفالة شهرية':'Monthly sponsorship','رسوم دراسية':'School fees','علاج وأدوية':'Medicine','هدية/عيدية':'Gift','أخرى':'Other','رسوم إدارية':'Administrative fee','تبرع عام':'General donation','تبرع مشروع':'Project donation'};
    return labels[label] ?? label;
  }

  Widget paymentCard(Map<String, dynamic> item, {required bool past}) {
    final en = widget.english;
    final amount = double.tryParse(item['amount']?.toString() ?? '') ?? 0;
    final lines = <String>[];
    void addLine(String label, dynamic value) { final text = value?.toString() ?? ''; if (text.isNotEmpty) lines.add(label + ': ' + text); }
    addLine(en ? 'Supervisor' : 'المشرف', item['supervisor_name']);
    addLine(en ? 'Sponsor' : 'الكفيل', item['sponsor_name']);
    addLine(en ? 'Orphan' : 'اليتيم', item['child_name']);
    addLine(en ? 'Period' : 'الفترة', item['payment_period']);
    addLine(en ? 'Submitted' : 'تاريخ الإرسال', item['created_at']);
    if (past) { addLine(en ? 'Reviewer' : 'المراجع', item['reviewer_name']); addLine(en ? 'Reviewed' : 'تاريخ المراجعة', item['reviewed_at']); }
    addLine(en ? 'Return reason' : 'سبب الإرجاع', item['return_note']);
    if (item['has_receipt'] == true) lines.add(en ? 'Receipt attached' : 'يوجد إيصال مرفق');
    if (item['has_unified_receipt'] == true) lines.add(en ? 'Combined receipt attached' : 'يوجد إيصال موحد مرفق');
    final title = (en ? 'Payment #' : 'تحصيل #') + (item['id']?.toString() ?? '') + ' — ' + purpose(item);
    return Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [Expanded(child: Text(title, style: const TextStyle(fontWeight: FontWeight.w800))), if (past) Chip(label: Text((item['status'] == 'approved') ? (en ? 'Approved' : 'معتمد') : (en ? 'Returned' : 'مُعاد')))]),
      const SizedBox(height: 5),
      Text(amount.toStringAsFixed(2) + ' ' + (item['currency_code']?.toString() ?? 'SDG'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
      if (lines.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 7), child: Text(lines.join('\n'))),
    ])));
  }

  Widget bodyList(List<Map<String, dynamic>> items, {required bool past}) {
    final en = widget.english;
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [Text(error!, textAlign: TextAlign.center), OutlinedButton(onPressed: load, child: Text(en ? 'Try again' : 'إعادة المحاولة'))])));
    if (items.isEmpty) return ListView(physics: const AlwaysScrollableScrollPhysics(), children: [Padding(padding: const EdgeInsets.all(32), child: Text(past ? (en ? 'No recent reviews.' : 'لا توجد مراجعات حديثة.') : (en ? 'No payments awaiting review.' : 'لا توجد تحصيلات بانتظار المراجعة.'), textAlign: TextAlign.center))]);
    return RefreshIndicator(onRefresh: load, child: ListView.builder(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(10), itemCount: items.length, itemBuilder: (_, index) => paymentCard(items[index], past: past)));
  }

  @override
  Widget build(BuildContext context) {
    final en = widget.english;
    return Scaffold(
      appBar: AppBar(title: Text(en ? 'Financial review queue' : 'طابور المراجعة المالية'), bottom: TabBar(controller: tabs, tabs: [Tab(text: (en ? 'Pending (' : 'معلقة (') + pending.length.toString() + ')'), Tab(text: en ? 'Recent history' : 'سجل المراجعات')])),
      body: TabBarView(controller: tabs, children: [bodyList(pending, past: false), bodyList(history, past: true)]),
    );
  }
}
