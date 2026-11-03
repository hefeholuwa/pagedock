<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($title ?? APP_NAME . ' — ' . APP_TAGLINE) ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%23C7F547%22/><text y=%2265%22 font-size=%2260%22 font-weight=%22bold%22 x=%2222%22 fill=%22black%22>P</text></svg>">
</head>
<body>
    <header class="navbar">
        <div class="nav-container">
            <a href="<?= url() ?>" class="logo">
                <span class="logo-box">P</span>
                <span class="logo-text">Page<span class="accent-text">Dock</span></span>
            </a>

            <!-- Desktop Links -->
            <nav class="nav-links desktop-only">
                <a href="<?= url() ?>" class="nav-item">Product</a>
                <a href="<?= url('docs') ?>" class="nav-item">Docs</a>
                <?php if (Auth::check()): ?>
                    <a href="<?= url('dashboard') ?>" class="nav-item">Dashboard</a>
                    <a href="<?= url('logout') ?>" class="btn-sm btn-ghost">Log Out</a>
                <?php else: ?>
                    <a href="<?= url('login') ?>" class="btn-sm btn-ghost">Sign In</a>
                    <a href="<?= url('register') ?>" class="btn-sm btn-accent">Get Started</a>
                <?php endif; ?>
            </nav>

            <!-- Mobile Hamburger Toggle -->
            <button type="button" class="mobile-menu-btn mobile-only" id="mobile-menu-toggle" aria-label="Toggle Menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer -->
        <div class="mobile-nav-drawer hidden" id="mobile-nav-drawer">
            <a href="<?= url() ?>" class="mobile-nav-link">Product</a>
            <a href="<?= url('docs') ?>" class="mobile-nav-link">Docs</a>
            <?php if (Auth::check()): ?>
                <a href="<?= url('dashboard') ?>" class="mobile-nav-link">Dashboard</a>
                <a href="<?= url('logout') ?>" class="mobile-nav-link text-danger">Log Out</a>
            <?php else: ?>
                <a href="<?= url('login') ?>" class="mobile-nav-link">Sign In</a>
                <a href="<?= url('register') ?>" class="mobile-nav-link accent-text">Create Free Account &rarr;</a>
            <?php endif; ?>
        </div>
    </header>
    <main class="main-content">
