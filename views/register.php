<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Create your free account</h2>
            <p class="text-muted">Keep all your websites, custom links, and lead inquiries in one place.</p>
        </div>

        <?php 
        $activeClaimTokens = Auth::getActiveGuestTokens();
        if (!empty($activeClaimTokens)): 
        ?>
            <div class="alert alert-info" style="background:var(--accent-soft); border:1px solid #bfe3d2; color:var(--accent-hover); border-radius:12px; padding:12px 14px; margin-bottom:16px; font-size:13px;">
                You have <strong><?= count($activeClaimTokens) ?> website(s)</strong> waiting in this session. Create your account to save and manage them permanently!
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="<?= url('register') ?>" method="POST" class="auth-form">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect ?? 'dashboard') ?>">

            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" name="name" id="name" placeholder="John Doe" class="input-text">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required placeholder="you@domain.com" class="input-text">
            </div>

            <div class="form-group">
                <label for="password">Password (min 6 chars)</label>
                <input type="password" name="password" id="password" required placeholder="••••••••" class="input-text">
            </div>

            <button type="submit" class="btn-primary w-full mt-2">Create Account</button>
        </form>

        <p class="auth-footer-text">
            Already have an account? <a href="<?= url('login' . (!empty($redirect) && $redirect !== 'dashboard' ? '?redirect=' . urlencode($redirect) : '')) ?>" class="accent-link">Sign in</a>
        </p>
    </div>
</div>
