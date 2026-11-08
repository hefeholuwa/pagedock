    </main>
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand-col">
                <a href="<?= url() ?>" class="logo">
                    <span class="logo-box">P</span>
                    <span class="logo-text">Page<span class="accent-text">Dock</span></span>
                </a>
                <p class="footer-tagline">
                    The sovereign static web hosting platform. Instant deployment, client-side preprocessing, and zero-backend forms for developers and AI agents.
                </p>
                <div class="footer-status-pill">
                    <span class="pulse-dot"></span>
                    <span>All Edge Regions Operational</span>
                </div>
            </div>

            <div class="footer-links-grid">
                <div class="footer-nav-col">
                    <span class="footer-col-title">Platform</span>
                    <a href="<?= url() ?>" class="footer-link">Deployment Dock</a>
                    <a href="<?= url('templates') ?>" class="footer-link">HTML Templates</a>
                    <a href="<?= url('docs') ?>" class="footer-link">Documentation</a>
                    <a href="<?= url('dashboard') ?>" class="footer-link">Dashboard</a>
                </div>

                <div class="footer-nav-col">
                    <span class="footer-col-title">Capabilities</span>
                    <a href="<?= url('docs#forms') ?>" class="footer-link">Form Engine (/__pd/form)</a>
                    <a href="<?= url('docs#analytics') ?>" class="footer-link">Edge Analytics (/__pd/v)</a>
                    <a href="<?= url('docs#redirects') ?>" class="footer-link">Clean URLs &amp; _redirects</a>
                    <a href="<?= url('docs#shared') ?>" class="footer-link">Shared Layout Blocks</a>
                </div>

                <div class="footer-nav-col">
                    <span class="footer-col-title">Developer</span>
                    <a href="<?= url('docs#cli') ?>" class="footer-link">pagedock-cli</a>
                    <a href="<?= url('docs#mcp') ?>" class="footer-link">Model Context Protocol</a>
                    <a href="<?= url('docs#api') ?>" class="footer-link">REST API Contract</a>
                    <a href="https://github.com" target="_blank" rel="noopener" class="footer-link">GitHub</a>
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
