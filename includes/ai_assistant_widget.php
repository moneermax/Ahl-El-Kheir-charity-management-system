<?php
$akAiCsrf = function_exists('csrf_token') ? csrf_token() : '';
?>
<div id="ak-ai-assistant" data-endpoint="<?php echo e(APP_URL . 'modules/ai_assistant/chat.php'); ?>" data-csrf="<?php echo e($akAiCsrf); ?>" data-lang="<?php echo e(AK_LANG); ?>">
  <button type="button" id="ak-ai-launcher" aria-controls="ak-ai-panel" aria-expanded="false"><?php echo e(AK_LANG === 'ar' ? 'مساعد أهل الخير' : 'Ahl El Kheir assistant'); ?></button>
  <section id="ak-ai-panel" role="dialog" aria-labelledby="ak-ai-title" hidden>
    <header><strong id="ak-ai-title"><?php echo e(AK_LANG === 'ar' ? 'مساعد أهل الخير' : 'Ahl El Kheir assistant'); ?></strong><button type="button" id="ak-ai-close" aria-label="<?php echo e(AK_LANG === 'ar' ? 'إغلاق' : 'Close'); ?>">×</button></header>
    <p><?php echo e(AK_LANG === 'ar' ? 'تُبحث الأدلة المحلية أولاً. لا تُرسل الأسئلة إلى Google إلا بموافقتك.' : 'Local guides are searched first. Questions are sent to Google only with your consent.'); ?></p>
    <p class="ak-ai-notice"><?php echo e(AK_LANG === 'ar' ? 'لا تُرسل الأسئلة الداخلية أو الحساسة إلى Google.' : 'Internal or sensitive questions are not sent to Google.'); ?></p>
    <div id="ak-ai-messages" aria-live="polite"><div class="ak-ai-message"><?php echo e(AK_LANG === 'ar' ? 'مرحباً! اسأل عن استخدام النظام.' : 'Hello! Ask about using the system.'); ?></div></div>
    <form id="ak-ai-form"><label class="ak-ai-consent"><input type="checkbox" id="ak-ai-consent"> <?php echo e(AK_LANG === 'ar' ? 'أوافق على إرسال السؤال العام غير السري إلى Google' : 'Allow sending this general, non-confidential question to Google'); ?></label><div class="ak-ai-compose"><textarea id="ak-ai-question" rows="2" maxlength="1000" required></textarea><button type="button" id="ak-ai-mic" disabled aria-label="Microphone">🎙</button><button type="submit" id="ak-ai-send"><?php echo e(AK_LANG === 'ar' ? 'إرسال' : 'Send'); ?></button></div></form>
  </section>
</div>


<style>
#ak-ai-assistant{position:fixed;z-index:1500;inset-inline-end:20px;bottom:20px;font-family:'Cairo',sans-serif;color:#18314f}
#ak-ai-launcher,#ak-ai-send{border:0;border-radius:999px;padding:11px 16px;background:#174d8e;color:#fff;font-weight:700;box-shadow:0 6px 24px #102f5533;cursor:pointer}
#ak-ai-panel{position:absolute;inset-inline-end:0;bottom:60px;width:min(390px,calc(100vw - 24px));height:min(560px,calc(100dvh - 100px));display:flex;flex-direction:column;background:#fff;border:1px solid #d3dce7;border-radius:15px;overflow:hidden;box-shadow:0 16px 50px #102f5533}
#ak-ai-panel[hidden]{display:none}#ak-ai-panel header{display:flex;align-items:center;justify-content:space-between;background:#174d8e;color:#fff;padding:12px 14px}#ak-ai-close{border:0;background:transparent;color:inherit;font-size:1.4rem;cursor:pointer}
.ak-ai-notice{font-size:.7rem;line-height:1.6;padding:9px 12px;margin:0;background:#f0f5fa;color:#38516d}
#ak-ai-messages{flex:1;overflow:auto;display:flex;flex-direction:column;gap:9px;padding:12px;background:#f8fafc}.ak-ai-message{white-space:pre-wrap;overflow-wrap:anywhere;align-self:flex-start;max-width:96%;padding:9px 11px;border:1px solid #dce4ed;border-radius:11px;background:#fff;font-size:.83rem;line-height:1.7}.ak-ai-message.user{align-self:flex-end;background:#e4efff}
#ak-ai-form{padding:10px;border-top:1px solid #e0e7ef}.ak-ai-consent{display:flex;gap:7px;align-items:flex-start;font-size:.68rem;line-height:1.5;margin-bottom:8px;color:#52657a}.ak-ai-compose{display:flex;align-items:flex-end;gap:5px}.ak-ai-compose textarea{flex:1;min-width:0;resize:vertical;border:1px solid #b7c7d9;border-radius:8px;padding:8px;font:inherit;font-size:.82rem}
@media(max-width:600px){#ak-ai-assistant{inset-inline-end:10px;bottom:10px}#ak-ai-panel{position:fixed;inset-inline:7px;bottom:65px;width:auto;height:min(70dvh,600px)}}
</style>

<script>
(function(){
var root=document.getElementById('ak-ai-assistant');
if(!root||root.dataset.ready==='1')return;
root.dataset.ready='1';
var lang=root.dataset.lang==='ar'?'ar':'en';
var panel=document.getElementById('ak-ai-panel'),launcher=document.getElementById('ak-ai-launcher');
var input=document.getElementById('ak-ai-question'),consent=document.getElementById('ak-ai-consent');
var send=document.getElementById('ak-ai-send'),messages=document.getElementById('ak-ai-messages'),form=document.getElementById('ak-ai-form');
function add(text,user,sources){
 var el=document.createElement('div');el.className='ak-ai-message'+(user?' user':'');el.textContent=text;
 if(sources&&sources.length){var s=document.createElement('small');s.textContent=(lang==='ar'?'المصدر: ':'Source: ')+sources.join(', ');el.appendChild(document.createElement('br'));el.appendChild(s);}
 messages.appendChild(el);messages.scrollTop=messages.scrollHeight;return el;
}
launcher.addEventListener('click',function(){panel.hidden=!panel.hidden;launcher.setAttribute('aria-expanded',panel.hidden?'false':'true');if(!panel.hidden)input.focus();});
document.getElementById('ak-ai-close').addEventListener('click',function(){panel.hidden=true;launcher.setAttribute('aria-expanded','false');launcher.focus();});
form.addEventListener('submit',async function(event){
 event.preventDefault();var question=input.value.trim();if(!question)return;
 add(question,true);input.value='';send.disabled=true;
 var waiting=add(lang==='ar'?'جارٍ البحث في الأدلة المحلية…':'Searching approved local guides…',false);
 try{
  var response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf,'Accept':'application/json'},body:JSON.stringify({question:question,allow_google:consent.checked})});
  var data=await response.json();waiting.remove();add(data.message||data.answer||'No answer returned.',false,data.sources||[]);
 }catch(error){waiting.remove();add(lang==='ar'?'تعذر الاتصال بالمساعد. حاول مرة أخرى.':'Could not connect to the assistant. Please try again.',false);}
 finally{send.disabled=false;consent.checked=false;input.focus();}
});
var SpeechRecognition=window.SpeechRecognition||window.webkitSpeechRecognition;
var mic=document.getElementById('ak-ai-mic');
if(SpeechRecognition&&'processLocally' in SpeechRecognition.prototype){
 mic.disabled=false;
 mic.title=lang==='ar'?'إملاء صوتي على الجهاز':'On-device dictation';
 mic.addEventListener('click',function(){
  try{
   var recognition=new SpeechRecognition();recognition.processLocally=true;recognition.lang=lang==='ar'?'ar-EG':'en-US';recognition.interimResults=true;
   var before=input.value;
   recognition.onresult=function(event){var transcript='';for(var i=0;i<event.results.length;i++)transcript+=event.results[i][0].transcript;input.value=(before?before+' ':'')+transcript;};
   recognition.onerror=function(){add(lang==='ar'?'تعذر الإملاء المحلي؛ اكتب سؤالك بدلاً منه.':'On-device dictation unavailable; please type instead.',false);};
   recognition.start();
  }catch(error){add(lang==='ar'?'الإملاء المحلي غير متاح في هذا المتصفح.':'On-device dictation is unavailable in this browser.',false);}
 });
}
})(); 
</script>