<?php ?>
<style>
#ldcChatBtn{position:fixed;right:18px;bottom:18px;z-index:9999;width:52px;height:52px;border-radius:999px;border:1px solid #334155;background:#0b1220;color:#e7eaf3;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.3)}
#ldcChatPanel{position:fixed;right:18px;bottom:82px;z-index:9999;width:360px;max-height:70vh;display:none;flex-direction:column;background:#111827;border:1px solid #1f2937;border-radius:14px;box-shadow:0 16px 40px rgba(0,0,0,.35);overflow:hidden}
#ldcChatHeader{padding:10px 12px;background:#0f172a;display:flex;align-items:center;justify-content:space-between}
#ldcChatHeader h4{margin:0;font-size:14px}
#ldcChatClose{cursor:pointer;border:none;background:transparent;color:#94a3b8;font-size:18px}
#ldcChatBody{padding:12px;overflow:auto;flex:1}
.ldcMsg{margin:8px 0}
.ldcMsg .who{display:inline-block;font-size:12px;padding:2px 8px;border:1px solid #334155;border-radius:999px;color:#a5b4fc}
.ldcMsg .txt{white-space:pre-wrap;margin-top:4px}
#ldcChatForm{display:flex;gap:8px;padding:10px;border-top:1px solid #1f2937}
#ldcChatInput{flex:1;background:#0b1220;border:1px solid #334155;color:#e7eaf3;border-radius:10px;padding:10px}
#ldcChatSend{background:#0b1220;border:1px solid #334155;color:#e7eaf3;border-radius:10px;padding:10px 12px;cursor:pointer}
</style>
<button id="ldcChatBtn" title="Chat IA">💬</button>
<div id="ldcChatPanel">
  <div id="ldcChatHeader"><h4>Chat IA foot</h4><button id="ldcChatClose" aria-label="Fermer">✕</button></div>
  <div id="ldcChatBody"></div>
  <form id="ldcChatForm"><input id="ldcChatInput" placeholder="Pose ta question..."><button id="ldcChatSend" type="submit">Envoyer</button></form>
</div>
<script>
(function(){
  const btn=document.getElementById('ldcChatBtn'), panel=document.getElementById('ldcChatPanel');
  const body=document.getElementById('ldcChatBody'), form=document.getElementById('ldcChatForm'), input=document.getElementById('ldcChatInput'), closeBtn=document.getElementById('ldcChatClose');
  const KEY='ldc_chat_open';
  function addMsg(role,text){ const d=document.createElement('div'); d.className='ldcMsg'; d.innerHTML=`<div class="who">${role==='user'?'Toi':'IA'}</div><div class="txt">${(text||'').replace(/</g,'&lt;')}</div>`; body.appendChild(d); body.scrollTop=body.scrollHeight; }
  async function loadHistory(){ body.innerHTML=''; try{ const r=await fetch('chat_history.php'); const data=await r.json(); (data.messages||[]).forEach(m=>{ if(m.role==='system'||m.role==='tool')return; addMsg(m.role,m.content); }); }catch(e){ addMsg('assistant','Erreur: historique'); } }
  function openPanel(){ panel.style.display='flex'; localStorage.setItem(KEY,'1'); loadHistory(); setTimeout(()=>input.focus(),100); }
  function closePanel(){ panel.style.display='none'; localStorage.removeItem(KEY); }
  btn.addEventListener('click',()=>{ panel.style.display==='flex'?closePanel():openPanel(); });
  closeBtn.addEventListener('click',closePanel);
  form.addEventListener('submit',async(e)=>{ e.preventDefault(); const q=input.value.trim(); if(!q) return; addMsg('user',q); input.value=''; addMsg('assistant','...'); try{ const res=await fetch('chat_send.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({q})}); const data=await res.json(); body.removeChild(body.lastChild); if(data.error){ addMsg('assistant','Erreur: '+data.error); return; } addMsg('assistant',data.text||'(réponse vide)'); }catch(err){ body.removeChild(body.lastChild); addMsg('assistant','Erreur réseau'); } });
  if(localStorage.getItem(KEY)==='1') openPanel();
})();
</script>
