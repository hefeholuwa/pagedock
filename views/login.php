<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Welcome back</h2>
            <p class="text-muted">Sign in to manage your sites and access unlimited deploys.</p>
        </div>

        <?php 
        $activeClaimTokens = Auth::getActiveGuestTokens();
        if (!empty($activeClaimTokens)): 
        ?>
            <div class="alert alert-info" style="background:rgba(199,245,71,0.08); border:1px solid rgba(199,245,71,0.3); color:#C7F547; border-radius:8px; padding:12px; margin-bottom:16px; font-size:13px;">
                You have <strong><?= count($activeClaimTokens) ?> deployed site(s)</strong> waiting in this session. Sign in to link them to your account.
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="<?= url('login') ?>" method="POST" class="auth-form">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect ?? 'dashboard') ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required placeholder="you@domain.com" class="input-text">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required placeholder="••••••••" class="input-text">
            </div>

            <button type="submit" class="btn-primary w-full mt-2">Sign In</button>
        </form>

        <p class="auth-footer-text">
            Don't have an account? <a href="<?= url('register' . (!empty($redirect) && $redirect !== 'dashboard' ? '?redirect=' . urlencode($redirect) : '')) ?>" class="accent-link">Sign up free</a>
        </p>
    </div>
</div>
