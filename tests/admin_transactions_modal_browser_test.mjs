import {createServer} from 'node:http';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import {extname,resolve} from 'node:path';
import assert from 'node:assert/strict';
import {browser,delay} from './browser_cdp.mjs';

const root=resolve(import.meta.dirname,'..');
const previewDir=process.env.VNZ_ADMIN_TRANSACTION_MODAL_PREVIEWS;
if(previewDir)await mkdir(previewDir,{recursive:true});
const styles=['admin_dashboard','admin_theme','seller_topbar','admin_topbar','admin_transactions'];
const server=createServer(async(req,res)=>{
 try{
  const url=new URL(req.url,'http://fixture');
  if(url.pathname.startsWith('/VNZ-TECH/assets/')){
   const path=resolve(root,url.pathname.slice('/VNZ-TECH/'.length));
   res.setHeader('Content-Type',extname(path)==='.css'?'text/css':'text/javascript');
   res.setHeader('Cache-Control','no-store');
   res.end(await readFile(path));
   return;
  }
  const theme=url.searchParams.get('theme')==='light'?'light':'dark';
  res.setHeader('Content-Type','text/html');
  res.end(`<!doctype html>
   <html data-admin-theme="${theme}">
    <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width,initial-scale=1">
     ${styles.map(name=>`<link rel="stylesheet" href="/VNZ-TECH/assets/css/${name}.css">`).join('')}
    </head>
    <body>
     <div class="admin-layout">
      <aside class="sidebar"><div class="sidebar-top">VNZ TECH Admin</div></aside>
      <main class="main-area">
       <header class="topbar seller-topbar admin-topbar"><div class="topbar-left"><span class="page-title">Transactions</span></div></header>
       <section class="content-area" id="contentArea">
        <button type="button" data-open-transaction-modal>View</button>
        <div id="transactionDetailsModal" class="admin-transaction-modal" aria-hidden="true">
         <div class="admin-transaction-modal-backdrop" data-close-transaction-modal></div>
         <div class="admin-transaction-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
          <div class="admin-transaction-modal-header"><div><div class="admin-transaction-modal-kicker">VNZ TECH MANAGEMENT</div><h2 id="modalTitle">Transaction Details</h2></div><button type="button" class="admin-transaction-modal-close" data-close-transaction-modal aria-label="Close">×</button></div>
          <div class="admin-transaction-modal-body">Synthetic transaction details</div>
         </div>
        </div>
       </section>
      </main>
     </div>
     <script>
      const modal=document.getElementById('transactionDetailsModal');
      document.querySelector('[data-open-transaction-modal]').addEventListener('click',()=>{
       modal.classList.add('is-open');
       modal.setAttribute('aria-hidden','false');
      });
      document.querySelectorAll('[data-close-transaction-modal]').forEach(button=>button.addEventListener('click',()=>{
       modal.classList.remove('is-open');
       modal.setAttribute('aria-hidden','true');
      }));
     </script>
    </body>
   </html>`);
 }catch(error){
  res.statusCode=500;
  res.end(String(error));
 }
});

await new Promise(resolveListen=>server.listen(0,'127.0.0.1',resolveListen));
let client;
let checks=0;
try{
 client=await browser();
 for(const theme of ['light','dark']){
  for(const width of [375,768,1440]){
   await client.call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<=768});
   await client.navigate(`http://127.0.0.1:${server.address().port}/?theme=${theme}`);
   await delay(120);
   await client.evaluate("document.querySelector('[data-open-transaction-modal]').click()");
   await delay(300);

   const layout=await client.evaluate(`(()=>{
    const modal=document.querySelector('.admin-transaction-modal');
    const backdrop=modal.querySelector('.admin-transaction-modal-backdrop');
    const panel=modal.querySelector('.admin-transaction-modal-panel');
    const topbar=document.querySelector('.admin-topbar');
    const sidebar=document.querySelector('.sidebar');
    const rect=node=>{const r=node.getBoundingClientRect();return {left:r.left,top:r.top,width:r.width,height:r.height};};
    const point=(x,y)=>document.elementFromPoint(x,y);
    return {
     open:modal.classList.contains('is-open'),
     ariaHidden:modal.getAttribute('aria-hidden'),
     modalLayer:getComputedStyle(modal).zIndex,
     topbarLayer:getComputedStyle(topbar).zIndex,
     sidebarLayer:getComputedStyle(sidebar).zIndex,
     modalRect:rect(modal),
     backdropRect:rect(backdrop),
     backdropOpacity:getComputedStyle(backdrop).opacity,
     panelBackground:getComputedStyle(panel).backgroundColor,
     headerPoint:point(innerWidth-2,2)?.closest('.admin-transaction-modal-backdrop')===backdrop,
     sidebarPoint:innerWidth>1024?point(2,innerHeight/2)?.closest('.admin-transaction-modal-backdrop')===backdrop:null,
     panelOnTop:point(innerWidth/2,innerHeight/2)?.closest('.admin-transaction-modal-panel')===panel,
     overflow:document.documentElement.scrollWidth>innerWidth+1
    };
   })()`);

   assert.equal(layout.open,true,`${theme} ${width}px opened`);
   assert.equal(layout.ariaHidden,'false');
   assert.equal(Number(layout.modalLayer),11035);
   assert.ok(Number(layout.modalLayer)>Number(layout.topbarLayer));
   assert.ok(Number(layout.modalLayer)>Number(layout.sidebarLayer));
   assert.deepEqual(layout.modalRect,{left:0,top:0,width:width,height:900});
   assert.deepEqual(layout.backdropRect,{left:0,top:0,width:width,height:900});
   assert.equal(layout.backdropOpacity,'1');
   assert.equal(layout.panelBackground,theme==='light'?'rgb(255, 255, 255)':'rgb(16, 16, 16)');
   assert.equal(layout.headerPoint,true,`${theme} ${width}px backdrop covers topbar`);
   if(width>1024)assert.equal(layout.sidebarPoint,true,'Backdrop covers desktop sidebar');
   assert.equal(layout.panelOnTop,true);
   assert.equal(layout.overflow,false);
   checks+=12+(width>1024?1:0);

   if(previewDir&&(width===375||width===1440)){
    const screenshot=await client.call('Page.captureScreenshot',{format:'png'});
    await writeFile(resolve(previewDir,`transaction-modal-${theme}-${width}.png`),Buffer.from(screenshot.data,'base64'));
   }

   await client.evaluate("document.querySelector('.admin-transaction-modal-close').click()");
   assert.equal(await client.evaluate("document.querySelector('.admin-transaction-modal').getAttribute('aria-hidden')"),'true');
   checks++;
  }
 }
 assert.equal(client.errors.length,0,client.errors.join('\n'));
 checks++;
 console.log(`PASS ${checks} Admin transaction modal viewport, shell layering, theme and close assertions across 375/768/1440px.`);
}finally{
 if(client)await client.close();
 server.closeAllConnections();
 await new Promise(resolveClose=>server.close(resolveClose));
}
