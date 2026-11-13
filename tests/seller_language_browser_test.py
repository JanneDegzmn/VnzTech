"""Offline seller-language regression: real PHP markup/assets, fake account data.

Requires PHP and Chrome. The fixtures never include database.php, submit forms,
or contact the application. Browser artifacts are written to a fresh temp dir.
"""

import html
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile


REPO = Path(__file__).resolve().parent.parent
PHP = shutil.which("php") or "C:/xampp/php/php.exe"
CHROME = (
    os.environ.get("CHROME_BINARY")
    or shutil.which("chrome")
    or "C:/Program Files/Google/Chrome/Application/chrome.exe"
)


def source(relative):
    return (REPO / relative).read_text(encoding="utf-8")


def render_fragment(markup, language, helpers):
    """Evaluate template markup only; omit every production bootstrap/query."""
    setup = (
        "$user=['full_name'=>'Current Password','email'=>'seller@example.test',"
        "'mobile'=>'09170000000','address'=>'Settings','created_at'=>'2026-01-01',"
        "'language'=>'" + language + "','profile_picture'=>'',"
        "'totp_enabled_at'=>null,'totp_login_required'=>0,"
        "'submission_updates'=>1,'offer_updates'=>0,'transaction_updates'=>1];"
        "$emailVerified=false;$csrfToken='fixture-csrf';$verificationNotice='';"
        "$fullName='Current Password';$profilePicture='';$initial='C';"
    )
    completed = subprocess.run(
        [PHP, "-r", setup + helpers + "?>" + markup],
        capture_output=True, text=True, encoding="utf-8", check=True,
    )
    return re.sub(r"<script\b[^>]*>[\s\S]*?</script>", "", completed.stdout, flags=re.I)


def representative_markup(language):
    settings = source("account/pages/settings.php")
    settings = settings[settings.index('<section class="seller-profile-page">'):]
    escape = (
        "function settingsEscape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}"
        "function profileEscape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}"
        "function dashboardEscape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}"
        "function dashboardDate($v){return 'Jan 01, 2026';}"
    )
    profile = source("account/pages/profile.php")
    profile = profile[profile.index('<section class="seller-profile-page">'):]
    dashboard = re.search(
        r'<section class="dashboard-section dashboard-account-section">[\s\S]*?</section>',
        source("account/pages/dashboard.php"),
    ).group(0)
    return (
        '<main id="contentArea"><div id="real-settings">'
        + render_fragment(settings, language, escape)
        + '</div><div id="real-profile">'
        + render_fragment(profile, language, escape)
        + '</div><div id="real-account">'
        + render_fragment(dashboard, language, escape)
        + "</div></main>"
    )


EXTRA_MARKUP = """
<section id="mixed-fixture">
  <button id="mixed-label"><svg data-test-icon aria-hidden="true" width="10" height="10"></svg> SAVE CHANGES <span data-test-count>7</span></button>
  <p id="mixed-phrase">Manage your account <strong data-inline-part>security and notification preferences.</strong></p>
  <input id="fixture-input" value="Current Password" placeholder="Enter your complete address" aria-label="Search offers" title="Close transaction details">
  <textarea id="fixture-textarea">Current Password</textarea>
  <div id="fixture-editable" contenteditable="true">Current Password</div>
  <div id="fixture-explicit" data-user-content aria-label="Search offers">Current Password</div>
  <div id="fixture-no-translate" translate="no" title="Close transaction details">Current Password</div>
  <div class="transaction-chat-message-content" id="fixture-chat">SAVE CHANGES</div>
  <div class="seller-offer-message"><p id="fixture-feedback">Current Password</p></div>
  <strong class="seller-offer-reference" id="fixture-reference">#SP-TEST-001</strong>
  <div class="seller-offer-card"><h3 id="fixture-item">Current Password</h3></div>
  <pre id="fixture-pre">Current Password</pre><code id="fixture-code">Current Password</code>
  <div id="async-fragment"></div>
</section>
"""


INSTRUMENT = r"""
window.__languageErrors = [];
window.addEventListener('error',event=>window.__languageErrors.push(event.message));
window.__languageObserverCalls=0;
const NativeMutationObserver=window.MutationObserver;
window.MutationObserver=class extends NativeMutationObserver {
 constructor(callback){super((records,observer)=>{window.__languageObserverCalls++;callback(records,observer)})}
};
window.__originalFormValues=Array.from(document.querySelectorAll('input,textarea,select')).map(element=>[element,element.value]);
window.__mixedIcon=document.querySelector('[data-test-icon]');
window.__mixedCount=document.querySelector('[data-test-count]');
window.__mixedStrong=document.querySelector('[data-inline-part]');
"""


CHECKS = r"""
window.addEventListener('load',async()=>{
 const normalize=value=>value.replace(/\s+/g,' ').trim();
 const check=(value,label)=>{if(!value)throw Error(label)};
 const wait=()=>new Promise(resolve=>setTimeout(()=>{document.getAnimations().forEach(animation=>animation.finish());resolve()},100));
 const languageCheck=(actual,english,label)=>check(LANGUAGE==='fil'?normalize(actual)!==normalize(english):normalize(actual)===normalize(english),label+' ('+normalize(actual)+')');
 try {
  await wait();
  const settings=document.querySelector('#real-settings');
  languageCheck(settings.querySelector('.seller-profile-header p').textContent,'Manage your account security and notification preferences.','settings introduction');
  languageCheck(settings.querySelector('label[for="sellerLanguage"]').textContent,'Preferred Language','language label');
  languageCheck(settings.querySelector('#sellerLanguageSubmit').textContent,'SAVE LANGUAGE','save language action');
  languageCheck(settings.querySelector('#sellerEmailVerificationSubmit').textContent,'SEND VERIFICATION EMAIL','verification action');
  languageCheck(document.querySelector('#real-profile label[for="current_password"]').textContent,'Current Password','password label');
  const input=document.querySelector('#fixture-input');
  languageCheck(input.placeholder,'Enter your complete address','placeholder');
  languageCheck(input.getAttribute('aria-label'),'Search offers','accessible label');
  languageCheck(input.title,'Close transaction details','title');
  const mixed=document.querySelector('#mixed-label');
  languageCheck(mixed.textContent.replace('7',''),'SAVE CHANGES','mixed inline button');
  check(mixed.querySelector('svg')===window.__mixedIcon&&mixed.querySelector('[data-test-count]')===window.__mixedCount,'mixed descendants preserved');
  check(window.__mixedCount.textContent==='7','nested count preserved');
  languageCheck(document.querySelector('#mixed-phrase').textContent,'Manage your account security and notification preferences.','phrase across inline markup');
  check(document.querySelector('[data-inline-part]')===window.__mixedStrong,'inline strong preserved');
  check(normalize(document.querySelector('#real-profile .seller-profile-card h2').textContent)==='Current Password','profile name preserved');
  check(normalize(document.querySelector('#real-account .account-info-card strong').textContent)==='Current Password','dashboard name preserved');
  check(normalize(document.querySelector('.settings-email-address').textContent)==='seller@example.test','email preserved');
  for(const id of ['fixture-textarea','fixture-editable','fixture-explicit','fixture-no-translate','fixture-feedback','fixture-item','fixture-pre','fixture-code']){
   check(document.getElementById(id).textContent==='Current Password','protected text '+id);
  }
  check(document.querySelector('#fixture-explicit').getAttribute('aria-label')==='Search offers','protected accessible text');
  check(document.querySelector('#fixture-no-translate').title==='Close transaction details','translate=no attribute');
  check(document.querySelector('#fixture-chat').textContent==='SAVE CHANGES','chat content preserved');
  check(document.querySelector('#fixture-reference').textContent==='#SP-TEST-001','reference preserved');
  for(const [element,value] of window.__originalFormValues)check(element.value===value,'form value unchanged '+element.name);
  const inserted=document.querySelector('#async-fragment');
  inserted.innerHTML='<h2>Personal Information</h2><button id="dynamic-chip">Needs your response (4)</button><p id="dynamic-count">3 offers in this negotiation</p><input id="dynamic-input" placeholder="Enter your complete address" value="Current Password"><span data-user-content id="dynamic-name">Current Password</span>';
  await wait();
  languageCheck(inserted.querySelector('h2').textContent,'Personal Information','async section heading');
  languageCheck(document.querySelector('#dynamic-chip').textContent,'Needs your response (4)','dynamic filter');
  check(document.querySelector('#dynamic-chip').textContent.includes('(4)'),'filter count preserved');
  languageCheck(document.querySelector('#dynamic-count').textContent,'3 offers in this negotiation','dynamic count');
  check(document.querySelector('#dynamic-count').textContent.includes('3'),'offer total preserved');
  languageCheck(document.querySelector('#dynamic-input').placeholder,'Enter your complete address','async placeholder');
  check(document.querySelector('#dynamic-input').value==='Current Password'&&document.querySelector('#dynamic-name').textContent==='Current Password','async values preserved');
  document.querySelector('#dynamic-chip').textContent='Needs your response (12)';
  input.setAttribute('aria-label','Filter offers');
  await wait();
  languageCheck(document.querySelector('#dynamic-chip').textContent,'Needs your response (12)','updated count translated');
  check(document.querySelector('#dynamic-chip').textContent.includes('(12)'),'updated count preserved');
  languageCheck(input.getAttribute('aria-label'),'Filter offers','updated accessible label');
  const dialogResult=window.VNZDialog.alert({title:'Request Approved',message:'Your request has been approved successfully.\nSubmission: #SP-TEST-001\nTransaction: #TX-TEST-001\nApproved amount: ₱598.95',confirmLabel:'Chat with Admin'});
  await wait();
  const dialog=document.querySelector('.vnz-dialog');
  languageCheck(dialog.querySelector('h2').textContent,'Request Approved','dynamic dialog title');
  languageCheck(dialog.querySelector('button').textContent,'Chat with Admin','dynamic dialog action');
  const message=dialog.querySelector('p').textContent;
  if(LANGUAGE==='fil')check(!message.includes('Your request has been approved successfully.'),'dynamic dialog explanation');
  else check(message.includes('Your request has been approved successfully.'),'English dialog explanation');
  check(message.includes('#SP-TEST-001')&&message.includes('#TX-TEST-001')&&message.includes('₱598.95'),'dialog references and amount preserved');
  dialog.querySelector('button').click();await dialogResult;await wait();
  check(window.VNZSellerLanguage.translate('seller@example.test #SP-TEST-001')==='seller@example.test #SP-TEST-001','unknown content remains literal');
  const settled=window.__languageObserverCalls;await wait();await wait();
  check(window.__languageObserverCalls===settled,'mutation observer becomes idle');
  check(window.__languageObserverCalls<100,'bounded observer callbacks');
  check(window.__languageErrors.length===0,'no script errors');
  parent.postMessage({ok:true,language:LANGUAGE,width:innerWidth,observer_callbacks:window.__languageObserverCalls},'*');
 }catch(error){parent.postMessage({ok:false,language:LANGUAGE,width:innerWidth,error:error.message},'*')}
});
"""


def main():
    if not Path(CHROME).is_file():
        raise SystemExit("Chrome is required; set CHROME_BINARY to its executable.")
    catalog = source("assets/js/seller-language-copy.js")
    engine = source("assets/js/seller-language.js")
    dialog_js = source("assets/js/vnz-dialog.js")
    styles = "\n".join(source(path) for path in [
        "assets/css/seller_dashboard.css", "assets/css/seller_theme.css",
        "assets/css/vnz_dialog.css",
    ])
    payload = {}
    for language in ["fil", "en"]:
        payload[language] = (
            '<!doctype html><html data-seller-language="' + language
            + '" data-seller-theme="light"><head><meta charset="utf-8"><style>'
            + styles + '</style></head><body>' + representative_markup(language)
            + EXTRA_MARKUP + '<script>const LANGUAGE=' + json.dumps(language)
            + ';' + INSTRUMENT + '</script><script>' + dialog_js
            + '</script><script>' + catalog + '</script><script>' + engine
            + '</script><script>' + CHECKS + '</script></body></html>'
        )
    harness = r"""<!doctype html><html><meta charset="utf-8"><style>body{margin:0;font:14px Arial;background:#f4f7fa}iframe{display:block;border:0}pre{margin:16px}</style><body><pre id="report">Running</pre><script>
const payload=PAYLOAD,results=[];
(async()=>{
 for(const [language,markup] of Object.entries(payload)){
  for(const width of [1200,375]){
   const frame=document.createElement('iframe');frame.width=width;frame.height=700;
   const result=new Promise(resolve=>window.addEventListener('message',function listener(event){if(event.source===frame.contentWindow){window.removeEventListener('message',listener);resolve(event.data)}}));
   document.body.append(frame);frame.srcdoc=markup;results.push(await result);frame.remove();
  }
 }
 document.documentElement.dataset.result=results.every(result=>result.ok)?'PASS':'FAIL';
 document.querySelector('#report').textContent=JSON.stringify(results,null,2);
 const preview=document.createElement('iframe');preview.width=1200;preview.height=700;
 preview.srcdoc=payload.fil.replace(/<script>window.addEventListener\('load',[\s\S]+?<\/script>/,'');
 document.querySelector('#report').before(preview);
})();
</script></body></html>""".replace("PAYLOAD", json.dumps(payload).replace("</", "<\\/"))
    run = Path(tempfile.mkdtemp(prefix="vnz-seller-language-"))
    (run / "fixture.html").write_text(harness, encoding="utf-8")
    completed = subprocess.run([
        CHROME, "--headless=new", "--disable-gpu", "--disable-background-networking",
        "--no-first-run", "--no-default-browser-check",
        f"--user-data-dir={run / 'profile'}", "--virtual-time-budget=15000",
        "--window-size=1280,900", f"--screenshot={run / 'preview.png'}", "--dump-dom",
        (run / "fixture.html").as_uri(),
    ], capture_output=True, text=True, encoding="utf-8", errors="replace", timeout=90)
    (run / "result.html").write_text(completed.stdout, encoding="utf-8")
    report = re.search(r'<pre id="report">(.*?)</pre>', completed.stdout, re.S)
    result = {
        "passed": completed.returncode == 0 and 'data-result="PASS"' in completed.stdout,
        "browser_exit_code": completed.returncode,
        "results": html.unescape(report.group(1)) if report else "missing",
        "artifacts": str(run),
    }
    print(json.dumps(result, ensure_ascii=False))
    raise SystemExit(0 if result["passed"] else 1)


if __name__ == "__main__":
    main()
