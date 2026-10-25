import 'package:flutter/material.dart';

class NativeToolPage extends StatelessWidget {
  const NativeToolPage({
    super.key,
    required this.english,
    required this.title,
    this.detail,
  });

  final bool english;
  final String title;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(22),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.phone_android_rounded,
                    size: 38, color: Theme.of(context).colorScheme.primary),
                const SizedBox(height: 16),
                Text(
                  english ? 'Native mobile workflow pending' : 'هذه الوظيفة لم تُنفذ داخل التطبيق بعد',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 10),
                Text(
                  english
                      ? 'To protect your workflow, this app will not hand off to the website or open another application. This feature must be implemented here with the system’s existing permissions and business rules.'
                      : 'حفاظاً على سير العمل، لن يحوّل التطبيق إلى الموقع ولن يفتح تطبيقاً خارجياً. يجب تنفيذ هذه الوظيفة هنا داخل التطبيق مع الالتزام بصلاحيات النظام وقواعد العمل الحالية.',
                  style: TextStyle(color: muted, height: 1.55),
                ),
                if (detail != null && detail!.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Text(
                    english ? 'Requested record: $detail' : 'السجل المطلوب: $detail',
                    style: TextStyle(color: muted, fontSize: 12),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
