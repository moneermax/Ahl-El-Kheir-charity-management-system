<?php
$akAiCsrf = function_exists('csrf_token') ? csrf_token() : '';
?>
<div id="ak-ai-assistant" data-endpoint="<?php echo e(APP_URL . 'modules/ai_assistant/chat.php'); ?>" data-csrf="<?php echo e($akAiCsrf); ?>" data-lang="<?php echo e(AK_LANG); ?>">
  <button type="button" id="ak-ai-launcher" aria-controls="ak-ai-panel" aria-expanded="false"><?php echo e(AK_LANG === 'ar' ? 'مساعد أهل الخير' : 'Ahl El Kheir assistant'); ?></button>
  <section id="ak-ai-panel" role="dialog" aria-labelledby="ak-ai-title" hidden>
    <header><strong id="ak-ai-title"><?php echo e(AK_LANG === 'ar' ? 'مساعد أهل الخير' : 'Ahl El Kheir assistant'); ?></strong><button type="button" id="ak-ai-close" aria-label="<?php echo e(AK_LANG === 'ar' ? 'إغلاق' : 'Close'); ?>">×</button></header>
    <p><?php echo e(AK_LANG === 'ar' ? 'تُبحث الأدلة المحلية أولاً. لا تُرسل الأسئلة إلى Google إلا بموافقتك.' : 'Local guides are searched first. Questions are sent to Google only with your consent.'); ?></p>
    <div id="ak-ai-messages" aria-live="polite"></div>
  </section>
</div>
