(function(){
'use strict';
function clean(){
 document.querySelectorAll('.msg-compose-attachment').forEach(function(el){el.style.display='none';});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',clean);else clean();
})();
