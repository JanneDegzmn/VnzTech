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
 for(const [width,height] of [[1200,800],[375,812],[768,1024],[1024,768]]) for(const theme of ['light','dark']) {
  await client.call('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<=1024});
  await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(150);
  await run(`document.documentElement.dataset.sellerTheme=${JSON.stringify(theme)};window.openTransactionChat(17)`);await delay(200);await readyWidget();
  check(await run(`!!document.querySelector('.seller-chat-context')&&!document.querySelector('.seller-chat-context').open`),'Context collapsed initially');
  check(await run(`document.querySelector('.seller-chat-context summary').textContent==='View submission info'`),'Clear context button label');
  await run(`document.querySelector('[data-chat-input]').value='Context toggle draft';document.querySelector('[data-chat-messages]').innerHTML=Array.from({length:5},(_,i)=>'<article class="transaction-chat-bubble"><p class="transaction-chat-message-content">Synthetic message visible '+(i+1)+'</p></article>').join('');`);
  check(await run(`(()=>{const list=document.querySelector('[data-chat-messages]'),b=list.getBoundingClientRect();return [...list.children].every(n=>{const r=n.getBoundingClientRect();return r.top>=b.top&&r.bottom<=b.bottom})})()`),'Five short replies fit without scrolling on normal viewport');
  const collapsed=await run(`document.querySelector('.transaction-chat-message-area').getBoundingClientRect().height`);
  if(width===1200||width===375){await mkdir('.qa-seller-chat-space',{recursive:true});const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(`.qa-seller-chat-space/context-${theme}-${width}.png`,Buffer.from(shot.data,'base64'));}
  await run(`document.querySelector('.seller-chat-context summary').click()`);await delay(100);
  check(await run(`document.querySelector('.seller-chat-context').open&&document.querySelector('.seller-chat-context-body').getBoundingClientRect().height>30`),'Info opens on explicit click');
  check(await visibleContent(),'Messages and composer remain visible with info expanded');
  check(await run(`document.querySelector('[data-chat-input]').value==='Context toggle draft'`),'Context toggle preserves draft');
  await run(`document.querySelector('.seller-chat-context summary').focus()`);
  await client.call('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13,text:'\r',unmodifiedText:'\r'});
  await client.call('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await delay(100);
  check(await run(`!document.querySelector('.seller-chat-context').open`),'Native Enter toggles context closed');
  check(await run(`document.querySelector('.transaction-chat-message-area').getBoundingClientRect().height>=${collapsed}-1`),'Collapse restores message space');
  await run(`document.querySelector('[data-chat-scroll-latest]').hidden=false`);
  check(await run(`(()=>{const n=document.querySelector('[data-chat-scroll-latest]'),b=n.getBoundingClientRect(),a=n.parentElement.getBoundingClientRect(),i=n.querySelector('svg').getBoundingClientRect();return Math.abs((b.left+b.right-a.left-a.right)/2)<1&&Math.abs((i.left+i.right-b.left-b.right)/2)<1&&Math.abs((i.top+i.bottom-b.top-b.bottom)/2)<1})()`),'Arrow button and SVG centered');
  if(width===375){
   await run(`document.querySelector('[data-chat-close]').click()`);await delay(200);
   await run(`document.querySelector('[aria-label="Close messages"]').click()`);await delay(200);
   await run(`document.querySelector('#sellerMobileMenuButton').click()`);await delay(100);
   check(await run(`(()=>{const b=document.querySelector('[data-seller-sidebar-close]').getBoundingClientRect(),s=document.querySelector('.sidebar-brand').getBoundingClientRect();return b.left<s.left+20&&b.right<s.left+65})()`),'Burger is on left of sidebar branding');
   await run(`document.querySelector('[data-seller-sidebar-close]').click()`);await delay(100);
   check(await run(`!document.querySelector('#sellerDashboardSidebar').classList.contains('mobile-open')`),'Left burger still closes navigation');
  }
 }
 check(client.errors.length===0,'No browser exceptions');
 console.log(JSON.stringify({passed:checks,coverage:'Seller v2 alignment/native disclosure/five short replies, desktop phone tablet both themes; synthetic widget'}));
}finally{await client.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
