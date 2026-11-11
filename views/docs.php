<div class="docs-container">
    <div class="docs-header" style="margin-bottom: 36px;">
        <div class="badge" style="margin-bottom: 14px;">
            <span>PLATFORM GUIDE &amp; DEVELOPER SPECS &middot; v2.0</span>
        </div>
        <h1 style="font-size: clamp(28px, 4vw, 38px); font-weight: 800; letter-spacing: -0.03em; margin-bottom: 12px; color: var(--fg);">
            PageDock Documentation
        </h1>
        <p class="text-muted" style="font-size: 17px; line-height: 1.6; max-width: 700px;">
            Everything you need to know about publishing pages, editing in the Studio, collecting form inquiries, and using our CLI or AI tools.
        </p>
    </div>

    <!-- Quick Navigation Index -->
    <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: 20px; padding: 20px 24px; margin-bottom: 40px; display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px;">
        <a href="#cli-deploy" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 700;">1. Terminal &amp; cURL Uploads &rarr;</a>
        <a href="#forms" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 700;">2. Built-in Contact Forms &rarr;</a>
        <a href="#studio" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 700;">3. In-Browser Studio &rarr;</a>
        <a href="#routing" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 700;">4. Clean URLs &amp; Redirects &rarr;</a>
        <a href="#mcp-agents" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 700;">5. AI Agents &amp; MCP Tools &rarr;</a>
    </div>

    <!-- 1. CLI & cURL Ingestion -->
    <div class="docs-section" id="cli-deploy" style="margin-bottom: 48px;">
        <h2 style="font-size: 22px; font-weight: 700; color: var(--fg); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            1. Terminal &amp; cURL Uploads
        </h2>
        <p style="color: var(--fg-muted); line-height: 1.6; margin-bottom: 16px;">
            Deploy instantly from your terminal without installing extra tools using standard cURL, or use the official <code>pagedock-cli</code>.
        </p>

        <h3 style="font-size: 16px; font-weight: 700; color: var(--fg); margin: 20px 0 8px 0;">Official CLI (Node.js / NPX)</h3>
        <pre class="code-block"><code># Deploy a build directory (e.g. Vite, Astro, Next export)
npx pagedock-cli deploy ./dist

# Deploy with a custom link name
npx pagedock-cli deploy ./dist --slug my-portfolio

# Pull down raw HTML from any site
npx pagedock-cli pull my-portfolio --out index.html

# Roll back to a previous version
npx pagedock-cli rollback my-portfolio 1</code></pre>
        <p style="font-size: 13.5px; color: var(--fg-muted); margin-top: 8px;">
            <em>Tip: The CLI automatically stores your project slug and claim token in a local <code>.pagedock</code> file, so future <code>pagedock deploy</code> runs update the same link automatically.</em>
        </p>

        <h3 style="font-size: 16px; font-weight: 700; color: var(--fg); margin: 24px 0 8px 0;">One-Liner cURL Uploads (Zero Install)</h3>
        <pre class="code-block"><code># 1. Deploy a single HTML file
curl -F "file=@index.html" <?= url('api/deploy') ?>

# 2. Deploy a complete multi-file ZIP folder with custom slug
curl -F "file=@website.zip" -F "slug=my-landing" <?= url('api/deploy') ?>

# 3. Stream raw HTML via pipe (stdin)
cat index.html | curl --data-binary @- <?= url('api/deploy') ?>

# 4. Update an existing site using your claim token
curl -F "file=@dist.zip" -H "X-Claim-Token: YOUR_CLAIM_TOKEN" <?= url('api/deploy') ?>?slug=my-landing</code></pre>
    </div>

    <!-- 2. Zero-Backend Form Engine -->
    <div class="docs-section" id="forms" style="margin-bottom: 48px;">
        <h2 style="font-size: 22px; font-weight: 700; color: var(--fg); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            2. Built-in Contact Forms &amp; Leads Inbox
        </h2>
        <p style="color: var(--fg-muted); line-height: 1.6; margin-bottom: 16px;">
            Point any HTML form's <code>action</code> attribute to <code>/__pd/form</code> (or <code>/f/{slug}</code>). Messages are captured, checked for spam, saved to your inbox, and sent to any connected alerts.
        </p>

        <pre class="code-block"><code>&lt;!-- Standard Static HTML Form --&gt;
&lt;form action="/__pd/form" method="POST"&gt;
    &lt;!-- Optional form label (contact, waitlist, order) --&gt;
    &lt;input type="hidden" name="_form" value="contact" /&gt;

    &lt;!-- Optional thank-you page redirect --&gt;
    &lt;input type="hidden" name="_redirect" value="/thank-you.html" /&gt;

    &lt;!-- Form fields --&gt;
    &lt;input type="text" name="name" placeholder="Your Name" required /&gt;
    &lt;input type="email" name="email" placeholder="Your Email" required /&gt;
    &lt;textarea name="message" placeholder="Your Message" required&gt;&lt;/textarea&gt;

    &lt;button type="submit"&gt;Send Message&lt;/button&gt;
&lt;/form&gt;</code></pre>

        <h3 style="font-size: 16px; font-weight: 700; color: var(--fg); margin: 24px 0 8px 0;">Spam Protection &amp; Exports</h3>
        <ul class="docs-list" style="color: var(--fg-muted); line-height: 1.8;">
            <li><strong style="color: var(--fg);">Honeypot Anti-Spam:</strong> Include <code>&lt;input type="text" name="_pd_hp" style="display:none"&gt;</code> or <code>_gotcha</code>. Automated bots filling this hidden field are filtered into your spam tab.</li>
            <li><strong style="color: var(--fg);">Time Gate Check:</strong> Automatic timing checks catch automated scripts submitting faster than a human visitor.</li>
            <li><strong style="color: var(--fg);">Webhooks:</strong> Connect Discord, Slack, or custom JSON webhooks for instant notifications.</li>
            <li><strong style="color: var(--fg);">Live Google Sheets Sync:</strong> Pull leads directly into Google Sheets with:<br>
                <code style="color: var(--accent); font-size: 12.5px;">=IMPORTDATA("<?= url('api/sites/YOUR_SLUG/forms/export?key=YOUR_KEY') ?>")</code>
            </li>
        </ul>
    </div>

    <!-- 3. In-Browser Studio -->
    <div class="docs-section" id="studio" style="margin-bottom: 48px;">
        <h2 style="font-size: 22px; font-weight: 700; color: var(--fg); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            3. In-Browser Studio &amp; Responsive Preview
        </h2>
        <p style="color: var(--fg-muted); line-height: 1.6; margin-bottom: 16px;">
            Click <strong>Studio</strong> on any site card in your dashboard (or visit <code>/editor/{slug}</code>) to make quick edits right in your browser.
        </p>
        <ul class="docs-list" style="color: var(--fg-muted); line-height: 1.8;">
            <li><strong style="color: var(--fg);">Multi-File Tabs:</strong> Switch between <code>index.html</code>, <code>style.css</code>, and <code>script.js</code> with syntax highlighting.</li>
            <li><strong style="color: var(--fg);">Phone, Tablet &amp; Desktop Views:</strong> Check how your page looks across screen sizes before saving.</li>
            <li><strong style="color: var(--fg);">Keyboard Shortcut:</strong> Press <kbd style="background: var(--bg-2); border: 1px solid var(--border); padding: 2px 6px; border-radius: 5px; color: var(--fg);">Cmd+S</kbd> or <kbd style="background: var(--bg-2); border: 1px solid var(--border); padding: 2px 6px; border-radius: 5px; color: var(--fg);">Ctrl+S</kbd> to save and publish your changes immediately.</li>
        </ul>
    </div>

    <!-- 4. Clean URLs & _redirects -->
    <div class="docs-section" id="routing" style="margin-bottom: 48px;">
        <h2 style="font-size: 22px; font-weight: 700; color: var(--fg); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            4. Clean URLs &amp; Redirect Rules
        </h2>
        <p style="color: var(--fg-muted); line-height: 1.6; margin-bottom: 16px;">
            PageDock automatically strips <code>.html</code> extensions from browser URLs (e.g. <code>/about.html</code> becomes <code>/about</code>).
        </p>
        <p style="color: var(--fg-muted); line-height: 1.6; margin-bottom: 16px;">
            Place a <code>_redirects</code> file in your site folder to configure custom redirects or Single Page App rules:
        </p>
        <pre class="code-block"><code># Permanent 301 Redirect
/old-page        /new-page        301

# Temporary 302 Redirect
/promo           /sale            302

# External Link Redirect
/whatsapp        https://wa.me/1234567890  302

# Single Page App (SPA) Fallback Rewrite
/*               /index.html      200</code></pre>
    </div>

    <!-- 5. AI Agents & MCP Tooling -->
    <div class="docs-section" id="mcp-agents" style="margin-bottom: 48px;">
        <h2 style="font-size: 22px; font-weight: 700; color: var(--fg); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            5. AI Coding Assistants (MCP)
        </h2>
        <p style="color: var(--fg-muted); line-height: 1.6; margin-bottom: 16px;">
            PageDock includes a <strong>Model Context Protocol (MCP)</strong> server (<code>pagedock-mcp</code>) so AI coding tools like Antigravity, Cursor, and Claude Code can publish and update websites directly.
        </p>
        <pre class="code-block"><code># Run PageDock MCP Server
npx pagedock-mcp

# Available Agent Tools:
# - deploy_html: Publish a static site directly
# - update_site_file: Edit index.html, style.css, or script.js
# - pull_site: Inspect HTML of any existing site
# - list_versions: View saved snapshots
# - rollback_site: Revert to an earlier version
# - get_site_analytics: Check page views and visitor stats
# - list_form_submissions: Read form leads & inquiries</code></pre>
    </div>

    <!-- 6. File Limits & Specifications -->
    <div class="docs-section">
        <h2 style="font-size: 22px; font-weight: 700; color: var(--fg); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            6. File Limits &amp; Supported Formats
        </h2>
        <ul class="docs-list" style="color: var(--fg-muted); line-height: 1.8;">
            <li><strong style="color: var(--fg);">Max Upload Size:</strong> 50 MB per ZIP archive or HTML file.</li>
            <li><strong style="color: var(--fg);">Guest Links:</strong> Pages published without an account stay live for 48 hours unless you sign up or sign in to keep them.</li>
            <li><strong style="color: var(--fg);">Supported Files:</strong> <code>.html</code>, <code>.htm</code>, and <code>.zip</code> containing CSS, JS, images (PNG, JPG, WebP, SVG, GIF), and fonts (WOFF, WOFF2, TTF).</li>
            <li><strong style="color: var(--fg);">Custom 404 Page:</strong> Include a <code>404.html</code> file in your site folder to show your own custom error page.</li>
        </ul>
    </div>
</div>
