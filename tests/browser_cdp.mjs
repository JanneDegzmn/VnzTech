// Dependency-free local Chrome driver for isolated feature checks (Node >=22).
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';

export const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
export async function browser() {
    const profile = await mkdtemp(join(tmpdir(), 'vnz-identity-chrome-'));
    const chrome = process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
    const processHandle = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--no-sandbox', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let hasExited = false;
    const processExited = new Promise(resolveExit => processHandle.once('exit', () => {
        hasExited = true;
        resolveExit();
    }));
    let socket, port;
    try {
        for (let i = 0; i < 100; i++) {
            try { port = Number((await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]); break; } catch { await delay(100); }
        }
        if (!port) throw new Error('Chrome debugging port unavailable');
        const target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find(t => t.type === 'page');
        socket = new WebSocket(target.webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
        let id = 0;
        const pending = new Map();
        const errors = [];
        socket.addEventListener('message', event => {
            const result = JSON.parse(event.data);
            if (result.id && pending.has(result.id)) {
                const task = pending.get(result.id); pending.delete(result.id); clearTimeout(task.timer);
                result.error ? task.reject(new Error(JSON.stringify(result.error))) : task.resolve(result.result);
            } else if (result.method === 'Runtime.exceptionThrown') errors.push(result.params.exceptionDetails.text);
        });
        const call = (method, params = {}) => new Promise((resolve, reject) => {
            const key = ++id;
            const timer = setTimeout(() => { pending.delete(key); reject(new Error(`CDP timeout: ${method}`)); }, 60000);
            pending.set(key, { resolve, reject, timer });
            socket.send(JSON.stringify({ id: key, method, params }));
        });
        await call('Runtime.enable'); await call('Page.enable');
        return {
            call, errors,
            async evaluate(expression) {
                const result = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
                if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
                return result.result.value;
            },
            async navigate(url) {
                await call('Page.navigate', { url });
                for (let i = 0; i < 100; i++) {
                    if (await this.evaluate('document.readyState === "complete"')) return;
                    await delay(100);
                }
                throw new Error('Page did not finish loading');
            },
            async close() {
                socket.close();
                try {
                    const version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
                    const browserSocket = new WebSocket(version.webSocketDebuggerUrl);
                    await new Promise((resolveOpen, reject) => {
                        browserSocket.addEventListener('open', resolveOpen, { once: true });
                        browserSocket.addEventListener('error', reject, { once: true });
                    });
                    browserSocket.send(JSON.stringify({ id: 1, method: 'Browser.close' }));
                    await Promise.race([processExited, delay(5000)]);
                    browserSocket.close();
                } catch {
                    if (!hasExited) processHandle.kill();
                }
                if (!hasExited) {
                    processHandle.kill();
                    await Promise.race([processExited, delay(3000)]);
                }
                if (!hasExited) throw new Error('Chrome did not exit after browser close');
                const tempRoot = resolve(tmpdir()) + sep;
                if (!resolve(profile).startsWith(tempRoot) || !profile.includes('vnz-identity-chrome-')) throw new Error('Refusing unsafe temporary profile cleanup');
                await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
            }
        };
    } catch (error) {
        socket?.close(); processHandle.kill();
        throw error;
    }
}
