import {createServer} from 'node:http';
import {mkdir,readFile,writeFile} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {join,resolve,sep} from 'node:path';
import assert from 'node:assert/strict';
import {browser,delay} from './browser_cdp.mjs';
const screenshotDir=process.env.VNZ_SITE_STATUS_SCREENSHOTS;
const root=resolve('.');
if(screenshotDir){assert.ok(resolve(screenshotDir).startsWith(root+sep+'.qa-site-status-'));await mkdir(screenshotDir,{recursive:true});}
const contrastRatio=(foreground,background)=>{
    const channels=value=>{const match=value.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);assert.ok(match,`Expected computed RGB color: ${value}`);return match.slice(1).map(Number).map(channel=>{const srgb=channel/255;return srgb<=0.04045?srgb/12.92:((srgb+0.055)/1.055)**2.4;});};
    const luminance=rgb=>{const [r,g,b]=channels(rgb);return 0.2126*r+0.7152*g+0.0722*b;};
    const values=[luminance(foreground),luminance(background)].sort((a,b)=>b-a);return (values[0]+0.05)/(values[1]+0.05);
};
const firstGradientRgb=value=>value.match(/rgb\(\d+,\s*\d+,\s*\d+\)/)?.[0];
const staticPages=Object.fromEntries(['sell_page','all_seller_services'].map(mode=>[mode,execFileSync('C:/xampp/php/php.exe',['tests/site_status_page_fixture.php',mode],{encoding:'utf8'})]));
const pressTab=async(shift=false)=>{const modifiers=shift?8:0;await client.call('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9,modifiers});await client.call('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9,modifiers});await delay(10);};
let active=false, mode='seller_portal', calls=0, writes=0;
const maintenance=()=>({version:1,active,service_mode:mode,reason:'Fixture maintenance <script>unsafe()</script>',duration_minutes:90,estimated_end_at:new Date(Date.now()+5400000).toISOString()});
const server=createServer(async(req,res)=>{
    const url=new URL(req.url,'http://fixture');
    if(url.pathname.includes('/assets/')){const path=url.pathname.replace('/VNZ-TECH/','');res.setHeader('Content-Type',path.endsWith('.css')?'text/css; charset=utf-8':'text/javascript; charset=utf-8');res.end(await readFile(path));return;}
    if(url.searchParams.get('page')==='maintenance'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(staticPages[url.searchParams.get('service')]||staticPages.sell_page);return;}
    if(url.pathname.endsWith('/site_status_updates.php')){calls++;res.setHeader('Content-Type','application/json');res.end(JSON.stringify({success:true,maintenance:maintenance(),announcements:{items:[{id:1,title:'Published <b>notice</b>',message:'Plain <img src=x onerror=unsafe()> notice'}],page:Number(url.searchParams.get('page')||1),pages:2,total:11}}));return;}
    if(url.pathname.endsWith('/mutation.php')){writes++;res.setHeader('Content-Type','application/json');res.end('{"success":true}');return;}
    const area=url.searchParams.get('area')||'seller',theme=url.searchParams.get('theme')||'light',scope=url.searchParams.get('scope')||(area==='seller'?'seller_portal':area);
    res.setHeader('Content-Type','text/html');res.end(`<!doctype html><html data-${area}-theme="${theme}" data-public-theme="dark"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/VNZ-TECH/assets/css/site-status.css"><script src="/VNZ-TECH/assets/js/site-status.js" defer data-site-status-area="${area}" data-site-status-scope="${scope}"></script></head><body><main><h1>Fixture account</h1><textarea id="draft">Unsent form text</textarea><button id="outside">Outside action</button></main></body></html>`);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
let client,checks=0;
try{
    client=await browser();
    for(const area of ['public','seller','admin'])for(const theme of ['light','dark'])for(const width of [375,768,1440]){
        active=false;mode='seller_portal';await client.call('Emulation.setDeviceMetricsOverride',{width,height:850,deviceScaleFactor:1,mobile:false});
        const before=calls;await client.navigate(`http://127.0.0.1:${server.address().port}/?area=${area}&theme=${theme}`);
        for(let i=0;i<30&&calls===before;i++)await delay(20);await delay(40);
        let result=await client.evaluate(`(()=>{const b=document.querySelector('.site-announcement-banner:not([hidden])');return {banner:b?.textContent,theme:document.documentElement.dataset.siteStatusTheme,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+1,bg:b&&getComputedStyle(b).backgroundColor,text:b&&getComputedStyle(b).color}})()`);
        assert.ok(result.banner.includes('Published <b>notice</b>'));assert.equal(result.theme,theme);assert.equal(result.overflow,false);assert.notEqual(result.bg,result.text);checks+=4;
        await client.evaluate(`document.querySelector('.site-announcement-banner:not([hidden]) button').click()`);await delay(60);
        assert.equal(await client.evaluate(`!!document.querySelector('dialog[open]')`),true);assert.equal(await client.evaluate(`document.querySelector('dialog img')===null`),true);checks+=2;
        if(area==='admin'){
            const palette=await client.evaluate(`(()=>{const dialog=document.querySelector('dialog[open]');const card=dialog.querySelector('.site-status-card');const heading=dialog.querySelector('h2');const message=card.querySelector('p');const button=dialog.querySelector('button');return {bg:getComputedStyle(dialog).backgroundColor,heading:getComputedStyle(heading).color,card:getComputedStyle(card).backgroundColor,message:getComputedStyle(message).color,button:getComputedStyle(button).backgroundColor,buttonText:getComputedStyle(button).color}})()`);
            assert.equal(palette.bg,'rgb(11, 11, 11)');assert.equal(palette.card,'rgb(11, 11, 11)');assert.equal(palette.heading,'rgb(242, 242, 242)');assert.equal(palette.button,'rgb(26, 26, 26)');assert.ok(contrastRatio(palette.message,palette.card)>=4.5);assert.ok(contrastRatio(palette.buttonText,palette.button)>=4.5);checks+=6;
        }
        await client.evaluate(`document.querySelector('dialog button').click();document.querySelector('#draft').focus()`);
        active=true;await client.evaluate('window.VNZSiteStatus.refresh(true)');await delay(30);
        if(area==='admin'){
           assert.equal(await client.evaluate('window.VNZSiteStatus.suspended'),false);assert.equal(await client.evaluate(`document.querySelector('main').inert`),false);assert.equal(await client.evaluate(`document.querySelector('.site-announcement-banner[aria-label="Maintenance status"]')`),null);assert.ok(await client.evaluate(`!!document.querySelector('.site-announcement-banner[aria-label="Site announcement"]:not([hidden])')`));checks+=4;
        }else if(area==='public'){
            assert.equal(await client.evaluate('window.VNZSiteStatus.suspended'),false);
            assert.equal(await client.evaluate(`document.querySelector('main').inert`),false);
            assert.equal(await client.evaluate(`!!document.querySelector('.site-runtime-maintenance')`),false);
            const oldWrites=writes;assert.equal(await client.evaluate(`fetch('/VNZ-TECH/backend/mutation.php',{method:'POST'}).then(r=>r.status)`),200);assert.equal(writes,oldWrites+1);
            checks+=5;
        }else{
            result=await client.evaluate(`(()=>{const o=document.querySelector('.site-runtime-maintenance');const card=o?.querySelector('.site-maintenance-card');const logo=o?.querySelector('.site-maintenance-brand img');const title=o?.querySelector('h1');const intro=o?.querySelector('.site-maintenance-intro');const details=o?.querySelector('.site-maintenance-details > div');const reason=details?.querySelector('p');const label=details?.querySelector('span');const status=o?.querySelector('.site-maintenance-status');const dot=status?.querySelector('span');const button=o?.querySelector('button');const rect=card?.getBoundingClientRect();return {visible:!!o&&!o.hidden,inert:document.querySelector('main').inert,reasonText:o?.textContent,focus:o?.contains(document.activeElement),draft:document.querySelector('#draft').value,logo:logo?.getAttribute('src'),brandLink:o?.querySelector('.site-maintenance-brand')?.getAttribute('href'),adminLink:[...o.querySelectorAll('a')].some(a=>/admin|login/i.test(a.href+' '+a.textContent)),title:title?.textContent,button:button?.textContent,cardBg:card&&getComputedStyle(card).backgroundColor,titleColor:title&&getComputedStyle(title).color,introColor:intro&&getComputedStyle(intro).color,detailBg:details&&getComputedStyle(details).backgroundColor,reasonColor:reason&&getComputedStyle(reason).color,labelColor:label&&getComputedStyle(label).color,statusBg:status&&getComputedStyle(status).backgroundColor,statusText:status&&getComputedStyle(status).color,dotColor:dot&&getComputedStyle(dot).backgroundColor,buttonColor:button&&getComputedStyle(button).color,buttonGradient:button&&getComputedStyle(button).backgroundImage,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+1,cardWidth:rect?.width}})()`);
            assert.equal(result.visible,true);assert.equal(result.inert,true);assert.equal(result.focus,true);assert.equal(result.draft,'Unsent form text');assert.ok(result.reasonText.includes('1h 30m'));assert.equal(result.logo,'/VNZ-TECH/assets/images/vnz.png');assert.equal(result.brandLink,'/VNZ-TECH/index.php');assert.equal(result.adminLink,false);assert.equal(result.title,'We’re making things better.');assert.equal(result.button,'Check status');assert.equal(result.overflow,false);assert.ok(result.cardWidth<=width);assert.ok(contrastRatio(result.titleColor,result.cardBg)>=4.5);assert.ok(contrastRatio(result.introColor,result.cardBg)>=4.5);assert.ok(contrastRatio(result.reasonColor,result.detailBg)>=4.5);assert.ok(contrastRatio(result.labelColor,result.detailBg)>=4.5);assert.ok(contrastRatio(result.statusText,result.statusBg)>=4.5);assert.ok(contrastRatio(result.dotColor,result.statusBg)>=3);assert.ok(contrastRatio(result.buttonColor,firstGradientRgb(result.buttonGradient))>=4.5);checks+=20;
            await pressTab();assert.equal(await client.evaluate(`document.activeElement.matches('.site-maintenance-brand')`),true);
            await pressTab();assert.equal(await client.evaluate(`document.activeElement.matches('.site-runtime-maintenance button')`),true);
            result=await client.evaluate(`(()=>{const button=document.querySelector('.site-runtime-maintenance button');const style=getComputedStyle(button);return {focusVisible:button.matches(':focus-visible'),outlineWidth:style.outlineWidth,outlineStyle:style.outlineStyle}})()`);
            assert.equal(result.focusVisible,true);assert.equal(result.outlineWidth,'3px');assert.equal(result.outlineStyle,'solid');
            await pressTab();assert.equal(await client.evaluate(`document.activeElement.matches('.site-runtime-maintenance a[href]')`),true);
            await pressTab(true);assert.equal(await client.evaluate(`document.activeElement.matches('.site-runtime-maintenance button')`),true);checks+=7;
            if(screenshotDir&&(width===375||width===1440)){await delay(250);const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(join(screenshotDir,`maintenance-seller-${theme}-${width}.png`),Buffer.from(shot.data,'base64'));}
            const oldWrites=writes;assert.equal(await client.evaluate(`fetch('/VNZ-TECH/backend/mutation.php',{method:'POST'}).then(r=>r.status)`),503);assert.equal(writes,oldWrites);checks+=2;
            active=false;await client.evaluate('window.VNZSiteStatus.refresh(true)');await delay(20);
            assert.equal(await client.evaluate(`document.querySelector('main').inert`),false);assert.equal(await client.evaluate(`document.querySelector('#draft').value`),'Unsent form text');checks+=2;
        }
    }
    await client.call('Emulation.setDeviceMetricsOverride',{width:375,height:850,deviceScaleFactor:1,mobile:false});
    for(const test of [
        {mode:'submissions',scope:'submissions',affected:true},
        {mode:'submissions',scope:'offers_trades',affected:false},
        {mode:'sell_page',scope:'seller_portal',affected:false},
        {mode:'all_seller_services',scope:'messaging',affected:true},
        {mode:'seller_portal',scope:'sell_page',affected:false},
    ]){
        active=true;mode=test.mode;const before=calls;
        await client.navigate(`http://127.0.0.1:${server.address().port}/?area=seller&theme=dark&scope=${test.scope}`);
        for(let i=0;i<30&&calls===before;i++)await delay(20);await delay(30);
        assert.equal(await client.evaluate('window.VNZSiteStatus.suspended'),test.affected,`${test.mode} ${test.scope} suspension`);
        assert.equal(await client.evaluate(`!!document.querySelector('.site-runtime-maintenance')`),test.affected,`${test.mode} ${test.scope} overlay`);
        const oldWrites=writes;assert.equal(await client.evaluate(`fetch('/VNZ-TECH/backend/mutation.php',{method:'POST'}).then(r=>r.status)`),test.affected?503:200);assert.equal(writes,oldWrites+(test.affected?0:1));checks+=4;
    }
    for(const serviceMode of ['sell_page','all_seller_services'])for(const theme of ['light','dark'])for(const width of [375,768,1440]){
        active=true;mode=serviceMode;await client.call('Emulation.setDeviceMetricsOverride',{width,height:850,deviceScaleFactor:1,mobile:false});
        const before=calls;await client.navigate(`http://127.0.0.1:${server.address().port}/?page=maintenance&service=${serviceMode}`);
        for(let i=0;i<30&&calls===before;i++)await delay(20);
        await client.evaluate(`document.documentElement.setAttribute('data-public-theme','${theme}')`);await delay(30);
        const result=await client.evaluate(`(()=>{const card=document.querySelector('.site-maintenance-card');const title=card?.querySelector('h1');const details=card?.querySelector('.site-maintenance-details > div');const button=card?.querySelector('button');const rect=card?.getBoundingClientRect();return {theme:document.documentElement.dataset.siteStatusTheme,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+1,width:rect?.width,cardBg:card&&getComputedStyle(card).backgroundColor,titleColor:title&&getComputedStyle(title).color,reasonBg:details&&getComputedStyle(details).backgroundColor,reasonColor:details&&getComputedStyle(details.querySelector('p')).color,buttons:card?.querySelectorAll('button').length,brand:card?.querySelector('.site-maintenance-brand')?.getAttribute('href'),area:document.querySelector('script[data-site-status-area]')?.dataset.siteStatusArea,scope:document.querySelector('script[data-site-status-area]')?.dataset.siteStatusScope,adminLink:[...card.querySelectorAll('a')].some(a=>/admin|login/i.test(a.href+' '+a.textContent)),buttonText:button?.textContent}})()`);
        assert.equal(result.theme,theme);assert.equal(result.overflow,false,`${mode} ${theme} ${width} no overflow`);assert.ok(result.width<=width);assert.equal(result.area,'public');assert.equal(result.scope,'sell_page');assert.equal(result.brand,'/VNZ-TECH/index.php');assert.equal(result.adminLink,false);assert.equal(result.buttons,1);assert.equal(result.buttonText,'Check status');assert.equal(await client.evaluate('window.VNZSiteStatus.suspended'),false);assert.equal(await client.evaluate(`!!document.querySelector('.site-runtime-maintenance')`),false);assert.ok(contrastRatio(result.titleColor,result.cardBg)>=4.5);assert.ok(contrastRatio(result.reasonColor,result.reasonBg)>=4.5);checks+=13;
        if(screenshotDir&&(width===375||width===1440)){const shot=await client.call('Page.captureScreenshot',{format:'png'});await writeFile(join(screenshotDir,`maintenance-static-${serviceMode}-${theme}-${width}.png`),Buffer.from(shot.data,'base64'));}
        await pressTab();assert.equal(await client.evaluate(`document.activeElement.matches('.site-maintenance-brand')`),true);await pressTab();assert.equal(await client.evaluate(`document.activeElement.matches('[data-maintenance-recheck]')`),true);checks+=2;
        const focus=await client.evaluate(`(()=>{const button=document.querySelector('[data-maintenance-recheck]');const style=getComputedStyle(button);return {visible:button.matches(':focus-visible'),outline:style.outlineWidth}})()`);assert.equal(focus.visible,true);assert.equal(focus.outline,'3px');checks+=2;
    }
    assert.equal(client.errors.length,0,client.errors.join('\n'));checks++;
    console.log(`PASS ${checks} site-status client assertions: public/seller/admin and generated sell-page maintenance views in both themes at 375/768/1440, scoped overlays, accessibility, public/Admin availability, announcement safety, and blocked seller fetch.`);
}finally{if(client)await client.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
