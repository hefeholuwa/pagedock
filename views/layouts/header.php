<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($title ?? APP_NAME . ' — ' . APP_TAGLINE) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/home.css') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= asset('favicon.svg') ?>">
    <link rel="shortcut icon" href="<?= asset('favicon.svg') ?>">
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
    <header class="navbar">
        <div class="nav-container">
            <a href="<?= url() ?>" class="logo">
                <span class="logo-box" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 12C16 10.3431 17.3431 9 19 9H35.5C44.0604 9 51 15.9396 51 24.5C51 33.0604 44.0604 40 35.5 40H25V51C25 52.6569 23.6569 54 22 54H19C17.3431 54 16 52.6569 16 51V12ZM25 18V31H35C38.5899 31 41.5 28.0899 41.5 24.5C41.5 20.9101 38.5899 18 35 18H25Z" fill="currentColor"/>
                        <rect x="30" y="45" width="20" height="9" rx="4.5" fill="currentColor" fill-opacity="0.65"/>
                    </svg>
                </span>
                <span class="logo-text">Page<span class="accent-text">Dock</span></span>
            </a>

            <!-- Desktop Links -->
            <nav class="nav-links desktop-only">
                <a href="<?= url() ?>" class="nav-item">Product</a>
                <a href="<?= url('templates') ?>" class="nav-item">Templates</a>
                <a href="<?= url('docs') ?>" class="nav-item">Docs</a>
                <?php if (Auth::check()): ?>
                    <?php $currentUser = Auth::user(); ?>
                    <?php if (Auth::isAdmin()): ?>
                        <a href="<?= url('admin') ?>" class="nav-item nav-admin-badge" style="background:rgba(4,120,87,0.12); color:var(--accent); font-weight:700; padding:4px 10px; border-radius:6px; border:1px solid rgba(4,120,87,0.25);">Admin</a>
                    <?php endif; ?>
                    <a href="<?= url('dashboard') ?>" class="nav-item">Dashboard</a>
                    <span class="nav-user-pill" style="font-size:12px; color:var(--fg-muted); display:flex; align-items:center; gap:6px;">
                        <span style="width:22px; height:22px; border-radius:50%; background:var(--accent); color:#ffffff; font-weight:800; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">
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
            <a href="<?= url('templates') ?>" class="mobile-nav-link">Templates</a>
            <a href="<?= url('docs') ?>" class="mobile-nav-link">Docs</a>
            <?php if (Auth::check()): ?>
                <?php if (Auth::isAdmin()): ?>
                    <a href="<?= url('admin') ?>" class="mobile-nav-link" style="color:var(--accent); font-weight:700;">Admin Console</a>
                <?php endif; ?>
                <a href="<?= url('dashboard') ?>" class="mobile-nav-link">Dashboard</a>
                <a href="<?= url('logout') ?>" class="mobile-nav-link text-danger">Log Out</a>
            <?php else: ?>
                <a href="<?= url('login') ?>" class="mobile-nav-link">Sign In</a>
                <a href="<?= url('register') ?>" class="mobile-nav-link accent-text">Create Free Account &rarr;</a>
            <?php endif; ?>
        </div>
    </header>
    <main class="main-content">
