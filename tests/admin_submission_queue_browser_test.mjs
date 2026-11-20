import {createServer} from 'node:http';
import {readFile} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {browser,delay} from './browser_cdp.mjs';
let checks=0;
const check=(condition,message)=>{assert.ok(condition,message);checks++;};
for(const [scenario,status,count] of [['post',405],['guest',401],['denied',403],['inactive',403],['invalid',400],['invalidtotal',400],['missingtotal',400],['ok',200,2],['late',200,1]]) {
 const result=execFileSync('C:/xampp/php/php.exe',['tests/admin_submission_queue_endpoint_fixture.php',scenario],{encoding:'utf8'});
 check(result.includes('STATUS:'+status),'Endpoint '+scenario+' status');
 const body=JSON.parse(result.split('\nSTATUS:')[0]);
 check(body.success===(status===200),'Endpoint '+scenario+' success flag');
 if(count!==undefined)check(body.new_count===count,'Authorized reader returns only arrival count');
 if(scenario==='late')check(body.newer_count===0&&body.current_total===4,'Late lower-ID arrival detected by total growth');
}
const pageSource=await readFile('admin/pages/submissions.php','utf8');
const inline=pageSource.slice(pageSource.lastIndexOf('<script>')+8,pageSource.lastIndexOf('</script>'));
// Last closing tag belongs to the external queue script; retain only the inline body.
const inlineBody=inline.slice(0,inline.indexOf('</script>'));
let pollCount=7,pollNewer=null,fail=false,lastPollUrl='';
const server=createServer(async(req,res)=>{
 if(req.url.startsWith('/VNZ-TECH/backend/')){lastPollUrl=req.url;res.setHeader('Content-Type','application/json');res.end(JSON.stringify(fail?{success:false}:{success:true,newer_count:pollNewer??pollCount,current_total:10+pollCount,new_count:pollCount}));return;}
 if(req.url.startsWith('/VNZ-TECH/assets/')){res.setHeader('Content-Type','text/javascript');res.end(await readFile('assets/js/admin-submission-queue.js'));return;}
 res.setHeader('Content-Type','text/html');res.end(`<!doctype html><html><body>
 <div id="adminSubmissionModal"><textarea id="draft">Synthetic review draft</textarea></div>
 <div data-admin-submission-queue data-after="10" data-opening-total="10" data-queue="review" data-list-page="3">
 <button data-queue-filter="new">New</button><button data-queue-filter="review">Review</button>
 <div data-queue-update-banner hidden><span data-queue-update-message role="status"></span><button data-queue-refresh>Refresh</button></div></div>
 <input id="submissionSearch" value="fixture"><select id="submissionStatusFilter"><option value="">All</option><option selected value="under_review">Under review</option><option value="accepted">Accepted</option></select>
 <select id="submissionItemTypeFilter"><option selected value="Whole Phone">Phones</option></select>
 <select id="submissionAssignmentFilter"><option value="all">All</option><option selected value="mine">Mine</option></select>
 <select id="submissionSortFilter"><option value="newest">Newest</option><option selected value="oldest">Oldest</option></select>
 <button id="clearSubmissionFilters">Clear</button>
 <script>window.calls=[];window.loadAdminPage=(...args)=>calls.push(args);window.timerCount=0;window.timerCancels=0;
 const realSet=window.setInterval,realClear=window.clearInterval;window.setInterval=(...args)=>{timerCount++;return realSet(...args)};window.clearInterval=(id)=>{timerCancels++;realClear(id)};</script>
 <script>${inlineBody}</script><script src="/VNZ-TECH/assets/js/admin-submission-queue.js"></script></body></html>`);
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const client=await browser();
const run=source=>client.evaluate(source);
try{
 await client.navigate(`http://127.0.0.1:${server.address().port}/`);await delay(180);
 check(await run(`!document.querySelector('[data-queue-update-banner]').hidden`),'Arrival banner displayed');
 check(await run(`document.querySelector('[data-queue-update-message]').textContent.includes('7 new submissions')`),'Accurate count');
 check(await run(`calls.length===0`),'Polling never navigates');
 check(new URL(lastPollUrl,'http://fixture').searchParams.get('opening_total')==='10','Opening total sent to authorized reader');
 pollCount=9;pollNewer=0;
 await run(`document.dispatchEvent(new Event('visibilitychange'))`);await delay(100);
 check(await run(`document.querySelector('[data-queue-update-message]').textContent.includes('9 new submissions')`),'Banner catches late lower-ID arrival through total growth');
 await run(`document.querySelector('#adminSubmissionModal').classList.add('is-open');document.querySelector('[data-queue-refresh]').click()`);
 check(await run(`calls.length===0&&document.querySelector('#draft').value==='Synthetic review draft'`),'Refresh blocked with review open and draft preserved');
 await run(`document.querySelector('#adminSubmissionModal').classList.remove('is-open');document.querySelector('[data-queue-refresh]').click()`);
 const refresh=await run(`calls.at(-1)`);
 check(refresh[0]==='submissions'&&refresh[1].list_page===3,'Refresh preserves current page');
 for(const [key,value]of Object.entries({search:'fixture',status:'under_review',item_type:'Whole Phone',queue:'review',assignment:'mine',sort:'oldest'}))check(refresh[1][key]===value,'Refresh preserves '+key);
 await run(`document.querySelector('[data-queue-filter="new"]').click()`);
 check(await run(`calls.at(-1)[1].queue==='new'&&calls.at(-1)[1].status===''&&calls.at(-1)[1].list_page===1`),'Group tab resets exact status and page');
 await run(`document.querySelector('#submissionStatusFilter').value='accepted';document.querySelector('#submissionStatusFilter').dispatchEvent(new Event('change'))`);await delay(300);
 check(await run(`calls.at(-1)[1].queue===''&&calls.at(-1)[1].status==='accepted'&&calls.at(-1)[1].assignment==='mine'&&calls.at(-1)[1].sort==='oldest'`),'Exact status clears group but preserves assignment/order');
 await run(`document.querySelector('#submissionSearch').value='edited';document.querySelector('#submissionSearch').dispatchEvent(new Event('input'))`);await delay(300);
 check(await run(`calls.at(-1)[1].search==='edited'&&calls.at(-1)[1].assignment==='mine'`),'Search debounce preserves queue filters');
 await run(`document.querySelector('#submissionAssignmentFilter').value='all';document.querySelector('#submissionAssignmentFilter').dispatchEvent(new Event('change'))`);
 check(await run(`calls.at(-1)[1].assignment==='all'&&calls.at(-1)[1].list_page===1`),'Assignment change resets pagination');
 await run(`document.querySelector('#clearSubmissionFilters').click()`);await delay(300);
 check(await run(`calls.at(-1)[1].search===''&&calls.at(-1)[1].queue===''&&calls.at(-1)[1].assignment==='all'&&calls.at(-1)[1].sort==='newest'`),'Clear resets all queue filters');
 await run(`const script=document.createElement('script');script.src='/VNZ-TECH/assets/js/admin-submission-queue.js?again';document.body.append(script)`);await delay(150);
 check(await run(`timerCount===2&&timerCancels===1`),'Repeated script cleans previous polling timer');
 check(client.errors.length===0,'No browser uncaught errors');
 console.log('PASS '+checks+' queue endpoint/controller assertions (synthetic fixture, no real sessions or database)');
}finally{await client.close();await new Promise(r=>server.close(r));}
