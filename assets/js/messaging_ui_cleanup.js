(function(){
'use strict';

function positionEmojiPicker(picker){
    if(!picker || !picker.classList.contains('open')) return;
    var button = document.querySelector('.msg-tool[aria-label="الرموز التعبيرية"]');
    if(!button) return;
    var rect = button.getBoundingClientRect();
    var gap = 8;
    var width = Math.min(380, window.innerWidth - 24);
    var left = rect.left;
    var top = rect.top - gap;
    picker.style.width = width + 'px';
    picker.style.left = Math.max(12, Math.min(left, window.innerWidth - width - 12)) + 'px';
    picker.style.top = 'auto';
    picker.style.bottom = Math.max(12, window.innerHeight - rect.top + gap) + 'px';
}

function fixEmojiPicker(picker){
    if(!picker || picker.dataset.floatingFixed==='1') return;
    picker.dataset.floatingFixed='1';
    picker.style.position='fixed';
    picker.style.zIndex='100000';
    picker.style.maxHeight='min(430px, calc(100vh - 90px))';
    var grid=picker.querySelector('.msg-emoji-grid');
    if(grid){
        grid.style.maxHeight='320px';
        grid.style.overflowY='auto';
        grid.style.overflowX='hidden';
    }
    positionEmojiPicker(picker);
}

function clean(){
    document.querySelectorAll('.msg-compose-attachment').forEach(function(el){el.style.display='none';});
    document.querySelectorAll('.msg-emoji-picker').forEach(fixEmojiPicker);
}

var observer=new MutationObserver(function(mutations){
    mutations.forEach(function(m){
        m.addedNodes.forEach(function(node){
            if(node.nodeType!==1) return;
            if(node.matches && node.matches('.msg-emoji-picker')) fixEmojiPicker(node);
            if(node.querySelectorAll) node.querySelectorAll('.msg-emoji-picker').forEach(fixEmojiPicker);
        });
    });
});

observer.observe(document.documentElement,{childList:true,subtree:true});
window.addEventListener('resize',function(){document.querySelectorAll('.msg-emoji-picker.open').forEach(positionEmojiPicker);});
window.addEventListener('scroll',function(){document.querySelectorAll('.msg-emoji-picker.open').forEach(positionEmojiPicker);},true);

document.addEventListener('click',function(event){
    var picker=event.target.closest ? event.target.closest('.msg-emoji-picker') : null;
    if(picker){
        setTimeout(function(){fixEmojiPicker(picker);positionEmojiPicker(picker);},0);
        return;
    }
    var button=event.target.closest ? event.target.closest('.msg-tool[aria-label="الرموز التعبيرية"]') : null;
    if(button){
        setTimeout(function(){document.querySelectorAll('.msg-emoji-picker.open').forEach(function(p){fixEmojiPicker(p);positionEmojiPicker(p);});},0);
    }
});

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',clean);else clean();
})();
