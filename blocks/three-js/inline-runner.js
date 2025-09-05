(function(){
  if (window.StepfoxThreeInlineRunner) return;

  function detectHtml(s){ try{ return /<(?:!doctype|html|head|body|meta|link|style|script)\b/i.test(String(s||'')); }catch(e){ return false; } }

  function loadScript(u){ return new Promise(function(res){ var s=document.createElement('script'); s.src=u; s.async=false; s.onload=function(){res();}; s.onerror=function(){res();}; document.head.appendChild(s); }); }

  async function ensureThree(autoLoad, local1, local2){
    if (!autoLoad) return;
    if (window.THREE) return;
    var srcs=[]; if(local1) srcs.push(local1); if(local2) srcs.push(local2);
    srcs.push('https://cdn.jsdelivr.net/npm/three@0.165.0/build/three.min.js');
    srcs.push('https://unpkg.com/three@0.165.0/build/three.min.js');
    srcs.push('https://cdnjs.cloudflare.com/ajax/libs/three.js/0.165.0/three.min.js');
    for (var i=0; i<srcs.length && !window.THREE; i++){ try{ await loadScript(srcs[i]); }catch(e){} }
  }

  function absoluteUrl(u){ try{ return new URL(u, location.href).href; }catch(e){ return u; } }

  async function runHtml(container, html){
    container.innerHTML = '';
    var dom=(new DOMParser()).parseFromString(String(html||''),'text/html');
    var head=dom.head||document.createElement('head');
    var body=dom.body||document.createElement('body');
    var styleEls=head.querySelectorAll('style');
    for (var i=0;i<styleEls.length;i++){ var st=document.createElement('style'); st.textContent=styleEls[i].textContent; document.head.appendChild(st); }
    var css=head.querySelectorAll('link[rel="stylesheet"]');
    await Promise.all(Array.prototype.map.call(css, function(l){ return new Promise(function(res){ var el=document.createElement('link'); el.rel='stylesheet'; el.href=l.getAttribute('href'); el.onload=function(){res();}; el.onerror=function(){res();}; document.head.appendChild(el); }); }));
    while (body.firstChild){ container.appendChild(body.firstChild); }
    // Execute scripts from HEAD first, then BODY (now in container)
    var headScripts = head.querySelectorAll('script');
    var bodyScripts = container.querySelectorAll('script');
    var all = Array.prototype.slice.call(headScripts).concat(Array.prototype.slice.call(bodyScripts));
    function next(i){ if(i>=all.length) return; var s=all[i]; if (s.src){ var u=s.getAttribute('src'); loadScript(absoluteUrl(u)).then(function(){ next(i+1); }); } else { var t=s.textContent||''; var bl=new Blob([t],{type:'text/javascript'}); var u=URL.createObjectURL(bl); loadScript(u).then(function(){ try{ URL.revokeObjectURL(u);}catch(e){} next(i+1); }); } }
    next(0);
  }

  function runJs(container, code){
    // remove previous canvases inside container to reduce contexts
    try{ Array.prototype.forEach.call(container.querySelectorAll('canvas'), function(c){ c.remove(); }); }catch(e){}
    var wrapped='(function(){try\n{\n'+String(code)+'\n}\ncatch(e){}})();';
    var bl=new Blob([wrapped],{type:'text/javascript'});
    var u=URL.createObjectURL(bl);
    loadScript(u).then(function(){ try{ URL.revokeObjectURL(u);}catch(e){} });
  }

  async function mount(container){
    var payloadEl = container.querySelector('[data-payload]');
    if(!payloadEl) return;
    var payload = {};
    try{ payload = JSON.parse(payloadEl.getAttribute('data-payload')||'{}'); }catch(e){ payload = {}; }
    var code = payload.code || '';
    var isHtml = !!payload.isHtml;
    if (!isHtml && detectHtml(code)) isHtml = true;
    await ensureThree(!!payload.autoLoadThree, payload.local1||'', payload.local2||'');
    if (isHtml) { runHtml(container, code); } else { runJs(container, code); }
  }

  window.StepfoxThreeInlineRunner = { mount: mount };
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.sfa-three-inline').forEach(function(el){ mount(el); });
  });
})();


