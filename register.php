<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($user) {
    redirect(dashboard_path($user['role_name']));
}
$role = ($_GET['role'] ?? '') === 'landlord' ? 'landlord' : 'student';
$next = post_login_path(is_string($_GET['next'] ?? null) ? $_GET['next'] : '');
$old = $_SESSION['register_values'] ?? [];
unset($_SESSION['register_values']);
$pageTitle = 'Create an account';
$authPage = true;
$pageScripts = ['assets/js/account.js'];
require __DIR__ . '/includes/document_head.php';
?>
<body class="account-body">
<a class="skip-link" href="#main">Skip to content</a>
<main id="main" class="account-layout register-layout">
    <section class="account-illustration">
        <?php require __DIR__ . '/includes/brand.php'; ?>
        <div class="account-story">
            <h2>Student Housing Made Simple</h2>
            <p>Find verified boarding houses, track vacancies, and manage rent in <?= e(DAYON_LOCATION) ?>.</p>
            <ol class="signup-steps">
                <li aria-current="step"><span class="step-dot"><?= icon('check') ?></span><span>1. Create Account</span></li>
                <li><span class="step-dot"></span><span>2. <?= $role === 'landlord' ? 'Submit Verification' : 'Complete Your Profile' ?></span></li>
                <li><span class="step-dot"></span><span>3. <?= $role === 'landlord' ? 'List and Manage Rooms' : 'Find and Compare Rooms' ?></span></li>
            </ol>
            <div class="signup-location"><?= icon('shield') ?><span>Verified boarding houses<br><?= e(DAYON_LOCATION) ?></span></div>
        </div>
    </section>
    <div class="account-stage">
        <section class="account-card">
            <h1>Create your Dayon account</h1>
            <?php require __DIR__ . '/includes/flash.php'; ?>
            <nav class="role-tabs" aria-label="Choose account type">
                <?php foreach (['student' => ['Student / Tenant', 'Find rooms & track rent'], 'landlord' => ['Landlord / Owner', 'List rooms & manage tenants']] as $value => [$label, $sub]): ?>
                    <a href="<?= e(url('register.php?' . http_build_query(['role' => $value, 'next' => $next]))) ?>"<?= $role === $value ? ' aria-current="page"' : '' ?>>
                        <?php if ($role === $value): ?><span class="role-check"><?= icon('check') ?></span><?php endif; ?>
                        <strong><?= e($label) ?></strong>
                        <span><?= e($sub) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="post" action="<?= e(url('actions/auth_register.php')) ?>" class="account-form">
                <?= csrf_field() ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="account-field-grid">
                    <label>First Name<input name="first_name" value="<?= e($old['first_name'] ?? '') ?>" maxlength="80" autocomplete="given-name" placeholder="First Name" required></label>
                    <label>Last Name<input name="last_name" value="<?= e($old['last_name'] ?? '') ?>" maxlength="80" autocomplete="family-name" placeholder="Last Name" required></label>
                    <label>Email Address<input name="email" value="<?= e($old['email'] ?? '') ?>" type="email" maxlength="150" autocomplete="email" placeholder="Email Address" required></label>
                    <label>Mobile Number<input name="phone_number" value="<?= e($old['phone_number'] ?? '') ?>" type="tel" maxlength="20" autocomplete="tel" placeholder="Mobile Number" required></label>
                    <?php if ($role === 'student'): ?>
                        <label>School / Student ID<input name="school_id_number" value="<?= e($old['school_id_number'] ?? '') ?>" maxlength="50" placeholder="School / Student ID" required></label>
                        <label>Year Level
                            <select name="year_level" required>
                                <option value="">Select year</option>
                                <?php for ($year = 1; $year <= 6; $year++): ?>
                                    <option value="<?= $year ?>"<?= (int) ($old['year_level'] ?? 0) === $year ? ' selected' : '' ?>>Year <?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="span-2">Course or Program<input name="course_program" value="<?= e($old['course_program'] ?? '') ?>" maxlength="120" placeholder="Course or Program" required></label>
                    <?php endif; ?>
                    <?php foreach (['password' => 'Password', 'confirmation' => 'Confirm Password'] as $field => $label): ?>
                        <div class="password-field">
                            <label for="register-<?= e($field) ?>"><?= e($label) ?></label>
                            <input id="register-<?= e($field) ?>" name="<?= e($field) ?>" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="<?= e($label) ?>" required>
                            <button type="button" class="password-toggle" aria-label="Show password" aria-controls="register-<?= e($field) ?>" aria-pressed="false" data-password-toggle hidden>
                                <span data-password-hidden><?= icon('eye-off') ?></span><span data-password-visible hidden><?= icon('eye') ?></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="muted">Use at least 8 characters for your password.</small>
                <button class="button wide" type="submit">Create Account</button>
            </form>
            <p class="account-switch">Already have an account? <a href="<?= e(url('login.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Log In</a></p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
