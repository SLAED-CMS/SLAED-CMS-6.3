(() => {
const host = document.querySelector('[data-presentation]');
const root = document.documentElement;
const drawn = new WeakSet();
const setInterval = (task, delay) => window.setInterval(() => {
    if (document.hidden || root.dataset.demoMotion === 'off') return;
    if (window.self !== window.top && host.getBoundingClientRect().bottom < 0) return;
    task();
}, delay);
const requestAnimationFrame = (task) => window.requestAnimationFrame((stamp) => {
    if ((document.hidden || root.dataset.demoMotion === 'off') && drawn.has(task)) {
        return window.setTimeout(() => requestAnimationFrame(task), 200);
    }
    drawn.add(task);
    task(stamp);
});

const $=s=>host.querySelector(s), $$=s=>[...host.querySelectorAll(s)];
// boot

// pointer light + tilt
addEventListener('pointermove',e=>{host.style.setProperty('--mx',e.clientX+'px');host.style.setProperty('--my',e.clientY+'px')});
const tilt=$('#tilt');
if(tilt){tilt.addEventListener('pointermove',e=>{const r=tilt.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;tilt.style.transform=`perspective(900px) rotateX(${(-y*2.6).toFixed(2)}deg) rotateY(${(x*3.2).toFixed(2)}deg) translateZ(2px)`});tilt.addEventListener('pointerleave',()=>tilt.style.transform='')}
// reveal
const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting)e.target.classList.add('in')}),{threshold:.09});$$('.reveal').forEach(x=>io.observe(x));
// progress + nav state + sticky 11-section roadmap
const secs=$$('section[id]'), nav=$$('.snav a');
const road=$('#pageRoad'), roadLinks=road?$$('#pageRoad a[href^="#"]'):[], roadStep=$('#roadStep'), roadPanel=road?.closest('.road-panel');
const roadSections=roadLinks.map(a=>host.querySelector(a.getAttribute('href'))).filter(Boolean);
function scrollTick(){
  const d=document.documentElement, max=d.scrollHeight-innerHeight, pct=max?scrollY/max*100:0;
  host.style.setProperty('--scroll',pct.toFixed(2));

  const marker=scrollY+innerHeight*.35;
  let current='top';
  secs.forEach(s=>{if(s.offsetTop<=marker)current=s.id});
  nav.forEach(a=>a.classList.toggle('active',a.getAttribute('href')==='#'+current||current==='top'&&a.getAttribute('href')==='#top'));

  if(roadSections.length){
    let currentIx=0;
    roadSections.forEach((s,i)=>{if(s.offsetTop<=marker)currentIx=i});
    if(marker<roadSections[0].offsetTop)currentIx=0;

    roadLinks.forEach((a,i)=>{
      a.classList.toggle('current',i===currentIx);
      a.classList.toggle('passed',i<currentIx);
      if(i===currentIx)a.setAttribute('aria-current','location');else a.removeAttribute('aria-current');
    });
    if(roadStep)roadStep.textContent=String(currentIx+1).padStart(2,'0')+' / '+String(roadSections.length).padStart(2,'0');

    const first=roadSections[0].offsetTop;
    const last=roadSections[roadSections.length-1].offsetTop+roadSections[roadSections.length-1].offsetHeight;
    const roadPct=Math.max(0,Math.min(100,(marker-first)/(last-first)*100));
    roadPanel?.style.setProperty('--road-progress',roadPct.toFixed(2)+'%');
  }
}
addEventListener('scroll',scrollTick,{passive:true});
addEventListener('resize',scrollTick,{passive:true});
scrollTick();

roadLinks.forEach((a,i)=>a.addEventListener('click',()=>{
  roadLinks.forEach((x,j)=>{x.classList.toggle('current',j===i);x.classList.toggle('passed',j<i)});
  if(roadStep)roadStep.textContent=String(i+1).padStart(2,'0')+' / '+String(roadLinks.length).padStart(2,'0');
}));

// duplicate ticker content for seamless loop
const t=$('#ticker');if(t)t.innerHTML+=t.innerHTML;
// subtle section parallax for archive/dna imagery
if(!matchMedia('(prefers-reduced-motion: reduce)').matches){let raf=0;addEventListener('scroll',()=>{if(raf)return;raf=requestAnimationFrame(()=>{const y=scrollY;$$('.dna-card img').forEach((im,i)=>{const r=im.parentElement.getBoundingClientRect();if(r.bottom>0&&r.top<innerHeight)im.style.transform=`translateY(${((innerHeight/2-r.top)*.012*(i%2?1:-1)).toFixed(1)}px) scale(1.045)`});raf=0})},{passive:true})}


// --- Demo J: stateful system layer ---
const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;

// Core panel: sections are clickable and auto-cycle until user touches it.
const CORE={
 'Система':[['kernel.boot','ok'],['config.load','ok'],['module.resolve','3ms'],['response.send','200']],
 'Новости':[['news.index','ok'],['news.category','4'],['comments.fetch','12'],['cache.store','ok']],
 'Пользователи':[['session.verify','ok'],['group.rights','admin'],['users.online','102'],['login.attempt','ok']],
 'Модули':[['modules.scan','685'],['module.enable','news'],['hooks.bind','18'],['registry.save','ok']],
 'Файлы':[['files.index','ok'],['upload.check','clean'],['download.count','+1'],['meta.write','ok']],
 'SEO':[['canonical.resolve','ok'],['meta.compose','ok'],['sitemap.queue','ready'],['robots.check','ok']],
 'Безопасность':[['request.filter','allowed'],['injection.scan','blocked'],['log.append','ok'],['session.guard','ok']],
 'Шаблоны':[['template.load','lite'],['blocks.render','6'],['partials.merge','ok'],['render.total','12ms']]
};
const coreBtns=$$('#coreMenu [data-core]'), coreLog=$('#coreLog'), corePath=$('#corePath');
let coreTouched=false, coreIndex=0;
function renderCore(key){
 coreBtns.forEach(b=>b.classList.toggle('on',b.dataset.core===key));
 if(corePath) corePath.textContent='admin · '+key.toLowerCase();
 if(!coreLog)return; coreLog.innerHTML='';
 (CORE[key]||[]).forEach(([op,res],i)=>{const row=document.createElement('div');row.className='logrow';row.innerHTML=`<time>18:${String(31+i).padStart(2,'0')}</time><span>${op}</span><em>${res}</em>`;coreLog.appendChild(row)});
}
coreBtns.forEach(b=>b.addEventListener('click',()=>{coreTouched=true;renderCore(b.dataset.core)}));
renderCore('Пользователи');
const coreTimer=setInterval(()=>{if(coreTouched)return clearInterval(coreTimer);coreIndex=(coreIndex+1)%coreBtns.length;renderCore(coreBtns[coreIndex].dataset.core)},3100);
const onlineMetric=$('#onlineMetric');setInterval(()=>{if(onlineMetric)onlineMetric.textContent=String(72+Math.round(Math.random()*19))},2700);

// Module topology: modules are actual toggles; detail pane reflects active selection.
const modTags={News:['news','categories','comments'],Files:['archive','meta','downloads'],Users:['groups','rights','profiles'],Security:['filter','logs','guard'],Search:['query','index','results'],SEO:['meta','canonical','sitemap'],Pages:['routes','content','blocks'],Media:['images','attach','thumbs']};
const modEls=$$('.mod[data-mod]'), modDetail=$('#moduleDetail'), modCount=$('#modCount');
let modTouched=false, modCycle=0;
function paintMods(){
 const active=modEls.filter(m=>m.dataset.on==='1');
 modEls.forEach(m=>m.classList.toggle('off',m.dataset.on!=='1'));
 if(modCount)modCount.textContent=`включено ${active.length} из 685 · нажмите модуль`;
 if(modDetail){modDetail.innerHTML='';active.slice(0,4).forEach((m,i)=>{const name=m.dataset.mod;const c=document.createElement('div');c.className='detail-card';c.style.animation=`reqIn .45s cubic-bezier(.2,1,.3,1) ${i*45}ms both`;c.innerHTML=`<div class="head"><b>${name}</b><span>● live</span></div><div class="tags">${(modTags[name]||[]).map(t=>`<span class="tag">${t}</span>`).join('')}</div>`;modDetail.appendChild(c)})}
}
modEls.forEach(m=>m.addEventListener('click',()=>{modTouched=true;m.dataset.on=m.dataset.on==='1'?'0':'1';paintMods()}));paintMods();
const modTimer=setInterval(()=>{if(modTouched)return clearInterval(modTimer);const n=modEls.length;modEls[modCycle%n].dataset.on='0';modEls[(modCycle+4)%n].dataset.on='1';modCycle++;paintMods()},2600);


// Cache 6.3 visualization: ON alternates MISS → HIT → HIT → BYPASS.
// OFF makes every request render live and disables the parser data cache too.
let cacheOn=true;
const cacheToggle=$('#cacheToggle'), cacheFlow=$('#cacheFlow'), cacheLabel=$('#cacheLabel');
const cacheDecision=$('#cacheDecision'), cacheRoute=$('#cacheRoute'), cacheCoreState=$('#cacheCoreState'), cacheCoreSub=$('#cacheCoreSub');
const pageCacheState=$('#pageCacheState'), parserCacheState=$('#parserCacheState'), dynamicState=$('#dynamicState'), lockState=$('#lockState');
const cacheModeText=$('#cacheModeText'), cacheNodeState=$('#cacheNodeState'), cacheNodeMode=$('#cacheNodeMode'), parserState=$('#parserState');
const packetHit=$('#packetHit'), packetMiss=$('#packetMiss');
const cacheNodes={};$$('[data-cache-node]').forEach(n=>cacheNodes[n.dataset.cacheNode]=n);
let cacheScenarioN=0, cacheStepTimer=null, cacheScenarioTimer=null;

const scenarios=[
 {mode:'miss', badge:'MISS · BUILD', route:'GET /index.php?name=news&cat=1 · guest', seq:['request','guard','cache','kernel','module','template','response']},
 {mode:'hit', badge:'HIT · READY PAGE', route:'GET /index.php?name=news&cat=1 · guest', seq:['request','guard','cache','response']},
 {mode:'hit', badge:'HIT · DYNAMIC LIVE', route:'GET / · guest', seq:['request','guard','cache','response']},
 {mode:'bypass', badge:'BYPASS · LIVE', route:'POST /account · or logged-in visitor', seq:['request','guard','kernel','module','template','response']}
];

function setCacheNodeActive(name){
 Object.entries(cacheNodes).forEach(([k,n])=>n.classList.toggle('active',k===name));
}
function setCacheCase(mode){
 $$('[data-cache-case]').forEach(c=>c.classList.toggle('on',c.dataset.cacheCase===mode));
}
function setCacheMode(mode,scenario){
 if(!cacheFlow)return;
 cacheFlow.classList.remove('mode-hit','mode-miss','mode-bypass','mode-off');
 cacheFlow.classList.add('mode-'+mode);
 cacheToggle?.classList.toggle('off',!cacheOn);
 cacheToggle?.setAttribute('aria-pressed',cacheOn?'true':'false');
 if(cacheLabel)cacheLabel.textContent=cacheOn?'Кэш включён':'Кэш выключен';
 if(cacheModeText)cacheModeText.textContent=cacheOn?'cache = 1':'cache = 0';
 if(cacheRoute)cacheRoute.textContent=scenario.route;
 if(cacheDecision){
   cacheDecision.className='cacheflow-decision '+mode;
   cacheDecision.textContent=scenario.badge;
 }
 if(packetHit)packetHit.style.display=(mode==='hit')?'':'none';
 if(packetMiss)packetMiss.style.display=(mode==='hit')?'none':'';
 Object.values(cacheNodes).forEach(n=>{n.classList.remove('skipped','cache-store')});
 if(mode==='hit'){
   ['kernel','module','template'].forEach(k=>cacheNodes[k]?.classList.add('skipped'));
   if(cacheNodeState)cacheNodeState.textContent='HIT';
   if(cacheNodeMode)cacheNodeMode.textContent='fresh body';
   if(cacheCoreState){cacheCoreState.textContent='HIT / SERVE';cacheCoreState.style.color=''}
   if(cacheCoreSub)cacheCoreSub.textContent='body → sidecar → dynamic';
   if(pageCacheState){pageCacheState.textContent='HIT · READY';pageCacheState.className='good'}
   if(parserCacheState){parserCacheState.textContent='SKIPPED ON PAGE HIT';parserCacheState.className='mute'}
   if(dynamicState){dynamicState.textContent='LIVE SUBSTITUTE';dynamicState.className='good'}
   if(lockState){lockState.textContent='NOT NEEDED';lockState.className='mute'}
   if(parserState)parserState.textContent='skip';
 }else if(mode==='miss'){
   if(cacheNodeState)cacheNodeState.textContent='MISS';
   if(cacheNodeMode)cacheNodeMode.textContent='lookup';
   if(cacheCoreState){cacheCoreState.textContent='MISS / BUILD';cacheCoreState.style.color=''}
   if(cacheCoreSub)cacheCoreSub.textContent='rebuild → body + sidecar';
   if(pageCacheState){pageCacheState.textContent='MISS · BUILD';pageCacheState.className='warn'}
   if(parserCacheState){parserCacheState.textContent='ACTIVE / WARM';parserCacheState.className='good'}
   if(dynamicState){dynamicState.textContent='MARKERS / LIVE';dynamicState.className='good'}
   if(lockState){lockState.textContent='ACQUIRED';lockState.className=''}
   if(parserState)parserState.textContent='warm';
 }else if(mode==='bypass'){
   cacheNodes.cache?.classList.add('skipped');
   if(cacheNodeState)cacheNodeState.textContent='BYPASS';
   if(cacheNodeMode)cacheNodeMode.textContent='not eligible';
   if(cacheCoreState){cacheCoreState.textContent='BYPASS / LIVE';cacheCoreState.style.color=''}
   if(cacheCoreSub)cacheCoreSub.textContent='full live render · no store';
   if(pageCacheState){pageCacheState.textContent='BYPASS';pageCacheState.className='mute'}
   if(parserCacheState){parserCacheState.textContent='ACTIVE IF ELIGIBLE';parserCacheState.className='good'}
   if(dynamicState){dynamicState.textContent='LIVE';dynamicState.className='good'}
   if(lockState){lockState.textContent='NOT USED';lockState.className='mute'}
   if(parserState)parserState.textContent='cacheable';
 }else{
   cacheNodes.cache?.classList.add('skipped');
   if(cacheNodeState)cacheNodeState.textContent='OFF';
   if(cacheNodeMode)cacheNodeMode.textContent='disabled';
   if(cacheCoreState){cacheCoreState.textContent='CACHE OFF';cacheCoreState.style.color=''}
   if(cacheCoreSub)cacheCoreSub.textContent='full live render every request';
   if(pageCacheState){pageCacheState.textContent='OFF';pageCacheState.className='mute'}
   if(parserCacheState){parserCacheState.textContent='OFF';parserCacheState.className='mute'}
   if(dynamicState){dynamicState.textContent='LIVE';dynamicState.className='good'}
   if(lockState){lockState.textContent='OFF';lockState.className='mute'}
   if(parserState)parserState.textContent='live';
 }
 setCacheCase(mode==='off'?'bypass':mode);
}
function runCacheScenario(){
 clearInterval(cacheStepTimer);
 const scenario=cacheOn?scenarios[cacheScenarioN++%scenarios.length]:{mode:'off',badge:'CACHE OFF · LIVE',route:'GET /index.php?name=news&cat=1 · live',seq:['request','guard','kernel','module','template','response']};
 setCacheMode(scenario.mode,scenario);
 let i=0;setCacheNodeActive(scenario.seq[0]);
 cacheStepTimer=setInterval(()=>{
   i=(i+1)%scenario.seq.length;
   const step=scenario.seq[i];setCacheNodeActive(step);
   if(scenario.mode==='miss'&&step==='response'){
     cacheNodes.cache?.classList.add('cache-store');
     if(cacheDecision){cacheDecision.className='cacheflow-decision store';cacheDecision.textContent='STORE · BODY + SIDECAR'}
     setTimeout(()=>cacheNodes.cache?.classList.remove('cache-store'),760);
   }
 },720);
}
cacheToggle?.addEventListener('click',()=>{cacheOn=!cacheOn;cacheScenarioN=0;runCacheScenario()});
runCacheScenario();
cacheScenarioTimer=setInterval(runCacheScenario,5600);
setInterval(()=>{$$('#rpsMini i').forEach(i=>i.style.setProperty('--h',(22+Math.random()*68).toFixed(0)+'%'))},850);


// Runtime event stream is alive instead of being a frozen screenshot.
const eventStream=$('#eventStream'), eventPool=[['request.filter','allowed',0],['session.verify','verified',0],['query.analyze','review',1],['cache.refresh','complete',0],['template.render','complete',0],['injection.scan','blocked',1],['files.download','+1',0],['sitemap.build','ok',0]];let eventN=0;
function pushRuntime(){if(!eventStream)return;const [op,res,warn]=eventPool[eventN++%eventPool.length],d=new Date(),e=document.createElement('div');e.className='evt'+(warn?' warn':'');e.style.animation='reqIn .45s cubic-bezier(.2,1,.3,1) both';e.innerHTML=`<time>${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}</time><b>${op}</b><span>${res}</span>`;eventStream.prepend(e);while(eventStream.children.length>5)eventStream.lastElementChild.remove()}
if(eventStream){eventStream.innerHTML='';for(let i=0;i<5;i++)pushRuntime();setInterval(pushRuntime,2300)}





// Owner voices: deliberately demo content. Interaction only changes presentation.
$$('.voice-card').forEach((card,idx)=>{
 card.addEventListener('mouseenter',()=>{
   card.style.setProperty('--mx',(18+idx*31)+'%');
   card.querySelector('.voice-avatar')?.animate(
     [{transform:'scale(.94)'},{transform:'scale(1.08)'},{transform:'scale(1)'}],
     {duration:420,easing:'cubic-bezier(.2,.8,.2,1)'}
   );
 });
});



// Block Manager demo: draggable placement + status toggle.
let draggedBlock=null;
$$('.block-row').forEach(row=>{
  row.addEventListener('dragstart',()=>{draggedBlock=row;row.classList.add('dragging')});
  row.addEventListener('dragend',()=>{row.classList.remove('dragging');draggedBlock=null;updateBlockCount()});
  row.querySelector('em')?.addEventListener('click',e=>{
    e.stopPropagation();
    const em=e.currentTarget,on=!em.classList.contains('on');
    em.classList.toggle('on',on);row.classList.toggle('off',!on);
    em.innerHTML=on?'<i class="bi bi-check-lg"></i>':'<i class="bi bi-x-lg"></i>';
    updateBlockCount();
  });
});
$$('.block-drop').forEach(drop=>{
  drop.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('drag-over')});
  drop.addEventListener('dragleave',()=>drop.classList.remove('drag-over'));
  drop.addEventListener('drop',e=>{
    e.preventDefault();drop.classList.remove('drag-over');
    if(draggedBlock)drop.appendChild(draggedBlock);
  });
});
function updateBlockCount(){
  const n=$$('.block-row em.on').length,el=$('#blocksActive');
  if(el)el.textContent=n+' active';
}
updateBlockCount();

// Runtime Debugger tabs.
$$('.devtabs button').forEach(btn=>btn.addEventListener('click',()=>{
  $$('.devtabs button').forEach(x=>x.classList.toggle('on',x===btn));
  $$('.devpane').forEach(p=>p.classList.toggle('on',p.dataset.devpane===btn.dataset.devtab));
}));



// Block position router: topology-first presentation instead of an admin table.
const blockNodes=$$('.bnode[data-bnode]'),blockVList=$('#blockVList'),blockVisualCount=$('#blockVisualCount'),blockFocus=$('#blockFocus');
const blockSlots=$$('#blockVStage [data-slot]'),blockWires=$$('#blockVStage [data-wire]');
function paintBlockVisual(focus=null){
  const active=blockNodes.filter(n=>n.classList.contains('active'));
  if(blockVisualCount)blockVisualCount.textContent=active.length+' active';
  blockSlots.forEach(s=>s.dataset.hot='0');
  blockWires.forEach(w=>w.classList.remove('on'));
  active.forEach(n=>{
    const slot=host.querySelector(`#blockVStage [data-slot="${n.dataset.pos}"]`);
    if(slot)slot.dataset.hot='1';
    host.querySelector(`#blockVStage [data-wire="${n.dataset.bnode}"]`)?.classList.add('on');
  });
  if(blockFocus){
    const f=focus||active[0];
    blockFocus.textContent=(f?.dataset.pos||'layout').replaceAll('-',' ').toUpperCase();
  }
  if(blockVList){
    blockVList.innerHTML='';
    active.forEach((n,i)=>{
      const c=document.createElement('div');c.className='bvp-card';
      c.innerHTML=`<b>${n.dataset.bnode}</b><span>${String(i+1).padStart(2,'0')}</span><small>${n.dataset.pos.replaceAll('-',' ')} · ${n.querySelector('small')?.textContent||''}</small>`;
      blockVList.appendChild(c);
    });
  }
}
blockNodes.forEach(n=>{
  n.addEventListener('click',()=>{
    n.classList.toggle('active');
    n.querySelector('em').textContent=n.classList.contains('active')?'ON':'OFF';
    paintBlockVisual(n);
  });
});
paintBlockVisual();

// Smooth live series, adapted from the user's v7 standalone demo:
// targets shift slowly; the visible line continuously eases toward them with Bézier segments.
function smoothCanvasSeries(cv,color,n,min=25,max=80,fill=true){
  if(!cv)return;
  const ctx=cv.getContext('2d');
  let target=Array.from({length:n},()=>min+Math.random()*(max-min));
  let cur=target.slice();
  let lastShift=performance.now();

  function size(){
    const w=cv.clientWidth,h=cv.clientHeight,dpr=Math.min(devicePixelRatio||1,2);
    const rw=Math.max(1,Math.round(w*dpr)),rh=Math.max(1,Math.round(h*dpr));
    if(cv.width!==rw||cv.height!==rh){cv.width=rw;cv.height=rh}
    ctx.setTransform(dpr,0,0,dpr,0,0);
    return {w,h};
  }
  function paint(now){
    
    const {w,h}=size();
    if(!w||!h){requestAnimationFrame(paint);return}
    if(now-lastShift>900){
      target.push(min+Math.random()*(max-min));target.shift();
      lastShift=now;
    }
    for(let i=0;i<n;i++)cur[i]+=(target[i]-cur[i])*.10;

    ctx.clearRect(0,0,w,h);
    const px=i=>i/(n-1)*w,py=v=>h-v/100*(h-4)-2;
    ctx.beginPath();ctx.moveTo(px(0),py(cur[0]));
    for(let i=1;i<n;i++){
      const x0=px(i-1),y0=py(cur[i-1]),x1=px(i),y1=py(cur[i]),xm=(x0+x1)/2;
      ctx.bezierCurveTo(xm,y0,xm,y1,x1,y1);
    }
    ctx.strokeStyle=color;ctx.lineWidth=1.75;ctx.lineJoin='round';ctx.lineCap='round';ctx.stroke();

    if(fill){
      const g=ctx.createLinearGradient(0,0,0,h);
      g.addColorStop(0,'rgba(105,202,255,.22)');
      g.addColorStop(1,'rgba(105,202,255,0)');
      ctx.lineTo(px(n-1),h);ctx.lineTo(px(0),h);ctx.closePath();ctx.fillStyle=g;ctx.fill();
    }
    requestAnimationFrame(paint);
  }
  requestAnimationFrame(paint);
}
smoothCanvasSeries($('#corePulseCanvas'),'#61c6fa',44,24,78,true);
smoothCanvasSeries($('#responseSparkCanvas'),'#69caff',40,20,72,true);


// Development Live — real repository snapshot, focus rotates but never invents commits.
const DEV_COMMITS=[
 {sha:'f360f6a',date:'26.08.2026',title:'One window frame for the whole system, and the file manager grows up.',text:'Один канонический window-frame вместо ручных копий; файловые менеджеры получают сортировку, полные листинги, insert options и свойства файлов.',tags:['fragments/window.html','core/helpers.php','file manager']},
 {sha:'1a200a8',date:'25.08.2026',title:'Module block setting works again, and content takes back the width it frees.',text:'Восстановлено чтение side/top настройки модулей; grid теперь отдаёт контенту ширину колонок, которых реально нет.',tags:['index.php','theme.css','915 tests / 193 gates']},
 {sha:'687d958',date:'25.08.2026',title:'Presentation page gets a stand of 23 designs; implementation catalogue gets 71 screenshots.',text:'Презентационная страница сравнивается на реальных вариантах дизайна; каталог внедрений снова показывает настоящие сайты SLAED.',tags:['demo/','23 designs','71 screenshots']},
 {sha:'7cd6144',date:'25.08.2026',title:'Photo bands carry a light beam; season art ships as WebP.',text:'Фото-полосы получают контролируемое движение, footer — icon grid, а сезонные изображения переведены в WebP.',tags:['theme.css','WebP','motion']},
 {sha:'12e1c35',date:'24.08.2026',title:'Settings window comes back after POST; demo carries 16 band treatments.',text:'Настройки сохраняют контекст окна после навигации, а demo/ получает отдельный стенд вариантов оформления.',tags:['admin UX','sessionStorage','demo/']}
];
const commitRows=$$('.commit-row'),devFocusSha=$('#devFocusSha'),devFocusTitle=$('#devFocusTitle'),devFocusText=$('#devFocusText'),devFocusTags=$('#devFocusTags'),devFocusState=$('#devFocusState');
let devIx=0,devTouched=false;
function focusCommit(ix,user=false){
 devIx=ix; if(user)devTouched=true;
 commitRows.forEach((r,i)=>r.classList.toggle('on',i===ix));
 const c=DEV_COMMITS[ix]; if(!c)return;
 if(devFocusSha)devFocusSha.textContent=c.sha+' · '+c.date;
 if(devFocusTitle)devFocusTitle.textContent=c.title;
 if(devFocusText)devFocusText.textContent=c.text;
 if(devFocusTags)devFocusTags.innerHTML=c.tags.map(t=>`<i>${t}</i>`).join('');
 if(devFocusState)devFocusState.textContent=ix===0?'HEAD':'RECENT';
}
commitRows.forEach((r,i)=>r.addEventListener('mouseenter',()=>focusCommit(i,true)));
setInterval(()=>{if(devTouched){devTouched=false;return}focusCommit((devIx+1)%DEV_COMMITS.length)},4200);

// Relative age is calculated in-browser from the real GitHub timestamp.
$$('[data-iso]').forEach(el=>{
 const d=new Date(el.dataset.iso),now=new Date(),mins=Math.max(0,Math.floor((now-d)/60000));
 const small=el.parentElement?.querySelector('small');
 if(small&&mins<1440){const rel=mins<60?`${mins} мин назад`:`${Math.floor(mins/60)} ч назад`;small.dataset.base=small.textContent;small.textContent=rel}
});

// Live block: standalone remains an honest snapshot; only the "source ready" heartbeat moves.
const liveSync=$('#liveSync'); let syncN=0;
setInterval(()=>{if(liveSync){syncN=(syncN+1)%4;liveSync.textContent=syncN===0?'source ready':'poll '+('···'.slice(0,syncN))}},1200);


// PDO execution visualization follows the real Database::getSqlQuery() shape.
const pdoNodes={};$$('[data-pdo]').forEach(n=>pdoNodes[n.dataset.pdo]=n);
const pdoPacket=$('#pdoPacket'),pdoVerb=$('#pdoVerb'),pdoQuery=$('#pdoQuery'),pdoQueryText=$('#pdoQueryText'),pdoParams=$('#pdoParams');
const pdoStatus=$('#pdoStatus'),pdoResult=$('#pdoResult'),pdoElapsed=$('#pdoElapsed'),pdoCode=$('#pdoCode'),pdoMode=$('#pdoMode'),pdoTrace=$('#pdoTrace');
const pdoEpoch=$('#pdoEpoch'),pdoQnum=$('#pdoQnum'),pdoSqlTime=$('#pdoSqlTime'),pdoSqlTimeTop=$('#pdoSqlTimeTop');
let pdoIx=0,pdoQ=12,pdoTotal=.0110,pdoStepTimer=null;

const PDO_CASES=[
 {verb:'SELECT',query:'SELECT id, title FROM slaed_news WHERE cat = :cat ORDER BY time DESC LIMIT ?',params:[':cat=2','?=10'],prepared:true,result:'10 rows · FETCH_BOTH',elapsed:.00083,write:false},
 {verb:'SELECT',query:'SELECT id, user_name FROM slaed_users WHERE user_id = :id',params:[':id=42'],prepared:true,result:'1 row · FETCH_BOTH',elapsed:.00046,write:false},
 {verb:'SELECT',query:'SELECT COUNT(*) AS total FROM slaed_comments WHERE status = ?',params:['?=1'],prepared:true,result:'1 field · FETCH_BOTH',elapsed:.00061,write:false},
 {verb:'UPDATE',query:'UPDATE slaed_news SET counter = counter + 1 WHERE id = :id',params:[':id=125'],prepared:true,result:'1 affected row',elapsed:.00074,write:true},
 {verb:'SHOW',query:'SHOW TABLE STATUS',params:[],prepared:false,result:'34 rows · PDOStatement',elapsed:.00112,write:false}
];
const PDO_POS={module:10,database:30,pdo:50,sql:70,statement:90};

function pdoTraceRows(c){
 if(!pdoTrace)return;
 const rows=c.prepared
   ? [['00.000','pdo.prepare','ready'],['00.001','stmt.execute','ok'],[c.elapsed.toFixed(5),'fetch','result']]
   : [['00.000','pdo.query','direct'],[c.elapsed.toFixed(5),'statement','ready'],[(c.elapsed+.00008).toFixed(5),'fetch','result']];
 pdoTrace.innerHTML=rows.map((r,i)=>`<div class="${c.write&&i===1?'write':''}"><time>${r[0]}</time><b>${r[1]}</b><em>${r[2]}</em></div>`).join('');
}
function paintPdoCase(c){
 if(pdoVerb)pdoVerb.textContent=c.verb;
 if(pdoQuery){pdoQuery.classList.toggle('write',c.write)}
 if(pdoPacket)pdoPacket.classList.toggle('write',c.write);
 if(pdoQueryText)pdoQueryText.textContent=c.query;
 if(pdoParams)pdoParams.innerHTML=c.params.map(p=>`<i>${p}</i>`).join('');
 if(pdoStatus){pdoStatus.textContent=c.write?'WRITE / TRANSACTIONAL':'READ / READY';pdoStatus.style.color=c.write?'#ffd079':''}
 if(pdoMode)pdoMode.textContent=c.prepared?'PREPARED':'DIRECT QUERY';
 if(pdoResult)pdoResult.textContent=c.result;
 if(pdoElapsed)pdoElapsed.textContent=c.elapsed.toFixed(5)+' sec';
 if(pdoCode){
   pdoCode.innerHTML=c.prepared
     ? `<span class="com">// params → native prepared statement</span><br><span class="var">$stmt</span> = <span class="var">$pdo</span>-&gt;<span class="fn">prepare</span>(<span class="var">$query</span>);<br><span class="var">$stmt</span>-&gt;<span class="fn">execute</span>(<span class="var">$params</span>);<br><span class="var">$rows</span> = <span class="var">$stmt</span>-&gt;<span class="fn">fetchAll</span>(PDO::<span class="kw">FETCH_BOTH</span>);`
     : `<span class="com">// no params → direct PDO query</span><br><span class="var">$stmt</span> = <span class="var">$pdo</span>-&gt;<span class="fn">query</span>(<span class="var">$query</span>);<br><span class="var">$rows</span> = <span class="var">$stmt</span>-&gt;<span class="fn">fetchAll</span>(PDO::<span class="kw">FETCH_BOTH</span>);`;
 }
 pdoTraceRows(c);
}
function runPdo(){
 clearInterval(pdoStepTimer);
 const c=PDO_CASES[pdoIx++%PDO_CASES.length];paintPdoCase(c);
 let seq=['module','database','pdo','sql','statement'],i=0;
 Object.values(pdoNodes).forEach(n=>n.classList.remove('on'));
 if(pdoPacket){pdoPacket.style.left=PDO_POS.module+'%';pdoPacket.style.transform='translateX(-50%) scale(1)'}
 pdoNodes.module?.classList.add('on');
 pdoStepTimer=setInterval(()=>{
   pdoNodes[seq[i]]?.classList.remove('on');i++;
   if(i>=seq.length){
     clearInterval(pdoStepTimer);
     if(pdoPacket)pdoPacket.style.transform='translateX(-50%) scale(.55)';
     pdoQ++;pdoTotal+=c.elapsed;
     if(pdoQnum)pdoQnum.textContent=pdoQ;
     if(pdoSqlTime)pdoSqlTime.textContent=pdoTotal.toFixed(4)+' s';
     if(pdoSqlTimeTop)pdoSqlTimeTop.textContent=pdoTotal.toFixed(3)+' sec';
     if(c.write&&pdoEpoch){pdoEpoch.classList.add('show');setTimeout(()=>pdoEpoch.classList.remove('show'),1700)}
     $$('#pdoRows i').forEach(b=>b.style.setProperty('--h',(25+Math.random()*68).toFixed(0)+'%'));
     return;
   }
   const step=seq[i];pdoNodes[step]?.classList.add('on');
   if(pdoPacket)pdoPacket.style.left=PDO_POS[step]+'%';
 },590);
}
runPdo();setInterval(runPdo,4300);
setInterval(()=>{const r=$('#guardRate');if(r)r.textContent=String(112+Math.round(Math.random()*42))},2200);


// Request Guard: Pong-style traffic inspection.
let guardOn=true,blocked=0,allowed=0,trafficN=0;
const guardToggle=$('#guardToggle'),guardLabel=$('#guardLabel'),guardMode=$('#guardMode'),guardState=$('#guardState');
const guardScene=$('#guardScene'),securityGate=$('#securityGate'),slaedHouse=$('#slaedHouse'),securityLog=$('#securityLog');
const blockedCount=$('#blockedCount'),allowedCount=$('#allowedCount'),quarantineCount=$('#quarantineCount'),quarantine=$('#quarantine');
const shieldStatus=$('#shieldStatus');

const TRAFFIC=[
 {kind:'human',label:'HUMAN',icon:'bi-person-fill',text:'GET /index.php?name=news',zone:'human',color:'#6ecfff',bad:false,result:'allow'},
 {kind:'search',label:'SEARCH',icon:'bi-search',text:'crawler · GET /sitemap.xml',zone:'search',color:'#bba2ff',bad:false,result:'index'},
 {kind:'ai',label:'AI',icon:'bi-stars',text:'AI agent · GET /content',zone:'ai',color:'#71dfb0',bad:false,result:'classify'},
 {kind:'bot',label:'BOT',icon:'bi-robot',text:'service bot · GET /rss',zone:'bot',color:'#ffd27a',bad:false,result:'allow'},
 {kind:'human',label:'HUMAN',icon:'bi-person-fill',text:'GET /files · browser',zone:'human',color:'#6ecfff',bad:false,result:'allow'},
 {kind:'evil',label:'SQLi',icon:'bi-database-exclamation',text:'id=1 UNION SELECT …',zone:'content',color:'#ff8497',bad:true,result:'deny'},
 {kind:'evil',label:'XSS',icon:'bi-code-slash',text:'q=%3Cscript%3E…%3C/script%3E',zone:'content',color:'#ff8497',bad:true,result:'deny'},
 {kind:'evil',label:'TRAVERSAL',icon:'bi-folder-x',text:'../../etc/passwd',zone:'content',color:'#ff8497',bad:true,result:'deny'},
 {kind:'evil',label:'BRUTE',icon:'bi-person-lock',text:'POST /account × 27',zone:'human',color:'#ff8497',bad:true,result:'deny'},
 {kind:'evil',label:'PROBE',icon:'bi-bug-fill',text:'GET /.env /backup /config',zone:'content',color:'#ff8497',bad:true,result:'deny'}
];

function addSecurityLog(label,result,kind){
 if(!securityLog)return;
 const d=new Date(),l=document.createElement('div');
 l.className=kind==='deny'?'deny':kind==='warn'?'warn':'';
 l.innerHTML=`<time>${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}</time><span>${label}</span><em>${result}</em>`;
 securityLog.prepend(l);while(securityLog.children.length>6)securityLog.lastElementChild.remove();
}
function lightHouse(zone){
 const target=host.querySelector(`[data-house-zone="${zone}"]`);
 target?.classList.add('lit','open');
 slaedHouse?.classList.add('safe');
 const pulse=document.createElement('span');pulse.className='house-pulse';guardScene?.appendChild(pulse);setTimeout(()=>pulse.remove(),700);
 setTimeout(()=>target?.classList.remove('lit','open'),850);
}
function gateSparks(kind='deny'){
 if(!guardScene||!securityGate)return;
 const g=securityGate.getBoundingClientRect(),s=guardScene.getBoundingClientRect();
 for(let i=0;i<(kind==='deny'?10:5);i++){
   const p=document.createElement('i');p.className='guard-spark';
   p.style.left=(g.left-s.left+g.width*.5)+'px';p.style.top=(g.top-s.top+g.height*.5)+'px';
   p.style.setProperty('--sx',((Math.random()-.5)*(kind==='deny'?82:48)).toFixed(0)+'px');
   p.style.setProperty('--sy',((Math.random()-.5)*(kind==='deny'?72:42)).toFixed(0)+'px');
   guardScene.appendChild(p);setTimeout(()=>p.remove(),620);
 }
 securityGate.classList.add('hit');setTimeout(()=>securityGate.classList.remove('hit'),480);
}

/* Moving paddle: idle motion is continuous. While a packet approaches, it chases that packet's lane. */
let guardTop=160,guardDir=1,guardTarget=null,lastGuardFrame=performance.now();
const guardMin=102,guardMax=258;
function guardLoop(now){
 if(securityGate){
   const dt=Math.min(35,now-lastGuardFrame||16);
   if(guardTarget!==null&&guardOn){
     const delta=guardTarget-guardTop;
     guardTop+=delta*Math.min(1,dt*.014);
     if(Math.abs(delta)<1.2)guardTop=guardTarget;
   }else{
     guardTop+=guardDir*dt*.075;
     if(guardTop>=guardMax){guardTop=guardMax;guardDir=-1}
     if(guardTop<=guardMin){guardTop=guardMin;guardDir=1}
   }
   securityGate.style.top=guardTop.toFixed(1)+'px';
 }
 lastGuardFrame=now;requestAnimationFrame(guardLoop);
}
requestAnimationFrame(guardLoop);

function setShieldState(state,text){
 if(!securityGate)return;
 securityGate.classList.remove('checking','allowing','denying','miss');
 if(state)securityGate.classList.add(state);
 if(shieldStatus)shieldStatus.textContent=text||'SCAN';
}
function aimGuardAt(y){
 if(!guardOn){guardTarget=null;return}
 const shieldH=58;
 guardTarget=Math.max(guardMin,Math.min(guardMax,y-shieldH*.5));
 setShieldState('checking','TRACK');
}
function releaseGuard(delay=460){
 setTimeout(()=>{guardTarget=null;setShieldState('',guardOn?'SCAN':'BYPASS')},delay);
}

function catchToQuarantine(el,item){
 if(!guardScene||!quarantine){el.remove();return}
 gateSparks('deny');setShieldState('denying','BLOCK');
 el.classList.add('caught','guard-impact');

 /* First rebound left: the "pong" moment. */
 const leftNow=parseFloat(el.style.left)||0;
 el.style.transition='left .16s cubic-bezier(.2,.9,.3,1),transform .16s ease';
 el.style.left=Math.max(100,leftNow-34)+'px';
 el.style.transform='rotate(-13deg) scale(.92)';

 setTimeout(()=>{
   const sr=guardScene.getBoundingClientRect(),qr=quarantine.getBoundingClientRect();
   const targetLeft=qr.left-sr.left+16+Math.random()*70;
   const targetTop=qr.top-sr.top+25+Math.random()*10;
   el.style.transition='left .68s cubic-bezier(.55,0,.9,.45),top .68s cubic-bezier(.55,0,.9,.45),transform .68s,opacity .68s';
   el.style.left=targetLeft+'px';el.style.top=targetTop+'px';el.style.transform='rotate('+(35+Math.random()*75)+'deg) scale(.46)';el.style.opacity='.16';
   setTimeout(()=>{
     blocked++;if(blockedCount)blockedCount.textContent=blocked;if(quarantineCount)quarantineCount.textContent=blocked;
     addSecurityLog(item.label,'BLOCK → QUARANTINE','deny');el.remove();releaseGuard(120);
   },690);
 },165);
}
function passToHouse(el,item,isBad){
 if(!guardScene||!slaedHouse){el.remove();return}
 const sr=guardScene.getBoundingClientRect(),hr=slaedHouse.getBoundingClientRect();
 const targetLeft=hr.left-sr.left+35;
 const zoneY={human:.72,search:.48,ai:.58,bot:.62,content:.42}[item.zone]||.55;
 const targetTop=hr.top-sr.top+hr.height*zoneY;

 if(!isBad){
   gateSparks('allow');setShieldState('allowing','PASS');
   el.classList.add('guard-impact');
 }
 el.style.transition='left 1.02s cubic-bezier(.3,.65,.2,1),top 1.02s cubic-bezier(.3,.65,.2,1),transform 1.02s,opacity .35s 1s';
 el.style.left=targetLeft+'px';el.style.top=targetTop+'px';el.style.transform='scale(.84)';
 setTimeout(()=>{
   allowed++;if(allowedCount)allowedCount.textContent=allowed;
   if(isBad){
     slaedHouse.classList.add('breach');setTimeout(()=>slaedHouse.classList.remove('breach'),700);
     addSecurityLog(item.label,'MISSED → BREACH','warn');
   }else{
     lightHouse(item.zone);addSecurityLog(item.label,'CHECK → '+item.result.toUpperCase(),'allow');
   }
   releaseGuard(100);
 },720);
 setTimeout(()=>{el.style.opacity='0';setTimeout(()=>el.remove(),380)},1060);
}
function guardMiss(el,item){
 securityGate?.classList.add('miss');setShieldState('miss','MISS');
 setTimeout(()=>securityGate?.classList.remove('miss'),480);
 passToHouse(el,item,item.bad);
}

function spawnTraveler(){
 if(!guardScene)return;
 const item=TRAFFIC[trafficN++%TRAFFIC.length];
 const el=document.createElement('div');el.className='traveler '+item.kind+(item.bad?' evil':'');el.style.setProperty('--tc',item.color);

 /* Five lanes centered around the scene. */
 const lanes=[122,156,190,224,258];
 const y=lanes[trafficN%lanes.length]+(Math.random()*10-5);
 el.style.top=y.toFixed(0)+'px';
 el.innerHTML=`<span class="avatar"><i class="bi ${item.icon}"></i></span><span class="bubble"><b>${item.label}</b><span>${item.text}</span></span>`;
 guardScene.appendChild(el);el.getBoundingClientRect();

 const sr=guardScene.getBoundingClientRect(),gr=securityGate?.getBoundingClientRect();
 const gateLeft=gr?gr.left-sr.left-88:sr.width*.48;

 /* Shield starts chasing shortly before impact. In OFF mode it keeps playing idle Pong and misses. */
 setTimeout(()=>aimGuardAt(y),720);

 el.style.transition='left 1.72s cubic-bezier(.35,.05,.55,1),top 1.72s ease';
 el.style.left=gateLeft+'px';

 setTimeout(()=>{
   if(!guardOn){guardMiss(el,item);return}
   if(item.bad)catchToQuarantine(el,item);
   else{
     /* brief inspection pause, then pass */
     setShieldState('checking','CHECK');
     setTimeout(()=>passToHouse(el,item,false),180);
   }
 },1740);
}
function paintGuard(){
 guardToggle?.classList.toggle('off',!guardOn);guardToggle?.setAttribute('aria-pressed',guardOn?'true':'false');
 securityGate?.classList.toggle('off',!guardOn);guardMode?.classList.toggle('off',!guardOn);
 guardScene?.classList.toggle('guard-bypass',!guardOn);
 if(!guardOn){guardTarget=null;setShieldState('', 'BYPASS')}else setShieldState('', 'SCAN');
 if(guardLabel)guardLabel.textContent=guardOn?'Защита включена':'Защита выключена';
 if(guardMode)guardMode.textContent=guardOn?'GUARD ON · PONG FILTER':'GUARD OFF · MISSES';
 if(guardState){guardState.textContent=guardOn?'ACTIVE / TRACKING':'BYPASS / MISSES';guardState.style.color=guardOn?'#68dba5':'#ffd078'}
}
guardToggle?.addEventListener('click',()=>{guardOn=!guardOn;paintGuard()});
paintGuard();
for(let i=0;i<3;i++)setTimeout(spawnTraveler,i*680);
setInterval(spawnTraveler,1380);


// Top nav follows the same active section as the side map.
const topLinks=$$('.topnav a[href^="#"]');
const topObs=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){topLinks.forEach(a=>{const on=a.getAttribute('href')==='#'+e.target.id;a.style.background=on?'#0c2537':'';a.style.color=on?'#ecf9ff':''})}}),{rootMargin:'-30% 0px -62% 0px'});$$('section[id]').forEach(s=>topObs.observe(s));


})();
