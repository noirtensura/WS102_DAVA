<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$adminMode = ($_GET['portal'] ?? '') === 'admin';
$next = $adminMode ? '' : post_login_path(is_string($_GET['next'] ?? null) ? $_GET['next'] : '');
if ($user) {
    redirect($next ?: dashboard_path($user['role_name']));
}
$registerUrl = 'register.php' . ($next ? '?next=' . rawurlencode($next) : '');
$pageTitle = $adminMode ? 'Administrator sign-in' : 'Log in';
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
            <h2><?= $adminMode ? 'Manage Dayon' : 'Welcome Back to Dayon' ?></h2>
            <ul class="account-checks">
                <?php foreach (($adminMode ? ['Review landlord verification', 'Approve local boarding houses', 'Monitor occupancy and audit logs'] : ['Live bed vacancy updates', 'Digital rent and utility receipts', 'Verified campus landlords']) as $point): ?>
                    <li><?= icon('check') ?> <?= e($point) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <div class="account-stage">
        <section class="account-card">
            <h1><?= $adminMode ? 'Sign in to the Admin Portal' : 'Sign in to your account' ?></h1>
            <p class="account-intro"><?= $adminMode ? 'Use your Dayon administrator account.' : 'Students, tenants, and landlords can sign in here.' ?></p>
            <?php require __DIR__ . '/includes/flash.php'; ?>
            <form method="post" action="<?= e(url('actions/auth_login.php')) ?>" class="account-form" data-login-form>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <?php if ($adminMode): ?><input type="hidden" name="portal" value="admin"><?php endif; ?>
                <label><span class="visually-hidden">Email address</span><input name="email" type="email" maxlength="150" autocomplete="username" placeholder="Email Address" required></label>
                <div class="password-field">
                    <label for="login-password" class="visually-hidden">Password</label>
                    <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Password" required>
                    <button type="button" class="password-toggle" aria-label="Show password" aria-controls="login-password" aria-pressed="false" data-password-toggle hidden>
                        <span data-password-hidden><?= icon('eye-off') ?></span><span data-password-visible hidden><?= icon('eye') ?></span>
                    </button>
                </div>
                <div class="login-options">
                    <label class="check-label" data-remember-option hidden><input type="checkbox" name="remember_email"> Remember email</label>
                    <details class="login-help">
                        <summary>Forgot password?</summary>
                        <p>Contact your Dayon administrator for help recovering your account.</p>
                    </details>
                </div>
                <button class="button wide" type="submit">Log In</button>
            </form>
            <?php if (!$adminMode): ?><p class="account-switch">New to Dayon? <a href="<?= e(url($registerUrl)) ?>">Create an account</a></p><?php endif; ?>
        </section>
        <a class="account-alt-link" href="<?= e(url($adminMode ? 'login.php' : 'login.php?portal=admin')) ?>"><?= $adminMode ? 'Student and Landlord Sign-In' : 'Admin Portal' ?> <?= icon('arrow') ?></a>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
