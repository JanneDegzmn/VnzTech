import {createServer} from 'node:http';
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';
import {browser,delay} from './browser_cdp.mjs';
const php='C:/xampp/php/php.exe';
const markup=execFileSync(php,['tests/admin_topbar_fixture.php'],{encoding:'utf8'});
const restricted=execFileSync(php,['tests/admin_topbar_fixture.php','none'],{encoding:'utf8'});
assert.ok(!restricted.includes('data-floating-messages-toggle'),'Restricted role gets no chat launcher');
const previewDir=process.env.VNZ_ADMIN_TOPBAR_PREVIEWS;
if(previewDir)await mkdir(previewDir,{recursive:true});
const queueMarkup=execFileSync(php,['tests/admin_queue_controls_fixture.php'],{encoding:'utf8'});
const widgets=new Map();
const widget=(id,cid)=>{const key=id+':'+cid;if(!widgets.has(key))widgets.set(key,execFileSync(php,['tests/admin_chat_widget_fixture.php',String(id),String(cid)],{encoding:'utf8'}));return widgets.get(key);};
let mode='ok';
const conversations=[{conversation_id:201,conversation_type:'private',display_name:'Fixture Seller',last_message:'Fixture private preview',unread_count:2,updated_at:'2026-11-20 10:00:00'},{conversation_id:202,conversation_type:'private',conversation_class:'unclaimed_guest_submission',guest_submission:true,display_name:'Fixture Guest',last_message:'Guest preview',unread_count:1},{conversation_id:203,conversation_type:'transaction',conversation_class:'transaction',transaction_id:17,display_name:'SELLER - Fixture',transaction_reference:'TEST-TX-17',unread_count:0},{conversation_id:204,conversation_type:'global',display_name:'Global Chat',unread_count:0}];
const server=createServer(async(req,res)=>{
 if(req.url.startsWith('/VNZ-TECH/assets/')){try{const p=req.url.split('?')[0].replace('/VNZ-TECH/','');res.setHeader('Content-Type',p.endsWith('.css')?'text/css':'text/javascript');res.setHeader('Cache-Control','no-store');res.end(await readFile(p));}catch{res.writeHead(404).end();}return;}
 if(req.url.includes('admin_topbar_updates')){res.setHeader('Content-Type','application/json');if(mode==='error'){res.writeHead(503).end('{}');return;}if(mode==='expired'){res.writeHead(401).end('{}');return;}res.end(JSON.stringify({success:true,items:mode==='empty'?[]:[{page:'submissions',label:'New submissions',count:5},{page:'transactions',label:'Transaction updates',count:2}]}));return;}
 if(req.url.includes('transaction-chat-window.php')){const url=new URL(req.url,'http://fixture');res.end(widget(Number(url.searchParams.get('transaction_id')||17),Number(url.searchParams.get('conversation_id')||0)));return;}
 if(req.url.includes('chat_')){res.setHeader('Content-Type','application/json');res.end(JSON.stringify({success:true,data:{conversations,contacts:[{actor_type:'seller',actor_id:100,conversation_id:201,display_name:'Fixture Seller'}],trade_requests:[]}}));return;}
 res.setHeader('Content-Type','text/html');res.end(`<!doctype html><html data-admin-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${['admin_dashboard','admin_submissions','admin_theme','transaction_chat','seller_topbar','admin_topbar'].map(n=>'<link rel="stylesheet" href="/VNZ-TECH/assets/css/'+n+'.css">').join('')}</head><body><div class="admin-layout"><aside class="sidebar" id="sidebar"><div class="sidebar-top"><div class="brand">Fixture Admin</div><button class="sidebar-close" id="sidebarClose">Close</button></div></aside><div class="main-area">${markup}<div id="contentArea" style="min-height:1600px">${queueMarkup}</div></div></div><section id="adminSubmissionModal" class="admin-submission-modal"><textarea>Review draft</textarea></section><script>window.pageBackCount=0;addEventListener('popstate',event=>{const messageRoot=document.querySelector('[data-floating-messages]');if(event.state?.vnzMessages||messageRoot?.dataset.mobileHistoryPending==='true'||(innerWidth<=1024&&messageRoot?.dataset.mobileMessagesView&&messageRoot.dataset.mobileMessagesView!=='closed'))return;window.pageBackCount++;});window.loadAdminPage=(...args)=>window.lastNavigation=args;document.querySelector('#menuButton').onclick=()=>document.querySelector('#sidebar').classList.add('mobile-open');document.querySelector('#sidebarClose').onclick=()=>document.querySelector('#sidebar').classList.remove('mobile-open');</script><script src="/VNZ-TECH/assets/js/floating-messages.js"></script><script src="/VNZ-TECH/assets/js/admin-topbar.js"></script></body></html>`);
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const client=await browser();let checks=1;
const run=s=>client.evaluate(s);const check=(v,m)=>{assert.ok(v,m);checks++;};
const prepare=async()=>{for(let i=0;i<30;i++){if(await run(`!!document.querySelector('[data-floating-chat-window]:not([hidden]) [data-chat-input]')`))break;await delay(100);}return run(`(()=>{const w=document.querySelector('[data-floating-chat-window]:not([hidden])');w.querySelector('[data-chat-input]').disabled=false;w.querySelector('[data-chat-input]').value='Admin draft';w.querySelector('[data-chat-messages]').innerHTML=Array.from({length:5},(_,i)=>'<article class="transaction-chat-bubble"><p class="transaction-chat-message-content">Reply '+(i+1)+'</p></article>').join('');})()`);};
try{
 for(const [scenario,status,length] of [['post',405,0],['guest',401,0],['inactive',401,0],['none',200,0],['view',200,3],['trade',200,1],['all',200,4]]){
  const raw=execFileSync(php,['tests/admin_topbar_updates_fixture.php',scenario],{encoding:'utf8'}).trim();
  const [body,actual]=raw.split('\nSTATUS:');check(Number(actual)===status,'Update endpoint status '+scenario);
  const result=JSON.parse(body);if(status===200)check(result.items.length===length,'Update endpoint permission-scoped items '+scenario);
 }
 for(const width of [320,375,768,1024,1440]) for(const theme of ['light','dark']){
  mode='ok';await client.call('Emulation.setDeviceMetricsOverride',{width,height:width<=375?812:900,deviceScaleFactor:1,mobile:width<=1024});
  await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(200);await run(`document.documentElement.dataset.adminTheme=${JSON.stringify(theme)}`);
  await delay(250);
  check(await run(`(()=>{const h=document.querySelector('[data-admin-topbar]').getBoundingClientRect(),b=document.querySelector('.sidebar-top').getBoundingClientRect();return Math.abs(h.height-85)<1&&Math.abs(b.height-85)<1&&Math.abs(h.bottom-b.bottom)<1})()`),'Admin header and branding share 85px aligned border');
  check(await run(`(()=>{const h=getComputedStyle(document.querySelector('[data-admin-topbar]')),b=getComputedStyle(document.querySelector('.sidebar-top'));return h.borderBottomColor===b.borderBottomColor})()`),'Admin border color continues across shell');
  check(await run(`(()=>{const h=getComputedStyle(document.querySelector('[data-admin-topbar]')),b=getComputedStyle(document.querySelector('[data-admin-updates-toggle]'));if(document.documentElement.dataset.adminTheme==='light')return h.backgroundColor==='rgb(255, 255, 255)';return [h.backgroundColor,b.backgroundColor].every(v=>{const n=v.match(/[0-9]+/g).map(Number);return n[0]===n[1]&&n[1]===n[2]})})()`),'Admin topbar follows neutral dark / existing light panel palette');
  check(await run(`document.querySelectorAll('[data-floating-messages-toggle]').length===1&&document.querySelector('[data-floating-messages]').dataset.placement==='admin-topbar'`),'Single admin topbar chat');
  check(await run(`document.documentElement.scrollWidth<=innerWidth`),'Admin header/queue controls have no horizontal overflow');
  check(await run(`document.querySelectorAll('[data-queue-filter]').length===5&&[...document.querySelectorAll('[data-queue-filter]')].every(n=>n.getBoundingClientRect().height>=44)`),'Queue controls visible with usable targets');
  check(await run(`document.querySelector('[data-admin-updates-badge]').textContent==='7'`),'Admin authorized update total');
  await run(`document.querySelector('[data-admin-updates-toggle]').click()`);await delay(100);
  check(await run(`!document.querySelector('#adminTopbarUpdates').hidden`),'Admin bell opens');
  await run(`document.querySelector('#adminSubmissionModal').classList.add('is-open');document.querySelector('[data-admin-updates-list] a').click()`);
  check(await run(`!window.lastNavigation&&document.querySelector('[data-admin-updates-state]').textContent.includes('current review')`),'Notifications protect open review draft');
  await run(`document.querySelector('#adminSubmissionModal').classList.remove('is-open');document.querySelector('[data-admin-updates-list] a').click()`);
  check(await run(`window.lastNavigation[0]==='submissions'`),'Notification explicit navigation');
  await run(`document.querySelector('[data-floating-messages-toggle]').click()`);await delay(200);
  if(width<=1024){
   check(await run(`!document.querySelector('[data-mobile-inbox]').hidden`),'Admin mobile/tablet direct inbox');
   check(await run(`(()=>{const b=document.querySelector('[data-mobile-inbox]').getBoundingClientRect();return b.left===0&&b.top===0&&Math.abs(b.width-innerWidth)<1&&Math.abs(b.height-visualViewport.height)<2})()`),'Admin inbox fills viewport');
   if(width===375){await mkdir('.qa-admin-ux',{recursive:true});const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(`.qa-admin-ux/inbox-${theme}-375.png`,Buffer.from(shot.data,'base64'));}
   check(await run(`!!document.querySelector('[data-inbox-filter="guest-sellers"]')&&document.querySelector('[data-inbox-filter="contacts"]').textContent==='Seller requests'`),'Actual admin-specific categories');
   await run(`document.querySelector('[data-inbox-filter="guest-sellers"]').click()`);
   check(await run(`document.querySelectorAll('.seller-inbox-row').length===1&&document.querySelector('.seller-inbox-row').textContent.includes('Fixture Guest')`),'Guest filter uses authorized guest record');
   await run(`document.querySelector('[data-inbox-filter="all"]').click();document.querySelector('.seller-inbox-row').click()`);await delay(200);await prepare();
  }else{await run(`window.openTransactionChat(17)`);await delay(200);await prepare();}
  const chatPalette=await run(`(()=>{const window=document.querySelector('[data-floating-chat-window]:not([hidden])'),chat=window.querySelector('.transaction-chat'),header=window.querySelector('.floating-chat-window-header'),info=window.querySelector('.transaction-chat-info'),composer=window.querySelector('.transaction-chat-composer'),workspace=window.querySelector('.floating-chat-workspace-sidebar'),bubble=window.querySelector('.transaction-chat-bubble');return {window:getComputedStyle(window).backgroundColor,header:getComputedStyle(header).backgroundColor,chat:getComputedStyle(chat).backgroundColor,info:getComputedStyle(info).backgroundColor,composer:getComputedStyle(composer).backgroundColor,workspace:getComputedStyle(workspace).backgroundColor,bubble:getComputedStyle(bubble).backgroundColor}})()`);
  const expectedChat=theme==='light'?{window:'rgb(255, 255, 255)',header:'rgb(247, 249, 252)',chat:'rgb(255, 255, 255)',info:'rgb(247, 249, 252)',composer:'rgb(247, 249, 252)',workspace:'rgb(247, 249, 252)',bubble:'rgb(241, 245, 249)'}:{window:'rgb(24, 24, 24)',header:'rgb(32, 32, 32)',chat:'rgb(17, 17, 17)',info:'rgb(27, 27, 27)',composer:'rgb(27, 27, 27)',workspace:'rgb(27, 27, 27)',bubble:'rgb(24, 35, 46)'};
  check(JSON.stringify(chatPalette)===JSON.stringify(expectedChat),'Admin chat panels follow active theme and preserve message bubble colors');
  if(previewDir&&(width===375||width===1440)){const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(resolve(previewDir,`admin-${theme}-${width}.png`),Buffer.from(shot.data,'base64'));}
  check(await run(`!!document.querySelector('.seller-chat-context')&&!document.querySelector('.seller-chat-context').open`),'Admin context collapsed');
  check(await run(`(()=>{const n=document.querySelector('[data-chat-messages]'),b=n.getBoundingClientRect();return [...n.children].every(n=>{const x=n.getBoundingClientRect();return x.top>=b.top&&x.bottom<=b.bottom})})()`),'Five short admin replies visible');
  await run(`document.querySelector('.seller-chat-context .transaction-chat-info').insertAdjacentHTML('beforeend','<p>'+('Synthetic info detail. '.repeat(40))+'</p>');document.querySelector('.seller-chat-context summary').click()`);await delay(100);
  check(await run(`(()=>{const n=document.querySelector('.seller-chat-context-body');return getComputedStyle(n).scrollbarWidth==='none'&&n.scrollHeight>n.clientHeight&&getComputedStyle(n.querySelector('.transaction-chat-info')).overflow==='visible'})()`),'Hidden context scrollbar, one scroll region');
  await run(`document.querySelector('.seller-chat-context-body').scrollTop=100`);
  check(await run(`document.querySelector('.seller-chat-context-body').scrollTop>0`),'Info stays scrollable without scrollbar');
  await run(`document.querySelector('.seller-chat-context summary').click();document.querySelector('[data-chat-scroll-latest]').hidden=false`);
  check(await run(`(()=>{const n=document.querySelector('[data-chat-scroll-latest]'),a=n.getBoundingClientRect(),b=n.parentElement.getBoundingClientRect();return Math.abs(a.left+a.right-b.left-b.right)<2})()`),'Admin latest arrow centered');
  if(width<=1024){
   await run(`history.back()`);await delay(200);
   check(await run(`!document.querySelector('[data-mobile-inbox]').hidden&&window.pageBackCount===0`),'Chat Back does not reload admin page');
   await run(`document.querySelector('[aria-label="Close messages"]').click()`);await delay(200);
   check(await run(`window.pageBackCount===0&&!document.documentElement.classList.contains('seller-messages-active')`),'Close preserves underlying review page');
  }else{
   await run(`window.openTransactionChat(18)`);await delay(150);
   check(await run(`document.querySelectorAll('[data-floating-chat-window]:not([hidden])').length===2`),'Admin desktop multi-chat');
  }
 }
 mode='error';await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(200);
 check(await run(`!document.querySelector('[data-admin-updates-retry]').hidden`),'Admin update error retry');
 mode='empty';await run(`document.querySelector('[data-admin-updates-retry]').click()`);await delay(200);
 check(await run(`document.querySelector('[data-admin-updates-badge]').hidden`),'Empty update badge hidden');
 check(client.errors.length===0,'No admin browser exceptions');
 console.log(JSON.stringify({passed:checks,coverage:'Synthetic actual admin header/widget; role gate, light/dark, phone/tablet/desktop, context/history/review protection'}));
}finally{await client.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
