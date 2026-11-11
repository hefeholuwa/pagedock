    </main>
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand-col">
                <a href="<?= url() ?>" class="logo">
                    <span class="logo-box" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 12C16 10.3431 17.3431 9 19 9H35.5C44.0604 9 51 15.9396 51 24.5C51 33.0604 44.0604 40 35.5 40H25V51C25 52.6569 23.6569 54 22 54H19C17.3431 54 16 52.6569 16 51V12ZM25 18V31H35C38.5899 31 41.5 28.0899 41.5 24.5C41.5 20.9101 38.5899 18 35 18H25Z" fill="currentColor"/>
                            <rect x="30" y="45" width="20" height="9" rx="4.5" fill="currentColor" fill-opacity="0.65"/>
                        </svg>
                    </span>
                    <span class="logo-text">Page<span class="accent-text">Dock</span></span>
                </a>
                <p class="footer-tagline">
                    Create, customize, and host fast landing pages, business websites, and portfolios in seconds—with built-in customer forms and visitor tracking.
                </p>
                <div class="footer-status-pill">
                    <span class="pulse-dot"></span>
                    <span>All Systems Online &amp; Ready</span>
                </div>
            </div>

            <div class="footer-links-grid">
                <div class="footer-nav-col">
                    <span class="footer-col-title">Product</span>
                    <a href="<?= url() ?>" class="footer-link">Launch a Website</a>
                    <a href="<?= url('templates') ?>" class="footer-link">Ready-Made Templates</a>
                    <a href="<?= url('dashboard') ?>" class="footer-link">My Dashboard</a>
                    <a href="<?= url('docs') ?>" class="footer-link">Help &amp; Guides</a>
                </div>

                <div class="footer-nav-col">
                    <span class="footer-col-title">Features</span>
                    <a href="<?= url('__hh/form') ?>" class="footer-link">Customer Lead Forms</a>
                    <a href="<?= url('docs#analytics') ?>" class="footer-link">Visitor Tracking</a>
                    <a href="<?= url('docs#redirects') ?>" class="footer-link">Clean Custom Links</a>
                    <a href="<?= url('docs#shared') ?>" class="footer-link">Multi-Page Sync</a>
                </div>

                <div class="footer-nav-col">
                    <span class="footer-col-title">For Developers &amp; AI</span>
                    <a href="<?= url('docs#cli') ?>" class="footer-link">Terminal CLI Tool</a>
                    <a href="<?= url('docs#mcp') ?>" class="footer-link">AI Agent Connect (MCP)</a>
                    <a href="<?= url('docs#api') ?>" class="footer-link">Developer API</a>
                    <a href="<?= url('__pd/form') ?>" class="footer-link">Form Endpoint Guide</a>
                </div>
            </div>
        </div>

        <div class="footer-bottom-bar">
            <div class="footer-bottom-container">
                <p>&copy; <?= date('Y') ?> PageDock, Inc. All rights reserved.</p>
                <div class="footer-legal-links">
                    <a href="<?= url('docs') ?>" class="footer-sublink">Privacy</a>
                    <a href="<?= url('docs') ?>" class="footer-sublink">Terms</a>
                    <a href="<?= url('docs') ?>" class="footer-sublink">Security</a>
                </div>
            </div>
        </div>
    </footer>
    <script src="<?= asset('assets/js/vendor/fflate.min.js') ?>"></script>
    <script src="<?= asset('assets/js/vendor/dexie.min.js') ?>"></script>
    <script src="<?= asset('assets/js/pipeline.js') ?>"></script>
    <script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
