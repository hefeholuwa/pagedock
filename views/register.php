<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Create an account</h2>
            <p class="text-muted">Start hosting static HTML with zero friction.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="<?= url('register') ?>" method="POST" class="auth-form">
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
            Already have an account? <a href="<?= url('login') ?>" class="accent-link">Sign in</a>
        </p>
    </div>
</div>
