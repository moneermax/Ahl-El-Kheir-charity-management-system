import 'package:flutter/material.dart';

class AdminAccountsPage extends StatefulWidget {
  const AdminAccountsPage({super.key,required this.english,required this.loadAccounts,required this.submitAction});
  final bool english;
  final Future<Map<String,dynamic>> Function() loadAccounts;
  final Future<Map<String,dynamic>> Function(Map<String,dynamic>) submitAction;
  @override State<AdminAccountsPage> createState()=>_AdminAccountsPageState();
}
class _AdminAccountsPageState extends State<AdminAccountsPage>{
  final code=TextEditingController(),nameAr=TextEditingController(),nameEn=TextEditingController();
  String type='asset'; bool loading=true,saving=false; String? error; List<Map<String,dynamic>> items=[];
  @override void initState(){super.initState();refresh();}
  @override void dispose(){code.dispose();nameAr.dispose();nameEn.dispose();super.dispose();}
  String t(String ar,String en)=>widget.english?en:ar;
  String money(num v)=>v.toStringAsFixed(2).replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'),(m)=>',');
  String typeLabel(String value)=>switch(value){'asset'=>t('أصول','Asset'),'liability'=>t('التزامات','Liability'),'equity'=>t('حقوق ملكية','Equity'),'revenue'=>t('إيرادات','Revenue'),'expense'=>t('مصروفات','Expense'),_=>value};
  Future<void> refresh()async{
    setState(()=>{loading=true,error=null});
    try{final d=await widget.loadAccounts();final raw=d['items'];if(mounted)setState(()=>items=raw is List?raw.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList():<Map<String,dynamic>>[]);}
    catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>loading=false);}
  }
  Future<void> create()async{
    setState(()=>{saving=true,error=null});
    try{
      await widget.submitAction({'action':'create','code':code.text.trim(),'name_ar':nameAr.text.trim(),'name_en':nameEn.text.trim(),'account_type':type});
      if(!mounted)return;
      code.clear();nameAr.clear();nameEn.clear();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(t('تمت إضافة الحساب','Account created'))));
      await refresh();
    }catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>saving=false);}
  }
  Future<void> toggle(Map<String,dynamic> account)async{
    final id=int.tryParse(account['id']?.toString()??'')??0;
    if(id<1)return;
    final yes=await showDialog<bool>(context:context,builder:(context)=>AlertDialog(
      title:Text(t('تغيير حالة الحساب','Change account status')),
      content:Text(t('هل تريد تغيير حالة هذا الحساب؟','Change this account’s active status?')),
      actions:[TextButton(onPressed:()=>Navigator.pop(context,false),child:Text(t('إلغاء','Cancel'))),FilledButton(onPressed:()=>Navigator.pop(context,true),child:Text(t('تأكيد','Confirm')))],
    ));
    if(yes!=true||!mounted)return;
    setState(()=>{saving=true,error=null});
    try{await widget.submitAction({'action':'toggle_status','id':id});await refresh();}
    catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>saving=false);}
  }
  @override Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:Text(t('دليل الحسابات','Chart of accounts')),actions:[IconButton(onPressed:loading?null:refresh,tooltip:t('تحديث','Refresh'),icon:const Icon(Icons.refresh_rounded))]),
    body:RefreshIndicator(onRefresh:refresh,child:ListView(padding:const EdgeInsets.all(14),children:[
      Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
        Text(t('إضافة حساب','Add account'),style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800)),
        const SizedBox(height:10),
        TextField(key:const Key('account-code-input'),controller:code,keyboardType:TextInputType.number,decoration:InputDecoration(labelText:t('رمز الحساب (3–6 أرقام)','Account code (3–6 digits)'))),
        const SizedBox(height:8),
        TextField(key:const Key('account-name-ar-input'),controller:nameAr,decoration:InputDecoration(labelText:t('اسم الحساب بالعربية *','Arabic account name *'))),
        const SizedBox(height:8),
        TextField(key:const Key('account-name-en-input'),controller:nameEn,decoration:InputDecoration(labelText:t('اسم الحساب بالإنجليزية','English account name'))),
        const SizedBox(height:8),
        DropdownButtonFormField<String>(value:type,decoration:InputDecoration(labelText:t('نوع الحساب','Account type')),items:['asset','liability','equity','revenue','expense'].map((v)=>DropdownMenuItem(value:v,child:Text(typeLabel(v)))).toList(),onChanged:saving?null:(v){if(v!=null)setState(()=>type=v);}),
        const SizedBox(height:12),
        FilledButton.icon(onPressed:saving?null:create,icon:const Icon(Icons.add_rounded),label:Text(t('إضافة الحساب','Create account'))),
      ]))),
      if(error!=null)Card(color:Theme.of(context).colorScheme.errorContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(error!))),
      Row(children:[Expanded(child:Text(t('الحسابات','Accounts'),style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800))),Text('${items.length}')]),
      if(loading)const Padding(padding:EdgeInsets.all(20),child:Center(child:CircularProgressIndicator())),
      if(!loading&&items.isEmpty)Padding(padding:const EdgeInsets.all(20),child:Text(t('لا توجد حسابات','No accounts found'))),
      for(final a in items)Card(key:ValueKey('account-card-${a['id']}'),child:ListTile(
        title:Text('${a['code']??''} — ${a['name_ar']??''}',style:const TextStyle(fontWeight:FontWeight.w800)),
        subtitle:Text('${a['name_en']??''}\n${typeLabel(a['account_type']?.toString()??'')} · ${money(num.tryParse(a['balance']?.toString()??'0')??0)}'),
        isThreeLine:true,
        trailing:Column(mainAxisSize:MainAxisSize.min,children:[
          Text((int.tryParse(a['is_active']?.toString()??'0')??0)==1?t('نشط','Active'):t('موقوف','Inactive'),style:const TextStyle(fontSize:10,fontWeight:FontWeight.w800)),
          IconButton(key:Key('account-toggle-${a['id']}'),tooltip:t('تغيير الحالة','Toggle status'),onPressed:saving?null:()=>toggle(a),icon:const Icon(Icons.toggle_on_rounded)),
        ]),
      )),
    ])),
  );
}
