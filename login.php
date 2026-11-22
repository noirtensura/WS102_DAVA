<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$adminMode = ($_GET['portal'] ?? '') === 'admin';
$next = $adminMode ? '' : post_login_path(is_string($_GET['next'] ?? null) ? $_GET['next'] : '');
if ($user) {
    redirect($next ?: dashboard_path($user['role_name']));
}
$registerUrl = 'register.php' . ($next ? '?next=' . rawurlencode($next) : '');
$pageTitle = $adminMode ? 'Administrator Sign-In' : 'Sign In';
$authPage = true;
$pageScripts = ['assets/js/account.js'];
require __DIR__ . '/includes/document_head.php';
?>
<body class="account-body">
<a class="skip-link" href="#main">Skip to content</a>
<main id="main" class="account-layout login-layout">
    <section class="account-illustration">
        <?php require __DIR__ . '/includes/brand.php'; ?>
        <div class="account-story">
            <img class="account-photo" src="<?= e(url('assets/images/san-jose-study.webp')) ?>" width="1200" height="800" alt="A student bedroom with a desk beside a window" fetchpriority="high">
            <h2><?= $adminMode ? 'Dayon Admin Control Center' : 'Welcome Back to Dayon' ?></h2>
            <ul class="account-checks">
                <?php foreach (($adminMode ? ['Approve boarding houses & apartments', 'Review optional Antique permits for badges', 'Manage users, catalogs, reports & audit logs'] : ['Bookmark & compare boarding houses & apartments', 'Flexible landlord listings with multi-photo upload', 'Optional Antique permits & live bed availability']) as $point): ?>
                    <li><?= icon('check') ?> <?= e($point) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <div class="account-stage">
        <section class="account-card">
            <h1><?= $adminMode ? 'Sign in to Admin Portal' : 'Sign in to your account' ?></h1>
            <p class="account-intro"><?= $adminMode ? 'Manage landlords, listings, optional permits, and reports.' : 'Students, tenants, and landlords can sign in here.' ?></p>
            <?php require __DIR__ . '/includes/flash.php'; ?>
            <form method="post" action="<?= e(url('actions/auth_login.php')) ?>" class="account-form" data-login-form>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <?php if ($adminMode): ?><input type="hidden" name="portal" value="admin"><?php endif; ?>
                <label>Email address<input name="email" type="email" maxlength="150" autocomplete="username" required placeholder="you@example.com"></label>
                <div class="password-field">
                    <label for="login-password">Password</label>
                    <input id="login-password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">
                    <button type="button" class="password-toggle" aria-label="Show password" aria-controls="login-password" aria-pressed="false" data-password-toggle hidden>
                        <span data-password-hidden><?= icon('eye-off') ?></span><span data-password-visible hidden><?= icon('eye') ?></span>
                    </button>
                </div>
                <div class="login-options">
                    <label class="check-label" data-remember-option hidden><input type="checkbox" name="remember_email"> Remember email</label>
                    <details class="login-help">
                        <summary>Need help?</summary>
                        <p>Contact your Dayon administrator if you forgot your password or need account reactivation.</p>
                    </details>
                </div>
                <button class="button wide" type="submit">Log In</button>
            </form>

            <details class="demo-access-box">
                <summary><?= icon('info') ?> Quick Demo Accounts (Local Testing)</summary>
                <p class="muted">Click any account to auto-fill (password: <code>DayonDemo2026!</code>):</p>
                <div class="demo-account-buttons">
                    <?php if ($adminMode): ?>
                        <button type="button" class="button small secondary" data-demo-fill="admin@dayon.test">Admin (Alex Reyes)</button>
                    <?php else: ?>
                        <button type="button" class="button small secondary" data-demo-fill="student@dayon.test">Student Finder</button>
                        <button type="button" class="button small secondary" data-demo-fill="tenant@dayon.test">Student Tenant</button>
                        <button type="button" class="button small secondary" data-demo-fill="owner@dayon.test">Verified Landlord</button>
                        <button type="button" class="button small secondary" data-demo-fill="pending@dayon.test">New Landlord</button>
                    <?php endif; ?>
                </div>
            </details>

            <?php if (!$adminMode): ?>
                <p class="account-switch">New to Dayon? <a href="<?= e(url($registerUrl)) ?>">Create an account</a></p>
            <?php endif; ?>
        </section>
        <a class="account-alt-link" href="<?= e(url($adminMode ? 'login.php' : 'login.php?portal=admin')) ?>"><?= $adminMode ? 'Student & Landlord Sign-In' : 'Go to Admin Portal' ?> <?= icon('arrow') ?></a>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
