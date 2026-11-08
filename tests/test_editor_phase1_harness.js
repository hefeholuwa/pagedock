/**
 * Test Suite: PageDock AI HTML Editor — Phase 1 (Preview Harness & Message Protocol)
 * 
 * Verifies:
 * 1. Audio and video elements muted automatically on parse
 * 2. buffer-dom-ready postMessage signal
 * 3. Element hover outline and selection highlight
 * 4. element-selected postMessage schema (rect, tag, index, text, ancestors, computedStyles)
 * 5. In-place text editing on double click
 * 6. patch-element direct HTML replacement
 * 7. request-structure DOM serialization
 * 8. Form preview interception (/__pd/form)
 * 9. Hotkey and escape event forwarding
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

let passCount = 0;
let failCount = 0;

function assert(desc, condition, details = '') {
    if (condition) {
        console.log(`[PASS] ${desc}`);
        passCount++;
    } else {
        console.error(`[FAIL] ${desc} ${details ? '(' + details + ')' : ''}`);
        failCount++;
    }
}

async function run() {
    console.log('=== PageDock AI Editor: Running Phase 1 Harness Verification ===\n');

    const harnessPath = path.join(__dirname, '../public/assets/js/pagedock-harness.js');
    assert('pagedock-harness.js exists on disk', fs.existsSync(harnessPath));

    const harnessCode = fs.readFileSync(harnessPath, 'utf8');
    assert('pagedock-harness.js has non-zero length', harnessCode.length > 1000);

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });

    const messagesReceived = [];

    // Expose binding to catch postMessage in parent
    await page.exposeFunction('onParentMessage', (msg) => {
        messagesReceived.push(msg);
    });

    // Parent page hosting the preview iframe
    const parentHtml = `
<!DOCTYPE html>
<html>
<head><title>Parent Test Host</title></head>
<body>
  <iframe id="preview" style="width: 800px; height: 600px; border: 1px solid #ccc;"></iframe>
  <script>
    window.addEventListener('message', function(e) {
      if (e.data && e.data.type) {
        window.onParentMessage(e.data);
      }
    });
  </script>
</body>
</html>
    `;

    await page.setContent(parentHtml);

    // Test iframe document with media, elements, and forms
    const testDoc = `
<!DOCTYPE html>
<html>
<head>
  <title>Harness Test Page</title>
</head>
<body>
  <audio id="test-audio" src="data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA="></audio>
  <video id="test-video" src="data:video/mp4;base64,AAAA"></video>

  <header id="main-header">
    <h1 id="page-title">PageDock Heading</h1>
  </header>

  <main id="main-content">
    <p id="desc-para">This is editable text content.</p>
    <button id="cta-button" class="btn-primary" style="padding: 10px 20px; background: blue; color: white;">Get Started</button>

    <form id="contact-form" action="/__pd/form" method="POST">
      <input type="hidden" name="_form" value="Inquiry">
      <input type="hidden" name="_success" value="Thanks for reaching out">
      <input type="text" name="name" value="Test User">
      <button type="submit" id="submit-btn">Send Inquiry</button>
    </form>
  </main>

  <script data-pagedock-ui>
    ${harnessCode}
  </script>
</body>
</html>
    `;

    // Write content into iframe
    const iframeEl = await page.$('#preview');
    const frame = await iframeEl.contentFrame();
    await frame.setContent(testDoc);

    await page.waitForTimeout(1000);

    // 1. Verify buffer-dom-ready message received
    const readyMsg = messagesReceived.find(m => m.type === 'buffer-dom-ready');
    assert('Parent received buffer-dom-ready message', !!readyMsg);

    // 2. Verify media auto-muted
    const isMuted = await frame.evaluate(() => {
        const a = document.getElementById('test-audio');
        const v = document.getElementById('test-video');
        return a.muted && v.muted;
    });
    assert('Audio and video elements are automatically muted', isMuted);

    // 3. Verify Hover Outline
    const ctaBtn = frame.locator('#cta-button');
    await ctaBtn.hover();
    await page.waitForTimeout(200);

    const hoverOutline = await frame.evaluate(() => {
        const btn = document.getElementById('cta-button');
        return btn.style.outline;
    });
    assert('Hovering element sets dashed cyan outline', hoverOutline.includes('dashed'));

    // 4. Click Element -> Verify element-selected postMessage
    await ctaBtn.click();
    await page.waitForTimeout(300);

    const selMsg = messagesReceived.find(m => m.type === 'element-selected');
    assert('Parent received element-selected message', !!selMsg);
    if (selMsg) {
        assert('Selected element tag is BUTTON', selMsg.tag === 'BUTTON');
        assert('Selected element text matches "Get Started"', selMsg.text === 'Get Started');
        assert('Selected element rect contains width & height', selMsg.rect && selMsg.rect.width > 0 && selMsg.rect.height > 0);
        assert('Selected element has ancestor breadcrumb chain', Array.isArray(selMsg.ancestors) && selMsg.ancestors.length >= 2);
        assert('Selected element computedStyles extracted', selMsg.computedStyles && selMsg.computedStyles.display === 'inline-block' || selMsg.computedStyles.color);
    }

    // 5. Test patch-element: Replace BUTTON HTML
    await page.evaluate(() => {
        const frameWin = document.getElementById('preview').contentWindow;
        frameWin.postMessage({
            type: 'patch-element',
            tag: 'BUTTON',
            tagIndex: 0,
            html: '<button id="cta-button" class="btn-patched" style="background: green; color: yellow;">Live Patched Button</button>'
        }, '*');
    });

    await page.waitForTimeout(500);

    const patchedText = await frame.evaluate(() => {
        const btn = document.getElementById('cta-button');
        return btn ? btn.textContent.trim() : '';
    });
    assert('patch-element updated button content in real-time', patchedText === 'Live Patched Button');

    // 6. Test In-place Text Editing on Double Click
    const para = frame.locator('#desc-para');
    await para.dblclick();
    await page.waitForTimeout(300);

    const textEditStartMsg = messagesReceived.find(m => m.type === 'text-edit-start');
    assert('Double clicking text-only element emits text-edit-start', !!textEditStartMsg);

    const isContentEditable = await frame.evaluate(() => {
        const p = document.getElementById('desc-para');
        return p.getAttribute('contenteditable');
    });
    assert('Double clicked paragraph is contenteditable', !!isContentEditable);

    // Commit text change by pressing Enter
    await page.keyboard.type(' More Text');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);

    const textEditCommitMsg = messagesReceived.find(m => m.type === 'element-text-edit');
    assert('Pressing Enter in inline text edit emits element-text-edit', !!textEditCommitMsg);
    if (textEditCommitMsg) {
        assert('element-text-edit contains updated text', textEditCommitMsg.text.includes('More Text'));
    }

    // 7. Test request-structure: DOM tree serialization
    await page.evaluate(() => {
        const frameWin = document.getElementById('preview').contentWindow;
        frameWin.postMessage({ type: 'request-structure' }, '*');
    });

    await page.waitForTimeout(400);

    const structMsg = messagesReceived.find(m => m.type === 'page-structure');
    assert('Parent received page-structure tree', !!structMsg && !!structMsg.tree);
    if (structMsg && structMsg.tree) {
        assert('Structure tree has tag BODY and child nodes', structMsg.tree.tag === 'BODY' && structMsg.tree.children.length > 0);
    }

    // 8. Test Form Preview Interception
    const submitBtn = frame.locator('#submit-btn');
    await submitBtn.click();
    await page.waitForTimeout(300);

    const formMsg = messagesReceived.find(m => m.type === 'form-preview-submit');
    assert('Preview form submission intercepted without page reload', !!formMsg);
    if (formMsg) {
        assert('Intercepted form has success message', formMsg.form.success.includes('Thanks'));
    }

    // 9. Test Hotkey Forwarding (Escape)
    await page.evaluate(() => {
        const frameWin = document.getElementById('preview').contentWindow;
        frameWin.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    });
    await page.waitForTimeout(200);

    const escMsg = messagesReceived.find(m => m.type === 'iframe-escape');
    assert('Pressing Escape inside iframe forwards iframe-escape to parent', !!escMsg);

    await browser.close();

    console.log('\n=========================================');
    console.log(`Results: ${passCount} Passed, ${failCount} Failed`);
    console.log('=========================================\n');

    if (failCount > 0) {
        process.exit(1);
    }
}

run().catch((err) => {
    console.error('Test execution error:', err);
    process.exit(1);
});

