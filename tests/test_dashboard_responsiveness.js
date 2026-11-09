/**
 * PageDock Dashboard Mobile Responsiveness Test Suite
 * Tests viewports: 320px, 360px, 375px, 390px, and 768px
 * Verifies zero horizontal scroll and proper containment of dashboard elements.
 */

const { spawn } = require('child_process');
const fs = require('fs');
const assert = require('assert');

async function wait(ms) {
    return new Promise(r => setTimeout(r, ms));
}

async function run() {
    console.log('=== Running Dashboard Mobile Responsiveness Test Suite ===\n');

    const chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    const tmpDir = '/tmp/chrome-test-dash-resp-' + Date.now();
    fs.mkdirSync(tmpDir, { recursive: true });

    const chrome = spawn(chromePath, [
        '--headless=new',
        '--remote-debugging-port=9555',
        '--user-data-dir=' + tmpDir,
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-extensions',
        '--window-size=360,780'
    ]);

    let wsUrl = null;
    for (let i = 0; i < 30; i++) {
        await wait(200);
        try {
            const res = await fetch('http://127.0.0.1:9555/json/version');
            if (res.ok) {
                const data = await res.json();
                wsUrl = data.webSocketDebuggerUrl;
                break;
            }
        } catch (e) {}
    }

    if (!wsUrl) {
        console.error('Failed to connect to headless Chrome');
        chrome.kill();
        process.exit(1);
    }

    const ws = new WebSocket(wsUrl);
    let id = 1;
    const callbacks = new Map();
    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && callbacks.has(msg.id)) {
            const cb = callbacks.get(msg.id);
            callbacks.delete(msg.id);
            cb(msg);
        }
    };
    await new Promise((resolve) => ws.onopen = resolve);

    function send(method, params = {}) {
        return new Promise((resolve) => {
            const msgId = id++;
            callbacks.set(msgId, (res) => resolve(res.result || res.error));
            ws.send(JSON.stringify({ id: msgId, method, params }));
        });
    }

    const target = await send('Target.createTarget', { url: 'about:blank' });
    const pageWsUrl = `ws://127.0.0.1:9555/devtools/page/${target.targetId}`;
    const pageWs = new WebSocket(pageWsUrl);
    await new Promise((resolve) => pageWs.onopen = resolve);

    let pageId = 1;
    const pageCallbacks = new Map();
    pageWs.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && pageCallbacks.has(msg.id)) {
            const cb = pageCallbacks.get(msg.id);
            pageCallbacks.delete(msg.id);
            cb(msg);
        }
    };

    function pageSend(method, params = {}) {
        return new Promise((resolve) => {
            const msgId = pageId++;
            pageCallbacks.set(msgId, (res) => resolve(res.result || res.error));
            pageWs.send(JSON.stringify({ id: msgId, method, params }));
        });
    }

    await pageSend('Page.enable');
    await pageSend('Network.enable');

    await pageSend('Network.setCookie', {
        name: 'PHPSESSID',
        value: 'testsession1234567890',
        domain: 'localhost',
        path: '/'
    });

    const testCases = [
        { name: 'iPhone SE (Ultra-compact)', width: 320, height: 568 },
        { name: 'Standard Android (Target Issue)', width: 360, height: 740 },
        { name: 'iPhone 12/13 Mini', width: 375, height: 812 },
        { name: 'Modern iPhone 14/15/16', width: 390, height: 844 },
        { name: 'Tablet / Medium Viewport', width: 768, height: 1024 }
    ];

    let passed = 0;
    let failed = 0;

    for (const tc of testCases) {
        await pageSend('Emulation.setDeviceMetricsOverride', {
            width: tc.width,
            height: tc.height,
            deviceScaleFactor: 2,
            mobile: tc.width < 768
        });

        await pageSend('Page.navigate', { url: 'http://localhost:8080/dashboard' });
        await wait(1000);

        const evalRes = await pageSend('Runtime.evaluate', {
            expression: `(() => {
                const winWidth = window.innerWidth;
                const docScrollWidth = document.documentElement.scrollWidth;
                const bodyScrollWidth = document.body.scrollWidth;
                const card = document.querySelector('.site-card');
                const shortcutBadge = document.querySelector('.search-shortcut-badge');
                const badgeStyle = shortcutBadge ? window.getComputedStyle(shortcutBadge) : null;
                const cardActionsView = document.querySelector('.btn-action-view');
                const cardActionsSecondary = document.querySelector('.card-actions-row.secondary-row');

                return {
                    winWidth,
                    docScrollWidth,
                    bodyScrollWidth,
                    hasHorizontalOverflow: bodyScrollWidth > winWidth,
                    cardWidth: card ? card.offsetWidth : null,
                    cardRight: card ? card.getBoundingClientRect().right : null,
                    shortcutBadgeDisplay: badgeStyle ? badgeStyle.display : null,
                    hasPrimaryViewBtn: !!cardActionsView,
                    hasSecondaryRow: !!cardActionsSecondary
                };
            })()`,
            returnByValue: true
        });

        const data = evalRes.result ? evalRes.result.value : evalRes;
        
        try {
            assert.strictEqual(data.hasHorizontalOverflow, false, `Viewport ${tc.name} must not have horizontal overflow (bodyScrollWidth: ${data.bodyScrollWidth}, winWidth: ${data.winWidth})`);
            assert(data.cardRight <= tc.width + 1, `Card right (${data.cardRight}) must be <= viewport width (${tc.width})`);
            if (tc.width < 768) {
                assert.strictEqual(data.shortcutBadgeDisplay, 'none', `Shortcut badge must be hidden on mobile`);
            }
            assert.strictEqual(data.hasPrimaryViewBtn, true, `Dedicated View Live Site primary button must exist`);
            assert.strictEqual(data.hasSecondaryRow, true, `Secondary row must exist`);
            
            console.log(`PASS: ${tc.name} (${tc.width}px): Zero overflow, card contained (${data.cardWidth}px), shortcut badge hidden`);
            passed++;
        } catch (err) {
            console.error(`FAIL: ${tc.name} (${tc.width}px): ${err.message}`);
            failed++;
        }
    }

    pageWs.close();
    ws.close();
    chrome.kill();

    console.log(`\n=== Dashboard Responsiveness Summary: ${passed} Passed, ${failed} Failed ===`);
    if (failed > 0) process.exit(1);
}

run().catch((err) => {
    console.error(err);
    process.exit(1);
});
