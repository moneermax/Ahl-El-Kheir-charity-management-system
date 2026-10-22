<?php
$akAiCsrf = function_exists('csrf_token') ? csrf_token() : '';
?>
<div id="ak-ai-assistant" data-endpoint="<?php echo e(APP_URL . 'modules/ai_assistant/chat.php'); ?>" data-csrf="<?php echo e($akAiCsrf); ?>" data-lang="<?php echo e(AK_LANG); ?>">
  <button type="button" id="ak-ai-launcher" aria-controls="ak-ai-panel" aria-expanded="false"><i class="fa-solid fa-comments" aria-hidden="true"></i><span><?php echo e(AK_LANG === 'ar' ? 'مساعد أهل الخير' : 'Ahl El Kheir assistant'); ?></span></button>
  <section id="ak-ai-panel" role="dialog" aria-labelledby="ak-ai-title" hidden>
    <header><strong id="ak-ai-title"><?php echo e(AK_LANG === 'ar' ? 'مساعد أهل الخير' : 'Ahl El Kheir assistant'); ?></strong><div class="ak-ai-header-actions"><button type="button" id="ak-ai-history-toggle"><?php echo e(AK_LANG === 'ar' ? 'سجل المحادثات' : 'Chat history'); ?></button><button type="button" id="ak-ai-new-chat"><?php echo e(AK_LANG === 'ar' ? 'محادثة جديدة' : 'New chat'); ?></button><button type="button" id="ak-ai-close" aria-label="<?php echo e(AK_LANG === 'ar' ? 'إغلاق' : 'Close'); ?>">×</button></div></header>
    <div id="ak-ai-history" hidden><div id="ak-ai-history-items"></div></div>
    <div id="ak-ai-messages" aria-live="polite"><div class="ak-ai-message"><?php echo e(AK_LANG === 'ar' ? 'مرحباً! اسأل عن استخدام النظام.' : 'Hello! Ask about using the system.'); ?></div></div>
    <form id="ak-ai-form"><label class="ak-ai-consent"><input type="checkbox" id="ak-ai-consent" checked> <?php echo e(AK_LANG === 'ar' ? 'أوافق على إرسال السؤال العام غير السري إلى Google' : 'Allow sending this general, non-confidential question to Google'); ?></label><div class="ak-ai-compose"><textarea id="ak-ai-question" rows="2" maxlength="1000" required></textarea><button type="button" id="ak-ai-mic" disabled aria-label="<?php echo e(AK_LANG === 'ar' ? 'إدخال صوتي' : 'Voice input'); ?>">🎙</button><button type="submit" id="ak-ai-send"><?php echo e(AK_LANG === 'ar' ? 'إرسال' : 'Send'); ?></button></div></form>
    <div id="ak-ai-voice-consent" role="dialog" aria-labelledby="ak-ai-voice-consent-title" hidden>
      <strong id="ak-ai-voice-consent-title"><?php echo e(AK_LANG === 'ar' ? 'الموافقة على الإدخال الصوتي' : 'Allow voice input'); ?></strong>
      <p><?php echo e(AK_LANG === 'ar' ? 'سيُسجَّل صوتك بعد موافقتك، ثم يُرفع إلى Google Gemini عبر خادم أهل الخير لتحويله إلى نص. قد تحتفظ Google بالملف الصوتي مؤقتاً لمدة تصل إلى 48 ساعة وفق خدمة الملفات المستخدمة. لن يُحفظ التسجيل في سجل محادثات أهل الخير؛ سيظهر النص الناتج في مربع السؤال لتراجعه وتعدّله قبل الإرسال. يمكنك الإلغاء والكتابة بدلاً من ذلك.' : 'After you agree, your voice will be recorded and uploaded to Google Gemini through the Ahl El Kheir server for transcription. Google may retain the audio file temporarily for up to 48 hours under the file service used. Ahl El Kheir will not save the recording in chat history; the resulting text will appear in the question box for you to review and edit before sending. You can cancel and type instead.'); ?></p>
      <div class="ak-ai-voice-consent-actions"><button type="button" id="ak-ai-voice-allow"><?php echo e(AK_LANG === 'ar' ? 'أوافق وابدأ الإدخال الصوتي' : 'Agree and start voice input'); ?></button><button type="button" id="ak-ai-voice-cancel"><?php echo e(AK_LANG === 'ar' ? 'إلغاء واستخدام الكتابة' : 'Cancel and type instead'); ?></button></div>
    </div>
  </section>
</div>


<style>
#ak-ai-assistant{position:fixed;z-index:1500;inset-inline-end:20px;bottom:20px;font-family:'Cairo',sans-serif;color:#18314f}
#ak-ai-launcher,#ak-ai-send{border:0;border-radius:999px;padding:11px 16px;background:#174d8e;color:#fff;font-weight:700;box-shadow:0 6px 24px #102f5533;cursor:pointer}#ak-ai-launcher{display:inline-flex;align-items:center;gap:9px}#ak-ai-launcher i{font-size:1.1rem}#ak-ai-launcher{background:linear-gradient(135deg,#ffe59a 0%,#e6b52e 55%,#c58b10 100%);color:#352707;border:1px solid #bd8610;box-shadow:0 7px 25px #a8791f66}#ak-ai-launcher:hover{background:linear-gradient(135deg,#ffedb7 0%,#f0c64b 55%,#d69b16 100%);box-shadow:0 9px 28px #a8791f80}#ak-ai-launcher:focus-visible{outline:3px solid #174d8e;outline-offset:3px}
#ak-ai-panel{position:absolute;inset-inline-end:0;bottom:60px;width:min(390px,calc(100vw - 24px));height:min(560px,calc(100dvh - 100px));display:flex;flex-direction:column;background:#fff;border:1px solid #d3dce7;border-radius:15px;overflow:hidden;box-shadow:0 16px 50px #102f5533}
#ak-ai-panel[hidden]{display:none}#ak-ai-panel header{display:flex;align-items:center;justify-content:space-between;gap:8px;background:#174d8e;color:#fff;padding:12px 14px}.ak-ai-header-actions{display:flex;align-items:center;gap:5px;flex-wrap:wrap;justify-content:flex-end}.ak-ai-header-actions button{border:1px solid #ffffff66;border-radius:7px;background:#ffffff1a;color:#fff;padding:5px 7px;font:inherit;font-size:.68rem;cursor:pointer}.ak-ai-header-actions button:hover{background:#ffffff33}#ak-ai-history{max-height:155px;overflow:auto;border-bottom:1px solid #dce4ed;background:#f2f6fb;padding:7px}#ak-ai-history[hidden]{display:none}#ak-ai-history-items{display:flex;flex-direction:column;gap:5px}.ak-ai-history-item{width:100%;text-align:start;border:1px solid #d8e1ec;border-radius:7px;background:#fff;color:#18314f;padding:7px 9px;font:inherit;font-size:.75rem;cursor:pointer;overflow-wrap:anywhere}.ak-ai-history-item:hover{background:#fff4cf;border-color:#e6b52e}.ak-ai-messages-empty{font-size:.78rem;color:#52657a;padding:8px}#ak-ai-mic{border:1px solid #b7c7d9;background:#fff;border-radius:8px;padding:8px;cursor:pointer}#ak-ai-mic:disabled{opacity:.4;cursor:not-allowed}#ak-ai-close{border:0;background:transparent;color:inherit;font-size:1.4rem;cursor:pointer}
#ak-ai-messages{flex:1;overflow:auto;display:flex;flex-direction:column;gap:9px;padding:12px;background:#f8fafc}.ak-ai-message{white-space:pre-wrap;overflow-wrap:anywhere;align-self:flex-start;max-width:96%;padding:9px 11px;border:1px solid #dce4ed;border-radius:11px;background:#fff;font-size:.83rem;line-height:1.7}.ak-ai-message.user{align-self:flex-end;background:#e4efff}
#ak-ai-form{padding:10px;border-top:1px solid #e0e7ef}.ak-ai-consent{display:flex;gap:7px;align-items:flex-start;font-size:.68rem;line-height:1.5;margin-bottom:8px;color:#52657a}.ak-ai-compose{display:flex;align-items:flex-end;gap:5px}.ak-ai-compose textarea{flex:1;min-width:0;resize:vertical;border:1px solid #b7c7d9;border-radius:8px;padding:8px;font:inherit;font-size:.82rem}
#ak-ai-voice-consent{padding:12px;border-top:1px solid #e0e7ef;background:#fffdf5;color:#352707;font-size:.78rem;line-height:1.7}#ak-ai-voice-consent[hidden]{display:none}#ak-ai-voice-consent p{margin:7px 0 10px}.ak-ai-voice-consent-actions{display:flex;flex-wrap:wrap;gap:7px}.ak-ai-voice-consent-actions button{border:1px solid #174d8e;border-radius:7px;padding:7px 9px;font:inherit;cursor:pointer}.ak-ai-voice-consent-actions #ak-ai-voice-allow{background:#174d8e;color:#fff}.ak-ai-voice-consent-actions #ak-ai-voice-cancel{background:#fff;color:#174d8e}
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
var historyPanel=document.getElementById('ak-ai-history'),historyItems=document.getElementById('ak-ai-history-items');
var historyToggle=document.getElementById('ak-ai-history-toggle'),newChat=document.getElementById('ak-ai-new-chat');
var currentConversationId=null;
var greeting=lang==='ar'?'مرحباً! اسأل عن استخدام النظام.':'Hello! Ask about using the system.';
function add(text,user){
 var el=document.createElement('div');el.className='ak-ai-message'+(user?' user':'');el.textContent=text;
 messages.appendChild(el);messages.scrollTop=messages.scrollHeight;return el;
}
function clearMessages(){messages.replaceChildren();add(greeting,false);}
function api(payload){return fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf,'Accept':'application/json'},body:JSON.stringify(payload)}).then(function(response){return response.json();});}
async function refreshHistory(){
 historyItems.replaceChildren();
 try{
  var data=await api({action:'history_list'});
  if(!data.ok)throw new Error(data.message||'History unavailable');
  if(!data.conversations||!data.conversations.length){
   var empty=document.createElement('div');empty.className='ak-ai-messages-empty';empty.textContent=lang==='ar'?'لا توجد محادثات محفوظة بعد.':'No saved conversations yet.';historyItems.appendChild(empty);return;
  }
  data.conversations.forEach(function(item){
   var button=document.createElement('button');button.type='button';button.className='ak-ai-history-item';button.textContent=item.title|| (lang==='ar'?'محادثة':'Conversation');
   button.addEventListener('click',function(){loadConversation(item.id);});historyItems.appendChild(button);
  });
 }catch(error){
  var failed=document.createElement('div');failed.className='ak-ai-messages-empty';failed.textContent=lang==='ar'?'تعذر تحميل السجل. تحقق من تطبيق ترحيل قاعدة البيانات.':'Could not load history. Verify the database migration is applied.';historyItems.appendChild(failed);
 }
}
async function loadConversation(id){
 historyItems.querySelectorAll('button').forEach(function(button){button.disabled=true;});
 try{
  var data=await api({action:'history_load',conversation_id:id});
  if(!data.ok)throw new Error(data.message||'Conversation unavailable');
  currentConversationId=Number(data.conversation.id);messages.replaceChildren();
  (data.messages||[]).forEach(function(item){add(item.content,item.speaker==='user');});
  if(!data.messages||!data.messages.length)add(greeting,false);
  historyPanel.hidden=true;historyToggle.setAttribute('aria-expanded','false');messages.scrollTop=messages.scrollHeight;input.focus();
 }catch(error){add(lang==='ar'?'تعذر فتح هذه المحادثة.':'Could not open this conversation.',false);}
 finally{historyItems.querySelectorAll('button').forEach(function(button){button.disabled=false;});}
}
launcher.addEventListener('click',function(){panel.hidden=!panel.hidden;launcher.setAttribute('aria-expanded',panel.hidden?'false':'true');if(!panel.hidden)input.focus();});
document.getElementById('ak-ai-close').addEventListener('click',function(){panel.hidden=true;launcher.setAttribute('aria-expanded','false');launcher.focus();});
historyToggle.addEventListener('click',function(){historyPanel.hidden=!historyPanel.hidden;historyToggle.setAttribute('aria-expanded',historyPanel.hidden?'false':'true');if(!historyPanel.hidden)refreshHistory();});
newChat.addEventListener('click',function(){currentConversationId=null;clearMessages();historyPanel.hidden=true;historyToggle.setAttribute('aria-expanded','false');input.focus();});
form.addEventListener('submit',async function(event){
 event.preventDefault();var question=input.value.trim();if(!question)return;
 add(question,true);input.value='';send.disabled=true;
 var waiting=add(lang==='ar'?'جارٍ البحث في الأدلة المحلية…':'Searching approved local guides…',false);
 try{
  var data=await api({action:'ask',question:question,conversation_id:currentConversationId,allow_google:consent.checked});
  waiting.remove();
  if(data.conversation_id)currentConversationId=Number(data.conversation_id);
  add(data.message||data.answer||'No answer returned.',false);
 }catch(error){waiting.remove();add(lang==='ar'?'تعذر الاتصال بالمساعد. حاول مرة أخرى.':'Could not connect to the assistant. Please try again.',false);}
 finally{send.disabled=false;input.focus();}
});
var mic=document.getElementById('ak-ai-mic');
var voiceConsentPanel=document.getElementById('ak-ai-voice-consent');
var voiceAllow=document.getElementById('ak-ai-voice-allow');
var voiceCancel=document.getElementById('ak-ai-voice-cancel');
var voiceConsentGranted=false;
var mediaStream=null;
var mediaRecorder=null;
var recordedChunks=[];
var recordingTimer=null;
var isRecording=false;
var maxRecordingMs=20000;
function voiceMessage(ar,en){add(lang==='ar'?ar:en,false);}
function releaseMicrophone(){
 if(recordingTimer!==null){window.clearTimeout(recordingTimer);recordingTimer=null;}
 if(mediaStream){mediaStream.getTracks().forEach(function(track){track.stop();});mediaStream=null;}
 mediaRecorder=null;
 recordedChunks=[];
 isRecording=false;
 mic.disabled=false;
 mic.textContent='🎙';
 mic.setAttribute('aria-label',lang==='ar'?'إدخال صوتي':'Voice input');
}
async function startAudioRecording(){
 if(!navigator.mediaDevices||typeof navigator.mediaDevices.getUserMedia!=='function'||typeof window.MediaRecorder==='undefined'){
  voiceMessage('التسجيل الصوتي غير مدعوم في هذا المتصفح أو الاتصال الحالي. يمكنك كتابة سؤالك.','Audio recording is not supported by this browser or current connection. You can type your question.');
  return;
 }
 try{
  mediaStream=await navigator.mediaDevices.getUserMedia({audio:true});
  var mimeCandidates=['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/ogg;codecs=opus'];
  var selectedMime='';
  for(var i=0;i<mimeCandidates.length;i++){
   if(typeof MediaRecorder.isTypeSupported!=='function'||MediaRecorder.isTypeSupported(mimeCandidates[i])){selectedMime=mimeCandidates[i];break;}
  }
  mediaRecorder=selectedMime?new MediaRecorder(mediaStream,{mimeType:selectedMime}):new MediaRecorder(mediaStream);
  recordedChunks=[];
  mediaRecorder.addEventListener('dataavailable',function(event){if(event.data&&event.data.size>0)recordedChunks.push(event.data);});
  mediaRecorder.addEventListener('stop',async function(){
   if(recordingTimer!==null){window.clearTimeout(recordingTimer);recordingTimer=null;}
   var recorder=mediaRecorder;
   var mime=(recorder&&recorder.mimeType?recorder.mimeType:'audio/webm').split(';')[0].toLowerCase();
   var blob=new Blob(recordedChunks,{type:mime});
   if(mediaStream){mediaStream.getTracks().forEach(function(track){track.stop();});mediaStream=null;}
   mediaRecorder=null;recordedChunks=[];isRecording=false;mic.disabled=true;mic.textContent='…';
   if(blob.size<1000){releaseMicrophone();voiceMessage('لم يُلتقط صوت واضح. حاول مرة أخرى أو اكتب سؤالك.','No usable audio was captured. Try again or type your question.');return;}
   if(blob.size>2400000){releaseMicrophone();voiceMessage('التسجيل طويل جداً. اضغط الميكروفون وسجّل سؤالاً أقصر.','The recording is too large. Record a shorter question.');return;}
   voiceMessage('جارٍ إرسال التسجيل إلى Google Gemini لتحويله إلى نص…','Sending the recording to Google Gemini for transcription…');
   try{
    var base64=await new Promise(function(resolve,reject){
     var reader=new FileReader();
     reader.onerror=function(){reject(new Error('read-failed'));};
     reader.onload=function(){
      var result=String(reader.result||'');
      var comma=result.indexOf(',');
      if(comma<0){reject(new Error('invalid-data-url'));return;}
      resolve(result.slice(comma+1));
     };
     reader.readAsDataURL(blob);
    });
    var data=await api({action:'transcribe_audio',audio_base64:base64,mime_type:mime});
    if(!data.ok||!data.transcript)throw new Error(data.message||'transcription-failed');
    var transcript=String(data.transcript).trim();
    if(transcript)input.value=(input.value.trim()?input.value.trim()+' ':'')+transcript;
    voiceMessage('اكتبي مراجعتك للنص ثم اضغطي إرسال عندما تكونين جاهزة.','Review or edit the transcript, then press Send when ready.');
   }catch(error){
    voiceMessage((error&&error.message&&error.message!=='transcription-failed')?error.message:'تعذر تحويل الصوت إلى نص الآن. يمكنك المحاولة مرة أخرى أو كتابة سؤالك.',
     (error&&error.message&&error.message!=='transcription-failed')?error.message:'Gemini could not transcribe the audio right now. Try again or type your question.');
   }finally{releaseMicrophone();input.focus();}
  });
  mediaRecorder.addEventListener('error',function(){
   releaseMicrophone();
   voiceMessage('حدث خطأ أثناء التسجيل. يمكنك المحاولة مرة أخرى أو كتابة سؤالك.','An error occurred while recording. Try again or type your question.');
  });
  mediaRecorder.start();
  isRecording=true;
  mic.textContent='■';
  mic.setAttribute('aria-label',lang==='ar'?'إيقاف التسجيل وإرسال الصوت للتحويل إلى نص':'Stop recording and send audio for transcription');
  voiceMessage('جارٍ التسجيل. اضغط ■ لإيقاف التسجيل وإرسال الصوت للتحويل إلى نص. سيتوقف التسجيل تلقائياً بعد 20 ثانية.','Recording. Press ■ to stop and send the audio for transcription. Recording stops automatically after 20 seconds.');
  recordingTimer=window.setTimeout(function(){if(mediaRecorder&&mediaRecorder.state==='recording')mediaRecorder.stop();},maxRecordingMs);
 }catch(error){
  releaseMicrophone();
  voiceMessage('تعذر الوصول إلى الميكروفون. تحقق من إذن المتصفح أو اكتب سؤالك.','Could not access the microphone. Check browser permission or type your question.');
 }
}
mic.disabled=!(navigator.mediaDevices&&typeof navigator.mediaDevices.getUserMedia==='function'&&typeof window.MediaRecorder!=='undefined');
mic.title=mic.disabled?(lang==='ar'?'التسجيل الصوتي غير متاح؛ يمكنك كتابة سؤالك':'Audio recording unavailable; you can type your question'):(lang==='ar'?'تسجيل السؤال صوتياً':'Record your question');
mic.addEventListener('click',function(){
 if(isRecording){
  if(mediaRecorder&&mediaRecorder.state==='recording')mediaRecorder.stop();
  return;
 }
 if(!voiceConsentGranted){
  voiceConsentPanel.hidden=false;
  voiceAllow.focus();
  return;
 }
 startAudioRecording();
});
voiceAllow.addEventListener('click',function(){
 voiceConsentGranted=true;
 voiceConsentPanel.hidden=true;
 startAudioRecording();
});
voiceCancel.addEventListener('click',function(){
 voiceConsentPanel.hidden=true;
 input.focus();
});
})(); 
</script>