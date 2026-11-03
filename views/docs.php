<div class="docs-container">
    <div class="docs-header">
        <h1>PageDock Documentation</h1>
        <p class="text-muted">How to deploy from terminal, paste code, and capture backendless forms.</p>
    </div>

    <div class="docs-section">
        <h2>1. Deploying via cURL / Terminal CLI</h2>
        <p>Deploy any local HTML file or ZIP folder directly with a single command:</p>
        <pre class="code-block"><code># Deploy single HTML file
curl -X POST -F "file=@index.html" <?= url('api/upload') ?>

# Deploy multi-page ZIP bundle with a custom slug
curl -X POST -F "file=@website.zip" -F "slug=my-portfolio" <?= url('api/upload') ?>
</code></pre>
    </div>

    <div class="docs-section">
        <h2>2. Zero-Backend Contact Forms</h2>
        <p>You can connect any standard HTML form on your PageDock hosted site to capture leads, contact inquiries, and feedback directly into your dashboard.</p>
        <pre class="code-block"><code>&lt;form action="<?= url('api/forms/YOUR_SLUG') ?>" method="POST"&gt;
    &lt;input type="text" name="name" placeholder="Your name" required /&gt;
    &lt;input type="email" name="email" placeholder="Your email" required /&gt;
    &lt;textarea name="message" placeholder="Your message"&gt;&lt;/textarea&gt;
    &lt;!-- Optional redirect after submission --&gt;
    &lt;input type="hidden" name="_redirect" value="<?= url('s/YOUR_SLUG') ?>?success=1" /&gt;
    &lt;button type="submit"&gt;Send Message&lt;/button&gt;
&lt;/form&gt;
</code></pre>
    </div>

    <div class="docs-section">
        <h2>3. File Limits &amp; Specifications</h2>
        <ul class="docs-list">
            <li><strong>Max upload size</strong>: 50 MB per zip or file.</li>
            <li><strong>Supported formats</strong>: <code>.html</code>, <code>.htm</code>, and <code>.zip</code> containing HTML, CSS, JS, images (PNG, JPG, SVG, WebP, GIF), and fonts (WOFF, WOFF2, TTF).</li>
            <li><strong>Entry point</strong>: <code>index.html</code> (or <code>index.htm</code>) is automatically detected and served at the root of your slug.</li>
        </ul>
    </div>
</div>
