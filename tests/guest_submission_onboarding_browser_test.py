"""Offline Chrome integration check: real dialog/assets, mocked status and mail APIs."""

import json
from pathlib import Path
import shutil
import subprocess
import tempfile


repo = Path(__file__).resolve().parent.parent
chrome = shutil.which("chrome") or "C:/Program Files/Google/Chrome/Application/chrome.exe"
if not Path(chrome).is_file():
    raise SystemExit("Chrome is required for this local browser check.")

fragment = subprocess.run(
    ["C:/xampp/php/php.exe", "-r",
     "$guestSubmissionOnboarding=['phase'=>'verify','auto_show'=>true,"
     "'last_sent_at'=>0,'email'=>'seller@example.test'];"
     "function generateCsrfToken(){return 'fixture-csrf';}"
     "require 'includes/guest-submission-claim-prompt.php';"],
    cwd=repo, capture_output=True, text=True, encoding="utf-8", check=True,
).stdout

mock = r"""
let mailCalls = 0;
let serverPhase = 'verify';
let serverResult = null;
let serverFeedbackId = '';
let refreshEvents = 0;
window.addEventListener('vnz:guest-claim-updated', () => refreshEvents++);
window.fetch = async (url) => {
    if (String(url).includes('request_email_verification')) {
        mailCalls++;
        if (mailCalls === 1) return {ok:false, status:503, json:async()=>({success:false, message:'Test mail service unavailable'})};
        return {ok:true, status:200, json:async()=>({success:true})};
    }
    return {ok:true, status:200, json:async()=>({success:true, available:true, phase:serverPhase, guest_flow:true, verification_result:serverResult, feedback_id:serverFeedbackId, auto_show:true, last_sent_at:mailCalls>1?Math.floor(Date.now()/1000):0})};
};
window.addEventListener('error', e => {document.documentElement.dataset.scriptError = e.message;});
"""

checks = r"""
window.addEventListener('load', async () => {
    const wait = () => new Promise(resolve => setTimeout(resolve, 60));
    const check = (ok, message) => {if (!ok) throw new Error(message);};
    try {
        await wait();
        const dialog = document.querySelector('dialog');
        const primary = document.querySelector('[data-guest-claim-primary]');
        const resend = document.querySelector('[data-guest-claim-resend]');
        const feedback = document.querySelector('[data-guest-claim-feedback]');
        check(!dialog.open, 'Must wait for the existing 2FA reminder');
        document.querySelector('[data-seller-2fa-reminder]').remove();
        await wait();
        check(dialog.open, 'Must open automatically after the reminder closes');
        check(dialog.contains(document.activeElement), 'Focus must stay inside the dialog');
        document.querySelector('[data-guest-claim-later]').click();
        check(!dialog.open, 'Maybe later must close the dialog');
        document.querySelector('[data-guest-claim-open]').click();
        await wait();
        check(dialog.open, 'The banner must reopen the dialog');
        primary.click();
        await wait();
        check(mailCalls === 1 && feedback.classList.contains('is-error'), 'Mail errors must be visible');
        check(!resend.hidden && !resend.disabled, 'Failed mail delivery must allow retry');
        resend.click();
        await wait();
        check(mailCalls === 2 && resend.disabled, 'Successful delivery must start resend cooldown');
        document.querySelector('[data-guest-claim-later]').click();
        const blocker = document.createElement('div');
        blocker.setAttribute('data-seller-2fa-reminder', '');
        document.body.append(blocker);
        serverPhase = 'verified'; window.dispatchEvent(new Event('focus')); await wait();
        check(!dialog.open, 'Verification feedback must queue behind another reminder');
        blocker.remove(); await wait();
        check(dialog.open, 'Verification must reopen the popup even after the claim prompt was dismissed');
        check(document.querySelector('[data-guest-claim-title]').textContent === 'Email verified successfully', 'Verification must show a clear confirmation');
        check(!document.querySelector('[data-guest-claim-copy]').textContent.includes('is now linked'), 'Verification alone is not link success');
        serverPhase = 'linked'; window.dispatchEvent(new Event('focus')); await wait();
        check(document.querySelector('[data-guest-claim-copy]').textContent.includes('is now linked'), 'Actual ownership must show link success');
        check(refreshEvents === 2, 'Verified and newly linked status must refresh the portal data');
        check(primary.textContent === 'View my submissions', 'Success must offer the submissions page');
        document.querySelector('[data-guest-claim-later]').click();
        window.dispatchEvent(new Event('focus')); await wait();
        check(!dialog.open, 'A shown verification notice must not reopen on every focus event');
        serverPhase = 'verify'; serverResult = 'invalid'; serverFeedbackId = 'new-invalid-event';
        window.dispatchEvent(new Event('focus')); await wait();
        check(dialog.open && document.querySelector('[data-guest-claim-title]').textContent.includes('no longer valid'), 'Invalid links must display a visible explanation');
        serverPhase = 'linked'; serverResult = 'success'; serverFeedbackId = 'new-success-event';
        window.dispatchEvent(new Event('focus')); await wait();
        check(!document.documentElement.dataset.scriptError, 'No uncaught script errors');
        document.documentElement.dataset.guestClaimTest = 'PASS';
    } catch (error) {
        document.documentElement.dataset.guestClaimTest = 'FAIL';
        document.documentElement.dataset.testError = error.message;
    }
});
"""

out = Path(tempfile.mkdtemp(prefix="vnz-guest-claim-browser-"))
base_css = (repo / "assets/css/seller_dashboard.css").read_text(encoding="utf-8")
css = (repo / "assets/css/guest_submission_claim.css").read_text(encoding="utf-8")
script = (repo / "assets/js/guest-submission-claim.js").read_text(encoding="utf-8")
fixture = out / "fixture.html"
fixture.write_text(
    '<!doctype html><html data-seller-language="en"><head><meta charset="utf-8">'
    f'<style>{base_css}\n{css}</style><script>{mock}</script>'
    f'<script>{checks}</script></head><body style="padding:32px">'
    '<div data-seller-2fa-reminder></div><main id="contentArea">'
    '<section class="dashboard-page"><h1>Welcome back, Seller.</h1></section></main>'
    f'{fragment}<script>{script}</script></body></html>', encoding="utf-8",
)
result = subprocess.run(
    [chrome, "--headless=new", "--disable-gpu", "--disable-background-networking",
     "--no-first-run", "--no-default-browser-check", f"--user-data-dir={out / 'profile'}",
     "--window-size=1100,850", "--virtual-time-budget=4000",
     f"--screenshot={out / 'preview.png'}", "--dump-dom", fixture.as_uri()],
    capture_output=True, text=True, encoding="utf-8", timeout=30,
    creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
)
(out / "result.html").write_text(result.stdout, encoding="utf-8")
passed = 'data-guest-claim-test="PASS"' in result.stdout
print(json.dumps({"passed": passed, "browser_exit_code": result.returncode, "artifacts": str(out)}))
if not passed:
    print(result.stdout[:1500])
    print(result.stderr[-1000:])
raise SystemExit(not passed)
