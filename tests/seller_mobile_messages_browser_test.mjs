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
let mode='ok',language='en',requests=0,chatMode='ok';
const conversations=Array.from({length:18},(_,i)=>({conversation_type:i===17?'global':i===16?'transaction':'private',conversation_class:i===16?'transaction':'private',transaction_id:i===16?17:0,conversation_id:201+i,display_name:'Fixture Chat '+(i+1),last_message:'Synthetic preview '+(i+1),unread_count:i%3===0?2:0,updated_at:'2026-11-20 09:'+String(59-i).padStart(2,'0')+':00'}));
contacts.forEach((c,i)=>{c.profile_picture=i===0?'uploads/rail-fixture.svg':i===1?'https://invalid.example/avatar.png':''; c.last_message='Synthetic contact preview'; c.presence={participants:[{is_online:i===0}]};});
const server=createServer(async(req,res)=>{
 if(req.url.startsWith('/VNZ-TECH/assets/')) {try{const file=req.url.split('?')[0].replace('/VNZ-TECH/','');res.setHeader('Content-Type',file.endsWith('.css')?'text/css':'text/javascript');res.end(await readFile(file));}catch{res.writeHead(404).end();}return;}
 if(req.url==='/VNZ-TECH/uploads/rail-fixture.svg'){res.setHeader('Content-Type','image/svg+xml');res.end('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" fill="#0284c7"/><circle cx="24" cy="24" r="12" fill="#e0f2fe"/></svg>');return;}
 if(req.url.includes('seller_topbar_api')){requests++;res.setHeader('Content-Type','application/json');if(mode==='error'){res.writeHead(503).end('{}');return;}if(mode==='expired'){res.writeHead(401).end('{}');return;}res.end(JSON.stringify({success:true,language,profile:{full_name:'Example Seller With A Longer Display Name',first_name:'Example',avatar_url:''},updates:{total:mode==='empty'?0:2,items:mode==='empty'?[]:[{submission_id:1,reference:'TEST-001',kind:'offer',updated_at:'2026-11-20 09:00'}]}}));return;}
 if(req.url.includes('transaction-chat-window.php')){res.setHeader('Content-Type','text/html');const url=new URL(req.url,'http://fixture');const id=Number(url.searchParams.get('transaction_id')||9);res.end(widgetFor(id,Number(url.searchParams.get('conversation_id')||0)));return;}
 if(req.url.includes('chat_')){res.setHeader('Content-Type','application/json');if(chatMode==='error'&&req.url.includes('chat_conversations')){res.writeHead(503).end('{}');return;}if(chatMode==='slow'&&req.method==='POST') await delay(400);res.end(JSON.stringify({success:true,data:chatMode==='empty'?{conversations:[],contacts:[],trade_requests:[]}:{conversation_type:'private',conversation_id:201,display_name:'Fixture Contact 1',conversations,trade_requests:[{trade_request_id:1,conversation_id:999,trade_item_name:'Fixture Trade',request_reference:'TEST-TRADE',last_message:'Trade preview',updated_at:'2026-11-20 08:00:00',unread_count:1}],contacts}}));return;}
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

try {
 for (const [width,height] of [[320,667],[375,812],[768,1024],[1024,768],[812,375]]) for(const theme of ['light','dark']) {
  chatMode='ok';
  await client.call('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:true});
  await client.navigate(`http://127.0.0.1:${server.address().port}/`); await delay(200);
  await run(`document.documentElement.dataset.sellerTheme=${JSON.stringify(theme)}`);
  check(await run(`(()=>{const h=document.querySelector('[data-seller-topbar]').getBoundingClientRect(),b=document.querySelector('.sidebar-brand').getBoundingClientRect();return Math.abs(h.height-85)<1&&Math.abs(b.height-85)<1&&Math.abs(h.bottom-b.bottom)<1})()`),'Seller header and branding share 85px aligned border');
  await run(`document.querySelector('[data-floating-messages-toggle]').click()`); await delay(300);
  check(await run(`!document.querySelector('[data-mobile-inbox]').hidden`),'Messages directly opens inbox');
  check(await run(`!document.querySelector('[data-floating-messages]').classList.contains('is-menu-open')`),'Category dropdown skipped');
  check(await run(`(()=>{const b=document.querySelector('[data-mobile-inbox]').getBoundingClientRect();return b.left===0&&b.top===0&&Math.abs(b.width-innerWidth)<1&&Math.abs(b.height-visualViewport.height)<2})()`),'Inbox fills viewport');
  check(await run(`document.documentElement.scrollWidth<=innerWidth`),'No page horizontal overflow');
  check(await run(`document.querySelectorAll('.seller-inbox-row').length===19`),'Conversations plus trade shown');
  check(await run(`document.querySelectorAll('.seller-inbox-person').length===12`),'Eligible people strip');
  check(await run(`!!document.querySelector('.seller-inbox-person img')&&!document.querySelector('img[src*="invalid.example"]')`),'Safe avatar only');
  check(await run(`document.querySelectorAll('.seller-inbox-online').length===1`),'Real presence only');
  check(await run(`(()=>{const s=getComputedStyle(document.querySelector('[data-mobile-inbox]'));return s.backgroundColor!=='rgba(0, 0, 0, 0)'&&s.color!==s.backgroundColor})()`),'Readable themed inbox surface');
  await run(`document.querySelector('[data-inbox-filter="unread"]').click()`);
  check(await run(`Array.from(document.querySelectorAll('.seller-inbox-row')).every(r=>r.classList.contains('is-unread'))`),'Unread filter');
  await run(`document.querySelector('[data-inbox-filter="all"]').click();const s=document.querySelector('.seller-inbox-search');s.value='Chat 4';s.dispatchEvent(new Event('input'));`);
  check(await run(`document.querySelectorAll('.seller-inbox-row').length===1`),'Search filters loaded rows');
  await run(`document.querySelector('.seller-inbox-row').click()`);await delay(250);await readyWidget();
  check(await run(`document.querySelector('[data-mobile-inbox]').hidden&&document.querySelector('[data-floating-messages]').dataset.mobileMessagesView==='conversation'`),'Selection opens full-screen chat');
  check(await run(`(()=>{const b=document.querySelector('[data-floating-chat-window]:not([hidden])').getBoundingClientRect();return b.left===0&&b.top===0&&Math.abs(b.width-innerWidth)<1&&Math.abs(b.height-visualViewport.height)<2})()`),'Chat fills viewport');
  check(await visibleContent(),'Real widget messages and composer visible');
  await run(`document.querySelector('[data-chat-input]').value='Retained mobile draft';document.querySelector('#contentArea').click()`);
  check(await run(`!!document.querySelector('[data-floating-chat-window]:not([hidden])')`),'Outside click retains conversation');
  await run(`document.querySelector('[data-chat-workspace-back]').click()`);await delay(250);
  check(await run(`!document.querySelector('[data-mobile-inbox]').hidden&&document.querySelector('.seller-inbox-search').value==='Chat 4'`),'Back restores inbox query');
  await run(`document.querySelector('.seller-inbox-row').click()`);await delay(200);
  check(await run(`document.querySelector('[data-chat-input]').value==='Retained mobile draft'&&document.querySelectorAll('[data-floating-chat-window]').length===1`),'Same widget/draft reused');
  await run(`history.back()`);await delay(250);
  check(await run(`!document.querySelector('[data-mobile-inbox]').hidden`),'Browser Back returns inbox');
  await run(`document.querySelector('[aria-label="Close messages"]').click()`);await delay(250);
  check(await run(`document.querySelector('[data-mobile-inbox]').hidden&&!document.documentElement.classList.contains('seller-messages-active')&&!document.querySelector('[data-floating-chat-window]:not([hidden])')`),'Close returns page with dock retained but hidden');
  await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(200);
  await run(`document.querySelector('.seller-inbox-search').value='';document.querySelector('.seller-inbox-search').dispatchEvent(new Event('input'));document.querySelector('.seller-inbox-actions button').click()`);
  check(await run(`document.querySelectorAll('.seller-inbox-row').length===12&&document.querySelector('.seller-inbox-header h2').textContent==='New message'`),'New-message eligible people');
  if(width===375){await mkdir('.qa-seller-mobile-messages',{recursive:true});await run(`document.querySelector('.seller-inbox-actions button').click()`);const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(`.qa-seller-mobile-messages/inbox-${theme}.png`,Buffer.from(shot.data,'base64'));}
 }

 // Back restores scroll/filter; native focus remains in the active dialog.
 await client.call('Emulation.setDeviceMetricsOverride',{width:375,height:812,deviceScaleFactor:1,mobile:true});
 await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(150);
 await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(250);
 check(await run(`document.activeElement.matches('.seller-inbox-header h2')`),'Opening inbox does not focus keyboard input');
 check(await run(`document.querySelector('[aria-label="Close messages"]').textContent==='\u00d7'`),'Close glyph correctly encoded');
 await run(`document.querySelector('.seller-inbox-list').scrollTop=300;window.savedInboxScroll=document.querySelector('.seller-inbox-list').scrollTop;document.querySelectorAll('.seller-inbox-row')[6].click()`);await delay(200);await readyWidget();
 await mkdir('.qa-seller-mobile-messages',{recursive:true});
 const conversationShot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile('.qa-seller-mobile-messages/conversation-light.png',Buffer.from(conversationShot.data,'base64'));
 await run(`document.querySelector('[data-chat-workspace-back]').click()`);await delay(220);
 check(await run(`Math.abs(document.querySelector('.seller-inbox-list').scrollTop-window.savedInboxScroll)<2`),'Back retains inbox scroll');
 await run(`document.querySelector('[data-inbox-filter="unread"]').click();document.querySelector('.seller-inbox-row').click()`);await delay(200);await readyWidget();
 await run(`document.querySelector('[data-chat-workspace-back]').click()`);await delay(220);
 check(await run(`document.querySelector('[data-inbox-filter="unread"]').getAttribute('aria-pressed')==='true'`),'Back retains unread filter');
 await run(`document.querySelector('.seller-inbox-actions button').focus()`);
 await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',modifiers:8});
 check(await run(`document.activeElement.matches('.seller-inbox-row:last-child')`),'Shift Tab wraps from first to last modal control');
 await client.call('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',modifiers:8});
 await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});await delay(220);
 check(await run(`document.querySelector('[data-mobile-inbox]').hidden&&document.activeElement.matches('[data-floating-messages-toggle]')`),'Escape closes inbox and restores launcher focus');
 await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(200);
 await run(`document.querySelector('[data-inbox-filter="all"]').click();document.querySelector('.seller-inbox-row').click()`);await delay(200);await readyWidget();
 await run(`window.keptMobileWidget=document.querySelector('[data-floating-chat-window]:not([hidden]) [data-transaction-chat]');window.keptMobileWidget.dataset.activeCallOwner='fixture';document.querySelector('[data-chat-workspace-back]').click()`);await delay(200);
 await run(`document.querySelectorAll('.seller-inbox-row')[1].click()`);await delay(200);await readyWidget();
 check(await run(`window.keptMobileWidget.isConnected&&window.keptMobileWidget.dataset.activeCallOwner==='fixture'`),'Switching chats retains active call owner node');
 await client.call('Emulation.setDeviceMetricsOverride',{width:812,height:260,deviceScaleFactor:1,mobile:true});await delay(200);
 check(await visibleContent(),'Very short landscape retains messages and composer');
 // Network errors, retry and empty data use the actual component shell.
 await client.call('Emulation.setDeviceMetricsOverride',{width:375,height:667,deviceScaleFactor:1,mobile:true});
 chatMode='error';await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(100);
 await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(250);
 check(await run(`!document.querySelector('.seller-inbox-retry').hidden`),'Failure exposes retry');
 chatMode='empty';await run(`document.querySelector('.seller-inbox-retry').click()`);await delay(250);
 check(await run(`!!document.querySelector('.seller-inbox-empty')&&document.querySelector('.seller-inbox-retry').hidden`),'Retry recovers to empty state');

 // Closing during a delayed open cannot reopen a conversation behind the owner.
 chatMode='slow';await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(150);
 await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(250);
 await run(`document.querySelector('.seller-inbox-person').click();document.querySelector('[aria-label="Close messages"]').click()`);await delay(650);
 check(await run(`document.querySelector('[data-mobile-inbox]').hidden&&!document.querySelector('[data-floating-chat-window]:not([hidden])')&&!document.documentElement.classList.contains('seller-messages-active')`),'Delayed open respects explicit close');
 // Resize keeps mounted widgets and desktop geometry.
 chatMode='ok';await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(250);
 await run(`window.openTransactionChat(17)`);await delay(200);await readyWidget();
 await run(`document.querySelector('[data-chat-input]').value='Resize draft'`);
 await client.call('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});await delay(200);
 check(await run(`(()=>{const b=document.querySelector('[data-floating-chat-window]:not([hidden])').getBoundingClientRect();return Math.abs(b.width-360)<1&&Math.abs(b.height-500)<1})()`),'Desktop keeps v5 dock dimensions');
 check(await run(`!document.documentElement.classList.contains('seller-messages-active')`),'Resize releases page scroll lock');
 await client.call('Emulation.setDeviceMetricsOverride',{width:768,height:1024,deviceScaleFactor:1,mobile:true});await delay(200);
 check(await visibleContent(),'Tablet resize retains composer/messages');
 check(await run(`document.querySelector('[data-chat-input]').value==='Resize draft'`),'Resize retains draft');
 check(client.errors.length===0,'No uncaught browser exceptions');
 console.log(JSON.stringify({passed:checks,coverage:'Synthetic actual PHP component/widget; mobile/tablet light/dark, navigation/search/filters/errors/resize; no live calls/messages'}));
} finally {await client.close(); server.closeAllConnections(); await new Promise(r=>server.close(r));}
