/* Source entry; bundle with 90 System/Tools/build-agent-office.cjs. */
const { Plugin, ItemView, Notice, parseYaml } = require('obsidian');
const VIEW_TYPE = 'vnz-agent-office';
const DETAILS = Object.fromEntries(COMPANY_ROSTER.agents.map(agent => [agent.name, [agent.title, agent.summary, agent.module]]));
const TASK_POINTER = '90 System/Tasks/Current Task.md';
const TASK_STATES = new Set(['planning', 'awaiting_approval', 'approved', 'in_progress', 'paused', 'blocked', 'implemented', 'verified', 'ready_for_review', 'accepted', 'completed', 'cancelled', 'needs_triage']);
const APPROVAL_STATES = new Set(['pending', 'approved', 'rejected', 'not_required', 'unknown']);
const AGENT_STATUSES = new Set(['idle', 'working', 'reviewing', 'done', 'blocked']);
const TASK_RECORD = /^90 System\/Tasks\/Records\/TASK-[A-Za-z0-9-]+\.md$/;


class AgentOfficeView extends ItemView {
    getViewType() { return VIEW_TYPE; }
    getDisplayText() { return 'Agent Office'; }
    getIcon() { return 'building-2'; }

    async onOpen() {
        const root = this.contentEl;
        root.replaceChildren();
        root.classList.add('vnz-office-view');
        root.classList.remove('is-paused');
        this.taskClosed = false;
        this.taskReadVersion = (this.taskReadVersion || 0) + 1;
        const doc = root.ownerDocument;
        const element = (tag, className, text, parent = root) => {
            const node = doc.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            parent.appendChild(node);
            return node;
        };
        const button = (text, className, parent, action) => {
            const node = element('button', className, text, parent);
            node.type = 'button';
            node.addEventListener('click', action);
            return node;
        };
        const header = element('header', 'vnz-office-header');
        const brand = element('div', 'vnz-office-brand', undefined, header);
        element('span', 'vnz-office-eyebrow', 'VNZTECH / YOUR IT COMPANY', brand);
        element('h1', '', 'Agent Office', brand);
        element('p', '', 'Ikaw ang owner. Si Atlas ang coordinator ng team mo.', brand);
        const links = element('div', 'vnz-office-header-actions', undefined, header);
        button('Home ↗', '', links, () => this.openNote('Home.md'));
        button('Agent Directory ↗', '', links, () => this.openNote('90 System/Agent Directory.md'));

        const toolbar = element('div', 'vnz-office-toolbar');
        const searchLabel = element('label', 'vnz-office-search', undefined, toolbar);
        element('span', 'vnz-office-sr-only', 'Hanap ng agent o team', searchLabel);
        const search = element('input', '', undefined, searchLabel);
        search.type = 'search';
        search.placeholder = 'Hanap ng agent o team…';
        const switches = element('div', 'vnz-office-switches', undefined, toolbar);
        const mapButton = button('Office map', '', switches, () => setMode('map'));
        const listButton = button('Agent list', '', switches, () => setMode('list'));
        mapButton.setAttribute('aria-pressed', 'true');
        listButton.setAttribute('aria-pressed', 'false');
        const pause = button('Pause animation', 'vnz-office-pause', toolbar, () => {
            const paused = root.classList.toggle('is-paused');
            pause.textContent = paused ? 'Play animation' : 'Pause animation';
            pause.setAttribute('aria-pressed', String(paused));
        });
        pause.setAttribute('aria-pressed', 'false');

        const briefing = element('div', 'vnz-office-briefing');
        const ownerDesk = element('section', 'vnz-office-owner-desk', undefined, briefing);
        element('span', 'vnz-office-eyebrow', 'YOU / CEO / FINAL APPROVER', ownerDesk);
        element('h2', '', 'Approval desk mo', ownerDesk);
        element('p', '', 'Team proposals, implementation scope, at result acceptance: sa iyo ang final decision.', ownerDesk);
        const ownerActions = element('div', 'vnz-office-owner-actions', undefined, ownerDesk);
        button('Task Center ↗', '', ownerActions, () => this.openNote('Task Center.md'));
        button('Approval Inbox ↗', '', ownerActions, () => this.openNote('Approval Inbox.md'));
        button('Decision Log ↗', '', ownerActions, () => this.openNote('Decision Log.md'));
        const task = element('section', 'vnz-office-task-card', undefined, briefing);
        this.taskCard = task;
        element('span', 'vnz-office-eyebrow', 'CURRENT TASK / RECORDED CHECKPOINT', task);
        this.taskTitle = element('h2', 'vnz-office-task-title', 'Binabasa yung checkpoint…', task);
        const meta = element('div', 'vnz-office-task-meta', undefined, task);
        this.taskStatus = element('span', 'vnz-office-task-status', 'Loading', meta);
        this.taskApproval = element('span', 'vnz-office-task-approval', 'Recorded approval: unknown', meta);
        this.taskRunner = element('p', 'vnz-office-task-runner', '', task);
        this.taskCheckpoint = element('p', 'vnz-office-task-checkpoint', '', task);
        this.taskNext = element('p', 'vnz-office-task-next', '', task);
        this.taskUpdated = element('small', 'vnz-office-task-updated', '', task);
        const taskActions = element('div', 'vnz-office-task-actions', undefined, task);
        this.openTaskButton = button('Buksan task record ↗', 'vnz-office-open-task', taskActions, () => {
            if (this.taskRecordPath) this.openNote(this.taskRecordPath);
        });
        this.openTaskButton.disabled = true;
        button('Refresh checkpoint', 'vnz-office-refresh-task', taskActions, () => this.refreshTask());

        const layout = element('div', 'vnz-office-layout');
        const sceneCard = element('section', 'vnz-office-scene-card', undefined, layout);
        sceneCard.setAttribute('aria-label', 'Agent office map');
        const sceneHeader = element('div', 'vnz-office-scene-header', undefined, sceneCard);
        element('span', '', 'THE WORKSPACE', sceneHeader);
        this.counter = element('span', 'vnz-office-counter', '20 agents · 6 teams', sceneHeader);
        this.counter.setAttribute('aria-live', 'polite');
        const scroll = element('div', 'vnz-office-scroll', undefined, sceneCard);
        const stage = element('div', 'vnz-office-stage', undefined, scroll);
        const svg = new DOMParser().parseFromString(renderOffice(), 'image/svg+xml').documentElement;
        stage.appendChild(doc.importNode(svg, true));
        const mapControls = element('div', 'vnz-office-map-controls', undefined, stage);
        const list = element('div', 'vnz-office-list', undefined, sceneCard);
        list.hidden = true;
        this.agentButtons = [];
        for (const agent of AGENT_LAYOUT) {
            const details = DETAILS[agent.name];
            if (!details) continue;
            const avatar = button(agent.name, 'vnz-office-agent', mapControls, () => this.selectAgent(agent));
            avatar.dataset.name = agent.name;
            avatar.style.left = `${agent.x / 12}%`;
            avatar.style.top = `${agent.y / 8.5}%`;
            avatar.style.setProperty('--agent-color', agent.color);
            avatar.setAttribute('aria-label', `${agent.name}, ${details[0]}. Tingnan ang detalye`);
            avatar.setAttribute('aria-pressed', 'false');
            const row = button('', 'vnz-office-list-agent', list, () => this.selectAgent(agent));
            row.dataset.name = agent.name;
            row.setAttribute('aria-pressed', 'false');
            element('strong', '', agent.name, row);
            element('span', '', details[0], row);
            element('small', '', agent.room, row);
            this.agentButtons.push({ agent, avatar, row, graphic: stage.querySelector(`[data-agent="${agent.name}"]`) });
        }
        this.empty = element('p', 'vnz-office-empty', 'Walang match. Try mo yung ibang name o team.', sceneCard);
        this.empty.hidden = true;
        element('p', 'vnz-office-map-hint', 'Pili ka ng agent para makita yung role at notes niya. Sa maliit na screen, pwede mong i-scroll yung map o gamitin yung Agent list.', sceneCard);

        const panel = element('aside', 'vnz-office-detail', undefined, layout);
        panel.setAttribute('aria-label', 'Selected agent');
        this.detailIcon = element('div', 'vnz-office-detail-avatar', 'AT', panel);
        element('span', 'vnz-office-eyebrow', 'MEET YOUR AGENT', panel);
        this.detailName = element('h2', '', '', panel);
        this.detailRole = element('p', 'vnz-office-detail-role', '', panel);
        this.detailDescription = element('p', 'vnz-office-detail-description', '', panel);
        this.detailRoom = element('p', 'vnz-office-detail-room', '', panel);
        element('h3', 'vnz-office-strategy-heading', 'Strategy niya', panel);
        this.detailStrategy = element('ol', 'vnz-office-strategy', undefined, panel);
        this.detailActivity = element('p', 'vnz-office-detail-activity', 'Activity: idle', panel);
        this.openAgent = button('Buksan agent note ↗', 'vnz-office-open-agent mod-cta', panel, () => this.openNote(`90 System/Agents/${this.selected.name}.md`));
        this.openModule = button('Buksan module note ↗', 'vnz-office-open-module', panel, () => {
            const module = DETAILS[this.selected.name][2];
            if (module) this.openNote(`40 Knowledge/System Modules/${module}.md`);
        });
        element('p', 'vnz-office-status-note', 'Recorded task/agent status ang ipinapakita. Walang AI tasks o external services na sini-start ng office.', panel);
        const footer = element('footer', 'vnz-office-footer');
        element('span', '', '20 agents. One second brain.', footer);
        button('Ponytail workflow ↗', '', footer, () => this.openNote('90 System/Ponytail.md'));

        const setMode = mode => {
            scroll.hidden = mode !== 'map';
            list.hidden = mode !== 'list';
            mapButton.setAttribute('aria-pressed', String(mode === 'map'));
            listButton.setAttribute('aria-pressed', String(mode === 'list'));
        };
        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase();
            let found = 0;
            for (const item of this.agentButtons) {
                const match = `${item.agent.name} ${item.agent.room} ${DETAILS[item.agent.name][0]}`.toLocaleLowerCase().includes(query);
                item.avatar.hidden = !match;
                item.row.hidden = !match;
                if (item.graphic) item.graphic.style.opacity = match ? '' : '0.12';
                if (match) found++;
            }
            this.counter.textContent = query ? `${found} sa 20 agents` : '20 agents · 6 teams';
            this.empty.hidden = found !== 0;
        });
        this.selectAgent(AGENT_LAYOUT.find(agent => agent.name === 'Atlas') || AGENT_LAYOUT[0]);
        const refreshIfRelevant = (file, oldPath) => {
            if (file?.path === TASK_POINTER || oldPath === TASK_POINTER) this.refreshTask();
        };
        this.taskEvents = ['modify', 'create', 'delete', 'rename'].map(event => this.app.vault.on(event, refreshIfRelevant));
        await this.refreshTask();
    }

    selectAgent(agent) {
        this.selected = agent;
        const [role, description, module] = DETAILS[agent.name];
        this.detailIcon.textContent = agent.name.slice(0, 2).toUpperCase();
        this.detailIcon.style.setProperty('--agent-color', agent.color);
        this.detailName.textContent = agent.name;
        this.detailRole.textContent = role;
        this.detailDescription.textContent = description;
        this.detailRoom.textContent = agent.room;
        const status = this.agentStatuses?.[agent.name] || 'idle';
        this.detailActivity.textContent = 'Activity: ' + status;
        this.detailActivity.dataset.status = status;
        this.detailStrategy.replaceChildren();
        for (const step of COMPANY_ROSTER.agents.find(person => person.name === agent.name).strategy) {
            const item = this.contentEl.ownerDocument.createElement('li');
            item.textContent = step;
            this.detailStrategy.appendChild(item);
        }
        this.openModule.hidden = !module;
        this.openAgent.disabled = !this.app.vault.getAbstractFileByPath(`90 System/Agents/${agent.name}.md`);
        for (const item of this.agentButtons) {
            const selected = item.agent.name === agent.name;
            item.avatar.setAttribute('aria-pressed', String(selected));
            item.row.setAttribute('aria-pressed', String(selected));
            if (item.graphic) item.graphic.classList.toggle('is-selected', selected);
        }
    }

    applyAgentStatuses() {
        for (const item of this.agentButtons) {
            const status = this.agentStatuses[item.agent.name] || 'idle';
            for (const name of AGENT_STATUSES) {
                if (item.graphic) item.graphic.classList.toggle('is-' + name, status === name);
                item.avatar.classList.toggle('is-' + name, status === name);
                item.row.classList.toggle('is-' + name, status === name);
            }
            item.avatar.dataset.status = status;
            item.row.dataset.status = status;
            item.avatar.setAttribute('aria-label', `${item.agent.name}, ${DETAILS[item.agent.name][0]}, activity ${status}`);
            if (item.graphic) item.graphic.dataset.status = status;
        }
        if (this.selected) {
            const status = this.agentStatuses[this.selected.name] || 'idle';
            this.detailActivity.textContent = 'Activity: ' + status;
            this.detailActivity.dataset.status = status;
        }
    }

    async refreshTask() {
        const version = ++this.taskReadVersion;
        try {
            const file = this.app.vault.getAbstractFileByPath(TASK_POINTER);
            if (!file) throw new Error('missing');
            const content = await this.app.vault.cachedRead(file);
            if (this.taskClosed || version !== this.taskReadVersion) return;
            const header = content.match(/^---\r?\n([\s\S]*?)\r?\n---(?:\r?\n|$)/);
            const data = header ? parseYaml(header[1]) : null;
            const required = ['task_id', 'title', 'task_record', 'status', 'approval', 'runner', 'checkpoint', 'next_action', 'updated_at'];
            if (!data || Array.isArray(data) || required.some(key => typeof data[key] !== 'string' || !data[key].trim())
                || !TASK_STATES.has(data.status) || !APPROVAL_STATES.has(data.approval)
                || !TASK_RECORD.test(data.task_record) || !/^TASK-[A-Za-z0-9-]+$/.test(data.task_id)
                || data.task_record !== `90 System/Tasks/Records/${data.task_id}.md`
                || !Number.isFinite(Date.parse(data.updated_at))) throw new Error('invalid');
            const knownAgents = new Set(AGENT_LAYOUT.map(agent => agent.name));
            const statuses = data.agent_status || {};
            if (!statuses || Array.isArray(statuses) || typeof statuses !== 'object'
                || Object.entries(statuses).some(([name, status]) => !knownAgents.has(name) || !AGENT_STATUSES.has(status))) throw new Error('invalid');
            if (!this.app.vault.getAbstractFileByPath(data.task_record)) throw new Error('missing record');
            const concise = text => text.length > 280 ? text.slice(0, 277) + '…' : text;
            this.taskCard.classList.remove('is-unavailable');
            this.taskTitle.textContent = concise(data.title);
            this.taskStatus.textContent = data.status.replaceAll('_', ' ');
            this.taskApproval.textContent = 'Recorded approval: ' + data.approval.replaceAll('_', ' ');
            this.taskRunner.textContent = 'Writer: ' + concise(data.runner) + ' / ' + data.task_id;
            this.taskCheckpoint.textContent = 'Huling checkpoint: ' + concise(data.checkpoint);
            this.taskNext.textContent = 'Next: ' + concise(data.next_action);
            this.taskUpdated.textContent = 'Recorded: ' + data.updated_at;
            this.agentStatuses = statuses;
            this.applyAgentStatuses();
            this.taskRecordPath = data.task_record;
            this.openTaskButton.disabled = false;
        } catch (error) {
            if (this.taskClosed || version !== this.taskReadVersion) return;
            this.taskCard.classList.add('is-unavailable');
            this.taskTitle.textContent = 'Hindi mabasa yung current task';
            this.taskStatus.textContent = 'Unavailable';
            this.taskApproval.textContent = 'Recorded approval: unknown';
            this.taskRunner.textContent = 'Walang verified checkpoint na maipakita.';
            this.taskCheckpoint.textContent = 'Buksan yung Task Center para i-review yung task record.';
            this.taskNext.textContent = 'Next: i-check yung Current Task note, tapos Refresh checkpoint.';
            this.taskUpdated.textContent = 'Recorded time unavailable';
            this.taskRecordPath = null;
            this.openTaskButton.disabled = true;
            this.agentStatuses = {};
            this.applyAgentStatuses();
        }
    }

    async openNote(path) {
        if (!this.app.vault.getAbstractFileByPath(path)) {
            new Notice('Hindi makita yung note. Check mo muna yung Agent Directory.');
            return;
        }
        try { await this.app.workspace.openLinkText(path, '', true); }
        catch (error) { new Notice('Hindi mabuksan yung note ngayon. Try ulit.'); }
    }

    async onClose() {
        this.taskClosed = true;
        this.taskReadVersion++;
        for (const ref of this.taskEvents || []) this.app.vault.offref(ref);
        this.taskEvents = [];
        this.contentEl.replaceChildren();
        this.agentButtons = [];
    }
}

module.exports = class AgentOfficePlugin extends Plugin {
    async onload() {
        this.registerView(VIEW_TYPE, leaf => new AgentOfficeView(leaf));
        this.addRibbonIcon('building-2', 'Open Agent Office', () => this.activateOffice());
        this.addCommand({ id: 'open-agent-office', name: 'Open Agent Office', callback: () => this.activateOffice() });
        this.registerObsidianProtocolHandler('vnz-office', () => this.activateOffice());
        const data = await this.loadData();
        this.app.workspace.onLayoutReady(() => {
            if (!data?.welcomed) {
                this.activateOffice().then(() => this.saveData({ ...data, welcomed: true })).catch(() => {
                    new Notice('Buksan ang Agent Office gamit ang command palette.');
                });
            }
        });
    }

    async activateOffice() {
        const workspace = this.app.workspace;
        let leaf = workspace.getLeavesOfType(VIEW_TYPE)[0];
        if (!leaf) {
            leaf = workspace.getLeaf('tab');
            await leaf.setViewState({ type: VIEW_TYPE, active: true });
        }
        await workspace.revealLeaf(leaf);
    }
};
