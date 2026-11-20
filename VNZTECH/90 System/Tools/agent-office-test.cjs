/* Native DOM regression for the bundled Obsidian view; no vault/account writes. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const vault = path.resolve(__dirname, '../..');
const pluginPath = path.join(vault, '.obsidian/plugins/vnz-agent-office');
const manifest = JSON.parse(fs.readFileSync(path.join(pluginPath, 'manifest.json'), 'utf8'));
assert.equal(manifest.id, 'vnz-agent-office');
const pluginSource = fs.readFileSync(path.join(pluginPath, 'main.js'), 'utf8');
const styles = fs.readFileSync(path.join(pluginPath, 'styles.css'), 'utf8');
const company = JSON.parse(fs.readFileSync(path.join(vault, '90 System/Company Roster.json'), 'utf8'));
const agents = fs.readdirSync(path.join(vault, '90 System/Agents'))
    .filter(name => name.endsWith('.md')).map(name => name.slice(0, -3)).sort();
assert.equal(agents.length, 20, 'The office represents the existing 20-agent roster.');
assert.deepEqual(company.agents.map(agent => agent.name).sort(), agents, 'Company titles cover the existing roster.');
assert.equal(company.departments.length, 6, 'Six company departments.');
assert.ok(company.agents.every(agent => agent.title && agent.department && agent.strategy.length === 3), 'Each agent has a title, department, and three strategies.');
const notes = [
    'Home.md', '90 System/Agent Directory.md', '90 System/Ponytail.md',
    'Task Center.md', 'Approval Inbox.md', 'Decision Log.md',
    ...agents.map(name => `90 System/Agents/${name}.md`),
    ...fs.readdirSync(path.join(vault, '40 Knowledge/System Modules'))
        .filter(name => name.endsWith('.md')).map(name => `40 Knowledge/System Modules/${name}`),
];
for (const note of notes) assert.ok(fs.existsSync(path.join(vault, note)), `Note exists: ${note}`);

const browserChecks = String.raw`
(async () => {
    const check = (value, message) => { if (!value) throw new Error(message); };
    const tick = () => new Promise(resolve => setTimeout(resolve, 30));
    const notices = [], opened = [], callbacks = [], commands = [], ribbons = [], protocols = [];
    const views = new Map(), leaves = [], saved = [], network = [];
    window.fetch = (...args) => { network.push(args); throw new Error('Unexpected network request'); };
    window.XMLHttpRequest = class { constructor() { network.push('XHR'); throw new Error('Unexpected XHR'); } };
    window.WebSocket = class { constructor() { network.push('WebSocket'); throw new Error('Unexpected socket'); } };
    const errors = [];
    window.addEventListener('error', event => errors.push(event.message));
    window.addEventListener('unhandledrejection', event => errors.push(String(event.reason)));
    const TASK_PATH = '90 System/Tasks/Current Task.md';
    const TASK_RECORD = '90 System/Tasks/Records/TASK-FIXTURE-001.md';
    const OTHER_RECORD = '90 System/Tasks/Records/TASK-FIXTURE-002.md';
    const task = {
        task_id: 'TASK-FIXTURE-001', title: 'Sample task (test fixture)', task_record: TASK_RECORD,
        status: 'planning', approval: 'pending', plan_version: 1, runner: 'Fixture runner',
        agent_status: { Atlas: 'working', Quinn: 'reviewing', Aegis: 'blocked' },
        assigned_agents: ['Atlas', 'Quinn'], checkpoint: 'Fixture checkpoint recorded.',
        next_action: 'Review the fixture plan.', blocker: '', updated_at: '2026-11-19T14:20:00+08:00'
    };
    const taskToRaw = value => '---\n' + JSON.stringify(value) + '\n---\n\nFixture only; never written to the vault.';
    let taskRaw = taskToRaw(task), taskExists = true, readError = false, deferNextRead = false;
    let readCalls = 0, vaultWrites = 0;
    const pendingReads = [], eventRefs = new Set();
    class TFile { constructor(note) { this.path = note; this.extension = 'md'; this.basename = note.split('/').at(-1).replace(/\.md$/, ''); } }
    const taskFile = () => new TFile(TASK_PATH);
    const emit = (event, ...args) => { for (const ref of [...eventRefs]) if (ref.event === event) ref.callback(...args); };
    const parseYaml = text => JSON.parse(text); // JSON documents are a valid YAML subset.
    let unavailable = '';
    const app = {
        vault: {
            getAbstractFileByPath(note) {
                if (note === TASK_PATH) return taskExists ? taskFile() : null;
                if (note === TASK_RECORD) return note !== unavailable ? new TFile(note) : null;
                if (note === OTHER_RECORD) return new TFile(note);
                return note !== unavailable && NOTES.includes(note) ? new TFile(note) : null;
            },
            cachedRead(file) {
                check(file.path === TASK_PATH, 'Only current-task routing note is read'); readCalls++;
                if (deferNextRead) { deferNextRead = false; return new Promise((resolve, reject) => pendingReads.push({ resolve, reject })); }
                if (readError) return Promise.reject(new Error('Fixture read failure'));
                return Promise.resolve(taskRaw);
            },
            on(event, callback) { const ref = { event, callback }; eventRefs.add(ref); return ref; },
            offref(ref) { eventRefs.delete(ref); },
            modify() { vaultWrites++; throw new Error('Office must not write notes'); },
            create() { vaultWrites++; throw new Error('Office must not create notes'); },
            delete() { vaultWrites++; throw new Error('Office must not delete notes'); },
            rename() { vaultWrites++; throw new Error('Office must not rename notes'); }
        },
        workspace: {
            onLayoutReady(callback) { callbacks.push(callback); },
            getLeavesOfType(type) { return leaves.filter(leaf => leaf.state?.type === type); },
            getLeaf() {
                const leaf = { app, async setViewState(state) {
                    this.state = state;
                    this.view = views.get(state.type)(this);
                    document.querySelector('#app').appendChild(this.view.contentEl);
                    leaves.push(this);
                    await this.view.onOpen();
                } };
                return leaf;
            },
            async revealLeaf(leaf) { check(leaves.includes(leaf), 'Reveal registered leaf'); },
            async openLinkText(note, source, newLeaf) { opened.push({ note, source, newLeaf }); }
        }
    };
    class Plugin {
        constructor() { this.app = app; }
        registerView(type, factory) { check(!views.has(type), 'No duplicate view registration'); views.set(type, factory); }
        addCommand(command) { commands.push(command); }
        addRibbonIcon(icon, label, callback) { ribbons.push({ icon, label, callback }); }
        registerObsidianProtocolHandler(name, callback) { protocols.push({ name, callback }); }
        async loadData() { return null; }
        async saveData(data) { saved.push(data); }
    }
    class ItemView {
        constructor(leaf) {
            this.leaf = leaf; this.app = leaf.app;
            this.contentEl = document.createElement('div'); this.contentEl.className = 'view-content';
            if (window.__officePaneWidth) this.contentEl.style.width = window.__officePaneWidth + 'px';
        }
    }
    class Notice { constructor(message) { notices.push(message); } }
    try {
        const module = { exports: {} };
        const require = name => {
            check(name === 'obsidian', 'Distributed bundle has no external module dependency: ' + name);
            return { Plugin, ItemView, Notice, TFile, parseYaml };
        };
        new Function('require', 'module', 'exports', PLUGIN_SOURCE)(require, module, module.exports);
        const plugin = new module.exports();
        await plugin.onload();
        check(views.size === 1 && views.has('vnz-agent-office'), 'Office view registered');
        check(commands.length === 1 && commands[0].id === 'open-agent-office', 'Office command registered');
        check(ribbons.length === 1 && ribbons[0].label === 'Open Agent Office', 'Office ribbon registered');
        check(protocols.length === 1 && protocols[0].name === 'vnz-office', 'Office protocol registered');
        check(callbacks.length === 1, 'Welcome waits for layout readiness');
        check(leaves.length === 0, 'No premature layout mutation');
        callbacks[0](); await tick();
        check(saved.length === 1 && saved[0].welcomed === true, 'Successful first-open welcome remembered');
        await commands[0].callback(); await ribbons[0].callback(); await protocols[0].callback();
        check(leaves.length === 1, 'Command/ribbon/protocol reuse existing office tab');
        const view = leaves[0].view, root = view.contentEl;
        check(view.getViewType() === 'vnz-agent-office' && view.getDisplayText() === 'Agent Office', 'View identity');
        const avatars = [...root.querySelectorAll('.vnz-office-agent[data-name]')];
        check(JSON.stringify(avatars.map(button => button.dataset.name).sort()) === JSON.stringify(AGENTS), 'All 20 known agents rendered');
        check(root.querySelectorAll('.vnz-office-stage [data-agent]').length === 20, 'All 20 agents have scene graphics');
        if (FORCE_REDUCED_MOTION) {
            check(matchMedia('(prefers-reduced-motion: reduce)').matches, 'Browser reduced-motion preference active');
            check([...root.querySelectorAll('.office-avatar')].every(avatar => getComputedStyle(avatar).animationName === 'none'), 'Reduced-motion disables avatar animation');
        }
        check(avatars.every(button => button.tagName === 'BUTTON' && button.type === 'button' && button.getAttribute('aria-label').includes(button.dataset.name)), 'Native labelled agent buttons');
        check(root.querySelector('.vnz-office-detail h2').textContent === 'Atlas', 'Coordinator initially selected');
        for (const avatar of avatars) {
            avatar.click();
            const member = COMPANY.agents.find(agent => agent.name === avatar.dataset.name);
            check(root.querySelector('.vnz-office-detail h2').textContent === avatar.dataset.name, 'Selected agent details: ' + avatar.dataset.name);
            check(root.querySelector('.vnz-office-detail-role').textContent === member.title, 'Upgraded company title: ' + avatar.dataset.name);
            check(root.querySelector('.vnz-office-detail-room').textContent.includes(member.department), 'Company department: ' + avatar.dataset.name);
            check(JSON.stringify([...root.querySelectorAll('.vnz-office-strategy li')].map(item => item.textContent)) === JSON.stringify(member.strategy), 'Strategies match company roster: ' + avatar.dataset.name);
            check(avatar.getAttribute('aria-pressed') === 'true', 'Selected state exposed');
            check(avatars.filter(button => button.getAttribute('aria-pressed') === 'true').length === 1, 'One selected map agent');
            root.querySelector('.vnz-office-open-agent').click(); await tick();
            const last = opened.at(-1);
            check(last.note === '90 System/Agents/' + avatar.dataset.name + '.md' && last.source === '' && last.newLeaf === true, 'Open exact agent note: ' + avatar.dataset.name);
        }
        check(opened.length === 20, '20 explicit agent note navigations');
        const ownerDesk = root.querySelector('.vnz-office-owner-desk');
        check(ownerDesk && ownerDesk.textContent.includes('CEO') && ownerDesk.textContent.includes('FINAL APPROVER'), 'Owner is final approver, separate from 20 agents');
        for (const target of ['Task Center', 'Approval Inbox', 'Decision Log']) {
            const action = [...ownerDesk.querySelectorAll('button')].find(button => button.textContent.includes(target));
            check(action, 'Owner desk action: ' + target); action.click(); await tick();
            check(opened.at(-1).note === target + '.md', 'Owner desk opens existing note: ' + target);
        }
        const taskField = name => root.querySelector('.vnz-office-task-' + name);
        const taskUnavailable = label => {
            check(taskField('title').closest('.is-unavailable'), label + ': visibly unavailable');
            check(!/idle|not started|walang task/i.test(taskField('status').textContent), label + ': no invented idle state');
            check(root.querySelector('.vnz-office-open-task').disabled, label + ': no stale task link');
        };
        const showTask = async (overrides, event = 'modify') => {
            taskExists = true; readError = false; taskRaw = taskToRaw({ ...task, ...overrides });
            emit(event, taskFile()); await tick();
        };
        check(taskField('title').textContent === task.title, 'Current task title read from routing note');
        for (const [selector, value] of [['checkpoint', task.checkpoint], ['next', task.next_action], ['runner', task.runner], ['updated', task.updated_at]]) {
            check(taskField(selector).textContent.includes(value), 'Recorded task field: ' + selector);
        }
        check(taskField('status').textContent.toLowerCase().includes('plan'), 'Recorded planning status');
        check(taskField('approval').textContent.toLowerCase().includes('pending'), 'Approval comes from recorded state');
        check(root.querySelector('.office-agent[data-agent="Atlas"]').classList.contains('is-working'), 'Working agent status animates');
        check(root.querySelector('.office-agent[data-agent="Quinn"]').classList.contains('is-reviewing'), 'Reviewing agent status animates');
        check(root.querySelector('.office-agent[data-agent="Aegis"]').classList.contains('is-blocked'), 'Blocked agent status animates');
        check(root.querySelector('.office-agent[data-agent="Vega"]').classList.contains('is-idle'), 'Unassigned agent remains idle');
        root.querySelector('.vnz-office-open-task').click(); await tick();
        check(opened.at(-1).note === TASK_RECORD, 'Current task opens canonical record');
        check(new Set([...eventRefs].map(ref => ref.event)).size === 4, 'All relevant vault event types watched');
        const unrelatedReads = readCalls;
        emit('modify', new TFile('Unrelated note.md'));
        emit('rename', new TFile('Renamed unrelated.md'), 'Other unrelated.md'); await tick();
        check(readCalls === unrelatedReads, 'Unrelated vault events do not reread task');
        for (const status of ['planning', 'awaiting_approval', 'approved', 'in_progress', 'paused', 'blocked', 'implemented', 'verified', 'ready_for_review', 'accepted', 'completed', 'cancelled', 'needs_triage']) {
            await showTask({ status, title: 'Fixture status: ' + status });
            check(taskField('title').textContent === 'Fixture status: ' + status && !taskField('title').closest('.is-unavailable'), 'Known recorded task status: ' + status);
        }
        await showTask({ approval: 'approved', checkpoint: 'Updated fixture checkpoint.', next_action: 'Run fixture checks.' });
        check(taskField('approval').textContent.toLowerCase().includes('approved'), 'Recorded approval updated');
        check(taskField('checkpoint').textContent.includes('Updated fixture checkpoint.') && taskField('next').textContent.includes('Run fixture checks.'), 'Vault modification refreshes checkpoint and next action');
        await showTask({ status: 'invented_runtime_status' }); taskUnavailable('Unknown status');
        await showTask({ approval: 'invented_approval' }); taskUnavailable('Unknown approval');
        await showTask({ agent_status: { Atlas: 'invented_agent_status' } }); taskUnavailable('Unknown agent status');
        await showTask({ agent_status: { UnknownAgent: 'working' } }); taskUnavailable('Unknown agent name');
        await showTask({ task_record: '../outside-task-record.md' }); taskUnavailable('Noncanonical record path');
        await showTask({ task_record: OTHER_RECORD }); taskUnavailable('Record does not match task identity');
        await showTask({ task_id: '../untrusted' }); taskUnavailable('Invalid task identity');
        await showTask({ updated_at: 'invalid timestamp' }); taskUnavailable('Invalid recorded time');
        await showTask({ checkpoint: '' }); taskUnavailable('Missing checkpoint');
        unavailable = TASK_RECORD; await showTask({}); taskUnavailable('Missing canonical task record'); unavailable = '';
        taskRaw = '---\n{not-json\n---'; emit('modify', taskFile()); await tick(); taskUnavailable('Malformed frontmatter');
        taskRaw = 'Plain text without frontmatter'; emit('modify', taskFile()); await tick(); taskUnavailable('Missing frontmatter');
        readError = true; emit('modify', taskFile()); await tick(); taskUnavailable('Read error'); readError = false;
        taskExists = false; emit('delete', taskFile()); await tick(); taskUnavailable('Deleted task');
        await showTask({ title: 'Recreated fixture task' }, 'create');
        check(taskField('title').textContent === 'Recreated fixture task', 'Create event recovers missing task');
        taskExists = false; emit('rename', new TFile('Moved Task.md'), TASK_PATH); await tick(); taskUnavailable('Task renamed away');
        taskExists = true; taskRaw = taskToRaw({ ...task, title: 'Renamed back fixture task' });
        emit('rename', taskFile(), 'Moved Task.md'); await tick();
        check(taskField('title').textContent === 'Renamed back fixture task', 'Rename back to routing path refreshes task');
        await showTask({ title: 'Task before race' }, 'create');
        deferNextRead = true; taskRaw = taskToRaw({ ...task, title: 'Stale fixture result' }); emit('modify', taskFile());
        check(pendingReads.length === 1, 'Older read deferred');
        await showTask({ title: 'Newest fixture result' });
        pendingReads.shift().resolve(taskToRaw({ ...task, title: 'Stale fixture result' })); await tick();
        check(taskField('title').textContent === 'Newest fixture result', 'Stale async read cannot replace newer result');
        deferNextRead = true; emit('modify', taskFile());
        taskExists = false; emit('delete', taskFile()); await tick();
        pendingReads.shift().resolve(taskToRaw(task)); await tick(); taskUnavailable('Deleted during pending read');
        await showTask({});
        const search = root.querySelector('input[type=search]');
        search.value = 'cipher'; search.dispatchEvent(new Event('input'));
        check(avatars.filter(button => !button.hidden).map(button => button.dataset.name).join() === 'Cipher', 'Search agent by name');
        search.value = 'no-such-team'; search.dispatchEvent(new Event('input'));
        check(avatars.every(button => button.hidden) && !root.querySelector('.vnz-office-empty').hidden, 'No-results state');
        search.value = ''; search.dispatchEvent(new Event('input'));
        check(avatars.every(button => !button.hidden) && root.querySelector('.vnz-office-empty').hidden, 'Search reset restores roster');
        const buttons = [...root.querySelectorAll('button')];
        buttons.find(button => button.textContent === 'Agent list').click();
        check(!root.querySelector('.vnz-office-list').hidden && root.querySelector('.vnz-office-scroll').hidden, 'Accessible list mode');
        if (innerWidth <= 600) check([...root.querySelectorAll('.vnz-office-list-agent')].every(button => button.getBoundingClientRect().height >= 44), 'Mobile list touch targets');
        root.querySelector('.vnz-office-list-agent[data-name="Aegis"]').click();
        check(root.querySelector('.vnz-office-detail h2').textContent === 'Aegis', 'List selects agent');
        const moduleButton = root.querySelector('.vnz-office-open-module');
        check(!moduleButton.hidden, 'Specialist module action available');
        moduleButton.click(); await tick();
        check(opened.at(-1).note === '40 Knowledge/System Modules/Identity and Accounts.md', 'Specialist opens actual module note');
        const pause = root.querySelector('.vnz-office-pause');
        pause.click(); check(root.classList.contains('is-paused') && pause.getAttribute('aria-pressed') === 'true', 'Animation pause accessible');
        pause.click(); check(!root.classList.contains('is-paused'), 'Animation resumes');
        unavailable = '90 System/Agents/Aegis.md';
        root.querySelector('.vnz-office-list-agent[data-name="Aegis"]').click();
        check(root.querySelector('.vnz-office-open-agent').disabled, 'Missing agent note disables navigation');
        const before = opened.length;
        await view.openNote(unavailable);
        check(opened.length === before && notices.length === 1, 'Missing note produces notice without navigation');
        unavailable = '';
        buttons.find(button => button.textContent === 'Office map').click();
        check(root.querySelector('.vnz-office-status-note').textContent.trim(), 'Directory includes status provenance');
        check(network.length === 0, 'No network/AI requests');
        check(vaultWrites === 0, 'Office never writes task or approval notes');
        check(document.documentElement.scrollWidth <= innerWidth + 1, 'No page-level horizontal overflow');
        const panel = root.querySelector('.vnz-office-detail');
        const text = getComputedStyle(panel).color;
        check(text === (THEME === 'light' ? 'rgb(24, 33, 43)' : 'rgb(235, 240, 245)'), 'Obsidian theme text inherited');
        check(root.getBoundingClientRect().width <= innerWidth + 1, 'View fits small pane');
        if (window.__officePaneWidth) {
            check(innerWidth >= 1200 && root.getBoundingClientRect().width === 850, 'Wide viewport with narrow split pane');
            check(panel.getBoundingClientRect().top >= root.querySelector('.vnz-office-scene-card').getBoundingClientRect().bottom, 'Container query stacks details in narrow pane');
        }
        check(errors.length === 0, 'No browser errors');
        const readsBeforeClose = readCalls;
        deferNextRead = true; emit('modify', taskFile());
        check(pendingReads.length === 1, 'Read pending before view closes');
        await view.onClose(); check(root.childElementCount === 0, 'View close clears scene');
        check(eventRefs.size === 0, 'Vault subscriptions removed on close');
        pendingReads.shift().resolve(taskToRaw({ ...task, title: 'Late closed-view result' }));
        emit('modify', taskFile()); await tick();
        check(root.childElementCount === 0 && readCalls === readsBeforeClose + 1, 'Closed view ignores stale result and later events');
        await view.onOpen(); check(root.querySelectorAll('.vnz-office-agent').length === 20, 'Reopen renders one roster');
        await tick(); check(eventRefs.size === 4, 'Reopen restores one subscription per event');
        root.querySelector('.vnz-office-pause').click();
        if (window.__officeListPreview) [...root.querySelectorAll('button')].find(button => button.textContent === 'Agent list').click();
        parent.postMessage({ ok: true, theme: THEME, width: innerWidth, pane_width: root.getBoundingClientRect().width, agents: avatars.length, note_navigations: opened.length, reduced_motion: matchMedia('(prefers-reduced-motion: reduce)').matches }, '*');
    } catch (error) { parent.postMessage({ ok: false, theme: THEME, width: innerWidth, error: error.message }, '*'); }
})();
`;

const encode = value => JSON.stringify(value).replace(/</g, '\\u003c');
const palette = `
html,body{margin:0;height:100%;font:14px system-ui}#app,.view-content{height:100%;box-sizing:border-box}
body.theme-light{--background-primary:#fff;--background-secondary:#f4f7fa;--background-secondary-alt:#edf2f7;--background-modifier-border:#dce3eb;--text-normal:#18212b;--text-muted:#586675;--text-faint:#718091;--interactive-normal:#f7f9fc;--interactive-hover:#edf2f7;--interactive-accent:#0369a1;--text-on-accent:#fff}
body.theme-dark{--background-primary:#171b22;--background-secondary:#10141a;--background-secondary-alt:#202630;--background-modifier-border:#343d49;--text-normal:#ebf0f5;--text-muted:#a3afbd;--text-faint:#8492a2;--interactive-normal:#242d39;--interactive-hover:#303d4c;--interactive-accent:#5bc0eb;--text-on-accent:#10212a}
body{background:var(--background-primary);color:var(--text-normal)}
button{background:var(--interactive-normal);color:var(--text-normal);border:1px solid var(--background-modifier-border)}
`;
const fixtures = {};
const browserMotionArgs = process.argv.includes('--reduced-motion') ? ['--force-prefers-reduced-motion'] : [];
for (const theme of ['light', 'dark']) {
    fixtures[theme] = `<!doctype html><html><head><meta charset="utf-8"><style>${palette}\n${styles}</style></head><body class="theme-${theme}"><div id="app"></div><script>const PLUGIN_SOURCE=${encode(pluginSource)},AGENTS=${encode(agents)},NOTES=${encode(notes)},COMPANY=${encode(company)},THEME=${encode(theme)},FORCE_REDUCED_MOTION=${browserMotionArgs.length > 0};${browserChecks}</script></body></html>`;
}
const harness = `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#d7dce2;font:14px system-ui}iframe{display:block;border:0;margin:0 0 12px}pre{margin:16px;white-space:pre-wrap}</style></head><body><pre id="report">Running</pre><script>
const fixtures=${encode(fixtures)},results=[];
(async()=>{
 const cases=Object.entries(fixtures).flatMap(([theme,markup])=>[1200,375].map(width=>({theme,markup,width})));
 cases.push({theme:'dark',width:1200,markup:fixtures.dark.replace('<div id="app"></div>','<div id="app"></div><script>window.__officePaneWidth=850;<\\/script>')});
 for(const {theme,markup,width} of cases){
  const frame=document.createElement('iframe');frame.width=width;frame.height=820;
  const result=new Promise(resolve=>window.addEventListener('message',function listener(event){if(event.source===frame.contentWindow){window.removeEventListener('message',listener);resolve(event.data)}}));
  document.body.append(frame);frame.srcdoc=markup;results.push(await result);frame.remove();
 }
 document.documentElement.dataset.result=results.every(result=>result.ok)?'PASS':'FAIL';document.querySelector('#report').textContent=JSON.stringify(results,null,2);
 for(const theme of ['light','dark']){const preview=document.createElement('iframe');preview.width=1200;preview.height=820;preview.srcdoc=fixtures[theme];document.querySelector('#report').before(preview)}
})();</script></body></html>`;
const run = fs.mkdtempSync(path.join(os.tmpdir(), 'vnz-agent-office-'));
fs.writeFileSync(path.join(run, 'fixture.html'), harness);
const chrome = process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
assert.ok(fs.existsSync(chrome), 'Set CHROME_BINARY to a Chrome executable.');
const browser = spawnSync(chrome, [
    ...browserMotionArgs,
    '--headless=new', '--disable-gpu', '--disable-background-networking', '--no-first-run',
    '--no-default-browser-check', `--user-data-dir=${path.join(run, 'profile')}`,
    '--virtual-time-budget=15000', '--window-size=1280,1800',
    `--screenshot=${path.join(run, 'preview.png')}`, '--dump-dom',
    new URL('file:///' + path.join(run, 'fixture.html').replace(/\\/g, '/')).href,
], { encoding: 'utf8', timeout: 90000, maxBuffer: 8 * 1024 * 1024 });
fs.writeFileSync(path.join(run, 'result.html'), browser.stdout || '');
const report = browser.stdout?.match(/<pre id="report">([\s\S]*?)<\/pre>/)?.[1];
const passed = browser.status === 0 && browser.stdout?.includes('data-result="PASS"');
const mobileMarkup = `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;display:flex;gap:12px;background:#cbd2db}iframe{border:0}</style></head><body><script>
const fixtures=${encode(fixtures)};
for(const theme of ['light','dark']){const frame=document.createElement('iframe');frame.width=375;frame.height=1000;frame.srcdoc=fixtures[theme].replace('<div id="app"></div>','<div id="app"></div><script>window.__officeListPreview=true;<\\/script>');document.body.append(frame)}
</script></body></html>`;
fs.writeFileSync(path.join(run, 'mobile.html'), mobileMarkup);
const mobileBrowser = spawnSync(chrome, [
    ...browserMotionArgs,
    '--headless=new', '--disable-gpu', '--disable-background-networking', '--no-first-run',
    '--no-default-browser-check', `--user-data-dir=${path.join(run, 'mobile-profile')}`,
    '--virtual-time-budget=5000', '--window-size=800,1020',
    `--screenshot=${path.join(run, 'mobile-preview.png')}`, '--dump-dom',
    new URL('file:///' + path.join(run, 'mobile.html').replace(/\\/g, '/')).href,
], { encoding: 'utf8', timeout: 90000, maxBuffer: 8 * 1024 * 1024 });
fs.writeFileSync(path.join(run, 'mobile-result.html'), mobileBrowser.stdout || '');
let guidePreview;
if (process.argv.includes('--capture-guide')) {
    fs.writeFileSync(path.join(run, 'standalone.html'), fixtures.dark);
    guidePreview = path.join(vault, '90 System/Attachments/Agent Office Preview.png');
    fs.mkdirSync(path.dirname(guidePreview), { recursive: true });
    const guideBrowser = spawnSync(chrome, [
        ...browserMotionArgs,
        '--headless=new', '--disable-gpu', '--disable-background-networking', '--no-first-run',
        '--no-default-browser-check', `--user-data-dir=${path.join(run, 'guide-profile')}`,
        '--virtual-time-budget=5000', '--window-size=1440,1500',
        `--screenshot=${guidePreview}`, '--dump-dom',
        new URL('file:///' + path.join(run, 'standalone.html').replace(/\\/g, '/')).href,
    ], { encoding: 'utf8', timeout: 90000, maxBuffer: 8 * 1024 * 1024 });
    fs.writeFileSync(path.join(run, 'standalone-result.html'), guideBrowser.stdout || '');
    assert.equal(guideBrowser.status, 0, 'Standalone guide screenshot completed');
    assert.ok(fs.existsSync(guidePreview), 'Guide screenshot exists');
}
console.log(JSON.stringify({ passed, browser_exit_code: browser.status, mobile_preview_exit_code: mobileBrowser.status, results: report || 'missing', artifacts: run, guide_preview: guidePreview }));
if (!passed) process.exitCode = 1;
