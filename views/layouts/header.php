<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($title ?? APP_NAME . ' — ' . APP_TAGLINE) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
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
                <a href="<?= url('templates') ?>" class="nav-item">Templates</a>
                <a href="<?= url('docs') ?>" class="nav-item">Docs</a>
                <?php if (Auth::check()): ?>
                    <?php $currentUser = Auth::user(); ?>
                    <a href="<?= url('dashboard') ?>" class="nav-item">Dashboard</a>
                    <span class="nav-user-pill" style="font-size:12px; color:var(--fg-muted); display:flex; align-items:center; gap:6px;">
                        <span style="width:22px; height:22px; border-radius:50%; background:var(--accent); color:#000; font-weight:800; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">
                            <?= strtoupper(substr(($currentUser['name'] ?? '') ?: ($currentUser['email'] ?? 'U'), 0, 1)) ?>
                        </span>
                        <span class="desktop-only"><?= htmlspecialchars(($currentUser['name'] ?? '') ?: explode('@', $currentUser['email'] ?? '')[0]) ?></span>
                    </span>
                    <a href="<?= url('logout') ?>" class="btn-sm btn-ghost">Log Out</a>
                <?php else: ?>
                    <a href="<?= url('login') ?>" class="btn-sm btn-ghost">Sign In</a>
                    <a href="<?= url('register') ?>" class="btn-sm btn-accent">Get Started</a>
                <?php endif; ?>
            </nav>

            <!-- Mobile Hamburger Toggle -->
            <button type="button" class="mobile-menu-btn mobile-only" id="mobile-menu-toggle" aria-label="Toggle Menu" aria-expanded="false">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Mobile Drawer Backdrop Overlay -->
        <div class="mobile-nav-backdrop hidden" id="mobile-nav-backdrop"></div>

        <!-- Mobile Drawer -->
        <div class="mobile-nav-drawer hidden" id="mobile-nav-drawer">
            <a href="<?= url() ?>" class="mobile-nav-link">Product</a>
            <a href="<?= url('templates') ?>" class="mobile-nav-link">HTML Templates</a>
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
