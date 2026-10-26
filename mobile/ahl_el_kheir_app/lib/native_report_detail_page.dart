import 'package:flutter/material.dart';

class NativeReportDetailPage extends StatefulWidget {
  const NativeReportDetailPage({super.key, required this.reportKey, required this.english, required this.loadReport});
  final String reportKey;
  final bool english;
  final Future<Map<String, dynamic>> Function(String report, String type, String from, String to) loadReport;
  @override
  State<NativeReportDetailPage> createState() => _NativeReportDetailPageState();
}
class _NativeReportDetailPageState extends State<NativeReportDetailPage> {
  late String type;
  late DateTime from;
  late DateTime to;
  bool loading = true;
  String? error;
  Map<String, dynamic> summary = {};
  List<Map<String, dynamic>> rows = [];
  String t(String ar, String en) => widget.english ? en : ar;
  static const types = <String,List<String>>{
    'financial':['income_expense','cashflow','treasury','disbursement','returns_voids'],
    'sponsorship':['coverage','retention','distribution','status_summary'],
    'operational':['supervisor','nanny','verification'],
    'hr':['headcount','attendance','leaves','payroll'],
  };
  String label(String key) => ({
    'income_expense':t('الإيرادات والمصروفات','Income and expenses'),'cashflow':t('التدفق النقدي','Cash flow'),
    'treasury':t('أرصدة الخزينة','Treasury balances'),'disbursement':t('التحويلات الشهرية','Monthly disbursements'),
    'returns_voids':t('الإرجاعات والإبطالات','Returns and voids'),'coverage':t('تغطية الكفالات','Sponsorship coverage'),
    'retention':t('استمرارية الرعاة','Sponsor retention'),'distribution':t('توزيع المشرفين','Supervisor distribution'),
    'status_summary':t('ملخص حالة الكفالات','Sponsorship status summary'),'supervisor':t('أداء المشرفين','Supervisor performance'),
    'nanny':t('أداء الحاضنات','Nanny performance'),'verification':t('التحقق من الأسر','Family verification'),
    'headcount':t('القوى العاملة','Headcount'),'attendance':t('الحضور','Attendance'),
    'leaves':t('الإجازات','Leave report'),'payroll':t('الرواتب','Payroll report'),
  })[key] ?? key;
  String field(String key) => ({
    'month':t('الشهر','Month'),'sponsorship_income':t('دخل الكفالات','Sponsorship income'),
    'donation_income':t('التبرعات','Donations'),'admin_fee_income':t('الرسوم الإدارية','Admin fees'),
    'total_income':t('إجمالي الإيرادات','Total income'),'total_expense':t('إجمالي المصروفات','Total expenses'),
    'inflow':t('الوارد','Inflow'),'outflow':t('الصادر','Outflow'),'code':t('الرمز','Code'),
    'name_ar':t('الاسم','Name'),'name_en':'English name','balance':t('الرصيد','Balance'),
    'nanny_name':t('الحاضنة','Nanny'),'families_count':t('الأسر','Families'),'total_amount':t('المبلغ','Amount'),
    'paid_count':t('المدفوع','Paid'),'returned_amount':t('المبلغ المرتجع','Returned amount'),
    'disbursement_status':t('حالة الدفعة','Batch status'),'type':t('النوع','Type'),'id':'ID',
    'reason':t('السبب','Reason'),'amount':t('المبلغ','Amount'),'date':t('التاريخ','Date'),
    'entity_name':t('الاسم','Name'),'city':t('المدينة','City'),'families':t('الأسر','Families'),
    'sponsored_children':t('الأطفال المكفولون','Sponsored children'),'status':t('الحالة','Status'),
    'sponsor_count':t('الرعاة','Sponsors'),'active_count':t('النشطون','Active'),
    'sponsorship_count':t('عدد الكفالات','Sponsorships'),'monthly_commitment':t('الالتزام الشهري','Monthly commitment'),
    'full_name':t('الاسم','Name'),'total_sponsors':t('إجمالي الرعاة','Total sponsors'),
    'active_sponsors':t('الرعاة النشطون','Active sponsors'),'total_collected':t('إجمالي التحصيل','Total collected'),
    'transaction_count':t('المعاملات','Transactions'),'disbursement_count':t('عدد الدفعات','Batches'),
    'paid_families':t('أسر مدفوعة','Paid families'),'returned_families':t('أسر مرتجعة','Returned families'),
    'pending_families':t('أسر معلقة','Pending families'),'total_families':t('إجمالي الأسر','Total families'),
    'orphan_verified':t('اليتيم متحقق','Orphan verified'),'mother_verified':t('الأم متحقق منها','Mother verified'),
    'bank_verified':t('البنك متحقق','Bank verified'),'fully_verified':t('مكتمل التحقق','Fully verified'),
    'employee_count':t('الموظفون','Employees'),'total_employees':t('إجمالي الموظفين','Total employees'),
    'active_employees':t('الموظفون النشطون','Active employees'),'total_days':t('أيام الحضور','Days'),
    'present':t('حاضر','Present'),'absent':t('غائب','Absent'),'late':t('متأخر','Late'),
    'half_day':t('نصف يوم','Half day'),'on_leave':t('إجازة','On leave'),'employee_code':t('رمز الموظف','Employee code'),
    'leave_type':t('نوع الإجازة','Leave type'),'leave_count':t('عدد الإجازات','Leaves'),
    'pending':t('معلق','Pending'),'approved':t('معتمد','Approved'),'rejected':t('مرفوض','Rejected'),
    'year':t('السنة','Year'),'total_basic':t('الراتب الأساسي','Basic salary'),'total_allowances':t('البدلات','Allowances'),
    'total_deductions':t('الاستقطاعات','Deductions'),'total_overtime':t('الإضافي','Overtime'),'total_net':t('صافي الرواتب','Net payroll'),
    'draft_count':t('مسودة','Draft'),'approved_count':t('معتمد','Approved'),'paid_count':t('مدفوع','Paid'),
  })[key] ?? key;
  @override
  void initState(){super.initState();type=types[widget.reportKey]!.first;final now=DateTime.now();from=DateTime(now.year,now.month,1);to=DateTime(now.year,now.month,now.day);refresh();}
  String dateValue(DateTime d)=>d.year.toString().padLeft(4,'0')+'-'+d.month.toString().padLeft(2,'0')+'-'+d.day.toString().padLeft(2,'0');
  Future<void> refresh() async {
    setState(()=>{loading=true,error=null});
    try {
      final data=await widget.loadReport(widget.reportKey,type,dateValue(from),dateValue(to));
      if(!mounted)return;
      setState(()=>{summary=data['summary'] is Map?Map<String,dynamic>.from(data['summary'] as Map):<String,dynamic>{},
        rows=data['rows'] is List?(data['rows'] as List).whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList():<Map<String,dynamic>>[]});
    } catch(e){if(mounted)setState(()=>error=e.toString());}
    finally{if(mounted)setState(()=>loading=false);}
  }
  Future<void> pickDate(bool start) async {
    final picked=await showDatePicker(context:context,initialDate:start?from:to,firstDate:DateTime(2000),lastDate:DateTime(2100));
    if(picked==null||!mounted)return;setState(()=>{if(start)from=picked;else to=picked});
  }
  @override
  Widget build(BuildContext context){
    final title=({'financial':t('التقارير المالية','Financial reports'),'sponsorship':t('تقارير الكفالات','Sponsorship reports'),
      'operational':t('التقارير التشغيلية','Operational reports'),'hr':t('تقارير الموارد البشرية','HR reports')})[widget.reportKey]??t('التقارير','Reports');
    final keys=rows.isEmpty?<String>[]:rows.first.keys.toList();
    return Scaffold(appBar:AppBar(title:Text(title),actions:[IconButton(onPressed:loading?null:refresh,icon:const Icon(Icons.refresh_rounded))]),
      body:RefreshIndicator(onRefresh:refresh,child:ListView(padding:const EdgeInsets.all(16),children:[
        Card(child:Padding(padding:const EdgeInsets.all(14),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          DropdownButtonFormField<String>(initialValue:type,isExpanded:true,decoration:InputDecoration(labelText:t('نوع التقرير','Report type'),border:const OutlineInputBorder()),
            items:types[widget.reportKey]!.map((v)=>DropdownMenuItem<String>(value:v,child:Text(label(v)))).toList(),
            onChanged:loading?null:(v){if(v!=null)setState(()=>type=v);}),
          const SizedBox(height:10),Wrap(spacing:8,runSpacing:8,children:[
            OutlinedButton(onPressed:()=>pickDate(true),child:Text(t('من: ','From: ')+dateValue(from))),
            OutlinedButton(onPressed:()=>pickDate(false),child:Text(t('إلى: ','To: ')+dateValue(to))),
            FilledButton(onPressed:loading?null:refresh,child:Text(t('تطبيق','Apply'))),
          ]),if(loading)const Padding(padding:EdgeInsets.only(top:10),child:LinearProgressIndicator()),
        ]))),
        if(error!=null)Card(color:Theme.of(context).colorScheme.errorContainer,child:Padding(padding:const EdgeInsets.all(12),child:Text(error!))),
        if(summary.isNotEmpty)GridView.count(crossAxisCount:MediaQuery.sizeOf(context).width>650?3:2,shrinkWrap:true,physics:const NeverScrollableScrollPhysics(),
          childAspectRatio:1.8,mainAxisSpacing:8,crossAxisSpacing:8,children:summary.entries.map((e)=>Card(child:Padding(padding:const EdgeInsets.all(12),
            child:Column(crossAxisAlignment:CrossAxisAlignment.start,mainAxisAlignment:MainAxisAlignment.center,children:[
              Text(field(e.key),style:Theme.of(context).textTheme.labelMedium),const SizedBox(height:5),
              Text(e.value?.toString()??'—',style:Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight:FontWeight.w800),maxLines:2,overflow:TextOverflow.ellipsis),
            ])))).toList()),
        const SizedBox(height:12),Text(t('النتائج','Results')+' ('+rows.length.toString()+')',style:Theme.of(context).textTheme.titleMedium),
        if(rows.isEmpty&&!loading)Card(child:Padding(padding:const EdgeInsets.all(20),child:Center(child:Text(t('لا توجد نتائج ضمن الفترة المحددة.','No results for the selected period.'))))),
        if(rows.isNotEmpty)Card(child:SingleChildScrollView(scrollDirection:Axis.horizontal,child:DataTable(
          columns:keys.map((k)=>DataColumn(label:Text(field(k)))).toList(),
          rows:rows.map((row)=>DataRow(cells:keys.map((k)=>DataCell(ConstrainedBox(constraints:const BoxConstraints(maxWidth:240),
            child:SelectableText(row[k]?.toString()??'—',maxLines:3)))).toList())).toList(),
        ))),
      ])));
  }
}
