import 'package:flutter/material.dart';

typedef LoadVouchers = Future<Map<String, dynamic>> Function(String type);
typedef IssueVoucher = Future<Map<String, dynamic>> Function(Map<String, dynamic> input);
typedef VoidVoucher = Future<Map<String, dynamic>> Function(int id, String reason);

class VoucherPage extends StatefulWidget {
  const VoucherPage({super.key, required this.english, required this.loadVouchers, this.issueVoucher, this.voidVoucher});
  final bool english;
  final LoadVouchers loadVouchers;
  final IssueVoucher? issueVoucher;
  final VoidVoucher? voidVoucher;

  @override
  State<VoucherPage> createState() => _VoucherPageState();
}

class _VoucherPageState extends State<VoucherPage> {
  String _type = '';
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _vouchers = [];
  List<Map<String, dynamic>> _cashAccounts = [];
  List<Map<String, dynamic>> _otherAccounts = [];
  bool _actionBusy = false;
  bool _canIssue = false;
  bool _canVoid = false;

  String t(String ar, String en) => widget.english ? en : ar;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final response = await widget.loadVouchers(_type);
      final data = response['data'];
      if (data is! Map) throw const FormatException('Invalid voucher response');
      final rawVouchers = data['vouchers'];
      final rawAccounts = data['cash_accounts'];
      final rawOtherAccounts = data['other_accounts'] ?? [];
      if (rawVouchers is! List || rawAccounts is! List || rawOtherAccounts is! List) {
        throw const FormatException('Missing voucher data');
      }
      if (!mounted) return;
      setState(() {
        _vouchers = rawVouchers.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList();
        _cashAccounts = rawAccounts.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList();
        _otherAccounts = rawOtherAccounts.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList();
        _canIssue = data['can_issue'] == true;
        _canVoid = data['can_void'] == true;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _openIssueDialog() async {
    if (!_canIssue || _actionBusy || _loading || widget.issueVoucher == null) return;
    final ok = await showDialog<bool>(context: context, builder: (_) => VoucherIssueDialog(
      english: widget.english, cashAccounts: _cashAccounts, otherAccounts: _otherAccounts, onSubmit: widget.issueVoucher!));
    if (ok == true && mounted) await _load();
  }

  Future<void> _voidVoucher(Map<String, dynamic> voucher) async {
    if (!_canVoid || _actionBusy || widget.voidVoucher == null || voucher['status']?.toString() != 'posted') return;
    final controller = TextEditingController();
    final reason = await showDialog<String>(context: context, builder: (ctx) => AlertDialog(
      title: Text(t('إبطال السند', 'Void voucher')),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(t('سيُنشأ قيد عكسي مع الاحتفاظ بالقيد الأصلي.', 'A reversing journal entry will be created; the original entry is preserved.')),
        TextField(key: const Key('voucher-void-reason'), controller: controller, autofocus: true, maxLines: 3,
          decoration: InputDecoration(labelText: t('سبب الإبطال', 'Void reason'), border: const OutlineInputBorder())),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text(t('إلغاء', 'Cancel'))),
        FilledButton(onPressed: () { final v=controller.text.trim(); if(v.length>=3) Navigator.pop(ctx,v); }, child: Text(t('إبطال السند','Void voucher'))),
      ],
    ));
    controller.dispose();
    if (reason == null || !mounted) return;
    setState(() => _actionBusy = true);
    try {
      await widget.voidVoucher!(int.tryParse(voucher['id']?.toString() ?? '') ?? 0, reason);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تم إبطال السند وإنشاء القيد العكسي.','Voucher voided and reversal entry created.'))));
      await _load();
    } catch(e) {
      if(mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${t('تعذر إبطال السند','Could not void voucher')}: $e')));
    } finally { if(mounted) setState(()=>_actionBusy=false); }
  }

  String _money(dynamic value) {
    final amount = double.tryParse(value?.toString() ?? '') ?? 0;
    return amount.toStringAsFixed(2);
  }

  String _date(dynamic value) {
    final raw = value?.toString() ?? '';
    if (raw.length >= 10) return raw.substring(0, 10);
    return raw.isEmpty ? '—' : raw;
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('سندات القبض والصرف', 'Receipt & payment vouchers')),
        actions: [IconButton(tooltip: t('تحديث', 'Refresh'), onPressed: _loading ? null : _load, icon: const Icon(Icons.refresh_rounded))],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text(t('أرصدة النقدية والبنوك والمحافظ', 'Cash, bank and wallet balances'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  if (_cashAccounts.isEmpty && !_loading)
                    Text(t('لا توجد حسابات نقدية متاحة.', 'No cash accounts are available.')),
                  for (final account in _cashAccounts)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      child: Row(children: [
                        Expanded(child: Text('${account['code'] ?? ''} · ${account['name_ar'] ?? ''}')),
                        const SizedBox(width: 12),
                        Text(_money(account['balance']), style: const TextStyle(fontWeight: FontWeight.w800, fontFeatures: [])),
                      ]),
                    ),
                ]),
              ),
            ),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: '', label: Text(t('الكل', 'All')), icon: const Icon(Icons.receipt_long_outlined)),
                ButtonSegment(value: 'receipt', label: Text(t('قبض', 'Receipts')), icon: const Icon(Icons.call_received_rounded)),
                ButtonSegment(value: 'payment', label: Text(t('صرف', 'Payments')), icon: const Icon(Icons.call_made_rounded)),
              ],
              selected: {_type},
              onSelectionChanged: _loading ? null : (selected) {
                setState(() => _type = selected.first);
                _load();
              },
            ),
            const SizedBox(height: 10),
            if (_canIssue && widget.issueVoucher != null)
              Align(alignment: AlignmentDirectional.centerStart, child: FilledButton.icon(
                onPressed: _loading || _actionBusy ? null : _openIssueDialog,
                icon: const Icon(Icons.add_rounded), label: Text(t('إصدار سند جديد','New voucher'))))
            else if (!_canIssue)
              Text(t('يمكنك استعراض السندات فقط؛ صلاحية الإصدار غير متاحة لهذا الدور.','You can view the register, but this role cannot issue vouchers.'),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant)),
            const SizedBox(height: 8),
            if (_loading) const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())),
            if (!_loading && _error != null)
              Card(child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  const Icon(Icons.cloud_off_rounded, size: 36),
                  const SizedBox(height: 8),
                  Text(t('تعذر تحميل السندات', 'Could not load vouchers'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 6),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 10),
                  FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh), label: Text(t('إعادة المحاولة', 'Retry'))),
                ]),
              )),
            if (!_loading && _error == null && _vouchers.isEmpty)
              Padding(padding: const EdgeInsets.all(28), child: Column(children: [
                const Icon(Icons.receipt_long_outlined, size: 40),
                const SizedBox(height: 8),
                Text(t('لا توجد سندات مطابقة.', 'No matching vouchers.')),
              ])),
            for (final voucher in _vouchers) _voucherCard(voucher, colors),
            if (!_loading && _error == null && _vouchers.isNotEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(t('يُعرض أحدث ${_vouchers.length} سند كحد أقصى 300 من الخادم.',
                              'Showing ${_vouchers.length} most recent vouchers; server limit is 300.'),
                  textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
              ),
          ],
        ),
      ),
    );
  }

  Widget _voucherCard(Map<String, dynamic> voucher, ColorScheme colors) {
    final isReceipt = voucher['voucher_type']?.toString() == 'receipt';
    final status = voucher['status']?.toString() ?? '';
    final statusLabel = switch (status) {
      'posted' => t('مُرحّل', 'Posted'),
      'voided' => t('مُبطل', 'Voided'),
      'draft' => t('مسودة', 'Draft'),
      _ => status.isEmpty ? '—' : status,
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            CircleAvatar(
              backgroundColor: isReceipt ? colors.primaryContainer : colors.tertiaryContainer,
              child: Icon(isReceipt ? Icons.call_received_rounded : Icons.call_made_rounded,
                color: isReceipt ? colors.onPrimaryContainer : colors.onTertiaryContainer),
            ),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(voucher['voucher_no']?.toString() ?? '—',
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
              Text(isReceipt ? t('سند قبض', 'Receipt voucher') : t('سند صرف', 'Payment voucher')),
              Text(_date(voucher['voucher_date']), style: Theme.of(context).textTheme.bodySmall),
            ])),
            const SizedBox(width: 8),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text(_money(voucher['amount']), style: TextStyle(fontWeight: FontWeight.w900, color: colors.primary, fontSize: 16)),
              Text(statusLabel, style: Theme.of(context).textTheme.labelSmall),
            ]),
          ]),
          const Divider(height: 22),
          _detail(t('الجهة', 'Party'), voucher['party_name']),
          _detail(t('حساب النقدية', 'Cash account'), '${voucher['cash_code'] ?? ''} · ${voucher['cash_name'] ?? ''}'),
          _detail(t('الحساب المقابل', 'Counter-account'), '${voucher['other_code'] ?? ''} · ${voucher['other_name'] ?? ''}'),
          if ((voucher['description']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('البيان', 'Description'), voucher['description']),
          if ((voucher['reference_number']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('رقم المرجع', 'Reference'), voucher['reference_number']),
          if ((voucher['entry_code']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('القيد المحاسبي', 'Journal entry'), voucher['entry_code']),
          if ((voucher['creator']?.toString() ?? '').trim().isNotEmpty)
            _detail(t('أنشأه', 'Created by'), voucher['creator']),          if (_canVoid && widget.voidVoucher != null && status == 'posted' && voucher['entry_id'] != null) ...[
            const SizedBox(height: 8),
            Align(alignment: AlignmentDirectional.centerEnd, child: OutlinedButton.icon(
              onPressed: _actionBusy ? null : () => _voidVoucher(voucher),
              icon: const Icon(Icons.block_rounded), label: Text(t('إبطال السند','Void voucher')))),
          ],
        ]),
      ),
    );
  }

  Widget _detail(String label, dynamic value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 112, child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700))),
      const SizedBox(width: 8),
      Expanded(child: Text((value?.toString() ?? '').trim().isEmpty ? '—' : value.toString())),
    ]),
  );
}


class VoucherIssueDialog extends StatefulWidget {
  const VoucherIssueDialog({super.key,required this.english,required this.cashAccounts,required this.otherAccounts,required this.onSubmit});
  final bool english;
  final List<Map<String,dynamic>> cashAccounts, otherAccounts;
  final IssueVoucher onSubmit;
  @override State<VoucherIssueDialog> createState()=>_VoucherIssueDialogState();
}
class _VoucherIssueDialogState extends State<VoucherIssueDialog> {
  final _key=GlobalKey<FormState>();
  final _amount=TextEditingController(),_party=TextEditingController(),_description=TextEditingController(),_reference=TextEditingController();
  String _type='receipt'; DateTime _date=DateTime.now(); int? _cashId,_otherId; bool _busy=false; String? _error;
  String t(String ar,String en)=>widget.english?en:ar;
  List<Map<String,dynamic>> get _eligible=>widget.otherAccounts.where((a)=>a['account_type']?.toString()==(_type=='receipt'?'revenue':'expense')).toList();
  String _dateText(DateTime d)=>'${d.year.toString().padLeft(4,'0')}-${d.month.toString().padLeft(2,'0')}-${d.day.toString().padLeft(2,'0')}';
  @override void initState(){super.initState();if(widget.cashAccounts.isNotEmpty)_cashId=int.tryParse(widget.cashAccounts.first['id'].toString());if(_eligible.isNotEmpty)_otherId=int.tryParse(_eligible.first['id'].toString());}
  @override void dispose(){_amount.dispose();_party.dispose();_description.dispose();_reference.dispose();super.dispose();}
  Future<void> _submit() async {
    if(_busy||!_key.currentState!.validate())return;
    if(_cashId==null||_otherId==null){setState(()=>_error=t('لا توجد حسابات مناسبة.','No eligible accounts are available.'));return;}
    final amount=double.tryParse(_amount.text.trim().replaceAll(',',''));
    if(amount==null||amount<=0){setState(()=>_error=t('أدخل مبلغاً صحيحاً.','Enter a valid amount.'));return;}
    setState(()=>_busy=true);
    try {
      await widget.onSubmit({'action':'issue_voucher','voucher_type':_type,'voucher_date':_dateText(_date),'amount':amount,'cash_account_id':_cashId,'other_account_id':_otherId,'party_name':_party.text.trim(),'description':_description.text.trim(),'reference_number':_reference.text.trim()});
      if(mounted)Navigator.pop(context,true);
    } catch(e){if(mounted)setState(()=>_error=e.toString());} finally{if(mounted)setState(()=>_busy=false);}
  }
  @override Widget build(BuildContext context){
    final eligible=_eligible;
    return AlertDialog(title:Text(t('إصدار سند مالي','Issue voucher')),content:SizedBox(width:520,child:SingleChildScrollView(child:Form(key:_key,child:Column(mainAxisSize:MainAxisSize.min,crossAxisAlignment:CrossAxisAlignment.stretch,children:[
      SegmentedButton<String>(segments:[ButtonSegment(value:'receipt',label:Text(t('قبض','Receipt'))),ButtonSegment(value:'payment',label:Text(t('صرف','Payment')))],selected:{_type},onSelectionChanged: _busy ? null : (s) { setState(() { _type = s.first; _otherId = _eligible.isEmpty ? null : int.tryParse(_eligible.first['id'].toString()); }); }),
      const SizedBox(height:12),
      DropdownButtonFormField<int>(initialValue:_cashId,decoration:InputDecoration(labelText:t('حساب النقدية','Cash account'),border:const OutlineInputBorder()),items:widget.cashAccounts.map((a)=>DropdownMenuItem<int>(value:int.tryParse(a['id'].toString()),child:Text('${a['code']??''} · ${a['name_ar']??''}'))).toList(),onChanged:_busy?null:(v)=>setState(()=>_cashId=v),validator:(v)=>v==null?t('اختر حساب النقدية.','Choose a cash account.'):null),
      const SizedBox(height:12),
      DropdownButtonFormField<int>(initialValue:_otherId,decoration:InputDecoration(labelText:t('الحساب المقابل','Counter-account'),border:const OutlineInputBorder()),items:eligible.map((a)=>DropdownMenuItem<int>(value:int.tryParse(a['id'].toString()),child:Text('${a['code']??''} · ${a['name_ar']??''}'))).toList(),onChanged:_busy?null:(v)=>setState(()=>_otherId=v),validator:(v)=>v==null?t('اختر الحساب المقابل.','Choose a counter-account.'):null),
      const SizedBox(height:12),
      OutlinedButton.icon(onPressed:_busy?null:()async{final d=await showDatePicker(context:context,initialDate:_date,firstDate:DateTime(2000),lastDate:DateTime.now());if(d!=null&&mounted)setState(()=>_date=d);},icon:const Icon(Icons.calendar_month_rounded),label:Text('${t('التاريخ','Date')}: ${_dateText(_date)}')),
      const SizedBox(height:12),
      TextFormField(key:const Key('voucher-amount'),controller:_amount,keyboardType:const TextInputType.numberWithOptions(decimal:true),decoration:InputDecoration(labelText:t('المبلغ (ج.س)','Amount (SDG)'),border:const OutlineInputBorder()),validator:(v){final n=double.tryParse((v??'').trim().replaceAll(',',''));return n==null||n<=0?t('أدخل مبلغاً أكبر من صفر.','Enter an amount greater than zero.'):null;}),
      const SizedBox(height:12),
      TextFormField(key:const Key('voucher-party'),controller:_party,maxLength:150,decoration:InputDecoration(labelText:_type=='receipt'?t('استلمنا من','Received from'):t('صرفنا إلى','Paid to'),border:const OutlineInputBorder()),validator:(v)=>(v??'').trim().isEmpty?t('هذا الحقل مطلوب.','This field is required.'):null),
      TextFormField(key:const Key('voucher-description'),controller:_description,maxLength:255,decoration:InputDecoration(labelText:t('البيان / سبب السند','Description / reason'),border:const OutlineInputBorder()),validator:(v)=>(v??'').trim().length<3?t('أدخل بياناً من 3 أحرف على الأقل.','Enter at least 3 characters.'):null),
      const SizedBox(height:12),
      TextFormField(key:const Key('voucher-reference'),controller:_reference,maxLength:100,decoration:InputDecoration(labelText:t('رقم المرجع (اختياري)','Reference (optional)'),border:const OutlineInputBorder())),
      if(_type=='payment') Padding(padding:const EdgeInsets.only(top:8),child:Text(t('سيُرفض السند إذا تجاوز الرصيد المتاح.','The server rejects payments exceeding the available balance.'),style:Theme.of(context).textTheme.bodySmall)),
      if(_error!=null) Padding(padding:const EdgeInsets.only(top:8),child:Text(_error!,style:TextStyle(color:Theme.of(context).colorScheme.error))),
    ])))),actions:[TextButton(onPressed:_busy?null:()=>Navigator.pop(context,false),child:Text(t('إلغاء','Cancel'))),FilledButton(onPressed:_busy?null:_submit,child:_busy?const SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2)):Text(t('إصدار وترحيل','Issue and post')))]);
  }
}
