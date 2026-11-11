<div class="dashboard-container" style="max-width: 960px; margin: 0 auto; padding: 40px 20px;">
    <!-- Top Status Badge -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 9999px; background: var(--accent-soft); border: 1px solid #bfe3d2; color: var(--accent-hover); font-family: var(--font-mono, monospace); font-size: 13px; font-weight: 700;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: pulse 2s infinite;"></span>
            ENDPOINT ACTIVE &middot; LISTENING FOR POST
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= url('docs#forms') ?>" class="btn-sm btn-ghost">&larr; Read Docs</a>
            <?php if (Auth::check()): ?>
                <a href="<?= url('dashboard') ?>" class="btn-sm btn-accent">Open Dashboard &rarr;</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Header -->
    <div style="margin-bottom: 36px;">
        <h1 style="font-size: clamp(26px, 4vw, 34px); font-weight: 800; letter-spacing: -0.03em; margin-bottom: 12px; color: var(--fg);">
            Zero-Backend Form Ingestion Endpoint
        </h1>
        <p style="color: var(--fg-muted); font-size: 16px; line-height: 1.6; max-width: 760px;">
            You opened <code><?= htmlspecialchars($endpoint) ?></code> directly in your web browser using an <strong style="color: var(--fg);">HTTP GET</strong> request. 
            This endpoint is operational and configured to process <strong style="color: var(--accent);">HTTP POST</strong> form submissions from your hosted HTML sites.
        </p>
    </div>

    <!-- Endpoint Mapping Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 36px;">
        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 20px; padding: 22px; box-shadow: var(--pdh-shadow);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent); font-weight: 700; margin-bottom: 6px;">
                Universal Endpoint (Default)
            </div>
            <div style="font-family: var(--font-mono, monospace); font-size: 16px; color: var(--fg); font-weight: 700; margin-bottom: 8px;">
                POST /__pd/form
            </div>
            <p style="font-size: 13.5px; color: var(--fg-muted); margin: 0; line-height: 1.55;">
                Recommended for all static forms. Automatically associates submissions with your site via Referer header or <code>_site</code> attribute.
            </p>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 20px; padding: 22px; box-shadow: var(--pdh-shadow);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #0284c7; font-weight: 700; margin-bottom: 6px;">
                Compatibility Alias (Active)
            </div>
            <div style="font-family: var(--font-mono, monospace); font-size: 16px; color: var(--fg); font-weight: 700; margin-bottom: 8px;">
                POST /__hh/form
            </div>
            <p style="font-size: 13.5px; color: var(--fg-muted); margin: 0; line-height: 1.55;">
                Legacy route fully maintained and routed interchangeably with <code>/__pd/form</code> for 100% backward compatibility.
            </p>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 20px; padding: 22px; box-shadow: var(--pdh-shadow);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #7c3aed; font-weight: 700; margin-bottom: 6px;">
                Direct Site Endpoint
            </div>
            <div style="font-family: var(--font-mono, monospace); font-size: 16px; color: var(--fg); font-weight: 700; margin-bottom: 8px;">
                POST /f/{slug}
            </div>
            <p style="font-size: 13.5px; color: var(--fg-muted); margin: 0; line-height: 1.55;">
                Explicit clean endpoint targeting a specific site slug (e.g., <code>/f/my-landing-page</code>). No Referer header required.
            </p>
        </div>
    </div>

    <!-- Live Interactive Test Console -->
    <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 20px; padding: 28px; margin-bottom: 36px; box-shadow: var(--pdh-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--border); padding-bottom: 14px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 18px; font-weight: 700; margin: 0; color: var(--fg); display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Live Test Console
            </h2>
            <span style="font-size: 12px; font-family: var(--font-mono, monospace); color: var(--fg-muted);">
                Target: <strong style="color: var(--fg);"><?= htmlspecialchars($endpoint) ?></strong>
            </span>
        </div>
        <p style="font-size: 14px; color: var(--fg-muted); margin-bottom: 20px;">
            Submit a test payload directly from your browser to verify form capture, validation, and webhook dispatch in real time:
        </p>

        <form id="endpoint-test-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--fg);">Your Name</label>
                <input type="text" name="name" value="Developer Test" required class="input-text">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--fg);">Email Address</label>
                <input type="email" name="email" value="tester@example.com" required class="input-text">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--fg);">Message</label>
                <textarea name="message" rows="3" required class="input-text" style="resize: vertical;">Testing the PageDock zero-backend form engine endpoint!</textarea>
            </div>
            <input type="hidden" name="_form" value="endpoint-diagnostics">
            <input type="hidden" name="_pd_ts" value="<?= time() ?>">
            <?php if (!empty($targetSlug)): ?>
                <input type="hidden" name="_site" value="<?= htmlspecialchars($targetSlug) ?>">
            <?php endif; ?>
            <div style="grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 6px; flex-wrap: wrap;">
                <button type="submit" id="test-submit-btn" class="btn-primary">
                    Send Test Submission (HTTP POST) &rarr;
                </button>
                <span id="test-status-indicator" style="font-size: 13px; font-family: var(--font-mono, monospace);"></span>
            </div>
        </form>

        <!-- Response Output Box -->
        <div id="test-response-box" style="display: none; margin-top: 20px; background: #0b1411; border: 1px solid #1f332b; border-radius: 12px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; font-family: var(--font-mono, monospace); color: #93aba1;">
                <span>HTTP Response Status: <strong id="test-http-status" style="color: #fff;"></strong></span>
                <span>Type: application/json</span>
            </div>
            <pre id="test-response-body" style="margin: 0; font-family: var(--font-mono, monospace); font-size: 13px; color: #6ee7b7; overflow-x: auto; white-space: pre-wrap;"></pre>
        </div>
    </div>

    <!-- Code Integration Example -->
    <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 20px; padding: 28px; margin-bottom: 36px; box-shadow: var(--pdh-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 18px; font-weight: 700; margin: 0; color: var(--fg);">
                Static HTML Integration Example
            </h2>
            <button type="button" id="copy-code-btn" class="btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Copy HTML Snippet
            </button>
        </div>
        <p style="font-size: 14px; color: var(--fg-muted); margin-bottom: 16px;">
            Paste this snippet into any HTML page on your hosted site. No backend scripting, servers, or API keys needed:
        </p>
        <pre class="code-block" id="form-snippet-code"><code>&lt;!-- Zero-Backend Static Form --&gt;
&lt;form action="<?= url(ltrim($endpoint, '/')) ?>" method="POST"&gt;
    &lt;!-- Optional Form Name &amp; Thank-You Redirect --&gt;
    &lt;input type="hidden" name="_form" value="contact" /&gt;
    &lt;input type="hidden" name="_redirect" value="/thank-you.html" /&gt;

    &lt;div&gt;
        &lt;label for="name"&gt;Your Name&lt;/label&gt;
        &lt;input type="text" id="name" name="name" placeholder="Ada Lovelace" required /&gt;
    &lt;/div&gt;

    &lt;div&gt;
        &lt;label for="email"&gt;Email Address&lt;/label&gt;
        &lt;input type="email" id="email" name="email" placeholder="ada@example.com" required /&gt;
    &lt;/div&gt;

    &lt;div&gt;
        &lt;label for="message"&gt;Message&lt;/label&gt;
        &lt;textarea id="message" name="message" rows="4" placeholder="How can we help?" required&gt;&lt;/textarea&gt;
    &lt;/div&gt;

    &lt;button type="submit"&gt;Send Message&lt;/button&gt;
&lt;/form&gt;</code></pre>
    </div>

    <!-- Supported Features Grid -->
    <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: 20px; padding: 28px;">
        <h2 style="font-size: 18px; font-weight: 700; margin: 0 0 18px 0; color: var(--fg);">
            Automated Features &amp; Controls
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
            <div>
                <h3 style="font-size: 14px; font-weight: 700; color: var(--fg); margin-bottom: 6px;">
                    Honeypot Anti-Spam
                </h3>
                <p style="font-size: 13px; color: var(--fg-muted); margin: 0; line-height: 1.5;">
                    Add <code>&lt;input type="text" name="_pd_hp" style="display:none"&gt;</code> or <code>_gotcha</code>. If bots fill it, submissions are quarantined automatically.
                </p>
            </div>
            <div>
                <h3 style="font-size: 14px; font-weight: 700; color: var(--fg); margin-bottom: 6px;">
                    Anti-Bot Time Gate
                </h3>
                <p style="font-size: 13px; color: var(--fg-muted); margin: 0; line-height: 1.5;">
                    Automatic timestamp validation flags automated headless bots submitting forms faster than humanly possible (&lt; 2s).
                </p>
            </div>
            <div>
                <h3 style="font-size: 14px; font-weight: 700; color: var(--fg); margin-bottom: 6px;">
                    Instant Webhooks
                </h3>
                <p style="font-size: 13px; color: var(--fg-muted); margin: 0; line-height: 1.5;">
                    Connect Discord webhooks (rich embeds), Slack (Block Kit), or generic JSON endpoints for instant team notifications.
                </p>
            </div>
            <div>
                <h3 style="font-size: 14px; font-weight: 700; color: var(--fg); margin-bottom: 6px;">
                    CSV &amp; Sheets Export
                </h3>
                <p style="font-size: 13px; color: var(--fg-muted); margin: 0; line-height: 1.5;">
                    Download RFC 4180 CSV exports from your dashboard or live-sync into Google Sheets using <code>=IMPORTDATA()</code>.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var testForm = document.getElementById('endpoint-test-form');
    var submitBtn = document.getElementById('test-submit-btn');
    var statusIndicator = document.getElementById('test-status-indicator');
    var responseBox = document.getElementById('test-response-box');
    var httpStatusEl = document.getElementById('test-http-status');
    var responseBodyEl = document.getElementById('test-response-body');
    var copyBtn = document.getElementById('copy-code-btn');

    if (testForm) {
        testForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            statusIndicator.textContent = 'Submitting POST...';
            statusIndicator.style.color = 'var(--fg-muted, #8b949e)';
            responseBox.style.display = 'none';

            var formData = new FormData(testForm);
            var targetEndpoint = '<?= url(ltrim($endpoint, '/')) ?>';

            fetch(targetEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(function(res) {
                httpStatusEl.textContent = res.status + ' ' + res.statusText;
                httpStatusEl.style.color = res.ok ? '#6ee7b7' : '#f87171';
                return res.json().catch(function() {
                    return { rawText: 'Response was not JSON' };
                });
            })
            .then(function(data) {
                submitBtn.disabled = false;
                responseBox.style.display = 'block';
                responseBodyEl.textContent = JSON.stringify(data, null, 2);

                if (data.ok || data.success) {
                    statusIndicator.textContent = '✓ Submission Captured Successfully!';
                    statusIndicator.style.color = 'var(--accent)';
                } else {
                    statusIndicator.textContent = 'Notice: ' + (data.error || 'Check response below');
                    statusIndicator.style.color = 'var(--danger)';
                }
            })
            .catch(function(err) {
                submitBtn.disabled = false;
                responseBox.style.display = 'block';
                httpStatusEl.textContent = 'Network Error';
                httpStatusEl.style.color = '#e5484d';
                responseBodyEl.textContent = String(err);
                statusIndicator.textContent = 'Submission failed';
                statusIndicator.style.color = '#e5484d';
            });
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var code = document.getElementById('form-snippet-code').innerText;
            navigator.clipboard.writeText(code).then(function() {
                var prev = copyBtn.innerHTML;
                copyBtn.textContent = '✓ Copied!';
                setTimeout(function() { copyBtn.innerHTML = prev; }, 2000);
            });
        });
    }
});
</script>

