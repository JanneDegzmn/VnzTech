import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile, mkdtemp, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:http';
import { browser, delay } from './browser_cdp.mjs';
const php='C:/xampp/php/php.exe';
const fixture=(...args)=>execFileSync(php,['tests/seller_account_ux_fixture.php',...args],{encoding:'utf8'});
let checks=0;
function check(value,label){assert.ok(value,label);checks++;}
for(const [mode,status,writes,language] of [['update-omit',200,1,'fil'],['update-language',200,1,'en'],['update-invalid',422,0,'fil'],['update-csrf',419,0,'fil'],['update-auth',401,0,'fil'],['update-method',405,0,'fil']]){
 const result=JSON.parse(fixture(mode));
 check(result.status===status,mode+' status');check(result.writes===writes,mode+' bounded writes');check(result.language===language,mode+' stored language');
 if(mode==='update-omit')check(result.session_language==='fil','omitted language preserves session');
}
const sources={};for(const page of ['profile','settings','security'])sources[page]=fixture(page);
sources.enabled=fixture('security','enabled');
const styles=await Promise.all(['seller_dashboard','seller_theme','seller_topbar','seller_account'].map(name=>readFile(`assets/css/${name}.css`,'utf8')));
const server=createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');
 const page=url.searchParams.get('page')||'profile';
 res.setHeader('Content-Type','text/html;charset=utf-8');
 const content=page==='security-icon'
  ? '<nav class="sidebar-nav security-icon-preview" aria-label="Seller modules"><button type="button" class="nav-item active" data-page="security"><span class="nav-icon" aria-hidden="true">&#128274;</span><span>Security</span></button></nav>'
  : `<main class="dashboard-main fixture-main">${sources[page]||sources.profile}</main>`;
 res.end(`<!doctype html><html data-seller-theme="${url.searchParams.get('theme')||'light'}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles.join('\n')} body{margin:0}.fixture-main{width:94%;margin:auto}.security-icon-preview{width:14rem;max-width:94%;margin:1rem}</style></head><body><script>window.calls=[];window.fetch=async (url,options)=>{window.calls.push({url:String(url),data:Object.fromEntries(options.body.entries())});return {ok:false,status:422,headers:{get:()=> 'application/json'},json:async()=>({success:false,message:'Fixture response'}),text:async()=>JSON.stringify({success:false,message:'Fixture response'})};};</script>${content}</body></html>`);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const origin=`http://127.0.0.1:${server.address().port}`;
const previewRoot=await mkdtemp(join(tmpdir(),'vnz-seller-account-ux-previews-'));
const chrome=await browser();
try{
 for(const width of [375,499,500,542,768,1440])for(const theme of ['light','dark'])for(const page of ['profile','settings','security','enabled']){
  await chrome.call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
  await chrome.navigate(`${origin}/?page=${page}&theme=${theme}`);
  const state=await chrome.evaluate(`(()=>{const p=document.querySelector('.seller-profile-page');return {forms:[...p.querySelectorAll('form')].map(f=>f.id),overflow:document.documentElement.scrollWidth>innerWidth+1,csrf:[...p.querySelectorAll('form')].every(f=>f.querySelector('[name="csrf_token"]')),heading:p.querySelector('h1').textContent.trim(),color:getComputedStyle(p.querySelector('h1')).color,securityPage:p.classList.contains('seller-security-page')};})()`);
  check(!state.overflow,`${page} ${theme} ${width} no horizontal overflow`);
  check(state.csrf,`${page} forms include CSRF`);
  if(page==='profile'||page==='settings')check(!state.securityPage,`${page} remains outside Security styling`);
  if(page==='profile'){check(state.forms.includes('sellerEmailVerificationForm'),'Profile email verification');check(!state.forms.includes('sellerPasswordForm'),'Profile excludes password');}
  if(page==='settings'){
   check(state.forms.includes('sellerLanguageForm')&&state.forms.includes('sellerNotificationForm'),'Settings preference forms');check(!state.forms.some(id=>id.includes('2fa')||id.includes('Password')||id.includes('Verification')),'Settings excludes security/email identity');
   const settingsButtons=await chrome.evaluate(`(()=>[...document.querySelectorAll('.seller-profile-page .seller-preference-save-button')].map(b=>({width:b.getBoundingClientRect().width,parentWidth:b.parentElement.getBoundingClientRect().width,minHeight:getComputedStyle(b).minHeight})))()`);
   check(settingsButtons.length===1,'Settings retains its notification save action');
   if(width<500)check(settingsButtons[0].width>=settingsButtons[0].parentWidth-1,'Settings notification action remains full width on mobile');
   else check(Math.abs(settingsButtons[0].width-190)<1,'Settings notification action keeps its 190px desktop width');
   check(settingsButtons[0].minHeight==='46px','Settings notification action height remains unchanged');
  }
  if(page==='security'||page==='enabled'){
   check(state.heading==='Security','Security heading');check(state.securityPage,'Security-specific styling scope');check(state.forms.includes('sellerPasswordForm'),'Security password');check(state.forms.includes(page==='enabled'?'seller2faLoginRequirementForm':'seller2faSetupForm'),'Security authenticator state');
   const buttons=await chrome.evaluate(`(()=>[...document.querySelectorAll('.seller-security-page .profile-save-button')].map(b=>({paddingLeft:getComputedStyle(b).paddingLeft,paddingRight:getComputedStyle(b).paddingRight,fontSize:getComputedStyle(b).fontSize,minHeight:getComputedStyle(b).minHeight,preference:b.classList.contains('seller-preference-save-button'),width:b.getBoundingClientRect().width,parentWidth:b.parentElement.getBoundingClientRect().width,whiteSpace:getComputedStyle(b).whiteSpace,minWidth:getComputedStyle(b).minWidth,textFits:b.scrollWidth<=b.clientWidth})))()`);
   check(buttons.length>=2,`${page} has Security actions`);
   check(buttons.every(b=>b.paddingLeft==='10.4px'&&b.paddingRight==='10.4px'&&b.fontSize==='12px'),`${page} actions use compact padding and readable text`);
   check(buttons.every(b=>b.minHeight===(b.preference?'46px':'42px')),`${page} action heights remain unchanged`);
   if(page==='enabled'){
    const preference=buttons.find(b=>b.preference);
    check(Boolean(preference),`${page} login preference action exists`);
    if(width<500)check(preference.width>=preference.parentWidth-1,`${page} mobile preference action remains full width`);
    else check(preference.width<190&&preference.minWidth==='0px'&&preference.whiteSpace==='nowrap'&&preference.textFits,`${page} desktop preference action is content-fit`);
   }
  }
 }
 for(const [theme,width] of [['light',375],['dark',1440]]){
  await chrome.call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
  await chrome.navigate(`${origin}/?page=security-icon&theme=${theme}`);
  const iconState=await chrome.evaluate(`(()=>{const button=document.querySelector('.nav-item[data-page="security"]');const icon=button.querySelector('.nav-icon');const active=getComputedStyle(icon).filter;button.classList.remove('active');const inactive=getComputedStyle(icon).filter;return {active,inactive,overflow:document.documentElement.scrollWidth>innerWidth+1};})()`);
  check(iconState.active==='grayscale(1)'&&iconState.inactive==='grayscale(1)',`Security icon remains grayscale ${theme} ${width} active/inactive`);
  check(!iconState.overflow,`Security icon preview ${theme} ${width} no horizontal overflow`);
  const shot=await chrome.call('Page.captureScreenshot',{format:'png'});
  await writeFile(join(previewRoot,`security-icon-${theme}-${width}.png`),Buffer.from(shot.data,'base64'));
 }
 for(const [page,id,endpoint] of [['profile','sellerProfileForm','update_profile.php'],['profile','sellerEmailVerificationForm','request_email_verification.php'],['settings','sellerNotificationForm','update_seller_notification_preferences.php'],['security','sellerPasswordForm','update_password.php'],['security','seller2faSetupForm','seller_2fa.php'],['enabled','seller2faLoginRequirementForm','seller_2fa.php']]){
  await chrome.navigate(`${origin}/?page=${page}`);
  await delay(100);
  await chrome.evaluate(`(()=>{const f=document.getElementById(${JSON.stringify(id)});f.querySelectorAll('input[type="password"]').forEach(i=>i.value='FixturePass123!');f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));})()`);
  await delay(80);
  const calls=await chrome.evaluate('window.calls');
  if(!calls)throw new Error(id+' fixture disappeared: '+await chrome.evaluate('JSON.stringify({url:location.href,body:document.body.innerText.slice(0,180),scripts:[...document.scripts].map(x=>x.textContent.slice(0,120))})')+' '+JSON.stringify(chrome.errors));
  check(calls.length===1&&calls[0].url.endsWith(endpoint),id+' owns one AJAX endpoint');
  check(calls[0].data.csrf_token==='fixture-token',id+' sends CSRF');
  if(id==='sellerProfileForm')check(!('language' in calls[0].data),'Profile omits unrelated language');
  check(await chrome.evaluate(`document.querySelector('#${id} button[type="submit"]').disabled===false`),id+' restores button on error');
 }
 await chrome.navigate(`${origin}/?page=security`);
 await chrome.evaluate(`document.getElementById('new_password').value='newpass123';document.getElementById('confirm_password').value='different123';document.getElementById('sellerPasswordForm').dispatchEvent(new Event('submit',{cancelable:true}));`);
 check(await chrome.evaluate('window.calls.length===0'),'Password mismatch blocks request');
 check(await chrome.evaluate("document.getElementById('passwordMessage').textContent.includes('do not match')"),'Password mismatch announced');
 await chrome.navigate(`${origin}/?page=settings`);
 await chrome.evaluate("document.getElementById('sellerLanguage').dispatchEvent(new Event('change',{bubbles:true}));");
 await delay(80);check(await chrome.evaluate("window.calls.length===1&&window.calls[0].url.endsWith('update_seller_language.php')"),'Language change uses dedicated endpoint');
 for(const page of ['profile','settings','security']){
  await chrome.navigate(`${origin}/?page=${page}`);
  await chrome.evaluate(`(()=>{const main=document.querySelector('main');const markup=main.innerHTML;main.innerHTML=markup;main.querySelectorAll('script').forEach(old=>{const fresh=document.createElement('script');fresh.textContent=old.textContent;old.replaceWith(fresh);});})()`);
  const id=page==='profile'?'sellerProfileForm':page==='settings'?'sellerNotificationForm':'sellerPasswordForm';
  await chrome.evaluate(`(()=>{window.fetch=async(url,options)=>{window.calls.push({url:String(url)});return {ok:true,json:async()=>({success:true,message:'Saved fixture'})}};const f=document.getElementById(${JSON.stringify(id)});f.querySelectorAll('input[type="password"]').forEach(i=>i.value='FixturePass123!');f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));})()`);
  await delay(80);
  check(await chrome.evaluate('window.calls.length===1'),page+' reinitialized AJAX binds once');
  check(await chrome.evaluate("document.querySelector('.profile-message.show.success')?.textContent==='Saved fixture'"),page+' successful save visible');
 }
 for(const [page,theme,width] of [['security','light',375],['security','dark',1440],['enabled','light',1440],['enabled','dark',375],['profile','light',768],['settings','dark',375]]){
  await chrome.call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
  await chrome.navigate(`${origin}/?page=${page}&theme=${theme}`);await delay(80);
  const shot=await chrome.call('Page.captureScreenshot',{format:'png'});
  await writeFile(join(previewRoot,`${page}-${theme}-${width}.png`),Buffer.from(shot.data,'base64'));
 }
 check(chrome.errors.length===0,'No uncaught browser exceptions');
 console.log('Synthetic page previews: '+previewRoot);
 console.log(`Seller account UX: ${checks} assertions passed; synthetic actual-source pages/endpoints; no live database/mail/login changes.`);
}finally{await chrome.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
