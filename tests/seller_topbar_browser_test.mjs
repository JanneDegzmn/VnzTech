import {createServer} from 'node:http';
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {browser,delay} from './browser_cdp.mjs';
const markup=execFileSync('C:/xampp/php/php.exe',['tests/seller_topbar_fixture.php'],{encoding:'utf8'});
const widgetCache=new Map();
const widgetFor=(id,conversationId=0)=>{
 const key=id+':'+conversationId;
 if(!widgetCache.has(key)) widgetCache.set(key,execFileSync('C:/xampp/php/php.exe',['tests/seller_chat_widget_fixture.php',String(id),String(conversationId)],{encoding:'utf8'}));
 return widgetCache.get(key);
};
const shellSource=await readFile('account/dashboard.php','utf8');
const sidebarScript=shellSource.slice(shellSource.indexOf('    const sellerSidebar ='),shellSource.indexOf('    const navItems ='));
const contacts=Array.from({length:12},(_,i)=>({actor_type:'seller',actor_id:i+100,display_name:'Fixture Contact '+(i+1),conversation_id:201+i,call_eligible:i<2,presence:{status:'offline'}}));
let mode='ok',language='en',requests=0;
const server=createServer(async(req,res)=>{
 if(req.url.startsWith('/VNZ-TECH/assets/')) {try{const file=req.url.split('?')[0].replace('/VNZ-TECH/','');res.setHeader('Content-Type',file.endsWith('.css')?'text/css':'text/javascript');res.end(await readFile(file));}catch{res.writeHead(404).end();}return;}
 if(req.url==='/VNZ-TECH/uploads/rail-fixture.svg'){res.setHeader('Content-Type','image/svg+xml');res.end('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" fill="#0284c7"/><circle cx="24" cy="24" r="12" fill="#e0f2fe"/></svg>');return;}
 if(req.url.includes('seller_topbar_api')){requests++;res.setHeader('Content-Type','application/json');if(mode==='error'){res.writeHead(503).end('{}');return;}if(mode==='expired'){res.writeHead(401).end('{}');return;}res.end(JSON.stringify({success:true,language,profile:{full_name:'Example Seller With A Longer Display Name',first_name:'Example',avatar_url:''},updates:{total:mode==='empty'?0:2,items:mode==='empty'?[]:[{submission_id:1,reference:'TEST-001',kind:'offer',updated_at:'2026-11-20 09:00'}]}}));return;}
 if(req.url.includes('transaction-chat-window.php')){res.setHeader('Content-Type','text/html');const url=new URL(req.url,'http://fixture');const id=Number(url.searchParams.get('transaction_id')||9);res.end(widgetFor(id,Number(url.searchParams.get('conversation_id')||0)));return;}
 if(req.url.includes('chat_')){res.setHeader('Content-Type','application/json');res.end(JSON.stringify({success:true,data:{conversations:[{conversation_type:'global',conversation_id:55,unread_count:3}],trade_requests:[],contacts}}));return;}
 res.setHeader('Content-Type','text/html');res.end(`<!doctype html><html data-seller-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/VNZ-TECH/assets/css/loader.css"><link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_dashboard.css"><link rel="stylesheet" href="/VNZ-TECH/assets/css/transaction_chat.css"><link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_theme.css"><link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_topbar.css"></head><body><div id="vnzLoader" class="vnz-loader" role="status">Loading fixture</div><div class="dashboard-layout"><aside id="sellerDashboardSidebar" class="dashboard-sidebar"><div class="sidebar-brand">Fixture navigation</div></aside><div id="sellerSidebarOverlay" class="seller-sidebar-overlay" aria-hidden="true"></div><button id="sellerMobileMenuButton" class="seller-mobile-menu-button" type="button" aria-label="Open navigation menu"><span aria-hidden="true">☰</span></button><main class="dashboard-main">${markup}<div id="contentArea" style="min-height:1800px">Synthetic seller content</div></main></div><script>${sidebarScript}</script><script>window.loadSellerPage=(...args)=>window.lastNavigation=args;</script><script src="/VNZ-TECH/assets/js/floating-messages.js"></script><script src="/VNZ-TECH/assets/js/seller-topbar.js"></script></body></html>`);
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const client=await browser();let checks=0;
const check=(x,message)=>{assert.ok(x,message);checks++;};
const run=expr=>client.evaluate(expr);
const readyWidget = () => run(`(() => {
 const widget=document.querySelector('[data-floating-chat-window]:not([hidden]) [data-transaction-chat]');
 widget.querySelector('[data-chat-status]').textContent='Fixture conversation ready';
 widget.querySelector('[data-chat-input]').disabled=false;
 widget.querySelector('[data-chat-messages]').innerHTML='<article class="transaction-chat-bubble"><p class="transaction-chat-message-content">Synthetic message visible</p></article>';
})()`);
const visibleContent = () => run(`(() => {
 const windowBox=document.querySelector('[data-floating-chat-window]:not([hidden])');
 const bounds=windowBox.getBoundingClientRect();
 return ['.floating-chat-window-body','.transaction-chat-message-area','[data-chat-messages]','[data-chat-input]'].every(selector=>{
  const node=windowBox.querySelector(selector), box=node.getBoundingClientRect();
  return getComputedStyle(node).display!=='none' && box.width>20 && box.height>10 && box.top>=bounds.top && box.bottom<=bounds.bottom+1;
 }) && windowBox.querySelector('[data-chat-messages]').textContent.includes('Synthetic message visible');
})()`);

try{
 // Mobile/tablet now use the inbox suite in seller_mobile_messages_browser_test.mjs.
 const widths=[1200];
 for(const width of widths)for(const theme of ['light','dark']){
  await client.call('Emulation.setDeviceMetricsOverride',{width,height:width<=440?667:800,deviceScaleFactor:1,mobile:width<761});
  await client.call('Emulation.setTouchEmulationEnabled',{enabled:width<=440,maxTouchPoints:1});
  await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(250);
  await run(`document.documentElement.dataset.sellerTheme=${JSON.stringify(theme)}`);
  check(await run(`(()=>{const h=document.querySelector('[data-seller-topbar]').getBoundingClientRect(),b=document.querySelector('.sidebar-brand').getBoundingClientRect();return Math.abs(h.height-85)<1&&Math.abs(b.height-85)<1&&Math.abs(h.bottom-b.bottom)<1})()`),'Seller header and branding share 85px aligned border');
  check(await run(`document.querySelectorAll('[data-floating-messages-toggle]').length===1`),'One launcher');
  check(await run(`getComputedStyle(document.querySelector('[data-floating-messages-toggle]')).position==='relative'`),'Topbar launcher not fixed at bottom');
  check(await run(`document.documentElement.scrollWidth<=innerWidth`),'No horizontal overflow');
  check(await run(`!document.querySelector('[data-topbar-greeting]') && !document.querySelector('.seller-topbar-eyebrow')`),'Redundant topbar greeting and seller label removed');
  check(await run(`(()=>{const h=document.querySelector('[data-seller-topbar]').getBoundingClientRect(),s=document.querySelector('.dashboard-sidebar').getBoundingClientRect();return innerWidth>760?Math.abs(h.left-s.right)<1:Math.abs(h.left)<1})()`),`Header edge aligns with shell on ${width}px`);
  check(await run(`['[data-topbar-notifications-toggle]','[data-floating-messages-toggle]'].every(s=>Math.abs(document.querySelector(s).getBoundingClientRect().width-44)<1)`),'Bell and chat controls retain 44px width');
  check(await run(`Math.abs(document.querySelector('.seller-topbar-avatar').getBoundingClientRect().width-48)<1`),'Avatar does not compress');
  check(await run(`document.querySelector('.seller-topbar-actions').getBoundingClientRect().right<=innerWidth+1`),'Action group remains in viewport');
  check(await run(`(()=>{const b=document.querySelector('[data-floating-messages-toggle]').getBoundingClientRect(),i=document.querySelector('[data-floating-messages-toggle] svg').getBoundingClientRect();return Math.abs((b.left+b.right-i.left-i.right)/2)<1})()`),'Chat glyph centered inside its button');
  check(await run(`getComputedStyle(document.querySelector('[data-floating-messages-toggle] svg')).strokeWidth==='1.55px'`),'Chat glyph uses refined stroke weight');
  check(await run(`document.querySelector('[data-topbar-notifications-badge]').textContent==='2'`),'Real fixture count');
  await run(`document.querySelector('[data-topbar-notifications-toggle]').click()`);await delay(220);
  check(await run(`!document.querySelector('#sellerTopbarNotifications').hidden`),'Bell opens');
  check(await run(`document.querySelector('#sellerTopbarNotifications').getBoundingClientRect().bottom<=innerHeight`),'Bounded bell');
  check(await run(`getComputedStyle(document.querySelector('#sellerTopbarNotifications')).animationName==='seller-panel-down'`),'Downward animation');
  await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(200);
  check(await run(`document.querySelector('#sellerTopbarNotifications').hidden`),'Chat closes bell');
  check(await run(`document.querySelector('[data-floating-messages]').dataset.topbarView==='categories'`),'Chat categories open');
  check(await run(`Array.from(document.querySelectorAll('.floating-messages-tooltip')).every(label=>{const style=getComputedStyle(label);return style.display!=='none' && style.visibility==='visible' && Number(style.opacity)===1 && label.getBoundingClientRect().width>0;})`),'Category titles visible with actual pointer media');
  if(width<=440) check(await run(`matchMedia('(hover: none)').matches`),'Touch-only hover:none media is active');
  if(width===375 || width===1200) {
    await mkdir('.qa-seller-topbar',{recursive:true});
    const categoryShot=await client.call('Page.captureScreenshot',{format:'png'});
    await writeFile(`.qa-seller-topbar/categories-${theme}-${width}.png`,Buffer.from(categoryShot.data,'base64'));
  }

  check(await run(`Array.from(document.querySelectorAll('.floating-messages-tooltip')).every(label=>{const style=getComputedStyle(label);return style.boxShadow==='none' && style.backgroundColor==='rgba(0, 0, 0, 0)' && style.borderTopWidth==='0px';})`),'Category labels have no background border or shadow');
  await run(`document.documentElement.classList.add('vnz-loading')`);await delay(400);
  check(await run(`!!document.elementFromPoint(innerWidth-35,35)?.closest('#vnzLoader')`),'Reload overlay covers and intercepts header controls');
  await run(`document.documentElement.classList.remove('vnz-loading')`);await delay(400);

  check(await run(`document.querySelector('[data-floating-messages-categories]').getBoundingClientRect().top>=document.querySelector('[data-seller-topbar]').getBoundingClientRect().bottom`),'Chat opens below header');
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
  check(await run(`document.querySelector('[data-floating-messages]').dataset.topbarView==='closed' && document.activeElement.matches('[data-floating-messages-toggle]')`),'Escape closes and restores focus');
  await run(`scrollTo(0,400)`);await delay(100);
  check(await run(`Math.abs(document.querySelector('[data-seller-topbar]').getBoundingClientRect().top)<2`),'Sticky header');
  await run(`scrollTo(0,0);document.querySelector('[data-topbar-notifications-toggle]').click()`);await delay(150);
  await run(`document.querySelector('[data-submission-id]').click()`);
  check(await run(`lastNavigation[0]==='submission' && lastNavigation[2].id===1`),'AJAX destination');
  await run(`document.querySelector('[data-topbar-profile]').click()`);
  check(await run(`lastNavigation[0]==='profile'`),'Profile destination');
  await run(`document.querySelector('[data-topbar-notifications-close]').click(); window.openTransactionChat(9)`);await delay(250);
  check(await run(`document.querySelector('[data-floating-chat-window]').getBoundingClientRect().bottom<=innerHeight`),'Chat height within viewport in each theme/width');
  await readyWidget();
  await delay(250);
  check(await run(`Math.abs(document.querySelector('[data-floating-chat-window]').getBoundingClientRect().bottom-(innerHeight-12))<2`),'Conversation docks at bottom');
  check(await visibleContent(),'Docked messages and composer visible');
  for(let cycle=0;cycle<2;cycle++) {
   await run(`document.querySelector('[data-chat-maximize]').click()`);await delay(300);
   check(await run(`(()=>{const b=document.querySelector('[data-floating-chat-window]').getBoundingClientRect();return b.width>=document.documentElement.clientWidth-26 && b.height>=innerHeight-26 && b.top<=13;})()`),'Fullscreen fills available viewport');
   check(await visibleContent(),'Fullscreen messages and composer visible');
   check(await run(`(()=>{const toggle=document.querySelector('[data-floating-messages-toggle]').getBoundingClientRect();return !!document.elementFromPoint(toggle.x+toggle.width/2,toggle.y+toggle.height/2)?.closest('[data-floating-chat-window]');})()`),'Fullscreen covers topbar controls instead of leaving an overlapping launcher');

   if(cycle===0 && (width===1200 || width===375)) {
    const fullscreenShot=await client.call('Page.captureScreenshot',{format:'png'});
    await writeFile(`.qa-seller-topbar/fullscreen-v3-${theme}-${width}.png`,Buffer.from(fullscreenShot.data,'base64'));
   }

   await run(`document.querySelector('[data-chat-input]').focus();document.querySelector('[data-chat-input]').value='Unsent fixture draft';document.querySelector('[data-chat-maximize]').click()`);await delay(300);
   check(await run(`(()=>{const b=document.querySelector('[data-floating-chat-window]').getBoundingClientRect();return b.width<=421 && Math.abs(b.bottom-(innerHeight-12))<2 && document.querySelector('[data-chat-input]').value==='Unsent fixture draft';})()`),'Restore returns to bottom and preserves unsent draft');
   check(await visibleContent(),'Restored content visible');
  }


  await run(`document.querySelector('[data-chat-workspace-back]').click()`);await delay(250);
  check(await run(`document.querySelector('[data-floating-chat-window]').hidden && !document.querySelector('[data-floating-messages-panel]').inert && document.querySelector('[data-floating-messages-title]').textContent==='Private Chat' && !document.querySelector('[data-floating-chat-window]').classList.contains('is-workspace-list-open')`),'Back minimizes only this chat and returns to topbar Private Chat list');

  check(await run(`(()=>{const list=document.querySelector('[data-floating-messages-list]');const b=list.getBoundingClientRect();const rows=Array.from(list.querySelectorAll('.floating-messages-call-row'));return rows.length===12 && list.scrollHeight>list.clientHeight && rows.filter(row=>{const r=row.getBoundingClientRect();return r.top>=b.top-1 && r.bottom<=b.bottom+1;}).length===5;})()`),'Five complete contacts visible with remaining contacts internally scrollable');
  check(await run(`document.querySelectorAll('[data-floating-messages-list] [data-call-request]').length===4`),'Contact call controls retained');
  if(width===1200 || width===375) {
   const contactsShot=await client.call('Page.captureScreenshot',{format:'png'});
   await writeFile(`.qa-seller-topbar/v4-contacts-${theme}-${width}.png`,Buffer.from(contactsShot.data,'base64'));
  }

  await run(`document.querySelector('[data-floating-messages-list]').scrollTop=9999`);
  check(await run(`document.querySelector('[data-floating-messages-list]').scrollTop>0`),'People list can scroll to remaining entries');
  await run(`window.openTransactionChat(9)`);await delay(250);
  await run(`document.querySelector('[data-chat-maximize]').click()`);await delay(300);
  check(await visibleContent(),'Fullscreen from list restores the selected conversation body');
  await run(`document.querySelector('[data-chat-maximize]').click()`);await delay(300);
  check(await run(`document.querySelector('[data-floating-messages-badge]').textContent==='3'`),'Existing unread chat badge');
  const chatShot=await client.call('Page.captureScreenshot',{format:'png'});await mkdir('.qa-seller-topbar',{recursive:true});await writeFile(`.qa-seller-topbar/chat-${theme}-${width}.png`,Buffer.from(chatShot.data,'base64'));
  await run(`document.querySelector('[data-chat-close]').click()`);await delay(220);
  await mkdir('.qa-seller-topbar',{recursive:true});
  await run(`document.querySelector('[data-topbar-notifications-toggle]').click()`);await delay(200);
  const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(`.qa-seller-topbar/${theme}-${width}.png`,Buffer.from(shot.data,'base64'));
 }
 await run(`document.querySelector('[data-topbar-notifications-close]').click(); window.openTransactionChat(9)`);await delay(300);
 check(await run(`document.querySelector('[data-floating-messages]').dataset.topbarView==='conversation' && !document.querySelector('[data-floating-chat-window]').hidden`),'Existing transaction chat API opens selected dropdown');
 check(await run(`document.querySelector('[data-floating-chat-window]').getBoundingClientRect().bottom<=innerHeight`),'Conversation bounded on mobile');
 await run(`window.mockWidget=document.querySelector('[data-transaction-chat]'); window.mockWidget.dataset.activeCallOwner='true'; document.querySelector('[data-chat-minimize]').click()`);
 check(await run(`document.querySelector('[data-floating-chat-window]').hidden && window.mockWidget.isConnected`),'Collapse preserves active call owner widget');
 await run(`window.openTransactionChat(9)`);await delay(150);
 check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===1 && !document.querySelector('[data-floating-chat-window]').hidden`),'Existing chat reopens without duplication');
 await run(`document.querySelector('[data-chat-close]').click()`);await delay(220);
 check(await run(`window.mockWidget.isConnected && window.mockWidget.parentElement.matches('[data-call-owner-host]')`),'Close retains existing call-owner preservation');
 await client.call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
 await run(`document.querySelector('[data-floating-messages-toggle]').click()`);
 check(await run(`getComputedStyle(document.querySelector('[data-floating-messages-categories]')).animationName==='none'`),'Reduced motion');
 await client.call('Emulation.setEmulatedMedia',{features:[]});
 await client.call('Emulation.setDeviceMetricsOverride',{width:375,height:480,deviceScaleFactor:1,mobile:true});
 await run(`document.querySelector('[data-topbar-notifications-toggle]').click()`);await delay(200);
 check(await run(`document.querySelector('#sellerTopbarNotifications').getBoundingClientRect().bottom<=innerHeight`),'Short-screen bell bounded');
 await run(`window.openTransactionChat(9)`);await delay(200);
 check(await run(`document.querySelector('[data-floating-chat-window]').getBoundingClientRect().bottom<=innerHeight`),'Short-screen conversation bounded');
 await readyWidget();await delay(250);
 check(await visibleContent(),'Short-screen docked messages and composer visible');
 await run(`document.querySelector('[data-chat-maximize]').click()`);await delay(300);
 check(await run(`document.querySelector('[data-floating-chat-window]').getBoundingClientRect().width<=innerWidth`),'Mobile maximized chat bounded');
 check(await visibleContent(),'Short-screen fullscreen messages and composer visible');
 await run(`document.querySelector('[data-chat-close]').click()`);await delay(200);
 mode='empty';await run(`window.dispatchEvent(new Event('vnz:seller-profile-updated'))`);await delay(200);
 check(await run(`document.querySelector('[data-topbar-notifications-badge]').hidden && document.querySelector('[data-topbar-state]').textContent.includes('caught up')`),'Empty state');
 language='fil';await run(`window.dispatchEvent(new Event('vnz:seller-profile-updated'))`);await delay(200);
 check(await run(`document.querySelector('[data-seller-topbar]').dataset.language==='fil' && document.querySelector('[data-topbar-notifications-title]').textContent.includes('Mga update')`),'Taglish controls refresh without a greeting');
 mode='error';await run(`window.dispatchEvent(new Event('vnz:seller-profile-updated'))`);await delay(200);
 check(await run(`!document.querySelector('[data-topbar-retry]').hidden && document.querySelector('[data-topbar-updates]').children.length===0`),'Error clears stale updates');
 mode='expired';await run(`document.querySelector('[data-topbar-retry]').click()`);await delay(200);
 check(await run(`document.querySelector('[data-topbar-state]').textContent.includes('Expired') && document.querySelector('[data-topbar-retry]').hidden`),'Expired session state');

 // Chat v4 multi-window lifecycle uses separate real widget instances and synthetic endpoints.
 mode='ok';language='en';
 for(const width of [1200]) for(const theme of ['light','dark']) {
  await client.call('Emulation.setDeviceMetricsOverride',{width,height:width<900?667:800,deviceScaleFactor:1,mobile:width<900});
  await client.call('Emulation.setTouchEmulationEnabled',{enabled:width<900,maxTouchPoints:1});
  await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(250);
  await run(`document.documentElement.dataset.sellerTheme=${JSON.stringify(theme)}`);
  await run(`window.openTransactionChat(1)`);await run(`window.openTransactionChat(2)`);await delay(300);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===${width>=900?2:1}`),'Two visible desktop docks, one small-screen dock');
  await run(`window.openTransactionChat(3)`);await delay(300);
  await run(`document.querySelectorAll('[data-floating-chat-window]').forEach(chat=>{chat.querySelector('[data-chat-input]').disabled=false;chat.querySelector('[data-chat-input]').value='Draft '+chat.dataset.transactionId;chat.querySelector('[data-chat-status]').textContent='Fixture ready';chat.querySelector('[data-chat-messages]').innerHTML='<article class="transaction-chat-bubble"><p class="transaction-chat-message-content">Synthetic conversation '+chat.dataset.transactionId+'</p></article>';})`);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===3 && document.querySelector('[data-chat-overflow]').dataset.hiddenChatCount==='${width>=900?1:2}'`),'Third chat retained and hidden-window count correct');
  await run(`document.querySelector('#contentArea').click()`);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===3 && document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===${width>=900?2:1}`),'Outside click retains docks');
  await run(`document.querySelector('[data-topbar-notifications-toggle]').click()`);await delay(100);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===${width>=900?2:1}`),'Bell opening retains docks');
  await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(100);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===${width>=900?2:1}`),'Chat selector opening retains docks');
  await run(`document.querySelector('#contentArea').click()`);
  if(width>=900) {
   const point=await run(`(()=>{const b=document.querySelector('[data-chat-rail] button').getBoundingClientRect();return {x:b.x+b.width/2,y:b.y+b.height/2};})()`);
   await client.call('Input.dispatchMouseEvent',{type:'mouseMoved',...point});await delay(100);
   check(await run(`getComputedStyle(document.querySelector('[data-chat-rail] .seller-chat-rail-name')).visibility==='visible'`),'Rail avatar reveals chat name on hover');
  }
  await run(`document.querySelector('[data-chat-rail] button').focus()`);
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown'});
  check(await run(`document.activeElement.matches('[data-chat-overflow-key]')`),'Rail supports keyboard focus selection');
  const multiShot=await client.call('Page.captureScreenshot',{format:'png'});
  await writeFile(`.qa-seller-topbar/v5-docks-${theme}-${width}.png`,Buffer.from(multiShot.data,'base64'));

  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13,text:'\r',unmodifiedText:'\r'});
  await client.call('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await delay(250);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===3 && Array.from(document.querySelectorAll('[data-floating-chat-window]')).every(chat=>chat.querySelector('[data-chat-input]').value==='Draft '+chat.dataset.transactionId)`),'Overflow selection retains every independent draft');
  await run(`window.openTransactionChat(1)`);await delay(150);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===3`),'Reopen same chat does not duplicate it');
  await run(`window.v4Selected=document.querySelector('[data-floating-chat-window].is-topbar-selected');window.v4DockIds=Array.from(document.querySelectorAll('[data-floating-chat-window]:not([hidden])')).map(chat=>chat.dataset.transactionId).sort(); window.v4Selected.querySelector('[data-chat-maximize]').click()`);await delay(300);
  await readyWidget();
  check(await visibleContent(),'Selected multi-chat fullscreen has real content and composer');
  await run(`document.querySelector('#contentArea').click()`);
  check(await run(`window.v4Selected.classList.contains('is-maximized') && !window.v4Selected.hidden`),'Outside click retains fullscreen');
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});await delay(250);
  check(await run(`!window.v4Selected.classList.contains('is-maximized') && JSON.stringify(Array.from(document.querySelectorAll('[data-floating-chat-window]:not([hidden])')).map(chat=>chat.dataset.transactionId).sort())===JSON.stringify(window.v4DockIds)`),'Escape restores prior visible dock arrangement');
  await run(`window.v4Selected.querySelector('[data-chat-minimize]').click()`);await delay(100);
  check(await run(`window.v4Selected.hidden && window.v4Selected.isConnected && window.v4Selected.classList.contains('is-minimized')`),'Minimize moves only selected chat into overflow');
  await run(`document.querySelector('[data-chat-overflow-key="'+window.v4Selected.dataset.dockKey+'"]').click()`);await delay(150);
  check(await run(`!window.v4Selected.hidden && !window.v4Selected.classList.contains('is-minimized')`),'Click/tap overflow option restores minimized chat');
  await run(`window.v4Selected.querySelector('[data-chat-workspace-back]').click()`);await delay(200);
  check(await run(`window.v4Selected.hidden && !document.querySelector('[data-floating-messages-panel]').inert && document.querySelector('[data-floating-messages-title]').textContent==='Private Chat'`),'Back returns to originating topbar list');
  check(await run(`document.querySelector('[data-floating-messages-panel]').getBoundingClientRect().top>=document.querySelector('[data-seller-topbar]').getBoundingClientRect().bottom`),'Back list is at top, not inside old titled dock');
  await run(`window.openTransactionChat(1);`);await delay(150);
  await run(`window.v4Selected.dataset.originCategory='categories'; window.v4Selected.querySelector('[data-chat-workspace-back]').click()`);await delay(100);
  check(await run(`document.querySelector('[data-floating-messages]').classList.contains('is-menu-open') && window.v4Selected.hidden`),'Back from category-origin chat returns to topbar categories');

  for(let id=4;id<=10;id++) await run(`window.openTransactionChat(${id})`);
  await run(`window.openTransactionChat(11)`);await delay(200);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===10 && document.querySelector('[data-floating-messages-error]').textContent.includes('10 open chats')`),'Bounded capacity gives explicit message without deleting open chats');
  await run(`document.querySelectorAll('[data-floating-chat-window]').forEach(chat=>chat.querySelector('[data-chat-minimize]').click())`);await delay(100);
  check(await run(`document.querySelector('[data-chat-overflow-toggle]').textContent==='+5' && !document.querySelector('[data-chat-overflow-toggle]').hidden && document.querySelectorAll('[data-chat-rail] button').length===5`),'Bounded rail reserves +N for remaining minimized chats');
  await run(`document.querySelector('[data-chat-overflow-toggle]').focus()`);
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown'});
  check(await run(`!document.querySelector('[data-chat-overflow-list]').hidden && document.activeElement.closest('[data-chat-overflow-list]')!==null`),'Plus N selector supports keyboard selection');
  if(width>=900) {
   const point=await run(`(()=>{const b=document.querySelector('[data-chat-overflow-toggle]').getBoundingClientRect();return {x:b.x+b.width/2,y:b.y+b.height/2};})()`);
   await client.call('Input.dispatchMouseEvent',{type:'mouseMoved',...point});
   const popup=await run(`(()=>{const b=document.querySelector('[data-chat-overflow-list]').getBoundingClientRect();return {x:b.x+b.width/2,y:b.bottom-10};})()`);
   await client.call('Input.dispatchMouseEvent',{type:'mouseMoved',x:point.x,y:point.y-35});
   await client.call('Input.dispatchMouseEvent',{type:'mouseMoved',...popup});await delay(220);
   check(await run(`!document.querySelector('[data-chat-overflow-list]').hidden`),'Plus N hover selector survives crossing pointer gap');
  }
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13,text:'\r',unmodifiedText:'\r'});
  await client.call('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await delay(150);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===1 && document.querySelectorAll('[data-floating-chat-window]').length===10`),'Selecting +N restores existing widget without duplication');

  await run(`document.querySelectorAll('[data-floating-chat-window]').forEach(chat=>chat.querySelector('[data-chat-close]').click())`);await delay(250);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]').length===0 && document.querySelector('[data-chat-overflow]').hidden`),'Explicit Close clears each window and overflow');
  if(width<761) {
   await run(`document.querySelector('[data-floating-messages-close]').click();document.querySelector('#sellerMobileMenuButton').click()`);await delay(250);
   check(await run(`document.querySelector('#sellerDashboardSidebar').classList.contains('mobile-open') && !document.querySelector('[data-seller-sidebar-close]').hidden`),'Open mobile sidebar has visible burger close');
   const sidebarShot=await client.call('Page.captureScreenshot',{format:'png'});
   await writeFile(`.qa-seller-topbar/v4-sidebar-${theme}-${width}.png`,Buffer.from(sidebarShot.data,'base64'));

   check(await run(`(()=>{const button=document.querySelector('[data-seller-sidebar-close]');const b=button.getBoundingClientRect();return b.width===44 && b.height===44 && !!document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)?.closest('[data-seller-sidebar-close]');})()`),'Sidebar close has reachable 44px hit target above topbar');
   await run(`document.querySelector('[data-seller-sidebar-close]').click()`);await delay(100);
   check(await run(`!document.querySelector('#sellerDashboardSidebar').classList.contains('mobile-open') && document.activeElement.id==='sellerMobileMenuButton'`),'Sidebar close uses existing toggle and restores focus');
  }
 }

 // Reference layout v5: observable geometry, rail avatars/+N and resize retention.
 for(const [width,expectedSlots] of [[1200,2],[1440,3],[1760,4],[1920,4]]) for(const theme of ['light','dark']) {
  const height=width===1760?643:width<900?667:800;
  await client.call('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<=768});
  await client.call('Emulation.setTouchEmulationEnabled',{enabled:width<900,maxTouchPoints:1});
  await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(200);
  await run(`document.documentElement.dataset.sellerTheme=${JSON.stringify(theme)}`);
  for(let id=1;id<=10;id++) await run(`window.openTransactionChat(${id})`);
  await delay(250);
  await run(`document.querySelectorAll('[data-floating-chat-window]').forEach(chat=>{chat.querySelector('[data-chat-input]').disabled=false;chat.querySelector('[data-chat-input]').value='Reference draft '+chat.dataset.transactionId;chat.querySelector('[data-chat-status]').textContent='Fixture ready';chat.querySelector('[data-chat-messages]').innerHTML='<article class="transaction-chat-bubble"><p class="transaction-chat-message-content">Synthetic message visible '+chat.dataset.transactionId+'</p></article>';})`);
  check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===${expectedSlots}`),'Reference layout fits expected visible slot count');
  check(await run(`Array.from(document.querySelectorAll('[data-floating-chat-window]:not([hidden])')).every(chat=>{const b=chat.getBoundingClientRect();return Math.abs(b.width-Math.min(360,innerWidth-84))<2 && Math.abs(b.height-500)<2 && Math.abs(b.bottom-(innerHeight-12))<2;})`),'Reference normal boxes are360x500 and bottom-aligned');
  check(await run(`(()=>{const boxes=Array.from(document.querySelectorAll('[data-floating-chat-window]:not([hidden])')).map(n=>n.getBoundingClientRect()).sort((a,b)=>a.x-b.x);return boxes.every((b,i)=>b.left>=0 && b.right<=document.documentElement.clientWidth && (!i || Math.abs(b.left-boxes[i-1].right-12)<2));})()`),'Reference windows have12px gaps without overflow');
  check(await visibleContent(),'Reference box has visible messages and composer');
  check(await run(`(()=>{const rail=document.querySelector('[data-chat-overflow]').getBoundingClientRect();return rail.right<=document.documentElement.clientWidth && rail.bottom<=innerHeight && Array.from(document.querySelectorAll('[data-floating-chat-window]:not([hidden])')).every(n=>n.getBoundingClientRect().right<=rail.left-10);})()`),'Dock windows do not overlap vertical rail');
  check(await run(`document.querySelector('[data-chat-overflow]').dataset.hiddenChatCount==='${10-expectedSlots}' && document.querySelectorAll('[data-chat-rail] button').length>0`),'Rail organizes other mounted chats with independent hidden count');
  check(await run(`Array.from(document.querySelectorAll('[data-chat-rail] button')).every(b=>b.getAttribute('aria-label').startsWith('Open ') && b.title && b.firstElementChild.textContent.trim())`),'Rail has accessible names and initials fallback');
  await run(`document.querySelector('[data-chat-rail] button').focus()`);
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown'});
  check(await run(`document.activeElement.closest('[data-chat-rail]')!==null && getComputedStyle(document.activeElement.querySelector('.seller-chat-rail-name')).visibility==='visible'`),'Rail names are visible for keyboard focus');
  if(width===1760 && theme==='light') {
   await run(`document.querySelector('[data-floating-chat-window][data-transaction-id="6"] [data-transaction-chat]').dataset.conversationPeerPicture='uploads/rail-fixture.svg';document.querySelector('[data-floating-chat-window][data-transaction-id="5"] [data-transaction-chat]').dataset.conversationPeerPicture='javascript:alert(1)';dispatchEvent(new Event('resize'))`);await delay(150);
   check(await run(`(()=>{const key=document.querySelector('[data-floating-chat-window][data-transaction-id="6"]').dataset.dockKey;const image=document.querySelector('[data-chat-rail] [data-chat-overflow-key="'+key+'"] img');return image && image.complete && image.naturalWidth>0 && getComputedStyle(image).objectFit==='cover';})()`),'Rail reuses available safe local avatar');
   check(await run(`(()=>{const key=document.querySelector('[data-floating-chat-window][data-transaction-id="5"]').dataset.dockKey;return !document.querySelector('[data-chat-rail] [data-chat-overflow-key="'+key+'"] img');})()`),'Unsafe avatar scheme uses initials fallback');
  }
  if([1760,375].includes(width)) {
   const layoutShot=await client.call('Page.captureScreenshot',{format:'png'});
   await writeFile(`.qa-seller-topbar/v5-reference-${theme}-${width}.png`,Buffer.from(layoutShot.data,'base64'));
  }
  if(width===1920) {
   await run(`window.referenceNodes=Array.from(document.querySelectorAll('[data-floating-chat-window]'));`);
   await client.call('Emulation.setDeviceMetricsOverride',{width:375,height:480,deviceScaleFactor:1,mobile:true});await delay(200);
   check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===1 && window.referenceNodes.every(n=>n.isConnected && n.querySelector('[data-chat-input]').value==='Reference draft '+n.dataset.transactionId)`),'Resize to phone retains all widgets and drafts');
   check(await run(`document.querySelector('[data-chat-overflow]').hidden`),'Mobile conversation hides desktop overflow rail');
   check(await visibleContent(),'Short reference layout retains message/composer visibility');
   await client.call('Emulation.setDeviceMetricsOverride',{width:1920,height:800,deviceScaleFactor:1,mobile:false});await delay(200);
   check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===4 && window.referenceNodes.every(n=>n.isConnected)`),'Resize back restores four desktop slots without duplicate widgets');
  }
 }
 await run(`document.querySelector('[data-floating-messages]').dataset.placement='floating'`);
 check(await run(`getComputedStyle(document.querySelector('[data-floating-messages-toggle]')).position==='fixed'`),'Default admin/public-style mode retains bottom-fixed launcher');
 check(client.errors.length===0,'No browser runtime exceptions');
 console.log(`${checks} browser checks passed; synthetic PHP markup and actual JS/CSS, desktop/mobile light/dark. ${requests} fixture notification reads.`);
}finally{await client.close();await new Promise(r=>server.close(r));}
