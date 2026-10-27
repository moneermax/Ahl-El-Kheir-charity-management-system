import 'package:flutter/material.dart';

typedef LoadChartAccounts = Future<Map<String, dynamic>> Function();
typedef AddChartAccount = Future<Map<String, dynamic>> Function(Map<String, dynamic> input);
typedef ToggleChartAccount = Future<Map<String, dynamic>> Function(int accountId);

class ChartOfAccountsPage extends StatefulWidget {
  const ChartOfAccountsPage({super.key, required this.english, required this.roleCode, required this.loadAccounts, required this.addAccount, required this.toggleAccount});
  final bool english;
  final String roleCode;
  final LoadChartAccounts loadAccounts;
  final AddChartAccount addAccount;
  final ToggleChartAccount toggleAccount;
  @override
  State<ChartOfAccountsPage> createState() => _ChartOfAccountsPageState();
}

class _ChartOfAccountsPageState extends State<ChartOfAccountsPage> {
  final _code = TextEditingController(), _nameAr = TextEditingController(), _nameEn = TextEditingController();
  String _type = 'asset';
  bool _loading = true, _busy = false, _canManage = false;
  String? _error;
  List<Map<String, dynamic>> _accounts = [];
  String t(String ar, String en) => widget.english ? en : ar;
  static const _types = ['asset', 'liability', 'equity', 'revenue', 'expense'];

  @override
  void initState() { super.initState(); refresh(); }
  @override
  void dispose() { _code.dispose(); _nameAr.dispose(); _nameEn.dispose(); super.dispose(); }

  Future<void> refresh() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.loadAccounts();
      if (data['accounts'] is! List) throw const FormatException('Missing accounts list');
      if (!mounted) return;
      setState(() {
        _accounts = (data['accounts'] as List).whereType<Map>().map((x) => Map<String, dynamic>.from(x)).toList();
        _canManage = data['can_manage'] == true && ['admin', 'financial_manager', 'sudo', 'super_admin', 'fm'].contains(widget.roleCode);
      });
    } catch (e) { if (mounted) setState(() => _error = e.toString()); }
    finally { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _add() async {
    final code = _code.text.trim(), nameAr = _nameAr.text.trim(), nameEn = _nameEn.text.trim();
    if (!RegExp(r'^\d{3,6}$').hasMatch(code) || nameAr.isEmpty) {
      setState(() => _error = t('أدخل رمزاً من 3 إلى 6 أرقام واسم الحساب بالعربية.', 'Enter a 3–6 digit code and an Arabic account name.'));
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      await widget.addAccount({'action':'add_account','code':code,'name_ar':nameAr,'name_en':nameEn,'account_type':_type});
      _code.clear(); _nameAr.clear(); _nameEn.clear();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(t('تمت إضافة الحساب.','Account added.'))));
      await refresh();
    } catch (e) { if (mounted) setState(() => _error = e.toString()); }
    finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _toggle(Map<String, dynamic> account) async {
    final id = int.tryParse(account['id']?.toString() ?? '');
    if (id == null) return;
    setState(() { _busy = true; _error = null; });
    try { await widget.toggleAccount(id); await refresh(); }
    catch (e) { if (mounted) setState(() => _error = e.toString()); }
    finally { if (mounted) setState(() => _busy = false); }
  }

  String _typeName(String type) => switch(type) {
    'asset' => t('أصول','Asset'), 'liability' => t('التزامات','Liability'),
    'equity' => t('حقوق ملكية','Equity'), 'revenue' => t('إيرادات','Revenue'),
    'expense' => t('مصروفات','Expense'), _ => type,
  };

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF6F4EE),
    appBar: AppBar(title: Text(t('دليل الحسابات','Chart of accounts')), actions: [
      IconButton(tooltip: t('تحديث','Refresh'), onPressed: _loading ? null : refresh, icon: const Icon(Icons.refresh_rounded)),
    ]),
    body: RefreshIndicator(onRefresh: refresh, child: ListView(
      physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
        Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(t('الحسابات المحاسبية','Accounting accounts'), style: const TextStyle(fontSize:18,fontWeight:FontWeight.w800)),
          const SizedBox(height:6), Text(t('الأرصدة محسوبة من القيود المحاسبية المُرحّلة فقط.','Balances include posted journal entries only.')),
          const SizedBox(height:10), Text(t('عدد الحسابات: \${_accounts.length}','Accounts: \${_accounts.length}'), style: const TextStyle(fontWeight:FontWeight.w700)),
          if (_loading) const LinearProgressIndicator(),
        ]))),
        if (_error != null) Card(child: Padding(padding: const EdgeInsets.all(12), child: Text(_error!,style: const TextStyle(color:Color(0xFFB42318))))),
        if (_canManage) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(t('إضافة حساب','Add account'),style: const TextStyle(fontSize:16,fontWeight:FontWeight.w800)),
          const SizedBox(height:8),
          TextField(controller:_code,keyboardType:TextInputType.number,textDirection:TextDirection.ltr,decoration:InputDecoration(labelText:t('رمز الحساب (3–6 أرقام) *','Account code (3–6 digits) *'))),
          TextField(controller:_nameAr,decoration:InputDecoration(labelText:t('اسم الحساب بالعربية *','Arabic account name *'))),
          TextField(controller:_nameEn,textDirection:TextDirection.ltr,decoration:InputDecoration(labelText:t('اسم الحساب بالإنجليزية','English account name'))),
          DropdownButtonFormField<String>(value:_type,decoration:InputDecoration(labelText:t('نوع الحساب','Account type')),items:_types.map((x)=>DropdownMenuItem(value:x,child:Text(_typeName(x)))).toList(),onChanged:_busy?null:(x){if(x!=null)setState(()=>_type=x);}),
          const SizedBox(height:10), FilledButton.icon(onPressed:_busy?null:_add,icon:const Icon(Icons.add_rounded),label:Text(t('إضافة الحساب','Add account'))),
        ]))),
        for (final a in _accounts) Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment:CrossAxisAlignment.start,children:[
            Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
              Text('\${a['code'] ?? ''} · \${a['name_ar'] ?? ''}',style:const TextStyle(fontWeight:FontWeight.w800,fontSize:15)),
              if ((a['name_en']?.toString()??'').isNotEmpty) Text(a['name_en'].toString(),textDirection:TextDirection.ltr,style:const TextStyle(color:Color(0xFF667085),fontSize:12)),
            ])),
            Text((int.tryParse(a['is_active']?.toString()??'')??0)==1?t('نشط','Active'):t('موقوف','Inactive')),
          ]),
          const Divider(height:20),
          Row(children:[Expanded(child:Text(_typeName(a['account_type']?.toString()??''),style:const TextStyle(color:Color(0xFF667085)))),Text((double.tryParse(a['balance']?.toString()??'')??0).toStringAsFixed(2),style:const TextStyle(fontWeight:FontWeight.w800,fontSize:16))]),
          if (_canManage) Align(alignment:AlignmentDirectional.centerEnd,child:TextButton.icon(
            onPressed:_busy||_loading?null:()=>_toggle(a),
            icon:Icon((int.tryParse(a['is_active']?.toString()??'')??0)==1?Icons.toggle_on_rounded:Icons.toggle_off_rounded),
            label:Text((int.tryParse(a['is_active']?.toString()??'')??0)==1?t('إيقاف الحساب','Deactivate account'):t('تفعيل الحساب','Activate account')),
          )),
        ]))),
        if (!_loading && _accounts.isEmpty && _error==null) Padding(padding:const EdgeInsets.all(24),child:Center(child:Text(t('لا توجد حسابات.','No accounts found.')))),
      ],
    )),
  );
}
