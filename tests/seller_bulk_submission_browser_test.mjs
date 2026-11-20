import { createServer } from 'node:http';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const screenshotDir = process.env.VNZ_BULK_SCREENSHOTS;
if (screenshotDir) {
    assert.ok(resolve(screenshotDir).startsWith(root + sep + '.qa-bulk-'));
    await mkdir(screenshotDir, { recursive: true });
}

const phpQuote = value => "'" + value.replaceAll('\\', '/').replaceAll("'", "\\'") + "'";
const faceTemplateResult = spawnSync(
    process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
    [
        '-r',
        `session_save_path(sys_get_temp_dir()); function vnzSubmissionFaceToken($context){return str_repeat('a',64);} require ${phpQuote(join(root, 'includes/submission-face-capture.php'))}; vnzRenderSubmissionFaceCapture('account','en');`
    ],
    { encoding: 'utf8', windowsHide: true }
);
assert.equal(faceTemplateResult.status, 0, faceTemplateResult.stderr);
assert.ok(!/Warning:|Fatal error:/.test(faceTemplateResult.stdout));

const dashboard = await readFile(join(root, 'account/dashboard.php'), 'utf8');
const section = (startMarker, endMarker) => {
    const start = dashboard.indexOf(startMarker);
    const end = dashboard.indexOf(endMarker, start + startMarker.length);
    assert.ok(start >= 0 && end > start, `Could not extract seller form source: ${startMarker}`);
    return dashboard.slice(start, end);
};
const modeSource = section(
    '    function isSellerBulkMode() {',
    '    const issueList = ['
);
const addItemSource = section(
    '    function addSellerItem() {',
    '    /* =====================================================\n       SETUP ITEM'
);
const setupItemSource = section(
    '    function setupSellerItem(',
    '    /* =====================================================\n       ADD ITEM BUTTON'
);
const addButtonSource = section(
    '    /* =====================================================\n       ADD ITEM BUTTON',
    '    /* =====================================================\n       SELL BUTTONS'
);
const issueListSource = section('    const issueList = [', '    const damageLevels = [');
const damageLevelsSource = section('    const damageLevels = [', '    function openSellerSellModal()');
assert.ok(dashboard.includes("vnzRenderSubmissionFaceCapture('account', $sellerLanguage)"));

let server;
let client;
let checks = 0;

const html = theme => `<!doctype html>
<html lang="en" data-seller-theme="${theme}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_dashboard.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_theme.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/submission_face_capture.css">
</head>
<body class="seller-modal-open">
<div id="sellerSellModal" class="seller-sell-modal is-open">
<section class="seller-sell-modal-panel">
<header class="seller-sell-modal-header"><div class="seller-sell-modal-title">SELL TO VNZ TECH</div><button class="seller-sell-modal-close" type="button">×</button></header>
<div class="seller-sell-modal-body"><div class="seller-form-wrap">
<div class="seller-form-intro"><h2>Sell an Item</h2><p>Submit a phone, scrap board, or a bulk lot for our team to review.</p></div>
<form id="sellerSubmissionForm" enctype="multipart/form-data">
<input type="hidden" name="submission_source" value="account"><input type="hidden" id="sellerBulkFormat" name="bulk_format" value="0">
${faceTemplateResult.stdout}
<section class="seller-form-section">
<div class="seller-form-section-title">Seller Information</div>
<div class="seller-account-info"><div class="seller-account-info-card"><span>Full Name</span><strong>Synthetic Seller</strong></div><div class="seller-account-info-card"><span>Email</span><strong>seller@example.invalid</strong></div></div>
<div class="seller-form-group full" style="margin-top:15px">
<label>Selling Method</label><div class="seller-form-methods">
<div class="seller-form-method-option"><input type="radio" id="method_per_piece" name="selling_method" value="per_piece" checked><label for="method_per_piece" class="seller-form-method-label">Sell Per Piece</label></div>
<div class="seller-form-method-option"><input type="radio" id="method_wholesale" name="selling_method" value="wholesale"><label for="method_wholesale" class="seller-form-method-label">Bulk</label></div>
</div><small class="seller-selling-method-help" data-selling-method-help>Enter each phone or scrap board with its details, photos, and asking price.</small>
</div></section>
<section class="seller-form-section"><div class="seller-form-section-title">Item Details</div><div id="sellerItemsContainer"></div><button type="button" class="seller-add-item" id="sellerAddItemBtn">+ Add Another Item</button></section>
<div class="seller-form-disclaimer">Synthetic preview only.</div><button class="seller-form-submit" type="button">SUBMIT ITEM FOR REVIEW</button>
</form></div></div></section></div>
<script>
const sellerSubmissionForm=document.getElementById('sellerSubmissionForm');
const sellerBulkFormatInput=document.getElementById('sellerBulkFormat');
const sellerSellingMethodHelp=sellerSubmissionForm.querySelector('[data-selling-method-help]');
const sellerItemsContainer=document.getElementById('sellerItemsContainer');
const sellerAddItemBtn=document.getElementById('sellerAddItemBtn');
let sellerItemIndex=0;
${issueListSource}
${damageLevelsSource}
${modeSource}
${addItemSource}
${setupItemSource}
${addButtonSource}
addSellerItem();
</script>
</body></html>`;

async function wait(expression) {
    for (let i = 0; i < 100; i++) {
        if (await client.evaluate(expression)) return;
        await delay(50);
    }
    throw new Error('Browser condition timed out: ' + expression);
}

const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => {
    const channel = value / 255;
    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
}).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
const contrast = (background, foreground) => (
    (Math.max(luminance(background), luminance(foreground)) + 0.05)
    / (Math.min(luminance(background), luminance(foreground)) + 0.05)
);

try {
    server = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html(url.searchParams.get('theme') === 'dark' ? 'dark' : 'light'));
            return;
        }
        const relative = url.pathname.replace(/^\/VNZ-TECH\//, '');
        if (!/^assets\/css\/[a-z0-9_-]+\.css$/.test(relative)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', 'text/css');
        res.end(await readFile(join(root, relative)));
    });
    await new Promise(resolveListen => server.listen(0, '127.0.0.1', resolveListen));
    client = await browser();

    for (const theme of ['light', 'dark']) {
        await client.call('Emulation.setDeviceMetricsOverride', {
            width: 1200,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false
        });
        await client.navigate(`http://127.0.0.1:${server.address().port}/?theme=${theme}`);
        await wait("document.querySelector('.seller-item-card')");

        const palette = await client.evaluate(`(()=> {
            const pick = selector => getComputedStyle(document.querySelector(selector));
            const rect = document.querySelector('.seller-sell-modal-panel').getBoundingClientRect();
            return {
                panel: pick('.seller-sell-modal-panel').backgroundColor,
                title: pick('.seller-sell-modal-title').color,
                label: pick('.seller-bulk-fields label').color,
                inputBg: pick('[data-bulk-quantity]').backgroundColor,
                inputText: pick('[data-bulk-quantity]').color,
                optionBg: getComputedStyle(document.querySelector('[data-bulk-item-type] option[value="Whole Phone"]')).backgroundColor,
                optionText: getComputedStyle(document.querySelector('[data-bulk-item-type] option[value="Whole Phone"]')).color,
                selectScheme: pick('[data-bulk-item-type]').colorScheme,
                buttonBg: pick('.seller-form-submit').backgroundColor,
                buttonText: pick('.seller-form-submit').color,
                titleBg: pick('.seller-sell-modal-header').backgroundColor,
                bounds: rect.left >= 0 && rect.right <= innerWidth + 1 && rect.top >= 0 && rect.bottom <= innerHeight + 1,
                scrollX: document.documentElement.scrollWidth > innerWidth + 1,
                focusRing: pick('[data-bulk-quantity]').outlineStyle
            };
        })()`);
        assert.ok(palette.bounds && !palette.scrollX, JSON.stringify(palette));
        assert.ok(contrast(palette.panel, palette.title) >= 4.5, JSON.stringify(palette));
        assert.ok(contrast(palette.inputBg, palette.inputText) >= 4.5, JSON.stringify(palette));
        assert.ok(contrast(palette.optionBg, palette.optionText) >= 4.5, JSON.stringify(palette));
        assert.ok(contrast(palette.buttonBg, palette.buttonText) >= 4.5, JSON.stringify(palette));
        assert.equal(palette.selectScheme, theme);
        assert.equal(theme === 'light' ? luminance(palette.panel) > 0.8 : luminance(palette.panel) < 0.05, true);

        const initial = await client.evaluate(`(()=> {
            const form = document.querySelector('#sellerSubmissionForm');
            const card = document.querySelector('.seller-item-card');
            return {
                bulk: form.querySelector('[name=bulk_format]').value,
                itemTitle: card.querySelector('[data-item-heading]').textContent.trim(),
                bulkHidden: card.querySelector('[data-bulk-fields]').style.display === 'none',
                perPieceRequired: card.querySelector('[data-issue-select]').required,
                photoRequired: card.querySelector('[data-photo-input]').required,
                faceCard: Boolean(form.querySelector('[data-face-capture]'))
            };
        })()`);
        assert.deepEqual(initial, {
            bulk: '0',
            itemTitle: 'Item 1',
            bulkHidden: true,
            perPieceRequired: true,
            photoRequired: true,
            faceCard: true
        });

        await client.evaluate("document.querySelector('label[for=method_wholesale]').click()");
        await wait("document.querySelector('[name=bulk_format]').value==='1'");
        await client.evaluate("document.querySelector('[data-bulk-quantity]').focus(); document.activeElement.blur()");
        for (let tab = 0; tab < 24; tab++) {
            await client.call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            await client.call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            if (await client.evaluate("document.activeElement===document.querySelector('[data-bulk-quantity]')")) break;
        }
        const focusState = await client.evaluate(`(()=> {
            const input=document.querySelector('[data-bulk-quantity]');
            return {
                bulkSelected:document.querySelector('#method_wholesale').checked,
                pieceSelected:document.querySelector('#method_per_piece').checked,
                ring:getComputedStyle(input).boxShadow,
                photoRequired:document.querySelector('[data-photo-input]').required
            };
        })()`);
        assert.equal(focusState.bulkSelected, true);
        assert.equal(focusState.pieceSelected, false);
        assert.notEqual(focusState.ring, 'none');
        assert.equal(focusState.photoRequired, true);
        await client.evaluate(`(()=> {
            const card=document.querySelector('.seller-item-card');
            card.querySelector('[data-bulk-item-type]').value='Whole Phone';
            card.querySelector('[data-bulk-item-type]').dispatchEvent(new Event('change',{bubbles:true}));
            card.querySelector('[data-bulk-quantity]').value='12';
            card.querySelector('[data-bulk-quantity-type]').value='1';
            card.querySelector('[name="items[0][seller_asking_price]"]').value='8500';
            card.querySelector('[name="items[0][additional_information]"]').value='Mixed phone lot';
        })()`);
        await client.evaluate("document.querySelector('#sellerAddItemBtn').click()");
        await wait("document.querySelectorAll('.seller-item-card').length===2");
        await client.evaluate(`(()=> {
            const card=document.querySelectorAll('.seller-item-card')[1];
            card.querySelector('[data-bulk-item-type]').value='Scrap Board';
            card.querySelector('[data-bulk-item-type]').dispatchEvent(new Event('change',{bubbles:true}));
            card.querySelector('[data-bulk-quantity]').value='7';
            card.querySelector('[data-bulk-quantity-type]').value='0';
            card.querySelector('[name="items[1][seller_asking_price]"]').value='1800';
            card.querySelector('[name="items[1][additional_information]"]').value='Scrap board lot';
        })()`);
        const bulkPayload = await client.evaluate(`(()=> {
            const form=document.querySelector('#sellerSubmissionForm');
            const cards=[...document.querySelectorAll('.seller-item-card')];
            const data=new FormData(form);
            return {
                bulk:data.get('bulk_format'),
                method:data.get('selling_method'),
                types:cards.map(card=>card.querySelector('[data-item-type-input]').value),
                quantities:cards.map(card=>card.querySelector('[data-bulk-quantity]').value),
                certainty:cards.map(card=>card.querySelector('[data-bulk-quantity-type]').value),
                prices:cards.map(card=>card.querySelector('[name$="[seller_asking_price]"]').value),
                notes:cards.map(card=>card.querySelector('[name$="[additional_information]"]').value),
                specs:[...data.keys()].some(key=>/\\[(brand|model|storage|issue|issue_details|damage_level|board_code)\\]/.test(key)),
                bulkFields:cards.every(card=>card.querySelector('[data-bulk-fields]').style.display!=='none'),
                methodHelp:document.querySelector('[data-selling-method-help]').textContent,
                addLabel:document.querySelector('#sellerAddItemBtn').textContent.trim()
            };
        })()`);
        assert.equal(bulkPayload.bulk, '1');
        assert.equal(bulkPayload.method, 'wholesale');
        assert.deepEqual(bulkPayload.types, ['Whole Phone', 'Scrap Board']);
        assert.deepEqual(bulkPayload.quantities, ['12', '7']);
        assert.deepEqual(bulkPayload.certainty, ['1', '0']);
        assert.deepEqual(bulkPayload.prices, ['8500', '1800']);
        assert.deepEqual(bulkPayload.notes, ['Mixed phone lot', 'Scrap board lot']);
        assert.equal(bulkPayload.specs, false);
        assert.equal(bulkPayload.bulkFields, true);
        assert.ok(bulkPayload.methodHelp.includes('total price per lot'));
        assert.equal(bulkPayload.addLabel, '+ Add Another Bulk Lot');
        assert.equal(await client.evaluate("document.querySelector('.seller-item-card [data-price-label]').textContent.trim()"), 'Total Asking Price for This Bulk Lot');
        checks += 3;

        await client.evaluate("document.querySelector('label[for=method_per_piece]').click()");
        await wait("document.querySelector('[name=bulk_format]').value==='0'");
        const switchedBack = await client.evaluate(`(()=> {
            const cards=[...document.querySelectorAll('.seller-item-card')];
            return {
                bulkDisabled:cards.every(card=>card.querySelector('[data-bulk-quantity]').disabled),
                phoneRequired:cards.every(card=>card.querySelector('[data-issue-select]').required),
                categoryRetained:cards.map(card=>card.querySelector('[data-item-type-input]').value),
                priceLabel:cards[0].querySelector('[data-price-label]').textContent.trim()
            };
        })()`);
        assert.deepEqual(switchedBack, {
            bulkDisabled: true,
            phoneRequired: true,
            categoryRetained: ['Whole Phone', 'Whole Phone'],
            priceLabel: 'Seller Asking Price'
        });
        checks++;

        if (screenshotDir) {
            await client.evaluate("document.querySelector('label[for=method_wholesale]').click()");
            await wait("document.querySelector('[name=bulk_format]').value==='1'");
            const screenshot = await client.call('Page.captureScreenshot', { format: 'png' });
            await writeFile(join(screenshotDir, `seller-bulk-${theme}-desktop.png`), Buffer.from(screenshot.data, 'base64'));
        }
    }

    for (const theme of ['light', 'dark']) {
        await client.call('Emulation.setDeviceMetricsOverride', {
            width: 375,
            height: 812,
            deviceScaleFactor: 1,
            mobile: true
        });
        await client.navigate(`http://127.0.0.1:${server.address().port}/?theme=${theme}`);
        await wait("document.querySelector('.seller-item-card')");
        await client.evaluate("document.querySelector('label[for=method_wholesale]').click()");
        await wait("document.querySelector('[name=bulk_format]').value==='1'");
        await delay(350);
        const mobile = await client.evaluate(`(()=> {
            const panel=document.querySelector('.seller-sell-modal-panel').getBoundingClientRect();
            return {
                viewport:innerWidth,
                scrollWidth:document.documentElement.scrollWidth,
                panelWidth:panel.width,
                panelHeight:panel.height,
                controls:[...document.querySelectorAll('.seller-sell-modal .seller-form-method-label,.seller-sell-modal .seller-form-group input:not([type=file]),.seller-sell-modal .seller-form-group select,.seller-sell-modal .seller-add-item,.seller-sell-modal-close')].filter(el=>el.getClientRects().length).map(el=>Math.min(el.getBoundingClientRect().width,el.getBoundingClientRect().height)),
                fieldsVisible:getComputedStyle(document.querySelector('[data-bulk-fields]')).display!=='none'
            };
        })()`);
        assert.equal(mobile.scrollWidth <= mobile.viewport + 1, true, JSON.stringify(mobile));
        assert.equal(mobile.panelWidth, mobile.viewport);
        assert.equal(mobile.fieldsVisible, true);
        assert.ok(mobile.controls.every(size=>size >= 44), JSON.stringify(mobile.controls));
        checks++;
        if (screenshotDir) {
            const screenshot = await client.call('Page.captureScreenshot', { format: 'png' });
            await writeFile(join(screenshotDir, `seller-bulk-${theme}-mobile.png`), Buffer.from(screenshot.data, 'base64'));
        }
    }

    console.log(`PASS ${checks} seller bulk form/theme checks; actual seller modal styles, rendered selfie-card markup, item template and mode functions used. Synthetic seller data only; no submit or live DB writes.`);
} finally {
    if (client) {
        try {
            await client.close();
        } catch (error) {
            console.error('Browser cleanup warning:', error.message);
        }
    }
    if (server) {
        server.closeAllConnections();
        await new Promise(resolveClose => server.close(resolveClose));
    }
}
