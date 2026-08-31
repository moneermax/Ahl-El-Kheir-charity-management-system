(function(){
'use strict';

var EMOJIS = ['😀','😃','😄','😁','😆','😅','😂','🤣','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😋','😛','😜','🤪','🤔','🤗','🤭','🤫','🤐','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','😯','😪','😫','🥱','😴','😌','🤓','😎','🥳','😭','😢','😤','😠','😡','🤬','😱','😨','😰','🙏','👏','👍','👎','❤️','💙','💚','💛','🧡','💜','🖤','🤝','✨','⭐','🔔','📌','✅','❌','⚠️','🎉','🌷','🌟','💡','🔥','💬'];

function getEmojiButton(){
    return document.querySelector('.msg-tool[aria-label="الرموز التعبيرية"]');
}

function getTextarea(){
    var button=getEmojiButton();
    if(!button) return null;
    var box=button.closest('.msg-reply-box');
    return box ? box.querySelector('textarea[name="body"]') : null;
}

function insertEmoji(textarea,emoji){
    if(!textarea) return;
    var start=textarea.selectionStart||0;
    var end=textarea.selectionEnd||0;
    var value=textarea.value||'';
    textarea.value=value.slice(0,start)+emoji+value.slice(end);
    var pos=start+emoji.length;
    textarea.focus();
    textarea.setSelectionRange(pos,pos);
    textarea.dispatchEvent(new Event('input',{bubbles:true}));
}

function createPortalPicker(){
    var picker=document.createElement('div');
    picker.className='msg-emoji-picker msg-emoji-picker-portal';
    picker.setAttribute('role','dialog');
    picker.setAttribute('aria-label','الرموز التعبيرية');
    picker.innerHTML='<div class="msg-emoji-head"><span><i class="far fa-face-smile"></i> الرموز التعبيرية</span><button type="button" class="msg-emoji-close" aria-label="إغلاق"><i class="fas fa-xmark"></i></button></div><div class="msg-emoji-grid"></div>';
    document.body.appendChild(picker);

    var grid=picker.querySelector('.msg-emoji-grid');
    EMOJIS.forEach(function(emoji){
        var b=document.createElement('button');
        b.type='button';
        b.className='msg-emoji';
        b.textContent=emoji;
        b.setAttribute('aria-label','إدراج '+emoji);
        b.addEventListener('mousedown',function(e){e.preventDefault();});
        b.addEventListener('click',function(e){
            e.preventDefault();
            insertEmoji(getTextarea(),emoji);
            picker.classList.remove('open');
        });
        grid.appendChild(b);
    });

    picker.querySelector('.msg-emoji-close').addEventListener('click',function(){
        picker.classList.remove('open');
        var ta=getTextarea();
        if(ta) ta.focus();
    });
    return picker;
}

function positionPicker(picker){
    if(!picker || !picker.classList.contains('open')) return;
    var button=getEmojiButton();
    if(!button) return;
    var rect=button.getBoundingClientRect();
    var gap=10;
    var width=Math.min(360,window.innerWidth-24);
    var maxHeight=Math.min(440,window.innerHeight-24);

    picker.style.width=width+'px';
    picker.style.maxHeight=maxHeight+'px';
    picker.style.left='0px';
    picker.style.top='0px';
    picker.style.bottom='auto';

    var actualHeight=Math.min(picker.scrollHeight,maxHeight);
    var left=rect.right-width;
    var top=rect.top-actualHeight-gap;

    if(top<12) top=rect.bottom+gap;
    if(top+actualHeight>window.innerHeight-12) top=Math.max(12,window.innerHeight-actualHeight-12);
    if(left<12) left=12;
    if(left+width>window.innerWidth-12) left=window.innerWidth-width-12;

    picker.style.left=Math.round(left)+'px';
    picker.style.top=Math.round(top)+'px';
}

function getPortalPicker(){
    return document.querySelector('.msg-emoji-picker-portal');
}

function togglePortalPicker(){
    var picker=getPortalPicker();
    if(!picker){
        picker=createPortalPicker();
        picker.classList.add('open');
    }else{
        picker.classList.toggle('open');
    }
    if(picker.classList.contains('open')){
        positionPicker(picker);
        var ta=getTextarea();
        if(ta) ta.focus();
    }
}

/*
 * Own the emoji-button click in the capture phase. The old reply-tools
 * implementation creates the picker inside .msg-reply-box. Intercepting
 * here prevents that old picker from ever being created, so there is no
 * parent overflow/transform that can clip the new portal picker.
 */
document.addEventListener('click',function(event){
    var button=event.target.closest ? event.target.closest('.msg-tool[aria-label="الرموز التعبيرية"]') : null;
    if(!button) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    togglePortalPicker();
},true);

document.addEventListener('click',function(event){
    var picker=event.target.closest ? event.target.closest('.msg-emoji-picker-portal') : null;
    if(picker) return;
    var button=event.target.closest ? event.target.closest('.msg-tool[aria-label="الرموز التعبيرية"]') : null;
    if(button) return;
    document.querySelectorAll('.msg-emoji-picker-portal.open').forEach(function(p){p.classList.remove('open');});
});

document.addEventListener('keydown',function(event){
    if(event.key==='Escape'){
        document.querySelectorAll('.msg-emoji-picker-portal.open').forEach(function(p){p.classList.remove('open');});
    }
});

window.addEventListener('resize',function(){
    document.querySelectorAll('.msg-emoji-picker-portal.open').forEach(positionPicker);
});
window.addEventListener('scroll',function(){
    document.querySelectorAll('.msg-emoji-picker-portal.open').forEach(positionPicker);
},true);

function removeLegacyPickers(){
    document.querySelectorAll('.msg-reply-box .msg-emoji-picker').forEach(function(p){p.remove();});
}

function injectUiFixes(){
    if(document.getElementById('messagingUiFinalFixes')) return;
    var style=document.createElement('style');
    style.id='messagingUiFinalFixes';
    style.textContent=''
        +'.msg-emoji-picker-portal{position:fixed!important;display:none!important;visibility:hidden!important;z-index:2147483647!important;box-sizing:border-box!important;overflow:hidden!important;pointer-events:auto!important;background:#fff!important;border:1px solid #d8e1ec!important;border-radius:15px!important;box-shadow:0 18px 45px rgba(15,35,65,.22)!important;padding:10px!important;}'
        +'.msg-emoji-picker-portal.open{display:block!important;visibility:visible!important;}'
        +'.msg-emoji-picker-portal .msg-emoji-grid{display:grid!important;grid-template-columns:repeat(9,1fr)!important;gap:3px!important;max-height:330px!important;overflow-y:auto!important;overflow-x:hidden!important;padding:2px!important;}'
        +'.msg-emoji-picker-portal .msg-emoji{border:0!important;background:transparent!important;border-radius:8px!important;font-size:21px!important;line-height:34px!important;height:36px!important;cursor:pointer!important;}'
        +'.msg-emoji-picker-portal .msg-emoji:hover{background:#edf3fb!important;transform:scale(1.08)!important;}'
        +'.msg-dialog{width:min(900px,calc(100vw - 100px))!important;}'
        +'.msg-thread{padding-inline:24px!important;}'
        +'.msg-root,.msg-reply-inner{max-width:740px!important;}'
        +'.msg-bubble{max-width:min(70%,640px)!important;}'
        +'.msg-reply{padding-inline:18px!important;}'
        +'@media(max-width:767px){.msg-dialog{width:calc(100vw - 20px)!important;}.msg-root,.msg-reply-inner{max-width:none!important;}.msg-bubble{max-width:92%!important;}.msg-thread{padding-inline:12px!important;}}';
    document.head.appendChild(style);
}

function clean(){
    removeLegacyPickers();
    document.querySelectorAll('.msg-compose-attachment').forEach(function(el){el.style.display='none';});
}

var observer=new MutationObserver(function(){
    removeLegacyPickers();
});
observer.observe(document.documentElement,{childList:true,subtree:true});

injectUiFixes();
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',clean); else clean();
})();