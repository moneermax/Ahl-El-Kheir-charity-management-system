(function(){
'use strict';

function getEmojiButton(){
    return document.querySelector('.msg-tool[aria-label="الرموز التعبيرية"]');
}

function positionEmojiPicker(picker){
    if(!picker || !picker.classList.contains('open')) return;
    var button=getEmojiButton();
    if(!button) return;
    var rect=button.getBoundingClientRect();
    var gap=10;
    var width=Math.min(380,window.innerWidth-24);
    var height=Math.min(430,window.innerHeight-24);
    var left=rect.left;
    var top=rect.top-height-gap;

    picker.style.width=width+'px';
    picker.style.maxWidth='none';
    picker.style.maxHeight=height+'px';
    picker.style.left='0px';
    picker.style.top='0px';
    picker.style.bottom='auto';

    /* Measure after dimensions are applied, then place above the button. */
    var actualHeight=Math.min(picker.scrollHeight,height);
    top=rect.top-actualHeight-gap;
    if(top<12) top=rect.bottom+gap;
    if(top+actualHeight>window.innerHeight-12) top=Math.max(12,window.innerHeight-actualHeight-12);
    if(left+width>window.innerWidth-12) left=window.innerWidth-width-12;
    if(left<12) left=12;

    picker.style.left=Math.round(left)+'px';
    picker.style.top=Math.round(top)+'px';
}

function floatEmojiPicker(picker){
    if(!picker) return;

    /* Move the picker to BODY. This is the important fix: no reply box,
       overlay, transform, or overflow:hidden parent can clip it. */
    if(picker.parentElement!==document.body){
        document.body.appendChild(picker);
    }

    picker.dataset.floatingFixed='1';
    picker.style.position='fixed';
    picker.style.zIndex='2147483000';
    picker.style.boxSizing='border-box';
    picker.style.overflow='hidden';
    picker.style.pointerEvents='auto';

    var grid=picker.querySelector('.msg-emoji-grid');
    if(grid){
        grid.style.maxHeight='330px';
        grid.style.overflowY='auto';
        grid.style.overflowX='hidden';
        grid.style.webkitOverflowScrolling='touch';
    }

    positionEmojiPicker(picker);
}

function clean(){
    document.querySelectorAll('.msg-compose-attachment').forEach(function(el){el.style.display='none';});
    document.querySelectorAll('.msg-emoji-picker').forEach(floatEmojiPicker);
}

function injectUiFixes(){
    if(document.getElementById('messagingUiFinalFixes')) return;
    var style=document.createElement('style');
    style.id='messagingUiFinalFixes';
    style.textContent=''
        +'.msg-emoji-picker{position:fixed!important;display:none!important;visibility:hidden!important;z-index:2147483000!important;overflow:hidden!important;box-sizing:border-box!important;pointer-events:auto!important;}'
        +'.msg-emoji-picker.open{display:block!important;visibility:visible!important;}'
        +'.msg-emoji-grid{max-height:330px!important;overflow-y:auto!important;overflow-x:hidden!important;}'
        +'.msg-dialog{width:min(1020px,calc(100vw - 90px))!important;}'
        +'.msg-thread{padding-inline:28px!important;}'
        +'.msg-root,.msg-reply-inner{max-width:820px!important;}'
        +'.msg-bubble{max-width:min(72%,700px)!important;}'
        +'.msg-reply{padding-inline:20px!important;}'
        +'@media(max-width:767px){.msg-dialog{width:calc(100vw - 20px)!important;}.msg-root,.msg-reply-inner{max-width:none!important;}.msg-bubble{max-width:92%!important;}.msg-thread{padding-inline:12px!important;}}';
    document.head.appendChild(style);
}

var observer=new MutationObserver(function(mutations){
    var found=false;
    mutations.forEach(function(m){
        m.addedNodes.forEach(function(node){
            if(node.nodeType!==1) return;
            if(node.matches && node.matches('.msg-emoji-picker')){floatEmojiPicker(node);found=true;}
            if(node.querySelectorAll){node.querySelectorAll('.msg-emoji-picker').forEach(function(p){floatEmojiPicker(p);found=true;});}
        });
    });
    if(found) setTimeout(clean,0);
});

observer.observe(document.documentElement,{childList:true,subtree:true});

window.addEventListener('resize',function(){
    document.querySelectorAll('.msg-emoji-picker.open').forEach(positionEmojiPicker);
});
window.addEventListener('scroll',function(){
    document.querySelectorAll('.msg-emoji-picker.open').forEach(positionEmojiPicker);
},true);

document.addEventListener('click',function(event){
    var picker=event.target.closest ? event.target.closest('.msg-emoji-picker') : null;
    if(picker){
        setTimeout(function(){floatEmojiPicker(picker);positionEmojiPicker(picker);},0);
        return;
    }
    var button=event.target.closest ? event.target.closest('.msg-tool[aria-label="الرموز التعبيرية"]') : null;
    if(button){
        setTimeout(function(){
            document.querySelectorAll('.msg-emoji-picker.open').forEach(function(p){
                floatEmojiPicker(p);
                positionEmojiPicker(p);
            });
        },0);
    }
});

injectUiFixes();
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',clean);else clean();
})();
