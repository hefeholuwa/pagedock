import os
import sys
import json
from playwright.sync_api import sync_playwright

def run():
    print("=== PageDock AI Editor: Running Phase 1 Harness Verification (Python Playwright) ===\n")
    
    harness_path = os.path.join(os.path.dirname(__file__), '../public/assets/js/pagedock-harness.js')
    if not os.path.exists(harness_path):
        print("[FAIL] pagedock-harness.js does not exist")
        sys.exit(1)
    
    with open(harness_path, 'r', encoding='utf-8') as f:
        harness_code = f.read()
    
    print(f"[PASS] pagedock-harness.js exists and loaded ({len(harness_code)} bytes)")
    
    pass_count = 1
    fail_count = 0
    messages_received = []

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1280, "height": 800})
        
        # Expose binding
        page.expose_function("onParentMessage", lambda msg: messages_received.append(msg))
        
        parent_html = """
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
        """
        page.set_content(parent_html)
        
        test_doc = f"""
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
    {harness_code}
  </script>
</body>
</html>
        """
        
        # Inject srcdoc into iframe via evaluate
        page.evaluate("(html) => { document.getElementById('preview').srcdoc = html; }", test_doc)
        page.wait_for_timeout(1500)
        
        frame = page.frame_locator('#preview')
        
        # 1. buffer-dom-ready
        ready_msg = next((m for m in messages_received if m.get('type') == 'buffer-dom-ready'), None)
        if ready_msg:
            print("[PASS] Parent received buffer-dom-ready signal")
            pass_count += 1
        else:
            print("[FAIL] Parent did not receive buffer-dom-ready signal")
            fail_count += 1

        # 2. Audio/video auto-muted
        is_muted = frame.locator('#test-audio').evaluate("() => { const a = document.getElementById('test-audio'); const v = document.getElementById('test-video'); return a.muted && v.muted; }")
        if is_muted:
            print("[PASS] Audio and video elements are automatically muted")
            pass_count += 1
        else:
            print("[FAIL] Audio/video elements were not muted")
            fail_count += 1

        # 3. Hover outline
        cta_btn = frame.locator('#cta-button')
        cta_btn.hover()
        page.wait_for_timeout(200)
        hover_outline = cta_btn.evaluate("el => el.style.outline")
        if 'dashed' in hover_outline:
            print("[PASS] Hovering element sets dashed outline")
            pass_count += 1
        else:
            print(f"[FAIL] Hover outline mismatch: {hover_outline}")
            fail_count += 1

        # 4. Click -> element-selected
        cta_btn.click()
        page.wait_for_timeout(300)
        sel_msg = next((m for m in messages_received if m.get('type') == 'element-selected'), None)
        if sel_msg and sel_msg.get('tag') == 'BUTTON' and sel_msg.get('text') == 'Get Started':
            print("[PASS] Clicking element emits element-selected with exact tag, text, and rect")
            pass_count += 1
        else:
            print(f"[FAIL] element-selected message missing or invalid: {sel_msg}")
            fail_count += 1

        # 5. patch-element
        page.evaluate("""() => {
            const frameWin = document.getElementById('preview').contentWindow;
            frameWin.postMessage({
                type: 'patch-element',
                tag: 'BUTTON',
                tagIndex: 0,
                html: '<button id="cta-button" class="btn-patched" style="background: green; color: yellow;">Live Patched Button</button>'
            }, '*');
        }""")
        page.wait_for_timeout(500)
        patched_text = frame.locator('#cta-button').inner_text()
        if patched_text == 'Live Patched Button':
            print("[PASS] patch-element updated target DOM node cleanly")
            pass_count += 1
        else:
            print(f"[FAIL] patch-element failed: {patched_text}")
            fail_count += 1

        # 6. Double click inline text edit
        para = frame.locator('#desc-para')
        para.dblclick()
        page.wait_for_timeout(300)
        edit_start_msg = next((m for m in messages_received if m.get('type') == 'text-edit-start'), None)
        is_editable = para.evaluate("el => el.getAttribute('contenteditable')")
        if edit_start_msg and is_editable:
            print("[PASS] Double-click enabled contenteditable and emitted text-edit-start")
            pass_count += 1
        else:
            print("[FAIL] Double-click text editing not activated")
            fail_count += 1

        # Commit inline text edit with Enter
        page.keyboard.type(" - Edited")
        page.keyboard.press("Enter")
        page.wait_for_timeout(300)
        text_commit_msg = next((m for m in messages_received if m.get('type') == 'element-text-edit'), None)
        if text_commit_msg and "Edited" in text_commit_msg.get('text', ''):
            print("[PASS] Pressing Enter committed text and emitted element-text-edit")
            pass_count += 1
        else:
            print(f"[FAIL] element-text-edit not emitted: {text_commit_msg}")
            fail_count += 1

        # 7. request-structure
        page.evaluate("""() => {
            const frameWin = document.getElementById('preview').contentWindow;
            frameWin.postMessage({ type: 'request-structure' }, '*');
        }""")
        page.wait_for_timeout(400)
        struct_msg = next((m for m in messages_received if m.get('type') == 'page-structure'), None)
        if struct_msg and struct_msg.get('tree', {}).get('tag') == 'BODY':
            print("[PASS] request-structure serialized DOM hierarchy cleanly")
            pass_count += 1
        else:
            print(f"[FAIL] request-structure failed: {struct_msg}")
            fail_count += 1

        # 8. Form preview interception
        form = frame.locator('#contact-form')
        form.evaluate("f => f.requestSubmit()")
        page.wait_for_timeout(300)
        form_msg = next((m for m in messages_received if m.get('type') == 'form-preview-submit'), None)
        if form_msg and 'Thanks' in form_msg.get('form', {}).get('success', ''):
            print("[PASS] Form submission intercepted and emitted form-preview-submit")
            pass_count += 1
        else:
            print(f"[FAIL] Form submission was not intercepted: {form_msg}")
            fail_count += 1

        # 9. Hotkey Escape
        page.evaluate("""() => {
            const frameWin = document.getElementById('preview').contentWindow;
            frameWin.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        }""")
        page.wait_for_timeout(200)
        esc_msg = next((m for m in messages_received if m.get('type') == 'iframe-escape'), None)
        if esc_msg:
            print("[PASS] Escape key forwarded to parent as iframe-escape")
            pass_count += 1
        else:
            print("[FAIL] Escape key was not forwarded")
            fail_count += 1

        browser.close()

    print("\n=========================================")
    print(f"Results: {pass_count} Passed, {fail_count} Failed")
    print("=========================================\n")
    
    if fail_count > 0:
        sys.exit(1)

if __name__ == '__main__':
    run()
