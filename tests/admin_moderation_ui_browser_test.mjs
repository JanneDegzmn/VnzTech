import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const screenshotDir = process.env.VNZ_MODERATION_UI_SCREENSHOTS;
if (screenshotDir) {
    assert.ok(resolve(screenshotDir).startsWith(root + sep + '.qa-admin-moderation-ui-'));
    await mkdir(screenshotDir, { recursive: true });
}

const settings = await readFile(join(root, 'admin/pages/moderation.php'), 'utf8');
for (const marker of [
    'admin-global-chat-moderation',
    'class="operations-settings-grid admin-moderation-page-grid"',
    'class="admin-global-chat-moderation-columns"',
    'data-moderation-rules',
    'data-moderation-pagination',
    'data-moderation-cases'
]) {
    assert.ok(settings.includes(marker), `Admin settings keeps expected moderation markup: ${marker}`);
}

assert.ok(!settings.includes('admin-password-card'), 'Moderation is separate from account security');

const scripts = [...settings.matchAll(/<script>([\s\S]*?)<\/script>/g)];
assert.ok(scripts.length > 0, 'Production moderation controller is available');
const moderationScript = scripts[0][1];
assert.match(moderationScript, /const rulesPerPage = 3/);

function makeRules(count) {
    return Array.from({ length: count }, (_, index) => ({
        id: index + 1,
        rule_text: `Fixture moderation rule ${index + 1}`,
        match_type: 'phrase',
        category: 'Other',
        severity: 'medium',
        action: 'review',
        enabled: 1
    }));
}

let moderationRules = makeRules(25);
const moderationCases = [{
    id: 501,
    matched_rule: 'Fixture credential rule',
    category: 'Credential theft',
    severity: 'critical',
    action: 'block',
    status: 'pending',
    message_snapshot: 'Never share your password.',
    current_content: 'Never share your password.',
    sender_name: 'Fixture sender',
    created_at: '2026-11-20 12:00:00'
}];

const html = theme => `<!doctype html>
<html lang="en" data-admin-theme="${theme}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_dashboard.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_theme.css">
</head>
<body>
<div id="contentArea">
<main class="admin-settings-page">
    <header class="operations-header"><div><span class="operations-eyebrow">ADMINISTRATION</span><h1>Chat Moderation</h1><p>Manage Global Chat rules and flagged messages.</p></div></header>
    <div class="operations-settings-grid admin-settings-priority-grid">
        <section class="operations-settings-card admin-appearance-card">
            <div class="admin-appearance-copy"><h2>Appearance</h2><p>Choose your admin theme.</p></div>
            <div class="operations-field"><label for="theme">Admin theme</label><select id="theme"><option>Dark</option></select></div>
        </section>
        <section class="operations-settings-card admin-profile-card">
            <h2>Admin profile</h2><p>Identify your admin account.</p>
            <div class="operations-field"><label for="profileName">First name</label><input id="profileName" value="Fixture Admin"></div>
            <div class="operations-form-actions"><button class="operations-button">Save profile</button></div>
        </section>
    </div>
    <div class="operations-settings-grid admin-moderation-page-grid">
    <section class="operations-settings-card admin-global-chat-moderation" data-global-chat-moderation data-csrf-token="fixture">
        <div class="admin-global-chat-moderation-heading">
            <div><h2>Global Chat moderation</h2><p>Manage rules and review flagged messages.</p></div>
            <button type="button" class="operations-button" data-moderation-refresh>Refresh</button>
        </div>
        <div class="operations-form-message" data-moderation-message hidden role="status"></div>
        <form class="admin-global-chat-rule-form" data-moderation-rule-form>
            <input type="hidden" name="rule_id" value="">
            <div class="operations-form-grid">
                <div class="operations-field"><label>Prohibited word or phrase</label><input name="rule_text" required></div>
                <div class="operations-field"><label>Match type</label><select name="match_type"><option value="word">Whole word</option><option value="phrase">Complete phrase</option></select></div>
                <div class="operations-field"><label>Category</label><select name="category"><option value="">Choose</option><option value="Other">Other</option></select></div>
                <div class="operations-field"><label>Severity</label><select name="severity"><option value="medium">Medium</option></select></div>
                <div class="operations-field"><label>Action</label><select name="rule_action"><option value="review">Review</option></select></div>
                <div class="operations-field"><label>Rule status</label><select name="enabled"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
            </div>
            <div class="operations-form-actions">
                <button class="operations-button" type="submit" data-moderation-save-rule>Save rule</button>
                <button class="operations-button admin-global-chat-cancel-edit" type="button" data-moderation-cancel-edit hidden>Cancel edit</button>
            </div>
        </form>
        <div class="admin-global-chat-moderation-columns">
            <section class="admin-global-chat-moderation-column">
                <h3>Configured rules</h3>
                <label class="admin-global-chat-search"><input type="search" data-moderation-rule-search aria-label="Search configured rules"></label>
                <div class="admin-global-chat-rules" data-moderation-rules></div>
                <nav class="admin-global-chat-rule-pagination" data-moderation-pagination aria-label="Configured rule pages" hidden></nav>
            </section>
            <section class="admin-global-chat-moderation-column">
                <h3>Flagged messages</h3>
                <div class="admin-global-chat-cases" data-moderation-cases></div>
            </section>
        </div>
    </section>
    </div>
    <div class="operations-settings-grid">
        <section class="operations-settings-card admin-password-card">
            <h2>Change password</h2><p>Use your current password to confirm changes.</p>
            <form class="admin-settings-form">
                <div class="operations-field"><label>Current password</label><input type="password" value=""></div>
                <div class="operations-form-actions"><button class="operations-button">Update password</button></div>
            </form>
            <div class="operations-settings-meta"><span>Role</span><strong>Super Admin</strong></div>
        </section>
        <section class="operations-settings-card admin-2fa-card">
            <h2>Two-factor authentication</h2>
            <div class="admin-2fa-status is-enabled"><strong>Authenticator enabled.</strong> Available for account recovery.</div>
            <div class="admin-2fa-status is-disabled"><strong>Authenticator not set up.</strong> Setup is optional.</div>
            <form class="admin-2fa-login-preference"><button class="operations-button">Save login preference</button></form>
        </section>
        <section class="operations-settings-card admin-activity-card">
            <h2>Recent admin activity</h2><p>Latest admin account changes.</p>
        </section>
    </div>
</main>
<section class="operations-settings-card" id="outsideSettingsCard">Outside Settings</section>
</div>
<script>window.confirm = () => true;</script>
<script>${moderationScript}</script>
</body>
</html>`;

let server;
let client;
let checks = 0;
const contrast = (background, foreground) => {
    const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number)
        .map(value => value / 255)
        .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
        .reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
    const back = luminance(background);
    const front = luminance(foreground);
    return (Math.max(back, front) + 0.05) / (Math.min(back, front) + 0.05);
};

async function tab() {
    await client.call('Input.dispatchKeyEvent', {
        type: 'rawKeyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9
    });
    await client.call('Input.dispatchKeyEvent', {
        type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9
    });
}

async function waitFor(expression) {
    for (let attempt = 0; attempt < 100; attempt += 1) {
        if (await client.evaluate(expression)) return;
        await delay(50);
    }
    throw new Error(`Timed out waiting for browser state: ${expression}`);
}

try {
    server = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/settings') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html(url.searchParams.get('theme') === 'light' ? 'light' : 'dark'));
            return;
        }
        if (url.pathname === '/VNZ-TECH/backend/global_chat_moderation.php') {
            res.setHeader('Content-Type', 'application/json; charset=utf-8');
            if (req.method === 'GET') {
                res.end(JSON.stringify({
                    success: true,
                    data: { rules: moderationRules, cases: moderationCases }
                }));
                return;
            }
            const body = [];
            for await (const chunk of req) body.push(chunk);
            const fields = new URLSearchParams(Buffer.concat(body).toString());
            if (fields.get('action') === 'delete_rule') {
                moderationRules = moderationRules.filter(rule => String(rule.id) !== fields.get('rule_id'));
            }
            res.end(JSON.stringify({ success: true, data: {} }));
            return;
        }
        const relative = url.pathname.replace(/^\/VNZ-TECH\//, '');
        if (!/^assets\/css\/admin_(dashboard|operations|theme)\.css$/.test(relative)) {
            res.writeHead(404).end();
            return;
        }
        res.setHeader('Content-Type', 'text/css');
        res.end(await readFile(join(root, relative)));
    });
    await new Promise(resolveListen => server.listen(0, '127.0.0.1', resolveListen));
    client = await browser();

    for (const theme of ['light', 'dark']) {
        for (const width of [320, 375, 768, 1024, 1440]) {
            moderationRules = makeRules(25);
            const height = width <= 375 ? 812 : 900;
            await client.call('Emulation.setDeviceMetricsOverride', {
                width, height, deviceScaleFactor: 1, mobile: width <= 768
            });
            await client.navigate(`http://127.0.0.1:${server.address().port}/settings?theme=${theme}`);
            await waitFor("document.querySelectorAll('[data-moderation-rules] .admin-global-chat-rule').length === 3");
            checks += 1;

            const layout = await client.evaluate(`(()=> {
                const rules=document.querySelector('[data-moderation-rules]');
                const cases=document.querySelector('[data-moderation-cases]');
                const columns=document.querySelector('.admin-global-chat-moderation-columns');
                const pagination=document.querySelector('[data-moderation-pagination]');
                const profileButton=document.querySelector('.admin-profile-card .operations-button');
                const ruleButton=document.querySelector('[data-moderation-save-rule]');
                const deleteButton=document.querySelector('.admin-global-chat-small-button.is-danger');
                const field=document.querySelector('.admin-profile-card input');
                const profileCard=document.querySelector('.admin-profile-card');
                const ruleCard=document.querySelector('.admin-global-chat-rule');
                const passwordCard=document.querySelector('.admin-password-card');
                const passwordGrid=passwordCard.parentElement;
                const passwordButton=document.querySelector('.admin-password-card .operations-button');
                const passwordMeta=document.querySelector('.admin-password-card .operations-settings-meta');
                const passwordTitle=passwordCard.querySelector('h2');
                const passwordLabel=passwordCard.querySelector('label');
                const priorityGrid=document.querySelector('.admin-settings-priority-grid');
                const moderationCard=document.querySelector('.admin-global-chat-moderation');
                const moderationGrid=moderationCard.parentElement;
                const pageTitle=document.querySelector('.operations-header h1');
                const enabled=document.querySelector('.admin-2fa-status.is-enabled');
                const disabled=document.querySelector('.admin-2fa-status.is-disabled');
                const title=document.querySelector('.admin-profile-card h2');
                const label=document.querySelector('.admin-profile-card label');
                const ruleMeta=document.querySelector('.admin-global-chat-rule-meta');
                const quote=document.querySelector('.admin-global-chat-case blockquote');
                const style=el=>getComputedStyle(el);
                const rect=el=>el.getBoundingClientRect();
                return {
                    overflow:document.documentElement.scrollWidth>innerWidth+1,
                    pageTitleColor:style(pageTitle).color,
                    columns:style(columns).gridTemplateColumns,
                    moderationCardWidth:rect(moderationCard).width,
                    moderationGridWidth:rect(moderationGrid).width,
                    rulesOverflow:style(rules).overflowY,
                    casesOverflow:style(cases).overflowY,
                    visibleRules:rules.children.length,
                    paginationHidden:pagination.hidden,
                    paginationText:pagination.querySelector('.vnz-pagination-count')?.textContent,
                    profileCardBg:style(profileCard).backgroundColor,
                    profileTitleColor:style(title).color,
                    profileLabelColor:style(label).color,
                    ruleCardBg:style(ruleCard).backgroundColor,
                    ruleMetaColor:style(ruleMeta).color,
                    passwordCardBg:style(passwordCard).backgroundColor,
                    passwordTitleColor:style(passwordTitle).color,
                    passwordLabelColor:style(passwordLabel).color,
                    passwordButtonBg:style(passwordButton).backgroundColor,
                    passwordButtonColor:style(passwordButton).color,
                    quoteBg:style(quote).backgroundColor,
                    quoteColor:style(quote).color,
                    outsideSettingsBg:style(document.querySelector('#outsideSettingsCard')).backgroundColor,
                    passwordGridColumn:style(passwordCard).gridColumn,
                    passwordWidth:rect(passwordCard).width,
                    passwordGridWidth:rect(passwordGrid).width,
                    passwordButtonDividerGap:rect(passwordMeta).top-rect(passwordButton).bottom,
                    passwordActionMarginBottom:style(passwordButton.parentElement).marginBottom,
                    profileBackgroundToken:style(document.querySelector('.admin-settings-page')).getPropertyValue('--settings-panel').trim(),
                    outsideBackgroundToken:style(document.querySelector('#outsideSettingsCard')).getPropertyValue('--settings-panel').trim(),
                    priorityGap:rect(moderationCard).top-rect(priorityGrid).bottom,
                    moderationBottomGap:rect(passwordGrid).top-rect(moderationCard).bottom,
                    profileBg:style(profileButton).backgroundColor,
                    profileColor:style(profileButton).color,
                    ruleBg:style(ruleButton).backgroundColor,
                    ruleColor:style(ruleButton).color,
                    deleteBg:style(deleteButton).backgroundColor,
                    deleteColor:style(deleteButton).color,
                    inputBg:style(field).backgroundColor,
                    inputColor:style(field).color,
                    enabledBg:style(enabled).backgroundColor,
                    enabledColor:style(enabled).color,
                    disabledBg:style(disabled).backgroundColor,
                    disabledColor:style(disabled).color
                };
            })()`);

            assert.equal(layout.overflow, false, `${theme} ${width}px has no horizontal overflow`);
            assert.equal(layout.pageTitleColor, theme==='light'?'rgb(24, 33, 43)':'rgb(242, 242, 242)', `${theme} ${width}px Chat Moderation page title remains readable`);
            assert.equal(layout.rulesOverflow, 'visible', `${theme} ${width}px rules use page scroll`);
            assert.equal(layout.casesOverflow, 'visible', `${theme} ${width}px cases use page scroll`);
            assert.equal(layout.visibleRules, 3, `${theme} ${width}px renders one page of rules`);
            assert.equal(layout.paginationHidden, false, `${theme} ${width}px shows multi-page controls`);
            assert.equal(layout.paginationText, 'Showing 1–3 of 25 rules');
            assert.equal(layout.columns.trim().split(/\s+/).length, width <= 900 ? 1 : 2);
            assert.ok(Math.abs(layout.moderationCardWidth-layout.moderationGridWidth)<=2, `${theme} ${width}px moderation card fills its page grid`);
            const expectedCard = theme === 'light' ? 'rgb(255, 255, 255)' : 'rgb(11, 11, 11)';
            assert.equal(layout.profileCardBg, expectedCard, `${theme} ${width}px uses the active Admin card theme`);
            assert.equal(layout.ruleCardBg, layout.profileCardBg);
            assert.equal(layout.passwordCardBg, layout.profileCardBg);
            assert.ok(contrast(layout.passwordCardBg, layout.passwordTitleColor) >= 4.5, 'Password card heading contrast');
            assert.ok(contrast(layout.passwordCardBg, layout.passwordLabelColor) >= 4.5, 'Password field label contrast');
            assert.ok(contrast(layout.passwordButtonBg, layout.passwordButtonColor) >= 4.5, 'Update password button contrast');
            assert.ok(contrast(layout.profileCardBg, layout.profileTitleColor) >= 4.5, 'Settings card heading contrast');
            assert.ok(contrast(layout.profileCardBg, layout.profileLabelColor) >= 4.5, 'Settings field label contrast');
            assert.ok(contrast(layout.ruleCardBg, layout.ruleMetaColor) >= 4.5, 'Configured rule metadata contrast');
            assert.equal(layout.quoteBg, theme === 'light' ? 'rgb(255, 255, 255)' : 'rgb(5, 5, 5)', 'Flagged-message quote follows the active Admin theme');
            assert.ok(contrast(layout.quoteBg, layout.quoteColor) >= 4.5, 'Flagged-message quote contrast');
            if (theme === 'dark') {
                assert.notEqual(layout.outsideSettingsBg, layout.profileCardBg, 'Dark Settings surfaces remain scoped to their page');
            } else {
                assert.equal(layout.outsideSettingsBg, 'rgb(255, 255, 255)');
            }
            assert.notEqual(layout.profileBackgroundToken, '');
            assert.equal(layout.outsideBackgroundToken, '');
            assert.equal(layout.passwordGridColumn, '1 / -1', 'Change password spans both Settings columns');
            assert.ok(Math.abs(layout.passwordWidth - layout.passwordGridWidth) <= 2, 'Change password fills the Settings grid width');
            assert.ok(layout.passwordButtonDividerGap >= 16, 'Update password button has at least 1rem of space before the Role divider');
            assert.equal(layout.passwordActionMarginBottom, '16px', 'Password-button spacing is scoped to the Settings card');
            assert.ok(layout.priorityGap >= 19, 'Admin profile and moderation cards have breathing room');
            assert.ok(layout.moderationBottomGap >= 19, 'Moderation and account-security panels have breathing room');
            assert.equal(layout.profileBg, layout.ruleBg, `${theme} ${width}px uses consistent primary colors`);
            assert.equal(layout.profileColor, layout.ruleColor);
            assert.ok(contrast(layout.profileBg, layout.profileColor) >= 4.5, 'Primary button contrast');
            assert.ok(contrast(layout.deleteBg, layout.deleteColor) >= 4.5, 'Delete button contrast');
            assert.ok(contrast(layout.inputBg, layout.inputColor) >= 4.5, 'Settings input contrast');
            assert.ok(contrast(layout.enabledBg, layout.enabledColor) >= 4.5, 'Enabled status contrast');
            assert.ok(contrast(layout.disabledBg, layout.disabledColor) >= 4.5, 'Disabled status contrast');
            checks += 36;

            if (theme === 'dark' && width === 1440) {
                await client.evaluate("document.querySelector('[data-rule-page=\"9\"]').click()");
                await waitFor("document.querySelector('.admin-global-chat-rule strong')?.textContent === 'Fixture moderation rule 25'");
                assert.equal(await client.evaluate("document.querySelector('.vnz-pagination-current')?.textContent"), '9');
                checks += 2;

                await client.evaluate(`(()=> {
                    const search=document.querySelector('[data-moderation-rule-search]');
                    search.value='Rule 25';
                    search.dispatchEvent(new Event('input',{bubbles:true}));
                })()`);
                assert.equal(await client.evaluate("document.querySelectorAll('[data-moderation-rules] .admin-global-chat-rule').length"), 1);
                assert.equal(await client.evaluate("document.querySelector('[data-moderation-pagination]').hidden"), true);
                assert.equal(await client.evaluate("document.querySelector('.vnz-pagination-count')"), null);
                checks += 3;

                await client.evaluate(`(()=> {
                    const search=document.querySelector('[data-moderation-rule-search]');
                    search.value='No matching fixture';
                    search.dispatchEvent(new Event('input',{bubbles:true}));
                })()`);
                assert.equal(await client.evaluate("document.querySelectorAll('[data-moderation-rules] .admin-global-chat-rule').length"), 0);
                assert.equal(await client.evaluate("document.querySelector('[data-moderation-rules] .admin-activity-empty')?.textContent"), 'No rules match your search.');
                assert.equal(await client.evaluate("document.querySelector('[data-moderation-pagination]').hidden"), true);
                checks += 3;

                await client.evaluate(`(()=> {
                    const search=document.querySelector('[data-moderation-rule-search]');
                    search.value='';
                    search.dispatchEvent(new Event('input',{bubbles:true}));
                })()`);
                await client.evaluate("document.querySelector('[data-rule-page=\"9\"]').click()");
                await client.evaluate("document.querySelector('[data-moderation-action=\"delete-rule\"][data-moderation-id=\"25\"]').click()");
                await waitFor("document.querySelectorAll('[data-moderation-rules] .admin-global-chat-rule').length === 3");
                assert.equal(await client.evaluate("document.querySelector('.vnz-pagination-current')?.textContent"), '8');
                assert.equal(await client.evaluate("document.querySelector('.vnz-pagination-count')?.textContent"), 'Showing 22–24 of 24 rules');
                checks += 3;

                await client.evaluate("document.querySelector('[data-moderation-rule-search]').focus()");
                let pageFocus;
                for (let tabCount = 0; tabCount < 60; tabCount += 1) {
                    await tab();
                    pageFocus = await client.evaluate(`(()=> {
                        const element=document.activeElement;
                        return {page:element?.dataset.rulePage, outline:getComputedStyle(element).outlineStyle};
                    })()`);
                    if (pageFocus.page === '1') break;
                }
                assert.equal(pageFocus.page, '1');
                assert.equal(pageFocus.outline, 'solid');
                checks += 2;

                moderationRules = [];
                await client.evaluate("document.querySelector('[data-moderation-refresh]').click()");
                await waitFor("document.querySelector('[data-moderation-rules] .admin-activity-empty')?.textContent === 'No moderation rules are configured.'");
                assert.equal(await client.evaluate("document.querySelectorAll('[data-moderation-rules] .admin-global-chat-rule').length"), 0);
                assert.equal(await client.evaluate("document.querySelector('[data-moderation-pagination]').hidden"), true);
                checks += 3;

                moderationRules = makeRules(25);
                await client.evaluate("document.querySelector('[data-moderation-refresh]').click()");
                await waitFor("document.querySelector('.admin-global-chat-rule strong')?.textContent === 'Fixture moderation rule 1'");
                assert.equal(await client.evaluate("document.querySelector('.vnz-pagination-current')?.textContent"), '1');
                checks += 2;
            }

            if (screenshotDir && (width === 375 || width === 1440)) {
                await client.evaluate('window.scrollTo(0, 0)');
                const screenshot = await client.call('Page.captureScreenshot', { format: 'png' });
                await writeFile(
                    join(screenshotDir, `admin-settings-${theme}-${width}.png`),
                    Buffer.from(screenshot.data, 'base64')
                );
                if (width === 1440) {
                    await client.evaluate("document.querySelector('.admin-password-card').scrollIntoView({block:'start'})");
                    await delay(250);
                    const passwordScreenshot = await client.call('Page.captureScreenshot', { format: 'png' });
                    await writeFile(
                        join(screenshotDir, `admin-settings-password-${theme}-${width}.png`),
                        Buffer.from(passwordScreenshot.data, 'base64')
                    );
                }
            }
        }
    }

    assert.equal(client.errors.length, 0, client.errors.join('\n'));
    console.log(`PASS ${checks} Admin Settings browser assertions: production moderation controller, three-item pagination, section order, password width, card spacing, theme-aware surfaces/contrast and keyboard-reachable pagination across five widths and both selector modes; synthetic settings data.`);
} finally {
    if (client) await client.close();
    if (server) {
        server.closeAllConnections();
        await new Promise(resolveClose => server.close(resolveClose));
    }
}
