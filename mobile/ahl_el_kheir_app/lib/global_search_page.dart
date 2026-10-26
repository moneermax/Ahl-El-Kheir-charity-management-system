import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:pdfrx/pdfrx.dart';

typedef GlobalSearchLoader = Future<Map<String, dynamic>> Function(String type, String query, int page);
typedef GlobalSearchOpenResult = void Function(String type, Map<String, dynamic> item);
typedef ArchivedDocumentLoader = Future<Map<String, dynamic>> Function(int versionId);

class GlobalSearchPage extends StatefulWidget {
  const GlobalSearchPage({super.key, required this.roleCode, required this.english, required this.loadSearch, required this.loadDocument, required this.onOpenResult});
  final String roleCode;
  final bool english;
  final GlobalSearchLoader loadSearch;
  final ArchivedDocumentLoader loadDocument;
  final GlobalSearchOpenResult onOpenResult;
  @override State<GlobalSearchPage> createState() => _GlobalSearchPageState();
}

class _GlobalSearchPageState extends State<GlobalSearchPage> {
  final queryController = TextEditingController();
  String selectedType = '';
  bool loading = false;
  String? error;
  List<Map<String, dynamic>> items = [];
  int total = 0, page = 1, pages = 1;
  String t(String ar, String en) => widget.english ? en : ar;

  String get role => switch (widget.roleCode) {
    'sudo' || 'super_admin' => 'admin',
    'gm' => 'general_manager',
    'vgm' => 'vice_general_manager',
    'fm' || 'finance' => 'financial_manager',
    _ => widget.roleCode,
  };

  String get searchPermissionRole => switch (widget.roleCode) {
    'sudo' => 'admin',
    'gm' => 'general_manager',
    'vgm' => 'vice_general_manager',
    'fm' || 'finance' => 'financial_manager',
    'staff' => 'administration',
    _ => widget.roleCode,
  };

  List<String> get types {
    final r = role;
    final result = <String>[];
    if (['admin','vice_general_manager','general_manager','supervisor','nanny'].contains(r)) result.add('families');
    if (['admin','vice_general_manager','general_manager','supervisor','financial_manager','accountant'].contains(r)) result.add('sponsors');
    if (['admin','vice_general_manager','general_manager','supervisor','accountant'].contains(r)) result.add('sponsorships');
    // This endpoint authorizes the persisted raw role, unlike the other list APIs.
    if (['admin','financial_manager','general_manager','vice_general_manager','accountant_staff','nanny'].contains(widget.roleCode)) result.add('payments');
    if (['admin','general_manager','vice_general_manager','financial_manager','accountant','supervisor','nanny','accountant_staff','administration','projects_manager','project_supervisor','staff'].contains(searchPermissionRole)) result.add('documents');
    return result;
  }

  @override void initState() { super.initState(); if (types.isNotEmpty) selectedType = types.first; }
  @override void dispose() { queryController.dispose(); super.dispose(); }

  String typeLabel(String type) => switch(type) {
    'families' => t('الأسر والأيتام','Families & children'),
    'sponsors' => t('الكفلاء','Sponsors'),
    'sponsorships' => t('الكفالات','Sponsorships'),
    'payments' => t('التحويلات الشهرية','Monthly disbursements'),
    'documents' => t('مستندات المشاريع المؤرشفة','Archived project documents'),
    _ => type,
  };

  Future<void> search({int targetPage = 1}) async {
    if (selectedType.isEmpty) return;
    setState(() { loading = true; error = null; page = targetPage; });
    try {
      final response = await widget.loadSearch(selectedType, queryController.text.trim(), targetPage);
      if (!mounted) return;
      final raw = response['data'] is Map ? response['data'] : response;
      final data = raw is Map ? Map<String,dynamic>.from(raw) : <String,dynamic>{};
      final list = data['items'];
      setState(() {
        items = list is List ? list.whereType<Map>().map((x)=>Map<String,dynamic>.from(x)).toList() : <Map<String,dynamic>>[];
        total = int.tryParse(data['total']?.toString() ?? '0') ?? 0;
        page = int.tryParse(data['page']?.toString() ?? targetPage.toString()) ?? targetPage;
        pages = int.tryParse(data['pages']?.toString() ?? '1') ?? 1;
      });
    } catch(e) {
      if (mounted) setState(() { error = e.toString(); items = []; total = 0; pages = 1; });
    } finally { if (mounted) setState(()=>loading=false); }
  }

  void _openResult(Map<String, dynamic> item) {
    if (selectedType != 'documents') { widget.onOpenResult(selectedType, item); return; }
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ArchivedDocumentPreviewPage(document: item, english: widget.english, loadDocument: widget.loadDocument)));
  }

  String statusLabel(String value) {
    const ar = {'active':'نشط / نشطة','pending':'قيد الانتظار','paused':'موقوف','completed':'مكتمل','inactive':'غير نشط','suspended':'موقوف','cancelled':'ملغى'};
    const en = {'active':'Active','pending':'Pending','paused':'Paused','completed':'Completed','inactive':'Inactive','suspended':'Suspended','cancelled':'Cancelled'};
    return (widget.english ? en : ar)[value] ?? value;
  }

  String titleFor(Map<String,dynamic> x) => switch(selectedType) {
    'families' => (x['mother_name']?.toString() ?? '') + ' · ' + (x['family_code']?.toString() ?? ''),
    'sponsors' => (x['full_name']?.toString() ?? '') + ' · ' + (x['sponsor_code']?.toString() ?? ''),
    'payments' => '${x['month']?.toString() ?? ''} · ${t('دفعة #','Batch #')}${x['id']?.toString() ?? ''}',
    'documents' => (x['display_title']?.toString() ?? '') + ' · ' + (x['project_code']?.toString() ?? ''),
    _ => x['sponsorship_code']?.toString() ?? '',
  };

  String subtitleFor(Map<String,dynamic> x) => switch(selectedType) {
    'families' => (x['mother_phone']?.toString() ?? '') + ' · ' + t('عدد الأطفال: ','Children: ') + (x['children_count']?.toString() ?? '0') + ' · ' + statusLabel(x['status']?.toString() ?? ''),
    'sponsors' => (x['phone']?.toString() ?? '') + ' · ' + t('الكفالات النشطة: ','Active sponsorships: ') + (x['active_sponsorship_count']?.toString() ?? '0') + ' · ' + statusLabel(x['status']?.toString() ?? ''),
    'payments' => (x['group_name']?.toString() ?? '') + ' · ' + (x['nanny_name']?.toString() ?? '') + ' · ' + (x['total_amount']?.toString() ?? '0') + ' · ' + statusLabel(x['status']?.toString() ?? ''),
    'documents' => (x['project_name']?.toString() ?? '') + ' · ' + (x['original_name']?.toString() ?? '') + ' · ' + t('الإصدار ', 'Version ') + (x['version_no']?.toString() ?? '1') + ((x['is_current_version'] == true) ? t(' · حالي', ' · Current') : ''),
    _ => (x['sponsor_name']?.toString() ?? '') + ' · ' + (x['child_name']?.toString() ?? '') + ' · ' + (x['family_code']?.toString() ?? '') + ' · ' + (double.tryParse(x['monthly_amount']?.toString() ?? '0') ?? 0).toStringAsFixed(2) + ' SDG · ' + statusLabel(x['status']?.toString() ?? ''),
  };

  @override Widget build(BuildContext context) {
    final available = types;
    return Scaffold(
      appBar: AppBar(title: Text(t('البحث الشامل','Global search'))),
      body: available.isEmpty
        ? Center(child: Padding(padding: const EdgeInsets.all(28), child: Column(mainAxisSize:MainAxisSize.min, children:[
            const Icon(Icons.search_off_rounded,size:48), const SizedBox(height:14),
            Text(t('لا تتوفر فئات بحث أصلية لهذا الدور حتى الآن.','No native search categories are available for this role yet.'),textAlign:TextAlign.center),
            const SizedBox(height:8),
            Text(t('لن يتم فتح الموقع أو أي صفحة خارجية.','The website and external pages will not be opened.'),textAlign:TextAlign.center,style:Theme.of(context).textTheme.bodySmall),
          ])))
        : Column(children:[
          Padding(padding:const EdgeInsets.fromLTRB(16,16,16,8),child:DropdownButtonFormField<String>(
            initialValue:selectedType,decoration:InputDecoration(labelText:t('نطاق البحث','Search in')),
            items:available.map((v)=>DropdownMenuItem(value:v,child:Text(typeLabel(v)))).toList(),
            onChanged: loading ? null : (v) { if (v != null) setState(() { selectedType = v; items = []; total = 0; page = 1; error = null; }); },
          )),
          Padding(padding:const EdgeInsets.fromLTRB(16,4,16,8),child:TextField(
            controller:queryController,textInputAction:TextInputAction.search,onSubmitted:(_)=>search(),
            decoration:InputDecoration(labelText:t('عبارة البحث','Search term'),hintText:t('الاسم أو الرقم أو الهاتف','Name, code or phone'),prefixIcon:const Icon(Icons.search_rounded),
              suffixIcon: IconButton(tooltip: t('مسح','Clear'), onPressed: () { queryController.clear(); setState(() { items = []; total = 0; page = 1; error = null; }); }, icon: const Icon(Icons.close_rounded)),
            ),
          )),
          Padding(padding:const EdgeInsets.symmetric(horizontal:16,vertical:4),child:SizedBox(width:double.infinity,child:FilledButton.icon(onPressed:loading?null:()=>search(),icon:const Icon(Icons.search_rounded),label:Text(t('بحث','Search'))))),
          Padding(padding:const EdgeInsets.symmetric(horizontal:16,vertical:8),child:Align(alignment:AlignmentDirectional.centerStart,child:Text(t('النتائج: ','Results: ')+total.toString(),style:Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight:FontWeight.w800)))),
          Expanded(child:loading?const Center(child:CircularProgressIndicator()):error!=null
            ? Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(mainAxisSize:MainAxisSize.min,children:[Text(error!,textAlign:TextAlign.center),const SizedBox(height:12),OutlinedButton(onPressed:()=>search(targetPage:page),child:Text(t('إعادة المحاولة','Try again')))])))
            : items.isEmpty?Center(child:Padding(padding:const EdgeInsets.all(24),child:Text(t('أدخل عبارة بحث لعرض النتائج.','Enter a search term to see results.'),textAlign:TextAlign.center)))
            : RefreshIndicator(onRefresh:()=>search(targetPage:page),child:ListView.separated(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.fromLTRB(16,4,16,16),itemCount:items.length,separatorBuilder:(_,__)=>const SizedBox(height:8),itemBuilder:(context,index){
              final item=items[index];
              return Card(child:ListTile(
                leading:CircleAvatar(child:Icon(switch(selectedType){'families'=>Icons.family_restroom_rounded,'sponsors'=>Icons.volunteer_activism_rounded,'payments'=>Icons.account_balance_wallet_rounded,'documents'=>Icons.description_rounded,_=>Icons.handshake_rounded})),
                title:Text(titleFor(item),maxLines:2,overflow:TextOverflow.ellipsis),
                subtitle:Text(subtitleFor(item),maxLines:3,overflow:TextOverflow.ellipsis),
                trailing:const Icon(Icons.chevron_right_rounded),
                onTap:()=>_openResult(item),
              ));
            })),
          ),
          if(!loading&&error==null&&pages>1) SafeArea(top:false,child:Padding(padding:const EdgeInsets.all(12),child:Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[
            OutlinedButton(onPressed:page>1?()=>search(targetPage:page-1):null,child:Text(t('السابق','Previous'))),
            Text(t('صفحة ','Page ')+page.toString()+t(' من ',' of ')+pages.toString()),
            OutlinedButton(onPressed:page<pages?()=>search(targetPage:page+1):null,child:Text(t('التالي','Next'))),
          ]))),
          Padding(padding:const EdgeInsets.fromLTRB(16,0,16,14),child:Text(t('تُعرض المستندات حسب صلاحيات الوصول إلى المشروع.','Documents are filtered by project access permissions.'),textAlign:TextAlign.center,style:Theme.of(context).textTheme.bodySmall)),
        ]),
    );
  }
}

class ArchivedDocumentPreviewPage extends StatefulWidget {
  const ArchivedDocumentPreviewPage({super.key, required this.document, required this.english, required this.loadDocument});
  final Map<String, dynamic> document;
  final bool english;
  final ArchivedDocumentLoader loadDocument;
  @override State<ArchivedDocumentPreviewPage> createState() => _ArchivedDocumentPreviewPageState();
}
class _ArchivedDocumentPreviewPageState extends State<ArchivedDocumentPreviewPage> {
  bool loading = true, saving = false;
  String? error;
  Uint8List? bytes;
  String mime = 'application/octet-stream';
  String filename = 'archived-document';
  String t(String ar, String en) => widget.english ? en : ar;
  @override void initState() { super.initState(); load(); }
  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final id = int.tryParse(widget.document['version_id']?.toString() ?? '');
      if (id == null || id < 1) throw StateError(t('معرّف الإصدار غير صالح.','Invalid document version ID.'));
      final result = await widget.loadDocument(id);
      final value = result['bytes'];
      if (value is! Uint8List) throw StateError(t('تعذر قراءة الملف.','Could not read the document.'));
      if (!mounted) return;
      setState(() { bytes = value; mime = (result['mime']?.toString() ?? 'application/octet-stream').toLowerCase(); filename = result['filename']?.toString() ?? 'archived-document'; });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  Future<void> saveFile() async {
    final value = bytes;
    if (value == null || saving) return;
    setState(() => saving = true);
    try {
      final path = await FilePicker.platform.saveFile(dialogTitle: t('حفظ المستند','Save document'), fileName: filename, bytes: value);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(path == null || path.isEmpty ? t('تم إلغاء الحفظ.','Save cancelled.') : t('تم حفظ المستند.','Document saved.'))));
    } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()))); }
    finally { if (mounted) setState(() => saving = false); }
  }
  @override Widget build(BuildContext context) {
    final title = widget.document['display_title']?.toString() ?? t('المستند المؤرشف','Archived document');
    final canImage = mime.startsWith('image/') && bytes != null;
    final canPdf = mime == 'application/pdf' && bytes != null;
    return Scaffold(
      appBar: AppBar(title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis), actions: [
        IconButton(tooltip: t('حفظ نسخة','Save a copy'), onPressed: bytes == null || saving ? null : saveFile, icon: saving ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.download_rounded)),
      ]),
      body: loading ? const Center(child: CircularProgressIndicator())
        : error != null ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(error!, textAlign: TextAlign.center), const SizedBox(height: 12),
            OutlinedButton.icon(onPressed: load, icon: const Icon(Icons.refresh_rounded), label: Text(t('إعادة المحاولة','Try again'))),
          ])))
        : canPdf ? PdfViewer.data(bytes!, sourceName: filename)
        : canImage ? InteractiveViewer(child: Center(child: Image.memory(bytes!, fit: BoxFit.contain)))
        : Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.insert_drive_file_outlined, size: 54), const SizedBox(height: 14),
            Text(t('تم العثور على المستند، لكن معاينته غير مدعومة داخل التطبيق. يمكنك حفظ نسخة على الجهاز.','The document was found, but its format cannot be previewed in-app. You can save a copy to the device.'), textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton.icon(onPressed: saving ? null : saveFile, icon: const Icon(Icons.download_rounded), label: Text(t('حفظ المستند','Save document'))),
          ]))),
    );
  }
}
