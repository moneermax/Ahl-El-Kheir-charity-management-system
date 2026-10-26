import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

class FinaCollectionPage extends StatefulWidget {
  const FinaCollectionPage({super.key, required this.english, required this.submitCollection});
  final bool english;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic>) submitCollection;
  @override
  State<FinaCollectionPage> createState() => _FinaCollectionPageState();
}

class _FinaCollectionPageState extends State<FinaCollectionPage> {
  final name = TextEditingController();
  final sponsorId = TextEditingController();
  final amount = TextEditingController();
  final date = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
  final purpose = TextEditingController();
  String sourceType = 'person';
  String method = 'cash';
  bool saving = false;
  String? error;
  String? receiptPath;
  String? receiptName;
  String t(String ar, String en) => widget.english ? en : ar;
  @override
  void dispose() { name.dispose(); sponsorId.dispose(); amount.dispose(); date.dispose(); purpose.dispose(); super.dispose(); }

  Future<void> pickReceipt() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
        allowMultiple: false,
        withData: false,
      );
      if (!mounted || result == null || result.files.isEmpty) return;
      final file = result.files.single;
      if (file.path == null || file.path!.isEmpty) {
        setState(() => error = t('تعذر الوصول إلى الملف المحدد.','Could not access the selected file.'));
        return;
      }
      if (file.size <= 0 || file.size > 10 * 1024 * 1024) {
        setState(() => error = t('يجب ألا يتجاوز الإيصال 10 ميجابايت.','Receipt must be no larger than 10 MB.'));
        return;
      }
      setState(() { receiptPath = file.path; receiptName = file.name; error = null; });
    } catch (_) {
      if (mounted) setState(() => error = t('تعذر اختيار ملف الإيصال.','Could not select the receipt file.'));
    }
  }

  Future<void> submit() async {
    FocusScope.of(context).unfocus();
    setState(() { saving = true; error = null; });
    try {
      final payload = <String, dynamic>{'source_type':sourceType,'source_name':name.text.trim(),'amount':amount.text.trim(),'method':method,'date':date.text.trim(),'purpose_note':purpose.text.trim()};
      if (receiptPath != null) payload['_receipt_path'] = receiptPath;
      if (sourceType == 'sponsor') {
        final id = int.tryParse(sponsorId.text.trim());
        if (id == null || id < 1) throw Exception(t('أدخل رقم كفيل صحيحاً.','Enter a valid sponsor ID.'));
        payload['sponsor_id'] = id;
      }
      final result = await widget.submitCollection(payload);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(t('تم تسجيل التحصيل وإرساله للمراجعة.','Collection submitted for review.'))));
      name.clear(); sponsorId.clear(); amount.clear(); purpose.clear();
      setState(() { receiptPath = null; receiptName = null; });
    } catch(e) { if(mounted) setState(()=>error=e.toString()); }
    finally { if(mounted) setState(()=>saving=false); }
  }

  Widget field(TextEditingController c,String ar,String en,{bool numeric=false}) => TextField(
    controller:c, keyboardType:numeric?const TextInputType.numberWithOptions(decimal:true):TextInputType.text,
    textDirection:numeric?TextDirection.ltr:null,
    decoration:InputDecoration(labelText:t(ar,en),border:const OutlineInputBorder()),
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar:AppBar(title:Text(t('تسجيل تحصيل فينا الخير','Register Fina collection'))),
    body:ListView(padding:const EdgeInsets.all(16),children:[
      Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
        Text(t('تحصيل مستقل لصالح فينا الخير','Standalone Fina collection'),style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800)),
        const SizedBox(height:8),
        Text(t('يظل الطلب معلقاً حتى المراجعة المالية ولا ينشئ قيداً قبل الاعتماد.','The collection stays pending and creates no journal entry before finance approval.')),
        const SizedBox(height:12),
        DropdownButtonFormField<String>(initialValue:sourceType,decoration:InputDecoration(labelText:t('نوع المصدر','Source type'),border:const OutlineInputBorder()),items:[
          DropdownMenuItem(value:'person',child:Text(t('شخص خارجي','External person'))),
          DropdownMenuItem(value:'organization',child:Text(t('منظمة / جهة','Organization'))),
          DropdownMenuItem(value:'other',child:Text(t('أخرى','Other'))),
          DropdownMenuItem(value:'sponsor',child:Text(t('كفيل مسجل','Registered sponsor'))),
        ],onChanged:saving?null:(v){if(v!=null)setState(()=>sourceType=v);}),
        const SizedBox(height:10),
        if(sourceType=='sponsor') field(sponsorId,'رقم الكفيل بالنظام','Sponsor ID',numeric:true) else field(name,'اسم مصدر الأموال *','Funds source name *'),
        const SizedBox(height:10),field(amount,'المبلغ *','Amount *',numeric:true),
        const SizedBox(height:10),
        DropdownButtonFormField<String>(initialValue:method,decoration:InputDecoration(labelText:t('طريقة الدفع','Payment method'),border:const OutlineInputBorder()),items:[
          DropdownMenuItem(value:'cash',child:Text(t('نقداً','Cash'))),
          DropdownMenuItem(value:'bank_transfer',child:Text(t('تحويل بنكي','Bank transfer'))),
          DropdownMenuItem(value:'credit_card',child:Text(t('بطاقة ائتمان','Credit card'))),
          DropdownMenuItem(value:'mobile',child:Text(t('محفظة / هاتف','Mobile wallet'))),
          DropdownMenuItem(value:'other',child:Text(t('أخرى','Other'))),
        ],onChanged:saving?null:(v){if(v!=null)setState(()=>method=v);}),
        const SizedBox(height:10),field(date,'التاريخ (YYYY-MM-DD)','Date (YYYY-MM-DD)'),
        const SizedBox(height:10),field(purpose,'الغرض / الملاحظات','Purpose / notes'),
        const SizedBox(height:10),
        OutlinedButton.icon(
          onPressed: saving ? null : pickReceipt,
          icon: const Icon(Icons.attach_file),
          label: Text(t('إرفاق إيصال (اختياري)','Attach receipt (optional)')),
        ),
        if (receiptName != null) Padding(
          padding: const EdgeInsets.only(top: 6),
          child: Row(children: [
            const Icon(Icons.description_outlined, size: 18),
            const SizedBox(width: 6),
            Expanded(child: Text(receiptName!, maxLines: 2, overflow: TextOverflow.ellipsis)),
            IconButton(
              tooltip: t('إزالة الإيصال','Remove receipt'),
              onPressed: saving ? null : () => setState(() { receiptPath = null; receiptName = null; }),
              icon: const Icon(Icons.close),
            ),
          ]),
        ),
        Text(t('الصيغ المقبولة JPG أو PNG أو PDF، بحد أقصى 10 ميجابايت.','Accepted formats: JPG, PNG, or PDF, up to 10 MB.'),style:Theme.of(context).textTheme.bodySmall),
        if(error!=null) Text(error!,style:TextStyle(color:Theme.of(context).colorScheme.error)),
        const SizedBox(height:12),
        FilledButton(onPressed:saving?null:submit,child:Text(t('إرسال للمراجعة المالية','Submit for finance review'))),
      ]))),
    ]),
  );
}
