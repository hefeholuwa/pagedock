import json
import time
from playwright.sync_api import sync_playwright

def test_editor_1to1():
    print("Beginning 1:1 HTMLHost AI Editor verification...")
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={'width': 1440, 'height': 900},
            user_agent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        )
        page = context.new_page()

        page.on('console', lambda msg: print(f"[BROWSER LOG] {msg.type}: {msg.text}"))

        # Navigate to editor with claim token to enable full management
        url = "http://localhost:8080/editor/everyday-imports-storefront?token=914ac18d770840eb3539261833a62d60"
        print(f"Navigating to {url} ...")
        resp = page.goto(url, wait_until="domcontentloaded", timeout=20000)
        assert resp.status == 200, f"Expected 200, got {resp.status}"

        # Wait for CodeMirror and Preview iframe
        page.wait_for_timeout(2500)

        # 1. Verify Topbar
        print("Checking topbar controls...")
        assert page.locator('.hh-topbar').is_visible(), "Topbar must be visible"
        assert page.locator('#hh-project-title').is_visible(), "Project title must be visible"
        assert page.locator('#hh-editmode-toggle').is_visible(), "Edit mode toggle must be visible"
        assert page.locator('#hh-aiedit-toggle').is_visible(), "AI Edit toggle must be visible"
        assert page.locator('#hh-chat-btn').is_visible(), "Chat button must be visible"
        assert page.locator('#hh-quota-pill').is_visible(), "Quota pill must be visible"
        assert page.locator('#hh-savedraft-btn').is_visible(), "Save draft must be visible"
        assert page.locator('#hh-publish-btn').is_visible(), "Publish button must be visible"

        page.screenshot(path="/Users/user/.gemini/antigravity/brain/49eff7d9-7653-44f0-9e5e-cd408b3dfbb1/pagedock_editor_topbar_live.png")
        print("[PASS] Topbar verified.")

        # 2. Verify Left Panel Tabs
        print("Checking Left Panel Tabs...")
        # Code tab is active
        assert page.locator('.CodeMirror').is_visible(), "CodeMirror should be mounted"

        # Switch to Library tab
        page.locator('button[data-tab="library"]').click()
        page.wait_for_timeout(500)
        assert page.locator('#hh-lib-sections-container').is_visible(), "Sections catalog should be visible"
        sections_count = page.locator('#hh-lib-sections-container .hh-lib-item-card').count()
        print(f"Sections rendered: {sections_count}")
        assert sections_count > 0, "Sections must render"

        # Click Elements subtab
        page.locator('button[data-lib-view="elements"]').click()
        page.wait_for_timeout(500)
        elements_count = page.locator('#hh-lib-elements-container .hh-lib-item-card').count()
        print(f"Elements rendered: {elements_count}")
        assert elements_count > 0, "Elements must render"

        page.screenshot(path="/Users/user/.gemini/antigravity/brain/49eff7d9-7653-44f0-9e5e-cd408b3dfbb1/pagedock_editor_library_live.png")
        print("[PASS] Library panel verified.")

        # Switch to Structure tab
        page.locator('button[data-tab="structure"]').click()
        page.wait_for_timeout(500)
        assert page.locator('#hh-tree-container').is_visible(), "Structure tree container should be visible"

        # Switch back to Code tab
        page.locator('button[data-tab="code"]').click()
        page.wait_for_timeout(500)

        # 3. Verify Viewport Switcher
        print("Testing responsive viewport switcher...")
        page.locator('.hh-device-btn[data-device="tablet"]').click()
        page.wait_for_timeout(400)
        wrapper_width = page.locator('#hh-viewport-wrapper').evaluate("el => el.style.width")
        assert wrapper_width == "768px", f"Expected 768px, got {wrapper_width}"

        page.locator('.hh-device-btn[data-device="mobile"]').click()
        page.wait_for_timeout(400)
        wrapper_width = page.locator('#hh-viewport-wrapper').evaluate("el => el.style.width")
        assert wrapper_width == "375px", f"Expected 375px, got {wrapper_width}"

        page.locator('.hh-device-btn[data-device="desktop"]').click()
        page.wait_for_timeout(400)
        wrapper_width = page.locator('#hh-viewport-wrapper').evaluate("el => el.style.width")
        assert wrapper_width == "100%", f"Expected 100%, got {wrapper_width}"
        print("[PASS] Viewport switcher verified.")

        # 4. Verify Preview Iframe & Element Selection Popover
        print("Testing preview element interaction & floating popover...")
        iframe_locator = page.frame_locator('#preview-iframe')
        
        # Target a prominent visible element in the page (e.g. h1, h2, or hero content)
        target = iframe_locator.locator('h1, h2, h3, header p, .hero p, button').first
        target.wait_for(state="visible", timeout=8000)
        tag_name = target.evaluate('el => el.tagName')
        print(f"Clicking visible element inside preview iframe: {tag_name}")
        target.click()
        page.wait_for_timeout(1500)

        # Check if floating popover appeared!
        popover = page.locator('#hh-popover-anchor')
        assert popover.is_visible(), "Floating popover anchor must be visible after clicking element!"
        assert page.locator('#hh-ai-popover').is_visible(), "Floating AI Popover must be visible by default!"
        
        print("Floating AI Edit popover visible! Tag:", page.locator('#pop-ai-tag').inner_text())
        page.screenshot(path="/Users/user/.gemini/antigravity/brain/49eff7d9-7653-44f0-9e5e-cd408b3dfbb1/pagedock_editor_ai_popover_live.png")

        # Test mode toggle: switch to Direct Element Edit
        page.locator('#pop-mode-switch').click()
        page.locator('#hh-direct-popover').wait_for(state="visible", timeout=4000)
        assert page.locator('#hh-direct-popover').is_visible(), "Direct Element Edit Popover should be visible after mode switch!"
        print("Direct Element Edit popover visible! Tag:", page.locator('#pop-direct-tag').inner_text())
        page.screenshot(path="/Users/user/.gemini/antigravity/brain/49eff7d9-7653-44f0-9e5e-cd408b3dfbb1/pagedock_editor_direct_popover_live.png")

        # Switch back to AI Edit and click a chip
        page.locator('#pop-direct-mode-switch').click()
        page.locator('#hh-ai-popover').wait_for(state="visible", timeout=4000)
        assert page.locator('#hh-ai-popover').is_visible()

        first_chip = page.locator('#pop-ai-chips .hh-chip').first
        if first_chip.is_visible():
            chip_text = first_chip.inner_text()
            print(f"Clicking suggestion chip: '{chip_text}'...")
            first_chip.click()
            page.wait_for_timeout(2000)
            print("AI edit executed! Checking quota...")
            quota_label = page.locator('#hh-quota-label').inner_text()
            print(f"Current quota: {quota_label}")
            assert quota_label == "1/5", f"Expected 1/5 quota, got {quota_label}"

        # 5. Verify Right AI Chat Drawer
        print("Testing AI Chat Drawer...")
        page.locator('#hh-chat-btn').click()
        page.wait_for_timeout(500)
        assert page.locator('#hh-chat-drawer').is_visible(), "AI Chat drawer should be open"
        
        # Send a chat message
        page.locator('#hh-chat-input').fill("Make the headline dark modern gradient")
        page.locator('#hh-send-chat-btn').click()
        page.wait_for_timeout(1500)
        
        page.screenshot(path="/Users/user/.gemini/antigravity/brain/49eff7d9-7653-44f0-9e5e-cd408b3dfbb1/pagedock_editor_chat_drawer_live.png")
        print("[PASS] AI Chat drawer verified.")

        # Full page screenshot of the final 1:1 editor
        page.screenshot(path="/Users/user/.gemini/antigravity/brain/49eff7d9-7653-44f0-9e5e-cd408b3dfbb1/pagedock_editor_full_live.png")
        print("Full editor screenshot saved.")

        browser.close()
        print("ALL 1:1 HTMLHOST AI EDITOR VERIFICATION TESTS PASSED SUCCESSFULLY!")

if __name__ == '__main__':
    test_editor_1to1()
